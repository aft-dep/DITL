<?php
/**
 * Shortcodes service file.
 *
 * @package PrestoPlayer\Services
 */

namespace PrestoPlayer\Services;

use PrestoPlayer\Blocks\AudioBlock;
use PrestoPlayer\Pro\Blocks\PlaylistBlock;
use PrestoPlayer\Models\Video;
use PrestoPlayer\Blocks\VimeoBlock;
use PrestoPlayer\Blocks\YouTubeBlock;
use PrestoPlayer\Blocks\SelfHostedBlock;
use PrestoPlayer\Models\ReusableVideo;
use PrestoPlayer\Services\ReusableVideos;
use PrestoPlayer\Pro\Blocks\BunnyCDNBlock;
use PrestoPlayer\Plugin;

/**
 * Shortcodes service.
 *
 * @package PrestoPlayer\Services
 */
class Shortcodes {

	/**
	 * Register shortcodes.
	 *
	 * @return void
	 */
	public function register() {
		add_shortcode( 'presto_player_chapter', '__return_false' );
		add_shortcode( 'presto_playlist_item', '__return_false' );
		add_shortcode( 'presto_player_overlay', '__return_false' );
		add_shortcode( 'presto_player_track', '__return_false' );
		add_shortcode( 'presto_player', array( $this, 'playerShortcode' ), 10, 2 );
		add_shortcode( 'presto_timestamp', array( $this, 'timestampShortcode' ), 10, 2 );
		add_shortcode( 'pptime', array( $this, 'timestampShortcode' ), 10, 2 );
		add_shortcode( 'presto_playlist', array( $this, 'playlistShortcode' ), 10, 2 );
		add_shortcode( 'presto_popup', array( $this, 'popupShortcode' ), 10, 2 );
	}

	/**
	 * Do the shortcode
	 *
	 * @param array  $atts Array of shortcode attributes.
	 * @param string $content String of content.
	 * @return void|string
	 */
	public function playerShortcode( $atts, $content ) {
		if ( ! is_admin() ) {
			// global is the most reliable between page builders.
			global $load_presto_js;
			$load_presto_js = true;
			( new Scripts() )->blockAssets(); // Enqueue block assets.
		}

		$atts = shortcode_atts(
			array(
				'id'                             => '',
				'src'                            => '',
				'title'                          => '',
				'provider'                       => '',
				'class'                          => '',
				'custom_field'                   => '',
				'poster'                         => '',
				'preload'                        => 'auto',
				'preset'                         => 0,
				'autoplay'                       => false,
				'plays_inline'                   => false,
				'chapters'                       => array(),
				'overlays'                       => array(),
				'muted_autoplay_preview'         => false,
				'muted_autoplay_caption_preview' => false,
				'ratio'                          => 'original',
			),
			$atts
		);

		// could not find source but ID is present.
		if ( ! $atts['src'] && ! $atts['custom_field'] && $atts['id'] ) {
			return ReusableVideos::getBlock( $atts['id'] );
		}

		$atts = $this->parseAttributes( $atts, $content );
		return $this->renderBlock( $atts );
	}

	/**
	 * Do the shortcode
	 *
	 * @param array  $atts Array of shortcode attributes.
	 * @param string $content String of content.
	 * @return void|string
	 */
	public function playlistShortcode( $atts, $content ) {
		if ( ! is_admin() ) {
			// global is the most reliable between page builders.
			global $load_presto_js;
			$load_presto_js = true;
			( new Scripts() )->blockAssets(); // enqueue block assets.
		}

		$atts = $this->parsePlaylistAttributes( $atts, $content );

		return ( new PlaylistBlock() )->html( $atts );
	}

	/**
	 * Timestamp shortcode.
	 *
	 * @param array  $atts Shortcode attributes.
	 * @param string $content Content inside shortcode.
	 * @return string
	 */
	public function timestampShortcode( $atts, $content ) {
		$atts = shortcode_atts(
			array(
				'time' => '',
			),
			$atts
		);
		return '<presto-timestamp time="' . esc_attr( $atts['time'] ) . '">' . $content . '</presto-timestamp>';
	}

