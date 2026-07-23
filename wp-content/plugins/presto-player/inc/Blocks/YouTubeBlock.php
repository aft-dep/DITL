<?php
/**
 * YouTube Block.
 *
 * @package PrestoPlayer\Blocks
 */

namespace PrestoPlayer\Blocks;

use PrestoPlayer\Models\Preset;
use PrestoPlayer\Support\Block;

/**
 * YouTube Block.
 */
class YouTubeBlock extends Block {

	/**
	 * Block name.
	 *
	 * @var string
	 */
	protected $name = 'youtube';

	/**
	 * Add url to template.
	 *
	 * @return array
	 */
	public function additionalAttributes() {
		return array(
			'src' => array(
				'type' => 'string',
			),
		);
	}

	/**
	 * Make youtube URL from attributes.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function makeUrl( $attributes ) {
		$id = $this->getIdFromURL( ! empty( $attributes['src'] ) ? $attributes['src'] : '' );

		if ( empty( $id ) ) {
			return '';
		}
		// Build youtube url.
		return add_query_arg(
			array(
				'iv_load_policy' => 3,
				'modestbranding' => 1,
				'playinline'     => ! empty( $attributes['playsInline'] ) ? 1 : 0,
				'showinfo'       => 0,
				'rel'            => 0,
				'enablejsapi'    => 1,
			),
			"//www.youtube.com/embed/{$id}"
		);
	}

	/**
	 * Add src.
	 *
	 * @param array $attributes Block attributes.
	 * @param array $default_config Default config.
	 * @return array
	 */
	public function sanitizeAttributes( $attributes, $default_config ) {
		$preset = ! empty( $attributes['preset'] ) ? new Preset( $attributes['preset'] ) : null;
		$id     = $this->getIdFromURL( ! empty( $attributes['src'] ) ? $attributes['src'] : '' );

		return array(
			'video_id'          => ! empty( $attributes['id'] ) ? $attributes['id'] : 0,
			'provider_video_id' => $id,
			'src'               => $this->makeUrl( $attributes ),
			'poster'            => isset( $attributes['poster'] ) ? esc_url( $attributes['poster'] ) : false,
			'hide_youtube'      => ! empty( $preset ) ? ! empty( (bool) $preset->hide_youtube ) : false,
		);
	}

	/**
	 * Gets the id from the Youtube URL.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public function getIdFromURL( $url = '' ) {
		preg_match( "/^(?:http(?:s)?:\/\/)?(?:www\.)?(?:m\.)?(?:youtu\.be\/|youtube\.com\/(?:(?:watch)?\?(?:.*&)?v(?:i)?=|(?:embed|v|vi|user|shorts)\/))([^\?&\"'>]+)/", $url, $matches );
		return ! empty( $matches[1] ) ? $matches[1] : '';
	}

	/**
	 * Register the block type.
	 *
	 * @return void
	 */
	public function registerBlockType() {
		register_block_type(
			PRESTO_PLAYER_PLUGIN_DIR . 'src/admin/blocks/blocks/youtube',
			array(
				'render_callback' => array( $this, 'html' ),
			)
		);
	}
}
