<?php
/**
 * Usage service for tracking plugin statistics.
 *
 * @package PrestoPlayer\Services
 */

namespace PrestoPlayer\Services;

use PrestoPlayer\Contracts\Service;
use PrestoPlayer\Models\ReusableVideo;
use PrestoPlayer\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Usage Service.
 *
 * Collects anonymous usage analytics via BSF Analytics when user opts in.
 * Data collected (when user opts in):
 * - Video counts.
 *
 * No personally identifiable information (PII) is collected.
 * All data is aggregated and anonymous.
 *
 * @package PrestoPlayer\Services
 */
class Usage implements Service {

	/**
	 * Register the service (admin only).
	 *
	 * @return void
	 */
	public function register() {
		if ( ! is_admin() ) {
			return;
		}

		$this->load_bsf_analytics_loader();
		$this->set_bsf_analytics_entity();
		add_filter( 'bsf_core_stats', array( $this, 'update_stats' ) );
	}

	/**
	 * Load the BSF Analytics loader if not already loaded.
	 *
	 * @return void
	 */
	private function load_bsf_analytics_loader() {
		if ( ! class_exists( 'BSF_Analytics_Loader' ) ) {
			require_once PRESTO_PLAYER_PLUGIN_DIR . 'inc/lib/bsf-analytics/class-bsf-analytics-loader.php';
		}
	}

	/**
	 * Set BSF Analytics Entity.
	 */
	public function set_bsf_analytics_entity() {
		if ( ! class_exists( 'BSF_Analytics_Loader' ) ) {
			return;
		}

		$pp_bsf_analytics = \BSF_Analytics_Loader::get_instance();

		$pp_bsf_analytics->set_entity(
			array(
				'presto-player' => array(
					'product_name'        => 'Presto Player',
					'path'                => PRESTO_PLAYER_PLUGIN_DIR . 'inc/lib/bsf-analytics',
					'author'              => 'Presto Made, Inc',
					'time_to_display'     => '+24 hours',
					'deactivation_survey' => apply_filters(
						'presto_player_deactivation_survey_data',
						array(
							array(
								'id'                => 'deactivation-survey-presto-player',
								'popup_logo'        => PRESTO_PLAYER_PLUGIN_URL . 'img/presto-player-icon-color.png',
								'plugin_slug'       => 'presto-player',
								'popup_title'       => __( 'Quick Feedback', 'presto-player' ),
								'support_url'       => 'https://prestoplayer.com/support/',
								'popup_description' => __( 'If you have a moment, please share why you are deactivating Presto Player:', 'presto-player' ),
								'show_on_screens'   => array( 'plugins' ),
								'plugin_version'    => Plugin::version(),
							),
						)
					),
					'hide_optin_checkbox' => true,
				),
			)
		);
	}

	/**
	 * Update BSF Analytics stats with Presto Player usage stats.
	 *
	 * @param array<mixed> $stats existing stats_data.
	 * @return array<mixed> $stats modified stats_data.
	 */
	public function update_stats( $stats ) {
		$media = new ReusableVideo();

		$stats['plugin_data']['presto_player'] = array(
			'free_version'  => Plugin::version(),
			'site_language' => get_locale(),
			'total_videos'  => $media->getTotalPublished(),
		);

		return apply_filters( 'presto_player_usage_stats', $stats );
	}
}