	/**
	 * Popup shortcode.
	 *
	 * @param array  $atts Shortcode attributes.
	 * @param string $content Content inside shortcode.
	 * @return string
	 */
	public function popupShortcode( $atts, $content ) {
		$atts = shortcode_atts(
			array(
				'media_id'    => '',
				'image_url'   => '',
				'button_text' => '',
			),
			$atts
		);

		if ( empty( $atts['media_id'] ) ) {
			return '';
		}

		// Get the popup block content.
		$popup_block_content = $this->popupBlockContent( $atts, $content );

		// Return the processed block markup.
		return $this->getProcessedBlockHtml( $popup_block_content );
	}

	/**
	 * Generate complete popup block content including trigger.
	 *
	 * @param array  $atts Shortcode attributes.
	 * @param string $content Content between shortcode tags.
	 * @return string
	 */
	private function popupBlockContent( $atts, $content ) {
		$trigger_content = '';

		// Image trigger.
		if ( ! empty( $atts['image_url'] ) ) {
			// Use core/image for non-premium users, core/cover for premium users.
			if ( ! Plugin::hasRequiredProVersion( 'popups' ) ) {
				// For non-premium users, use the popup-image-trigger variation of core/image.
				$trigger_content .= '<!-- wp:image {"url":"' . esc_url( $atts['image_url'] ) . '","className":"presto-popup-image-trigger","style":{"aspectRatio":"16/9","border":{"radius":"5px"}},"width":"100%","height":"auto","sizeSlug":"large"} -->
				<figure class="wp-block-image size-large presto-popup-image-trigger" style="aspect-ratio:16/9;border-radius:5px;width:100%"><img src="' . esc_url( $atts['image_url'] ) . '" alt="" style="width:100%;height:auto"/></figure>
				<!-- /wp:image -->';
			} else {
				// For premium users, use core/cover block with overlay and play button.
				$play_button_url  = PRESTO_PLAYER_PLUGIN_URL . 'img/play-button.svg';
				$trigger_content .= '<!-- wp:cover {"url":"' . esc_url( $atts['image_url'] ) . '","className":"presto-popup-cover-trigger","customOverlayColor":"#131313","isUserOverlayColor":true,"dimRatio":50,"minHeight":336,"minHeightUnit":"px","contentPosition":"center center","layout":{"type":"constrained"},"style":{"aspectRatio":"16/9","border":{"radius":"5px"}}} -->
				<div class="wp-block-cover presto-popup-cover-trigger" style="border-radius:5px;aspect-ratio:16/9;min-height:336px"><span aria-hidden="true" class="wp-block-cover__background has-background-dim has-background-dim-50" style="background-color:#131313"></span><div class="wp-block-cover__image-background" style="background-image:url(' . esc_url( $atts['image_url'] ) . ');background-position:50% 50%"></div><div class="wp-block-cover__inner-container"><!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"center"}} -->
				<div class="wp-block-group"><!-- wp:image {"url":"' . $play_button_url . '","alt":"","width":"48px","height":"auto","sizeSlug":"large","align":"center","className":"is-style-default","style":{"color":{"duotone":"unset"}}} -->
				<figure class="wp-block-image aligncenter size-large is-style-default" style="width:48px"><img src="' . esc_url( $play_button_url ) . '" alt="" style="width:48px;height:auto"/></figure>
				<!-- /wp:image --></div>
				<!-- /wp:group --></div></div>
				<!-- /wp:cover -->';
			}
		}

		// Button trigger.
		if ( ! empty( $atts['button_text'] ) ) {
			$trigger_content .= '<!-- wp:buttons -->
			<div class="wp-block-buttons"><!-- wp:button -->
			<div class="wp-block-button"><a class="wp-block-button__link wp-element-button">' . esc_html( $atts['button_text'] ) . '</a></div>
			<!-- /wp:button --></div>
			<!-- /wp:buttons -->';
		}

		// Custom content.
		if ( ! empty( $content ) ) {
			$trigger_content .= $content;
		}

		// Return complete popup block with trigger and media.
		return '<!-- wp:presto-player/popup {"popupId":"' . (int) $atts['media_id'] . '"} -->
