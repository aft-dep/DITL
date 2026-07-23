<?php
/**
 * Admin Notices.
 *
 * @package PrestoPlayer\Services
 */

namespace PrestoPlayer\Services;

use Astra_Notices;
use PrestoPlayer\Models\Video;
use PrestoPlayer\Support\Utility;

	/**
	 * Admin Notices.
	 */
class AdminNotices {

	/**
	 * NPS Survey dismiss timespan constant.
	 * Survey can be dismissed for 2 weeks before showing again.
	 *
	 * @var int
	 */
	const NPS_SURVEY_DISMISS_TIMESPAN = 2 * WEEK_IN_SECONDS;

	/**
	 * Ratings notice display delay constant.
	 * Notice displays after 7 days (604800 seconds).
	 *
	 * @var int
	 */
	const RATINGS_NOTICE_DISPLAY_DELAY = 604800; // 7 days in seconds.

	/**
	 * Register the admin notices.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_init', array( $this, 'dismiss' ) );
		add_action( 'init', array( $this, 'displayRatingsNotice' ) );
		add_action( 'admin_footer', array( $this, 'show_nps_notice' ), 999 );
	}

	/**
	 * Display the ratings notice.
	 *
	 * @return void
	 */
	public function displayRatingsNotice() {
		require_once PRESTO_PLAYER_PLUGIN_DIR . 'vendor/brainstormforce/astra-notices/class-astra-notices.php';
		$image_path = PRESTO_PLAYER_PLUGIN_URL . 'img/presto-player-icon-color.png';

		Astra_Notices::add_notice(
			array(
				'id'                         => 'presto-player-rating',
				'type'                       => '',
				'message'                    => sprintf(
					'<div class="notice-image">
						<img src="%1$s" class="custom-logo" alt="Sidebar Manager" itemprop="logo"></div> 
						<div class="notice-content">
							<div class="notice-heading">
								%2$s
							</div>
							%3$s<br />
							<div class="astra-review-notice-container">
								<a href="%4$s" class="astra-notice-close astra-review-notice button-primary" target="_blank">
								%5$s
								</a>
							<span class="dashicons dashicons-calendar"></span>
								<a href="#" data-repeat-notice-after="%6$s" class="astra-notice-close astra-review-notice">
								%7$s
								</a>
							<span class="dashicons dashicons-smiley"></span>
								<a href="#" class="astra-notice-close astra-review-notice">
								%8$s
								</a>
							</div>
						</div>',
					$image_path,
					__( 'Thanks a ton for choosing Presto Player! We are hard at work adding more features to help you harness the power of videos.', 'presto-player' ),
					__( 'Could you please do us a BIG favor and give us a 5-star rating on WordPress? It really boosts the motivation of our team.', 'presto-player' ),
					'https://wordpress.org/support/plugin/presto-player/reviews/?filter=5#new-post',
					__( 'Ok, you deserve it', 'presto-player' ),
					MONTH_IN_SECONDS,
					__( 'Nope, maybe later', 'presto-player' ),
					__( 'I already did', 'presto-player' )
				),
				'show_if'                    => $this->maybeDisplayRatingsNotice(),
				'repeat-notice-after'        => MONTH_IN_SECONDS,
				'display-notice-after'       => self::RATINGS_NOTICE_DISPLAY_DELAY,
				'priority'                   => 18,
				'display-with-other-notices' => false,
			)
		);
	}

	/**
	 * Check whether to display notice or not.
	 *
	 * @return bool
	 */
	public function maybeDisplayRatingsNotice() {
		$transient_status = get_transient( 'presto-player-rating' );

		if ( false !== $transient_status ) {
			return false;
		}

		$video_count = $this->getVideosCount();
		// Display ratings notice if video count is more than 1.
		return 0 < $video_count ? true : false;
	}

	/**
	 * Get the videos count.
	 *
	 * @return int
	 */
	public function getVideosCount() {
		$video = new Video();
		$items = $video->fetch(
			array(
				'per_page' => 1,
			)
		);

		return $items->total;
	}

	/**
	 * Check if the notice is dismissed.
	 *
	 * @param string $name Notice name.
	 * @return bool
	 */
	public static function isDismissed( $name ) {
		return (bool) get_option( 'presto_player_dismissed_notice_' . sanitize_text_field( $name ), false );
	}

	/**
	 * Dismiss the notice.
	 *
	 * @return void
	 */
	public function dismiss() {
		// Permissions check.
		if ( ! current_user_can( 'install_plugins' ) ) {
			return;
		}

		// Not our notices, bail.
		if ( ! isset( $_GET['presto_action'] ) || 'dismiss_notices' !== $_GET['presto_action'] ) {
			return;
		}

		// Get notice.
		$notice = ! empty( $_GET['presto_notice'] ) ? sanitize_text_field( $_GET['presto_notice'] ) : '';
		if ( ! $notice ) {
			return;
		}

		// Notice is dismissed.
		update_option( 'presto_player_dismissed_notice_' . sanitize_text_field( $notice ), 1 );
	}

	/**
	 * Render Presto Player NPS Survey notice.
	 *
	 * @return void
	 * @since 4.0.8
	 */
	public function show_nps_notice() {
		if ( ! class_exists( 'Nps_Survey' ) ) {
			return;
		}

		if ( ! Utility::isPrestoPlayerPage() ) {
			return;
		}

		// Don't load NPS at all when show_if would be false (no videos).
		// Avoids enqueuing NPS scripts/styles and outputting the survey div.
		if ( $this->getVideosCount() <= 0 ) {
			return;
		}

		\Nps_Survey::show_nps_notice(
			'nps-survey-presto-player',
			array(
				'show_if'          => $this->getVideosCount() > 0,
				'dismiss_timespan' => self::NPS_SURVEY_DISMISS_TIMESPAN,
				'display_after'    => 0,
				'plugin_slug'      => 'presto-player',
				'show_on_screens'  => Utility::PRESTO_PLAYER_SCREENS,
				'message'          => array(
					// Step 1 - Rating input.
					'logo'                  => esc_url( PRESTO_PLAYER_PLUGIN_URL . 'img/presto-player-icon-color.png' ),
					'plugin_name'           => __( 'Presto Player', 'presto-player' ),
					'nps_rating_title'      => __( 'Quick Question!', 'presto-player' ),
					'nps_rating_message'    => __( 'How would you rate Presto Player? Love it, hate it, or somewhere in between? Your honest answer helps us understand how we\'re doing.', 'presto-player' ),
					'rating_min_label'      => __( 'Hate it!', 'presto-player' ),
					'rating_max_label'      => __( 'Love it!', 'presto-player' ),

					// Step 2A - (rating 8-10).
					'feedback_title'        => __( 'Thanks a lot for your feedback! 😍', 'presto-player' ),
					'feedback_content'      => __( 'Thanks for being part of the Presto Player community! Got feedback or suggestions? We\'d love to hear it.', 'presto-player' ),

					// Step 2B - (rating 0-7).
					'plugin_rating_title'   => __( 'Thank you for your feedback', 'presto-player' ),
					'plugin_rating_content' => __( 'We value your input. How can we improve your experience?', 'presto-player' ),
				),
				'allow_review'     => false,
				'show_overlay'     => false,
			)
		);

		wp_add_inline_style( 'nps-survey-style', '[data-id="nps-survey-presto-player"] img { width: 1.3rem !important; }' );
	}
}
