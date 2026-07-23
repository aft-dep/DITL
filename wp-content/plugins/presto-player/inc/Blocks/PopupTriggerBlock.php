<?php
/**
 * Popup Trigger block.
 *
 * @package PrestoPlayer\Blocks
 */

namespace PrestoPlayer\Blocks;

/**
 * Popup Trigger block.
 */
class PopupTriggerBlock {
	/**
	 * Register the block type.
	 */
	public function register() {
		if ( ! file_exists( PRESTO_PLAYER_PLUGIN_DIR . 'src/admin/blocks/blocks/popup-trigger' ) ) {
			return;
		}

		add_action( 'init', array( $this, 'register_block' ) );
	}

	/**
	 * Registers the popup trigger block with WordPress.
	 */
	public function register_block() {
		register_block_type(
			PRESTO_PLAYER_PLUGIN_DIR . 'src/admin/blocks/blocks/popup-trigger',
			array(
				'render_callback' => array( $this, 'render_block' ),
			)
		);
	}

	/**
	 * Renders the block content for the popup trigger block.
	 *
	 * @param array  $attributes The block attributes.
	 * @param string $content    The block content.
	 *
	 * @return string The rendered HTML for the block.
	 */
	public function render_block( $attributes, $content ) {

		// If the content already has the popup directives, we already have a popup trigger.
		if ( strpos( $content, 'data-wp-on--click="actions.showPopup"' ) !== false ) {
			return $content;
		}

		ob_start();
		?>
		<div 
		<?php
		echo wp_kses_data(
			get_block_wrapper_attributes(
				array(
					'data-wp-on--click'            => 'actions.showPopup',
					'tabindex'                     => '0',
					'data-wp-on--keydown'          => 'actions.handleKeydown',
					'aria-label'                   => __( 'Open Presto Popup dialog', 'presto-player' ),
					'aria-haspopup'                => 'dialog',
					'data-wp-on-document--keydown' => 'callbacks.handleKeydown',
					'data-wp-bind--aria-expanded'  => 'state.overlayEnabled',
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
