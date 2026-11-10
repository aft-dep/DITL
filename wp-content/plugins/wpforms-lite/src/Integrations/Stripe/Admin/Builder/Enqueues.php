<?php

namespace WPForms\Integrations\Stripe\Admin\Builder;

use WPForms\Integrations\Stripe\Helpers;
use WPForms\Integrations\Stripe\TrialPeriod;

/**
 * Script enqueues for the Stripe Builder settings panel.
 *
 * @since 1.8.2
 */
class Enqueues {

	/**
	 * Initialize.
	 *
	 * @since 1.8.2
	 */
	public function init() {

		$this->hooks();
	}

	/**
	 * Hooks.
	 *
	 * @since 1.8.2
	 */
	private function hooks() {

		add_filter( 'wpforms_builder_strings', [ $this, 'javascript_strings' ], 10, 2 );
		add_action( 'wpforms_builder_enqueues', [ $this, 'enqueues' ] );
	}

	/**
	 * Add our localized strings to be available in the form builder.
	 *
	 * @since 1.8.2
	 *
	 * @param array $strings Form builder JS strings.
	 * @param array $form    Form data and settings.
	 *
	 * @return array
	 */
	public function javascript_strings( $strings, $form = [] ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed

		$strings = (array) $strings;

		$strings['stripe_recurring_heading']         = esc_html__( 'Missing Required Fields', 'wpforms-lite' );
		$strings['stripe_recurring_email']           = esc_html__( 'When recurring subscription payments are enabled, the Customer Email is required.', 'wpforms-lite' );
		$strings['stripe_required_one_time_fields']  = esc_html__( 'In order to complete your form\'s Stripe One-Time Payments, please check that all required (*) fields have been filled out.', 'wpforms-lite' );
		$strings['stripe_required_recurring_fields'] = esc_html__( 'In order to complete your form\'s Stripe Recurring Subscription Payments, please check that all required (*) fields have been filled out.', 'wpforms-lite' );
		$strings['stripe_required_both_fields']      = esc_html__( 'In order to complete your form\'s Stripe One-Time Payments and Recurring Subscription Payments, please check that all required (*) fields have been filled out.', 'wpforms-lite' );
		$strings['stripe_recurring_settings']        = wp_kses(
			__( 'Please go to the <a href="#" class="wpforms-stripe-settings-redirect">Stripe payment settings</a> and fill out the required field(s).', 'wpforms-lite' ),
			[
				'a' => [
					'href'  => [],
					'class' => [],
				],
			]
		);
		$strings['stripe_webhooks_heading']          = esc_html__( 'Webhooks Required', 'wpforms-lite' );
		$strings['stripe_webhooks_required']         = wp_kses(
			sprintf( /* translators: %s - WPForms payment settings page URL. */
				__( '<p>Stripe webhooks are required when using a trial period or a limited number of recurring cycles.</p><p>To proceed, please go to <a href="%s" target="_blank" rel="noopener noreferrer">Payment Settings</a> and check <strong>Enable Webhooks</strong>.</p>', 'wpforms-lite' ),
				esc_url( Helpers::get_settings_page_url() )
			),
			[
				'p'      => [],
				'strong' => [],
				'a'      => [
					'href'   => [],
					'target' => [],
					'rel'    => [],
				],
			]
		);

		return $strings;
	}

	/**
	 * Enqueue assets for the builder.
	 *
	 * @since 1.8.2
	 *
	 * @param string|null $view Current view.
	 */
	public function enqueues( $view = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found

		$min = wpforms_get_min_suffix();

		if ( Helpers::has_stripe_keys() ) {
			wp_enqueue_style(
				'wpforms-builder-stripe-common',
				WPFORMS_PLUGIN_URL . "assets/css/integrations/stripe/builder-stripe-common{$min}.css",
				[],
				WPFORMS_VERSION
			);
		}

		wp_enqueue_script(
			'wpforms-builder-stripe',
			WPFORMS_PLUGIN_URL . "assets/js/integrations/stripe/admin-builder-stripe{$min}.js",
			[ 'conditions' ],
			WPFORMS_VERSION,
			false
		);

		wp_enqueue_script(
			'wpforms-builder-modern-stripe',
			WPFORMS_PLUGIN_URL . "assets/js/integrations/stripe/admin-builder-modern-stripe{$min}.js",
			[],
			WPFORMS_VERSION,
			false
		);

		/**
		 * Allow to filter builder stripe script data.
		 *
		 * @since 1.8.2
		 * @since 1.9.5 Added the `field_slug` key.
		 * @since 2.0.4 Added the `trial_units` and `webhooks_enabled` keys.
		 *
		 * @param array $data Script data.
		 */
		$script_data = (array) apply_filters(
			'wpforms_integrations_stripe_admin_builder_enqueues_data',
			[
				'field_slug'       => Helpers::get_field_slug(),
				'field_slugs'      => [ 'stripe-credit-card' ],
				'is_pro'           => Helpers::is_pro(),
				'has_keys'         => Helpers::has_stripe_keys(),
				'cycles_max'       => Helpers::recurring_plan_cycles_max(),
				'trial_units'      => TrialPeriod::get_units(),
				'webhooks_enabled' => (bool) Helpers::is_webhook_enabled(),
				'i18n'             => [
					'cycles_default' => esc_html__( 'Unlimited', 'wpforms-lite' ),
				],
			]
		);

		wp_localize_script(
			'wpforms-builder-stripe',
			'wpforms_builder_stripe',
			$script_data
		);
	}
}
