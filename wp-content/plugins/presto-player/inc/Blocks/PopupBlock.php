<?php
/**
 * Popup block.
 *
 * @package PrestoPlayer\Blocks
 */

namespace PrestoPlayer\Blocks;

/**
 * Popup block.
 */
class PopupBlock {
	/**
	 * Register the block type.
	 */
	public function register() {
		if ( ! file_exists( PRESTO_PLAYER_PLUGIN_DIR . 'src/admin/blocks/blocks/popup' ) ) {
			return;
		}

		add_action( 'init', array( $this, 'register_block' ) );
	}

	/**
	 * Registers the popup block with WordPress.
	 */
	public function register_block() {
		register_block_type(
			PRESTO_PLAYER_PLUGIN_DIR . 'src/admin/blocks/blocks/popup',
			array(
				'render_callback' => array( $this, 'render_block' ),
			)
		);
	}

	/**
	 * Renders the block content for the popup block.
	 *
	 * @param array  $attributes The block attributes.
	 * @param string $content    The block content.
	 *
	 * @return string The rendered HTML for the block.
	 */
	public function render_block( $attributes, $content ) {
		ob_start(); ?>
		<div 
		<?php
		echo wp_kses_data(
			get_block_wrapper_attributes(
				array(
					'class'               => 'presto-popup',
					'data-wp-interactive' => 'presto-player/popup',
				)
			)
		);
		?>
		<?php
		echo wp_kses_data(
			wp_interactivity_data_wp_context(
				array(
					'id' => $attributes['popupId'] ?? null,
				)
			)
		);
		?>
		>
			<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