<div class="wp-block-presto-player-popup"><!-- wp:presto-player/popup-trigger -->
<div class="wp-block-presto-player-popup-trigger">' . $trigger_content . '</div>
<!-- /wp:presto-player/popup-trigger -->

<!-- wp:presto-player/popup-media -->
<div class="wp-block-presto-player-popup-media"><!-- wp:presto-player/reusable-display {"id":' . (int) $atts['media_id'] . '} -->
<div class="wp-block-presto-player-reusable-display"></div>
<!-- /wp:presto-player/reusable-display --></div>
<!-- /wp:presto-player/popup-media --></div>
<!-- /wp:presto-player/popup -->';
	}

	/**
	 * Parse playlist attributes.
	 *
	 * @param array  $atts Shortcode attributes.
	 * @param string $content Content inside shortcode.
	 * @return array
	 */
	public function parsePlaylistAttributes( $atts, $content = '' ) {

		// backwards compat.
		$atts['listTextPlural']     = $atts['listtextplural'] ?? null;
		$atts['listTextSingular']   = $atts['listtextplural'] ?? null;
		$atts['transitionDuration'] = $atts['transitionduration'] ?? null;

		$atts = shortcode_atts(
			array(
				'heading'            => __( 'Playlist', 'presto-player' ),
				'listTextPlural'     => __( 'Videos', 'presto-player' ),
				'listTextSingular'   => __( 'Video', 'presto-player' ),
				'transitionDuration' => 5,
				'styles'             => '',
			),
			$atts
		);

		$atts['items'] = $this->getPlaylistItems( $content );

		return $atts;
	}

	/**
	 * Parse attributes.
	 *
	 * @param array  $atts Shortcode attributes.
	 * @param string $content Content inside shortcode.
	 * @return array
	 */
	public function parseAttributes( $atts, $content = '' ) {
		$atts = shortcode_atts(
			array(
				'id'                             => '',
				'src'                            => '',
				'title'                          => '',
				'provider'                       => '',
				'class'                          => '',
				'custom_field'                   => '',
				'poster'                         => '',
				'preload'                        => 'auto',
				'preset'                         => 0,
				'autoplay'                       => false,
				'plays_inline'                   => false,
				'chapters'                       => array(),
				'overlays'                       => array(),
				'muted_autoplay_preview'         => false,
				'muted_autoplay_caption_preview' => false,
				'ratio'                          => 'original',
			),
			$atts
		);

		if ( ! $atts['id'] && ! $atts['src'] && ! $atts['custom_field'] ) {
			return array();
		}

		// custom field as a src.
		if ( $atts['custom_field'] ) {
			$atts['src'] = get_post_meta( get_the_ID(), $atts['custom_field'], true );

			// Compatibility for file input field type from Advanced custom field plugin.
			if ( function_exists( 'get_field' ) ) {
				// phpcs:disable-next-line Generic.Functions.CallTimePassByReference.NotAllowed
				$custom_field = \get_field( $atts['custom_field'] );
				if ( $custom_field ) {
					switch ( gettype( $custom_field ?? null ) ) {
						case 'array':
							$atts['src'] = $custom_field['url'] ?? '';
							break;
						case 'integer':
							$atts['src'] = wp_get_attachment_url( $custom_field ) ?? '';
							break;
						case 'string':
							$atts['src'] = $custom_field;
							break;
					}
				}
			}
		}

		// get provider based on src, if not provided.
		$atts['provider'] = ! $atts['provider'] ? $this->getProvider( $atts['src'] ) : $atts['provider'];

		$atts['id']           = $this->getOrCreateVideoId( $atts );
		$atts['chapters']     = $this->getChapters( $content );
		$atts['overlays']     = $this->getOverlays( $content );
		$atts['tracks']       = $this->getTracks( $content );
		$atts['playsInline']  = (bool) $atts['plays_inline'];
		$atts['mutedPreview'] = array(
			'enabled'  => (bool) $atts['muted_autoplay_preview'],
			'captions' => (bool) $atts['muted_autoplay_caption_preview'],
		);
		$atts['className']    = sanitize_html_class( $atts['class'] );

		unset( $atts['plays_inline'] );
		unset( $atts['muted_autoplay_preview'] );
		unset( $atts['muted_autoplay_caption_preview'] );
		unset( $atts['class'] );

		return $atts;
	}

	/**
	 * Renders the block with attributes.
	 *
	 * @param array $atts Block attributes.
	 * @return void|string
	 */
	public function renderBlock( $atts ) {
		switch ( $atts['provider'] ?? '' ) {
			case 'self-hosted':
				return ( new SelfHostedBlock() )->html( $atts, '' );

			case 'youtube':
				return ( new YouTubeBlock() )->html( $atts, '' );

			case 'vimeo':
				return ( new VimeoBlock() )->html( $atts, '' );

			case 'bunny':
				return ( new BunnyCDNBlock() )->html( $atts, '' );

			case 'audio':
				return ( new AudioBlock() )->html( $atts, '' );
		}
	}

	/**
	 * Get or create video id for analytics.
	 *
	 * @param array $atts Block attributes.
	 * @return int
	 */
	public function getOrCreateVideoId( $atts ) {
		$create = array(
			'src'  => $atts['src'],
			'type' => $atts['provider'],
		);
		if ( ! empty( $atts['title'] ) ) {
			$create['title'] = $atts['title'];
		}

		$video = new Video();
		$model = $video->getOrCreate(
			array( 'src' => $atts['src'] ),
			$create
		);

		$model = $model->toObject();
		return ! empty( $model->id ) ? $model->id : 0;
	}

	/**
	 * Get chapters from shortcodes.
	 *
	 * @param string $content Content inside shortcode.
	 * @return array
	 */
	public function getChapters( $content ) {
		$chapters = $this->getShortcodesAtts(
			'presto_player_chapter',
			$content,
			array(
				'time'  => '00:00',
				'title' => '',
			)
		);
		foreach ( (array) $chapters as $key => $chapter ) {
			if ( ! strpos( $chapter['time'], ':' ) ) {
				$chapters[ $key ]['time'] = '00:' . $chapter['time'];
			}
		}

		return $chapters;
	}

	/**
	 * Get playlist items from shortcodes.
	 *
	 * @param string $content Content inside shortcode.
	 * @return array
	 */
	public function getPlaylistItems( $content ) {
		$items = $this->getShortcodesAtts(
			'presto_playlist_item',
			$content,
			array(
				'duration' => '00:00',
				'title'    => '',
				'id'       => 0,
			)
		);
		foreach ( $items as $key => $item ) {
			$video_details = ( new ReusableVideo( $item['id'] ) )->getAttributes();
			$items[ $key ] = array(
				'id'       => $items[ $key ]['id'],
				'config'   => $video_details,
				'duration' => $items[ $key ]['duration'],
				'title'    => $items[ $key ]['title'],
			);
		}
		return $items;
	}

	/**
	 * Get overlays from shortcodes.
	 *
	 * @param string $content Content inside shortcode.
	 * @return array
	 */
	public function getOverlays( $content ) {

		$overlays = $this->getShortcodesAtts(
			'presto_player_overlay',
			$content,
			array(
				'start_time' => '00:00',
				'end_time'   => '',
				'text'       => '',
				'link'       => array(),
				'position'   => '',
			)
		);
		foreach ( (array) $overlays as $key => $overlay ) {
			if ( ! strpos( $overlay['start_time'], ':' ) ) {
				$overlays[ $key ]['startTime'] = '00:' . $overlay['start_time'];
			} else {
				$overlays[ $key ]['startTime'] = $overlay['start_time'];
			}

			if ( ! strpos( $overlay['end_time'], ':' ) ) {
				$overlays[ $key ]['endTime'] = '00:' . $overlay['end_time'];
			} else {
				$overlays[ $key ]['endTime'] = $overlay['end_time'];
			}

			$overlays[ $key ]['link']['url']           = $overlay['link_url'];
			$overlays[ $key ]['link']['opensInNewTab'] = (bool) $overlay['link_new_tab'];

			unset( $overlays[ $key ]['link_url'] );
			unset( $overlays[ $key ]['link_new_tab'] );
			unset( $overlays[ $key ]['start_time'] );
			unset( $overlays[ $key ]['end_time'] );
		}
		return $overlays;
	}

	/**
	 * Get tracks from shortcodes.
	 *
	 * @param string $content Content inside shortcode.
	 * @return array
	 */
	public function getTracks( $content ) {
		return $this->getShortcodesAtts(
			'presto_player_track',
			$content,
			array(
				'label'   => '',
				'src'     => '',
				'srclang' => '',
			)
		);
	}

	/**
	 * Get specific shortcode atts from content.
	 *
	 * @param string $name Name of shortcode.
	 * @param string $content Page content.
	 * @param array  $defaults Defaults for each shortcode.
	 * @return array
	 */
	public function getShortcodesAtts( $name, $content, $defaults = array() ) {
		$items = array();

		// if shortcode exists.
		if (
		preg_match_all( '/' . get_shortcode_regex() . '/s', $content, $matches )
		&& array_key_exists( 2, $matches )
		&& in_array( $name, $matches[2], true )
		) {
			foreach ( (array) $matches[0] as $key => $value ) {
				if ( strpos( $value, $name ) !== false ) {
					$items[] = wp_parse_args(
						shortcode_parse_atts( $matches[3][ $key ] ),
						$defaults
					);
				}
			}
		}

		return $items;
	}

	/**
	 * Maybe switch provider if the url is overridden.
	 *
	 * @param string $src Source URL.
	 * @return string
	 */
	protected function getProvider( $src ) {
		$provider = 'self-hosted';

		if ( ! empty( $src ) ) {
			$filetype = wp_check_filetype( $src );
			if ( isset( $filetype['type'] ) && false !== strpos( $filetype['type'], 'audio' ) ) {
				return 'audio';
			}

			$yt_rx             = '/^((?:https?:)?\/\/)?((?:www|m)\.)?((?:youtube\.com|youtu.be))(\/(?:[\w\-]+\?v=|embed\/|v\/)?)([\w\-]+)(\S+)?$/';
			$has_match_youtube = preg_match( $yt_rx, $src, $yt_matches );

			if ( $has_match_youtube ) {
				return 'youtube';
			}

			$vm_rx           = '/(https?:\/\/)?(www\.)?(player\.)?vimeo\.com\/([a-z]*\/)*([‌​0-9]{6,11})[?]?.*/';
			$has_match_vimeo = preg_match( $vm_rx, $src, $vm_matches );

			if ( $has_match_vimeo ) {
				return 'vimeo';
			}

			if ( strpos( $src, 'https://vz-' ) !== false && strpos( $src, 'b-cdn.net' ) !== false ) {
				return 'bunny';
			}
		}

		return $provider;
	}

	/**
	 * Returns the markup for the current block.
	 *
	 * @param string $content Block content.
	 * @global WP_Embed $wp_embed
	 *
	 * @return string Block markup.
	 */
	public function getProcessedBlockHtml( $content ) {
		global $wp_embed;

		if ( ! $content ) {
			return;
		}

		$content = $wp_embed->run_shortcode( $content );
		$content = $wp_embed->autoembed( $content );
		$content = do_blocks( $content );
		$content = wptexturize( $content );
		$content = convert_smilies( $content );
		$content = shortcode_unautop( $content );
		$content = wp_filter_content_tags( $content, 'template' );
		$content = do_shortcode( $content );
		$content = str_replace( ']]>', ']]&gt;', $content );

		return $content;
	}
}
