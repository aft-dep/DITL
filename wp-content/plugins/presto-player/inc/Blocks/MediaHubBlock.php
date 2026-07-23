<?php
/**
 * Media Hub Block.
 *
 * @package PrestoPlayer\Blocks
 */

namespace PrestoPlayer\Blocks;

/**
 * Media Hub Block.
 */
class MediaHubBlock {

	/**
	 * Register Block.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'registerBlockType' ) );
	}

	/**
	 * Register the block type.
	 *
	 * @return void
	 */
	public function registerBlockType() {
		register_block_type(
			PRESTO_PLAYER_PLUGIN_DIR . 'src/admin/blocks/blocks/media-hub',
			array(
				'render_callback' => array( $this, 'html' ),
			)
		);
	}
}
