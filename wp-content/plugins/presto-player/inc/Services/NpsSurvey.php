<?php
/**
 * NPS Survey Service.
 *
 * Responsible for loading the latest version of the NPS Survey library.
 * This ensures version arbitration works correctly when multiple plugins
 * ship the same library.
 *
 * @package PrestoPlayer\Services
 */

namespace PrestoPlayer\Services;

use PrestoPlayer\Contracts\Service;

/**
 * NPS Survey Service.
 */
class NpsSurvey implements Service {

	/**
	 * Register the service.
	 *
	 * @return void
	 */
	public function register() {
		$this->versionCheck();
		add_action( 'init', array( $this, 'load' ), 999 );
	}

	/**
	 * Version Check
	 *
	 * Compares the bundled NPS survey version against a global variable
	 * to ensure only the highest version is loaded (shared library pattern).
	 *
	 * @return void
	 */
	public function versionCheck(): void {
		$file = realpath( PRESTO_PLAYER_PLUGIN_DIR . 'inc/lib/nps-survey/version.json' );

		// Validate file exists and is readable before accessing.
		if ( $file && is_file( $file ) && is_readable( $file ) ) {
			// @codingStandardsIgnoreStart - file_get_contents is safe here: path is constructed from PRESTO_PLAYER_PLUGIN_DIR constant and hardcoded string, validated with realpath(), is_file(), and is_readable().
			$file_data = json_decode( file_get_contents( $file ), true );
			// @codingStandardsIgnoreEnd

			// Note: Global variables are used for version management across the NPS survey library.
			// This is intentional design to allow multiple plugins to share the same library instance.
			global $nps_survey_version, $nps_survey_init;

			$path    = realpath( PRESTO_PLAYER_PLUGIN_DIR . 'inc/lib/nps-survey/nps-survey.php' );
			$version = $file_data['nps-survey'] ?? 0;

			if ( null === $nps_survey_version ) {
				$nps_survey_version = '1.0.0';
			}

			// Compare versions - only register if our version is higher or equal.
			if ( version_compare( $version, $nps_survey_version, '>=' ) ) {
				$nps_survey_version = $version;
				$nps_survey_init    = $path;
			}
		}
	}

	/**
	 * Load the latest version of the NPS survey library.
	 *
	 * @return void
	 */
	public function load(): void {
		global $nps_survey_init;

		if ( ! empty( $nps_survey_init ) && is_file( realpath( $nps_survey_init ) ) ) {
			include_once realpath( $nps_survey_init );
		}
	}
}
