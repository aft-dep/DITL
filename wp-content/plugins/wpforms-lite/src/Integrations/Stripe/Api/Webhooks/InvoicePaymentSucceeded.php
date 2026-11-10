<?php

namespace WPForms\Integrations\Stripe\Api\Webhooks;

use RuntimeException;
use Stripe\Exception\ApiErrorException;
use WPForms\Db\Payments\Queries;
use WPForms\Integrations\Stripe\Helpers;
use WPForms\Vendor\Stripe\PaymentIntent;

/**
 * Webhook invoice.payment_succeeded class.
 *
 * @since 1.8.4
 */
class InvoicePaymentSucceeded extends Base {

	/**
	 * Handle invoice.payment_succeeded webhook.
	 *
	 * A subscription_cycle invoice completes the renewal it paid for. The paid $0 invoice Stripe issues when a
	 * trial subscription is created completes the trial signup row, which no charge.succeeded ever reaches.
	 *
	 * @since 1.8.4
	 * @since 2.0.4 Completes a trial signup row from its paid $0 signup invoice.
	 *
	 * @return bool
	 */
	public function handle() {

		if ( ! isset( $this->data->object->billing_reason ) || $this->data->object->paid !== true ) {
			return false; // Subscription not paid, so we are not going to proceed with update.
		}

		if ( $this->data->object->billing_reason === 'subscription_cycle' ) {
			return $this->complete_renewal( $this->data->object );
		}

		return $this->complete_trial_signup( $this->data->object );
	}

	/**
	 * Mark the trial signup that belongs to the given paid $0 invoice as completed.
	 *
	 * @since 2.0.4
	 *
	 * @param object $invoice Stripe invoice object from the event payload.
	 *
	 * @throws RuntimeException When the payment row could not be updated.
	 *
	 * @return bool
	 */
	private function complete_trial_signup( object $invoice ): bool {

		if ( empty( $invoice->subscription ) || (int) $invoice->amount_due !== 0 || (int) $invoice->amount_paid !== 0 ) {
			return false; // Something was charged: not the signup of a trial.
		}

		// The event fires while the submission is still saving its row.
		$this->delay();

		$db_payment = wpforms()->obj( 'payment' )->get_by( 'subscription_id', $invoice->subscription );

		if ( ! $db_payment || strpos( (string) $db_payment->transaction_id, 'seti_' ) !== 0 ) {
			return false; // A charged subscription is completed by charge.succeeded instead.
		}

		// Already completed: stay idempotent for duplicate webhooks.
		if ( $db_payment->status === 'completed' ) {
			return true;
		}

		if ( $db_payment->status !== 'processed' ) {
			return false;
		}

		$updated = wpforms()->obj( 'payment' )->update(
			$db_payment->id,
			[
				'status'           => 'completed',
				'date_updated_gmt' => gmdate( 'Y-m-d H:i:s' ),
			]
		);

		if ( ! $updated ) {
			throw new RuntimeException( 'Payment not updated' );
		}

		wpforms()->obj( 'payment_meta' )->add_log(
			$db_payment->id,
			sprintf( 'Stripe trial signup was completed. (Invoice ID: %1$s)', $invoice->id )
		);

		return true;
	}

	/**
	 * Mark the renewal that belongs to the given paid invoice as completed.
	 *
	 * @since 2.0.0
	 *
	 * @param object $invoice Stripe invoice object (event payload or live API object).
	 *
	 * @return bool
	 */
	public function complete_renewal( object $invoice ): bool {

		$db_renewal = ( new Queries() )->get_renewal_by_invoice_id( $invoice->id );

		if ( is_null( $db_renewal ) ) {
			return false; // Renewal not created yet; the other webhook will complete it.
		}

		// Already processed: stay idempotent for duplicate or racing webhooks.
		if ( $db_renewal->status === 'completed' ) {
			return true;
		}

		$currency = strtoupper( $invoice->currency );
		$amount   = $invoice->amount_paid / wpforms_get_currency_multiplier( $currency );

		wpforms()->obj( 'payment' )->update(
			$db_renewal->id,
			[
				'total_amount'    => $amount,
				'subtotal_amount' => $amount,
				'status'          => 'completed',
				'transaction_id'  => $invoice->payment_intent,
			]
		);

		$this->copy_meta_from_payment_intent( $db_renewal->id, $invoice->payment_intent );

		wpforms()->obj( 'payment_meta' )->add_log(
			$db_renewal->id,
			sprintf(
				'Stripe renewal was successfully paid. (Payment Intent ID: %1$s)',
				$invoice->payment_intent
			)
		);

		return true;
	}

	/**
	 * Copy meta from payment intent.
	 *
	 * @since 1.8.4
	 *
	 * @param int    $renewal_id        Renewal ID.
	 * @param string $payment_intent_id Payment Intent ID.
	 *
	 * @noinspection PhpMissingParamTypeInspection
	 */
	private function copy_meta_from_payment_intent( $renewal_id, $payment_intent_id ) {

		try {
			$payment_intent = PaymentIntent::retrieve( $payment_intent_id, Helpers::get_auth_opts() );
		} catch ( ApiErrorException $e ) {
			$payment_intent = null;
		}

		if ( ! isset( $payment_intent->charges->data[0]->payment_method_details ) ) {
			return;
		}

		$this->update_payment_method_details( $renewal_id, $payment_intent->charges->data[0]->payment_method_details );
	}
}
