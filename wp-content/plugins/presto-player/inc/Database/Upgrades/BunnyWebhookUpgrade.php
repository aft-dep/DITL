<?php
/**
 * Registers Bunny.net webhook URL for existing users on plugin update.
 *
 * @package PrestoPlayer\Database\Upgrades
 */

namespace PrestoPlayer\Database\Upgrades;

use PrestoPlayer\Models\Setting;
use PrestoPlayer\Pro\Controllers\BunnyVideoLibraryController;

/**
 * Registers Bunny.net webhook URL for existing users on plugin update.
 */
class BunnyWebhookUpgrade {

	/**
	 * Option name for tracking upgrade version.
	 *
	 * @var string
	 */
	protected $name = 'presto_player_bunny_webhook_version';

	/**
	 * Current upgrade version.
	 *
	 * @var int
	 */
	protected $version = 1;

	/**
	 * Run the upgrade.
	 *
	 * @return void
	 */
	public function run() {
		// Only run if Bunny is connected.
		if ( ! $this->isBunnyConnected() ) {
			return;
		}

		$current_version = get_option( $this->name, 0 );

		// Already done for this version.
		if ( $this->version <= $current_version ) {
			return;
		}

		$ran = $this->registerWebhooks();

		// Only mark complete when we actually ran. If skipped (Pro outdated), leave
		// version unset so it re-runs when Pro is updated.
		if ( $ran ) {
			update_option( $this->name, $this->version );
		}
	}

	/**
	 * Check if Bunny is connected (has library IDs stored).
	 *
	 * @return bool
	 */
	protected function isBunnyConnected() {
		return ! empty( Setting::get( 'bunny_stream_public', 'video_library_id' ) )
			|| ! empty( Setting::get( 'bunny_stream_private', 'video_library_id' ) );
	}

	/**
	 * Register webhooks for public and private libraries.
	 *
	 * Uses the service method to validate and register webhook URLs.
	 * Upgrade process continues silently on errors to avoid breaking the update.
	 * Skips if Pro plugin is outdated and does not have the webhooks() method.
	 *
	 * @return bool True if webhook registration was attempted, false if skipped (Pro outdated).
	 */
	protected function registerWebhooks() {
		// Require Pro plugin to have webhooks() method (added with transcription feature).
		// Skip gracefully if Core was updated before Pro to avoid fatal error.
		if ( ! class_exists( BunnyVideoLibraryController::class ) ) {
			return false;
		}

		$library_controller = new BunnyVideoLibraryController();

		// Check if the webhooks method exists.
		if ( ! method_exists( $library_controller, 'webhooks' ) ) {
			return false;
		}

		// Register webhook for public library if it exists.
		$public_service = $library_controller->webhooks( 'public' );
		$public_result  = $public_service->registerWebhookUrl();

		// Register webhook for private library if it exists.
		$private_service = $library_controller->webhooks( 'private' );
		$private_result  = $private_service->registerWebhookUrl();

		return true;
	}
}
