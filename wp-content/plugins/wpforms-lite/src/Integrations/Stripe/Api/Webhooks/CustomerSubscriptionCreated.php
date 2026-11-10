<?php

namespace WPForms\Integrations\Stripe\Api\Webhooks;

use RuntimeException;

/**
 * Webhook customer.subscription.created class.
 *
 * @since 1.8.4
 */
class CustomerSubscriptionCreated extends Base {

	/**
	 * Handle the Webhook's data.
	 *
	 * @since 1.8.4
	 *
	 * @throws RuntimeException If payment not found or not updated.
	 *
	 * @return bool
	 */
	public function handle() {

		$this->delay();

		$payment = wpforms()->obj( 'payment' )->get_by( 'subscription_id', $this->data->object->id );

		if ( ! $payment ) {
			return false;
		}

		// A trial starts as trialing. Anything else is active here: Stripe reports a charged subscription
		// as incomplete at creation, before the first invoice is paid.
		$status = $this->data->object->status === 'trialing' ? 'trialing' : 'active';

		if ( ! wpforms()->obj( 'payment' )->update( $payment->id, [ 'subscription_status' => $status ] ) ) {
			throw new RuntimeException( 'Payment not updated' );
		}

		wpforms()->obj( 'payment_meta' )->add_log(
			$payment->id,
			sprintf( 'Stripe subscription was set to %s.', $this->get_status_log_label( $status ) )
		);

		return true;
	}
}
