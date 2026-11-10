<?php

namespace WPForms\SmartTags\SmartTag;

/**
 * Base for the smart tags that describe a subscription trial.
 *
 * With a saved entry the trial is read from its payment record, so a notification resent later still
 * knows it. During a submission that stores no entry, the payment gateway supplies the trial of the
 * current request through a filter.
 *
 * @since 2.0.4
 */
abstract class TrialSmartTag extends SmartTag {

	/**
	 * Get the trial: its end timestamp, the first payment amount and the currency.
	 *
	 * @since 2.0.4
	 *
	 * @param array      $form_data Form data.
	 * @param int|string $entry_id  Entry ID.
	 *
	 * @return array Empty when there is no trial, otherwise `end`, `amount` and `currency`.
	 */
	protected function get_trial( array $form_data, $entry_id ): array {

		$trial = $this->get_saved_trial( (int) $entry_id );

		if ( $trial ) {
			return $trial;
		}

		/**
		 * Filter the trial of the current submission when no saved entry describes it.
		 *
		 * @since 2.0.4
		 *
		 * @param array      $trial     Trial data: `end` (Unix timestamp), `amount` and `currency`.
		 * @param array      $form_data Form data.
		 * @param int|string $entry_id  Entry ID.
		 */
		$trial = (array) apply_filters( 'wpforms_smart_tags_smart_tag_trial_smart_tag_get_trial', [], $form_data, $entry_id );

		return empty( $trial['end'] ) ? [] : $trial;
	}

	/**
	 * Prepare the value for the place the smart tag is printed in.
	 *
	 * In a confirmation redirect the value becomes a query argument: the entity-encoded currency symbol
	 * would split the URL on `&` and `#`, so entities are decoded and the value is URL-encoded instead
	 * of being escaped for HTML.
	 *
	 * @since 2.0.4
	 *
	 * @param string $value Smart tag value.
	 *
	 * @return string
	 */
	protected function format_value( string $value ): string {

		if ( $this->context === 'confirmation_redirect' ) {
			return rawurlencode( html_entity_decode( $value, ENT_QUOTES, 'UTF-8' ) );
		}

		return esc_html( $value );
	}

	/**
	 * Get the trial recorded on the payment of a saved entry.
	 *
	 * @since 2.0.4
	 *
	 * @param int $entry_id Entry ID.
	 *
	 * @return array
	 */
	private function get_saved_trial( int $entry_id ): array {

		if ( ! $entry_id ) {
			return [];
		}

		$payment = wpforms()->obj( 'payment' )->get_by( 'entry_id', $entry_id );

		if ( empty( $payment->id ) ) {
			return [];
		}

		$end = (int) wpforms()->obj( 'payment_meta' )->get_single( $payment->id, 'subscription_trial_end' );

		if ( ! $end ) {
			return [];
		}

		return [
			'end'      => $end,
			'amount'   => (string) wpforms()->obj( 'payment_meta' )->get_single( $payment->id, 'subscription_plan_amount' ),
			'currency' => (string) $payment->currency,
		];
	}
}
