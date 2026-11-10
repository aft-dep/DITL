<?php

namespace WPForms\Integrations\Stripe\Api\Traits;

use Exception;
use WPForms\Integrations\Stripe\Helpers;
use WPForms\Vendor\Stripe\SetupIntent;
use WPForms\Vendor\Stripe\Subscription;

/**
 * Trial subscription flow of the PaymentIntents API.
 *
 * A trial charges nothing at submission, so there is no PaymentIntent to confirm. The card is set up
 * first through a SetupIntent, with 3DS where the bank asks, and the subscription is created only once
 * the card is saved, keyed on the SetupIntent id so a replayed confirmation cannot create a second one.
 *
 * @since 2.0.4
 */
trait TrialSubscription {

	/**
	 * Process a trial subscription: set the card up on the first pass, subscribe on the confirmed one.
	 *
	 * @since 2.0.4
	 *
	 * @param array $args Subscription arguments, with the Stripe trial arguments under `trial`.
	 */
	protected function process_trial_subscription( array $args ) {

		if ( $this->setup_intent_id ) {
			$this->finalize_trial_subscription( $args );

			return;
		}

		if ( ! $this->payment_method_id ) {
			$this->error = esc_html__( 'Stripe subscription stopped, missing PaymentMethod id.', 'wpforms-lite' );

			return;
		}

		$this->start_trial_subscription( $args );
	}

	/**
	 * Refuse a SetupIntent on a payment that charges: a saved card pays for nothing on its own.
	 *
	 * @since 2.0.4
	 *
	 * @return bool True when the payment was stopped.
	 */
	protected function reject_setup_intent(): bool {

		if ( ! $this->setup_intent_id ) {
			return false;
		}

		$this->error = esc_html__( 'Stripe payment stopped, invalid SetupIntent.', 'wpforms-lite' );

		return true;
	}

	/**
	 * Set the submitted card up for off-session use and subscribe it when no authentication is needed.
	 *
	 * @since 2.0.4
	 *
	 * @param array $args Subscription arguments.
	 */
	protected function start_trial_subscription( array $args ) {

		try {
			$this->set_customer( $args['email'], $args['customer_name'] ?? '', $args['customer_address'] ?? [], $args['customer_phone'] ?? '', $args['customer_metadata'] ?? [] );

			$this->intent = $this->request_setup_intent( $this->get_setup_intent_params( $args ) );
		} catch ( Exception $e ) {
			$this->handle_exception( $e );

			return;
		}

		if ( $this->intent->status === 'succeeded' ) {
			$this->create_trial_subscription( $args );

			return;
		}

		if ( $this->intent->status !== 'requires_action' ) {
			$this->error = esc_html__( 'Stripe subscription stopped. Invalid SetupIntent status.', 'wpforms-lite' );

			return;
		}

		try {
			$this->set_bypass_captcha_3dsecure_token( $args );
		} catch ( Exception $e ) {
			$this->handle_exception( $e );

			return;
		}

		// Outside the try blocks: the response ends the request, nothing after it may intercept it.
		$this->request_setup_action_ajax( $this->intent );
	}

	/**
	 * Subscribe the card the browser finished authenticating.
	 *
	 * @since 2.0.4
	 *
	 * @param array $args Subscription arguments.
	 */
	protected function finalize_trial_subscription( array $args ) {

		$this->intent = $this->retrieve_setup_intent( $this->setup_intent_id, [ 'expand' => [ 'customer', 'payment_method' ] ] );

		if ( empty( $this->intent->status ) || $this->intent->status !== 'succeeded' ) {
			$this->error = esc_html__( 'Stripe subscription was not confirmed. Please check your Stripe dashboard.', 'wpforms-lite' );

			return;
		}

		$this->customer = $this->intent->customer;

		$this->create_trial_subscription( $args );
	}

