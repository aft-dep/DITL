<?php

namespace WPForms\Integrations\Stripe;

/**
 * Front-end note under the Stripe card field of a form whose every plan starts with a trial.
 *
 * @since 2.0.4
 */
class TrialNote {

	/**
	 * Get the note markup for a form, or an empty string when the note does not apply.
	 *
	 * @since 2.0.4
	 *
	 * @param array $form_data Form data and settings.
	 * @param int   $now       Unix timestamp the trial would start at.
	 *
	 * @return string
	 */
	public function get_html( array $form_data, int $now ): string {

		$end_dates = $this->get_trial_end_dates( $form_data, $now );

		if ( ! $end_dates ) {
			return '';
		}

		$generic = esc_html__( "You won't be charged today.", 'wpforms-lite' );

		if ( count( array_unique( $end_dates ) ) > 1 ) {
			$html = sprintf( '<div class="wpforms-stripe-trial-note">%s</div>', $generic );
		} else {
			$details = sprintf( /* translators: %1$s - recurring payment amount, %2$s - first payment date. */
				esc_html__( 'Your first payment of %1$s is due on %2$s.', 'wpforms-lite' ),
				'<span class="wpforms-stripe-trial-note-amount"></span>',
				esc_html( wp_date( get_option( 'date_format' ), reset( $end_dates ) ) )
			);

			$html = sprintf( '<div class="wpforms-stripe-trial-note">%1$s %2$s</div>', $generic, $details );
		}

		/**
		 * Filter the trial note markup printed under the Stripe card field.
		 *
		 * The `wpforms-stripe-trial-note-amount` span, when kept, is filled with the form total by the front-end script.
		 *
		 * @since 2.0.4
		 *
		 * @param string $html      Note markup.
		 * @param array  $form_data Form data and settings.
		 */
		return (string) apply_filters( 'wpforms_integrations_stripe_trial_note_get_html', $html, $form_data );
	}

	/**
	 * Get the trial end date of every plan, or an empty array when any path can charge today.
	 *
	 * @since 2.0.4
	 *
	 * @param array $form_data Form data and settings.
	 * @param int   $now       Unix timestamp the trial would start at.
	 *
	 * @return int[]
	 */
	private function get_trial_end_dates( array $form_data, int $now ): array {

		$settings = $form_data['payments']['stripe'] ?? [];

		if (
			empty( $settings['enable_recurring'] ) ||
			! empty( $settings['enable_one_time'] ) ||
			Helpers::is_legacy_payment_settings( $form_data ) ||
			Helpers::is_legacy_api_version()
		) {
			return [];
		}

		$end_dates = [];

		foreach ( (array) ( $settings['recurring'] ?? [] ) as $plan ) {
			$trial = is_array( $plan ) ? TrialPeriod::from_plan( $plan ) : null;

			// A plan without a trial charges at submission, so the promise would not hold.
			if ( ! $trial ) {
				return [];
			}

			$end_dates[] = $trial->get_end_timestamp( $now );
		}

		return $end_dates;
	}
}
