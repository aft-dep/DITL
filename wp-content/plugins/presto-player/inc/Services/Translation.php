<?php
/**
 * Translation.
 *
 * @package PrestoPlayer\Services
 */

namespace PrestoPlayer\Services;

use PrestoPlayer\Models\Setting;
use PrestoPlayer\Contracts\Service;

/**
 * Translation.
 */
class Translation implements Service {

	/**
	 * Preset name translations.
	 *
	 * @var array
	 */
	protected $preset_name_translations = array();

	/**
	 * Register the service.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'load_script_textdomain_relative_path', array( $this, 'scriptsPath' ), 10, 2 );
		add_filter( 'presto_player/presto_player_presets/data', array( $this, 'translateDefaultPresets' ) );
		add_action( 'init', array( $this, 'loadPluginTextDomain' ), 0 );
		add_action( 'init', array( $this, 'initPresetTranslations' ), 0 );
	}

	/**
	 * This is needed for Loco translate to work properly.
	 *
	 * @return void
	 */
	public function loadPluginTextDomain() {
		load_plugin_textdomain( 'presto-player', false, dirname( plugin_basename( PRESTO_PLAYER_PLUGIN_FILE ) ) . '/languages' );
	}

	/**
	 * Translate the default presets.
	 *
	 * @param object $preset Preset object.
	 * @return object
	 */
	public function translateDefaultPresets( $preset ) {
		if ( empty( $preset->is_locked ) ) {
			return $preset;
		}

		// Translate name.
		$preset->name = isset( $this->preset_name_translations[ $preset->slug ] ) ? $this->preset_name_translations[ $preset->slug ] : $preset->name;

		// Youtube options.
		if ( 'youtube' === $preset->slug ) {
			$preset->action_bar['text'] = __( 'Subscribe To Our YouTube Channel', 'presto-player' );
			// Unset action bar if no channel id.
			if ( ! Setting::get( 'youtube', 'channel_id' ) ) {
				unset( $preset->action_bar );
				return $preset;
			}
		}

		return $preset;
	}

	/**
	 * Get the scripts path.
	 *
	 * @param string $path Path.
	 * @param string $src Source.
	 * @return string
	 */
	public function scriptsPath( $path, $src ) {
		if ( strpos( $src, 'presto-player' ) !== false ) {
			return './src';
		}
		return $path;
	}

	/**
	 * Get the i18n.
	 *
	 * @return array
	 */
	public static function geti18n() {
		return array(
			'skip'                 => __( 'Skip', 'presto-player' ),
			'rewatch'              => __( 'Rewatch', 'presto-player' ),
			'emailPlaceholder'     => __( 'Email address', 'presto-player' ),
			'emailDefaultHeadline' => __( 'Enter your email to play this episode.', 'presto-player' ),
			'chapters'             => __( 'Chapters', 'presto-player' ),
			'show_chapters'        => __( 'Show Chapters', 'presto-player' ),
			'hide_chapters'        => __( 'Hide Chapters', 'presto-player' ),
			'restart'              => __( 'Restart', 'presto-player' ),
			/* translators: %1ss: Number of seconds to rewind */
			'rewind'               => sprintf( __( 'Rewind %1ss', 'presto-player' ), '{seektime}' ),
			'play'                 => __( 'Play', 'presto-player' ),
			'pause'                => __( 'Pause', 'presto-player' ),
			/* translators: %1ss: Number of seconds to fast forward */
			'fastForward'          => sprintf( __( 'Forward %1ss', 'presto-player' ), '{seektime}' ),
			'seek'                 => __( 'Seek', 'presto-player' ),
			/* translators: %1$1s: Current time, %2$2s: Total duration */
			'seekLabel'            => sprintf( __( '%1$1s of %2$2s', 'presto-player' ), '{currentTime}', '{duration}' ),
			'played'               => __( 'Played', 'presto-player' ),
			'buffered'             => __( 'Buffered', 'presto-player' ),
			'currentTime'          => __( 'Current time', 'presto-player' ),
			'duration'             => __( 'Duration', 'presto-player' ),
			'volume'               => __( 'Volume', 'presto-player' ),
			'mute'                 => __( 'Mute', 'presto-player' ),
			'unmute'               => __( 'Unmute', 'presto-player' ),
			'enableCaptions'       => __( 'Enable captions', 'presto-player' ),
			'disableCaptions'      => __( 'Disable captions', 'presto-player' ),
			'download'             => __( 'Download', 'presto-player' ),
			'enterFullscreen'      => __( 'Enter fullscreen', 'presto-player' ),
			'exitFullscreen'       => __( 'Exit fullscreen', 'presto-player' ),
			'frameTitle'           => __( 'Player for {title}', 'presto-player' ),
			'captions'             => __( 'Captions', 'presto-player' ),
			'settings'             => __( 'Settings', 'presto-player' ),
			'pip'                  => __( 'PIP', 'presto-player' ),
			'menuBack'             => __( 'Go back to previous menu', 'presto-player' ),
			'speed'                => __( 'Speed', 'presto-player' ),
			'normal'               => __( 'Normal', 'presto-player' ),
			'quality'              => __( 'Quality', 'presto-player' ),
			'loop'                 => __( 'Loop', 'presto-player' ),
			'start'                => __( 'Start', 'presto-player' ),
			'end'                  => __( 'End', 'presto-player' ),
			'all'                  => __( 'All', 'presto-player' ),
			'reset'                => __( 'Reset', 'presto-player' ),
			'disabled'             => __( 'Disabled', 'presto-player' ),
			'enabled'              => __( 'Enabled', 'presto-player' ),
			'advertisement'        => __( 'Ad', 'presto-player' ),
			'qualityBadge'         => array(
				2160 => __( '4K', 'presto-player' ),
				1440 => __( 'HD', 'presto-player' ),
				1080 => __( 'HD', 'presto-player' ),
				720  => __( 'HD', 'presto-player' ),
				576  => __( 'SD', 'presto-player' ),
				480  => __( 'SD', 'presto-player' ),
			),
			'auto'                 => __( 'AUTO', 'presto-player' ),
			'upNext'               => __( 'Up Next', 'presto-player' ),
			'startOver'            => __( 'Start Over', 'presto-player' ),
		);
	}

	/**
	 * Set translated preset names here after text domain is loaded.
	 *
	 * @return void
	 */
	public function initPresetTranslations() {
		$this->preset_name_translations = array(
			'default' => __( 'Default', 'presto-player' ),
			'course'  => __( 'Course', 'presto-player' ),
			'simple'  => __( 'Simple', 'presto-player' ),
			'minimal' => __( 'Minimal', 'presto-player' ),
			'youtube' => __( 'YouTube Optimized', 'presto-player' ),
		);
	}
}