	/**
	 * Create the trialing subscription on the saved card.
	 *
	 * @since 2.0.4
	 *
	 * @param array $args Subscription arguments.
	 */
	protected function create_trial_subscription( array $args ) {

		$sub_args = [
			'customer'               => $this->get_customer( 'id' ),
			'items'                  => [
				[
					'plan' => $this->get_plan_id( $args ),
				],
			],
			'default_payment_method' => $this->get_saved_payment_method_id(),
			'metadata'               => [
				'form_name' => $args['form_title'],
				'form_id'   => $args['form_id'],
				'cycles'    => $args['cycles'] ?? null,
			],
			'expand'                 => [ 'latest_invoice' ],
		] + (array) $args['trial'];

		if ( isset( $args['application_fee_percent'] ) ) {
			$sub_args['application_fee_percent'] = $args['application_fee_percent'];
		}

		if ( isset( $args['description'] ) ) {
			$sub_args['description'] = $args['description'];
		}

		try {
			$this->subscription = $this->request_subscription( $sub_args, [ 'idempotency_key' => 'wpforms-trial-' . $this->intent->id ] );

			if ( $this->subscription->status !== 'trialing' ) {
				$this->error = esc_html__( 'Stripe subscription stopped. Invalid subscription status.', 'wpforms-lite' );
			}
		} catch ( Exception $e ) {
			$this->handle_exception( $e );
		}
	}

	/**
	 * Ask the browser to authenticate the SetupIntent.
	 *
	 * @since 2.0.4
	 *
	 * @param SetupIntent $intent SetupIntent to authenticate.
	 */
	protected function request_setup_action_ajax( $intent ) {

		wp_send_json_success(
			[
				'action_required'            => true,
				'setup_intent_client_secret' => $intent->client_secret,
				'payment_method_id'          => $this->payment_method_id,
			]
		);
	}

	/**
	 * Retrieve a SetupIntent from Stripe.
	 *
	 * @since 2.0.4
	 *
	 * @param string $id   SetupIntent id.
	 * @param array  $args Additional arguments (e.g. 'expand').
	 *
	 * @return SetupIntent|null
	 */
	public function retrieve_setup_intent( string $id, array $args = [] ) {

		try {
			return SetupIntent::retrieve( wp_parse_args( $args, [ 'id' => $id ] ), Helpers::get_auth_opts() );
		} catch ( Exception $e ) {
			$this->handle_exception( $e );
		}

		return null;
	}

	/**
	 * Create and confirm a SetupIntent. Exceptions propagate so card declines reach the rate limits.
	 *
	 * @since 2.0.4
	 *
	 * @param array $params SetupIntent parameters.
	 *
	 * @return SetupIntent
	 */
	protected function request_setup_intent( array $params ) {

		return SetupIntent::create( $params, Helpers::get_auth_opts() );
	}

	/**
	 * Create a subscription. Exceptions propagate to the caller.
	 *
	 * @since 2.0.4
	 *
	 * @param array $params Subscription parameters.
	 * @param array $opts   Request options, e.g. the idempotency key.
	 *
	 * @return Subscription
	 */
	protected function request_subscription( array $params, array $opts ) {

		return Subscription::create( $params, $opts + Helpers::get_auth_opts() );
	}

	/**
	 * Get the id of the payment method the SetupIntent saved.
	 *
	 * @since 2.0.4
	 *
	 * @return string
	 */
	private function get_saved_payment_method_id(): string {

		$payment_method = $this->intent->payment_method ?? '';

		return is_object( $payment_method ) ? (string) $payment_method->id : (string) $payment_method;
	}

	/**
	 * Get the parameters of the SetupIntent that saves the submitted card for off-session use.
	 *
	 * @since 2.0.4
	 *
	 * @param array $args Subscription arguments.
	 *
	 * @return array
	 */
	private function get_setup_intent_params( array $args ): array {

		$params = [
			'customer'             => $this->get_customer( 'id' ),
			'payment_method'       => $this->payment_method_id,
			'usage'                => 'off_session',
			'confirm'              => true,
			'payment_method_types' => Helpers::is_link_supported() ? [ 'card', 'link' ] : [ 'card' ],
			'metadata'             => [
				'form_name' => $args['form_title'],
				'form_id'   => $args['form_id'],
			],
			'expand'               => [ 'payment_method' ],
		];

		// The acceptance evidence is the visitor's IP and user agent, which the GDPR settings may forbid collecting.
		if ( ! empty( $args['collect_user_details'] ) ) {
			$params['mandate_data'] = $this->get_mandate_data();
		}

		return $params;
	}

	/**
	 * Get the online acceptance evidence Stripe stores with an off-session card: the visitor's IP and user agent.
	 *
	 * @since 2.0.4
	 *
	 * @return array
	 */
	private function get_mandate_data(): array {

		return [
			'customer_acceptance' => [
				'type'   => 'online',
				'online' => [
					'ip_address' => wpforms_get_ip(),
					'user_agent' => sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ),
				],
			],
		];
	}
}
