<?php
/**
 * Plugin settings registration.
 *
 * @package presto-player
 */

namespace PrestoPlayer\Services;

use PrestoPlayer\Plugin;
use PrestoPlayer\Models\Setting;

/**
 * Settings service.
 */
class Settings {

	/**
	 * Register our settings
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_init', array( $this, 'registerSettings' ) );
		add_action( 'rest_api_init', array( $this, 'registerSettings' ) );
	}

	/**
	 * Sanitize usage tracking value to 'yes' or 'no'.
	 *
	 * @param mixed $value Input value. Accepts 'yes', 'no', boolean, or truthy/falsy values.
	 * @return string 'yes' or 'no'
	 */
	public function sanitize_usage_tracking( $value ) {
		if ( 'yes' === $value || 'no' === $value ) {
			return $value;
		}
		if ( ! is_scalar( $value ) ) {
			return 'no';
		}
		$bool = filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
		if ( null === $bool ) {
			$bool = (bool) $value;
		}
		return $bool ? 'yes' : 'no';
	}

	/**
	 * Register plugin settings for WP Settings API / REST.
	 *
	 * @return void
	 */
	public function registerSettings() {
		/**
		 * Analytics settings
		 */
		\register_setting(
			'presto_player',
			'presto_player_analytics',
			array(
				'type'         => 'object',
				'description'  => __( 'Analytics settings.', 'presto-player' ),
				'show_in_rest' => array(
					'name'   => 'presto_player_analytics',
					'type'   => 'object',
					'schema' => array(
						'properties' => array(
							'enable'     => array(
								'type' => 'boolean',
							),
							'purge_data' => array(
								'type' => 'boolean',
							),
						),
					),
				),
				'default'      => array(
					'enable'     => false,
					'purge_data' => true,
				),
			)
		);

		/**
		 * Branding settings
		 */
		\register_setting(
			'presto_player',
			'presto_player_branding',
			array(
				'type'         => 'object',
				'description'  => __( 'Branding settings.', 'presto-player' ),
				'show_in_rest' => array(
					'name'   => 'presto_player_branding',
					'type'   => 'object',
					'schema' => array(
						'properties' => array(
							'logo'       => array(
								'type'              => 'string',
								'sanitize_callback' => 'esc_url_raw',
							),
							'logo_width' => array(
								'type'              => 'number',
								'sanitize_callback' => 'intval',
							),
							'color'      => array(
								'type'              => 'string',
								'sanitize_callback' => 'sanitize_hex_color',
							),
							'player_css' => array(
								'type' => 'string',
							),
						),
					),
				),
				'default'      => array(
					'logo'       => '',
					'logo_width' => 150,
					'color'      => Setting::getDefaultColor(),
					'player_css' => '',
				),
			)
		);

		\register_setting(
			'presto_player',
			'presto_player_performance',
			array(
				'type'         => 'object',
				'description'  => __( 'Performance settings.', 'presto-player' ),
				'show_in_rest' => array(
					'name'   => 'presto_player_performance',
					'type'   => 'object',
					'schema' => array(
						'properties' => array(
							'module_enabled' => array(
								'type' => 'boolean',
							),
							'automations'    => array(
								'type' => 'boolean',
							),
						),
					),
				),
				'default'      => array(
					'module_enabled' => false,
					'automations'    => true,
				),
			)
		);

		/**
		 * Uninstall settings
		 */
		\register_setting(
			'presto_player',
			'presto_player_uninstall',
			array(
				'type'         => 'object',
				'description'  => __( 'Uninstall settings.', 'presto-player' ),
				'show_in_rest' => array(
					'name'   => 'presto_player_uninstall',
					'type'   => 'object',
					'schema' => array(
						'properties' => array(
							'uninstall_data' => array(
								'type' => 'boolean',
							),
						),
					),
				),
				'default'      => array(
					'uninstall_data' => false,
				),
			)
		);

		/**
		 * Analytics settings
		 */
		\register_setting(
			'presto_player',
			'presto_player_google_analytics',
			array(
				'type'         => 'object',
				'description'  => __( 'Google Analytics settings.', 'presto-player' ),
				'show_in_rest' => array(
					'name'   => 'presto_player_google_analytics',
					'type'   => 'object',
					'schema' => array(
						'properties' => array(
							'enable'           => array(
								'type' => 'boolean',
							),
							'use_existing_tag' => array(
								'type' => 'boolean',
							),
							'measurement_id'   => array(
								'type' => 'string',
							),
						),
					),
				),
				'default'      => array(
					'enable'           => false,
					'use_existing_tag' => false,
					'measurement_id'   => '',
				),
			)
		);

		/**
		 * General settings
		 */
		\register_setting(
			'presto_player',
			'presto_player_presets',
			array(
				'type'         => 'object',
				'description'  => __( 'Preset settings.', 'presto-player' ),
				'show_in_rest' => array(
					'name'   => 'presto_player_presets',
					'type'   => 'object',
					'schema' => array(
						'properties' => array(
							'default_player_preset' => array(
								'type'              => 'integer',
								'sanitize_callback' => 'intval',
							),
						),
					),
				),
				'default'      => array(
					'default_player_preset' => 1,
				),
			)
		);

		\register_setting(
			'presto_player',
			'presto_player_audio_presets',
			array(
				'type'         => 'object',
				'description'  => __( 'Preset settings.', 'presto-player' ),
				'show_in_rest' => array(
					'name'   => 'presto_player_audio_presets',
					'type'   => 'object',
					'schema' => array(
						'properties' => array(
							'default_player_preset' => array(
								'type'              => 'integer',
								'sanitize_callback' => 'intval',
							),
						),
					),
				),
				'default'      => array(
					'default_player_preset' => 1,
				),
			)
		);

		/**
		 * Youtube Settings
		 */
		\register_setting(
			'presto_player',
			'presto_player_youtube',
			array(
				'type'         => 'object',
				'description'  => __( 'Youtube settings.', 'presto-player' ),
				'show_in_rest' => array(
					'name'   => 'presto_player_youtube',
					'type'   => 'object',
					'schema' => array(
						'properties' => array(
							'nocookie'   => array(
								'type' => 'boolean',
							),
							'channel_id' => array(
								'type' => 'string',
							),
						),
					),
				),
				'default'      => array(
					'nocookie'   => false,
					'channel_id' => '',
				),
			)
		);

		/**
		 * Instant Video Width Setting
		 */
		\register_setting(
			'presto_player',
			'presto_player_instant_video_width',
			array(
				'type'         => 'string',
				'description'  => __( 'Instant video width.', 'presto-player' ),
				'show_in_rest' => array(
					'name'   => 'presto_player_instant_video_width',
					'type'   => 'string',
					'schema' => array(
						'type'    => 'string',
						'default' => '800px',
					),
				),
				'default'      => '800px',
			)
		);

		/**
		 * Set the default for media hub sync setting.
		 */
		\register_setting(
			'presto_player',
			'presto_player_media_hub_sync_default',
			array(
				'type'         => 'boolean',
				'description'  => __( 'Set the default for media hub sync setting.', 'presto-player' ),
				'show_in_rest' => array(
					'name'   => 'presto_player_media_hub_sync_default',
					'type'   => 'boolean',
					'schema' => array(
						'type'    => 'boolean',
						'default' => true,
					),
				),
				'default'      => true,
			)
		);

		/**
		 * Usage tracking setting.
		 *
		 * Stored as 'yes' or 'no'. Single source of truth for usage analytics opt-in.
		 */
		\register_setting(
			'presto_player',
			'presto-player_usage_optin',
			array(
				'type'              => 'string',
				'description'       => __( 'Usage tracking opt-in.', 'presto-player' ),
				'sanitize_callback' => array( $this, 'sanitize_usage_tracking' ),
				'show_in_rest'      => array(
					'name'   => 'presto-player_usage_optin',
					'type'   => 'string',
					'schema' => array(
						'type'    => 'string',
						'enum'    => array( 'yes', 'no' ),
						'default' => 'no',
					),
				),
				'default'           => 'no',
			)
		);
	}

	/**
	 * Render settings page container.
	 *
	 * @return void
	 */
	public static function template() {
		?>
		<?php do_action( 'presto_player_settings_header' ); ?>
		<div class="presto-player-dashboard__header">
			<img class="presto-player-dashboard__logo" src="<?php echo esc_url( PRESTO_PLAYER_PLUGIN_URL . '/img/logo.svg' ); ?>" />
			<div class="presto-player-dashboard__version">v<?php echo esc_html( Plugin::version() ); ?></div>
		</div>
		<div id="presto-settings-page"></div>
		<?php wp_auth_check_html(); ?>
		<?php do_action( 'presto_player_settings_footer' ); ?>
		<?php
	}
}
