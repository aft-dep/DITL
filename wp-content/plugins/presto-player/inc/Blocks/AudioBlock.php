<?php
/**
 * Audio Block.
 *
 * @package PrestoPlayer\Blocks
 */

namespace PrestoPlayer\Blocks;

use PrestoPlayer\Attachment;
use PrestoPlayer\Models\CurrentUser;
use PrestoPlayer\Support\Block;

/**
 * Audio Block.
 */
class AudioBlock extends Block {

	/**
	 * Block name.
	 *
	 * @var string
	 */
	protected $name = 'audio';

	/**
	 * Bail if user cannot access video.
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content Block content.
	 * @return bool
	 */
	public function middleware( $attributes, $content ) {
		// If private and user cannot access video, don't load.
		if ( ! empty( $attributes['visibility'] ) && 'private' === $attributes['visibility'] ) {
			if ( empty( $attributes['id'] ) ) {
				return false;
			}
			if ( ! CurrentUser::canAccessVideo( $attributes['id'] ) ) {
				return false;
			}
		}

		return parent::middleware( $attributes, $content );
	}

	/**
	 * Add curtain styles.
	 *
	 * @param array $attributes Attributes.
	 * @param array $default_config Default config.
	 * @return array
	 */
	public function sanitizeAttributes( $attributes, $default_config ) {

		$src = ! empty( $attributes['src'] ) ? $attributes['src'] : '';

		return array(
			'src'    => ! empty( $attributes['attachment_id'] ) ? Attachment::getSrc( $attributes['attachment_id'] ) : $src,
			'styles' => $default_config['styles'] . ' --presto-curtain-size: 25%',
		);
	}

	/**
	 * Register the block type.
	 *
	 * @return void
	 */
	public function registerBlockType() {
		register_block_type(
			PRESTO_PLAYER_PLUGIN_DIR . 'src/admin/blocks/blocks/audio',
			array(
				'render_callback' => array( $this, 'html' ),
			)
		);
	}
}
