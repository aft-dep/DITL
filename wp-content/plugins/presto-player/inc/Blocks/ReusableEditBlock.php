<?php
/**
 * Reusable Video Block Class
 *
 * @package PrestoPlayer\Blocks
 */

namespace PrestoPlayer\Blocks;

/**
 * Reusable Edit Block Class
 */
class ReusableEditBlock {

	/**
	 * Register Block
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
			PRESTO_PLAYER_PLUGIN_DIR . 'src/admin/blocks/blocks/reusable-edit',
		);
	}
}
