<?php
/**
 * Popup Media block.
 *
 * @package PrestoPlayer\Blocks
 */

namespace PrestoPlayer\Blocks;

/**
 * Popup Media block.
 */
class PopupMediaBlock {

	/**
	 * Register the block type.
	 */
	public function register() {
		if ( ! file_exists( PRESTO_PLAYER_PLUGIN_DIR . 'src/admin/blocks/blocks/popup-media' ) ) {
			return;
		}

		add_action( 'init', array( $this, 'register_block' ) );
		add_filter( 'enqueue_empty_block_content_assets', array( $this, 'enqueue_empty_block_content_assets' ), 10, 2 );
	}

	/**
	 * Registers the popup media block with WordPress.
	 */
	public function register_block() {
		register_block_type(
			PRESTO_PLAYER_PLUGIN_DIR . 'src/admin/blocks/blocks/popup-media',
			array(
				'render_callback' => array( $this, 'render_block' ),
			)
		);
	}

	/**
	 * Filters whether to enqueue assets for a block which has no rendered content.
	 *
	 * In WordPress 6.9+, assets enqueued by a block are dequeued again if the block
	 * ultimately renders no content. The popup media block renders its overlay markup
	 * in the footer for accessibility reasons, so its main render callback returns
	 * empty content. Opt this block out so its styles and scripts are preserved.
	 *
	 * @param bool   $enqueue    Whether to enqueue assets.
	 * @param string $block_name Block name.
	 *
	 * @return bool
	 */
	public function enqueue_empty_block_content_assets( $enqueue, $block_name ) {
		if ( 'presto-player/popup-media' === $block_name ) {
			return true;
		}

		return $enqueue;
	}

	/**
	 * Renders the block content for the popup media block.
	 *
	 * @param array    $attributes The block attributes.
	 * @param string   $content    The block content.
	 * @param WP_Block $block      The block instance.
	 * @return void
	 */
	public function render_block( $attributes, $content, $block ) {
		// We are setting this popup id because we need to be able to target the popup overlay.
		$popup_id = $block->context['presto-player/popup-id'] ?? null;

		$wrapper_attributes = get_block_wrapper_attributes(
			array(
				'class'                               => 'presto-popup__overlay',
				'aria-label'                          => esc_attr__( 'Presto Popup', 'presto-player' ),
				'data-wp-interactive'                 => 'presto-player/popup',
				'data-wp-bind--role'                  => 'state.roleAttribute',
				'data-wp-bind--aria-modal'            => 'state.ariaModal',
				'data-wp-class--presto-popup--active' => 'state.overlayEnabled',
				'data-wp-watch'                       => 'callbacks.setOverlayFocus',
				'tabindex'                            => '-1',
			)
		);

		add_action(
			'wp_footer',
			function () use ( $content, $popup_id, $wrapper_attributes ) {
				echo $this->render_overlay( $content, $popup_id, $wrapper_attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			},
			99
		);
	}

	/**
	 * Renders the block content for the popup media block.
	 * We are rendering the overlay in footer for accessibility reasons.
	 *
	 * @param string $content The block content.
	 * @param int    $id      The popup ID.
	 * @param string $wrapper_attributes The wrapper attributes.
	 * @return string The rendered HTML for the block.
	 */
	public function render_overlay( $content, $id, $wrapper_attributes ) {
		ob_start();
		?>
			<div <?php echo wp_kses_data( $wrapper_attributes ); ?>
			<?php
			echo wp_kses_data(
				wp_interactivity_data_wp_context(
					array(
						'id'        => $id,
						'popupList' => array(),
					)
				)
			);
			?>
				>
				<button type="button" aria-label="<?php esc_attr_e( 'Close', 'presto-player' ); ?>" class="presto-popup__close-button" data-wp-on--click="actions.hidePopup">
					<svg
						xmlns="http://www.w3.org/2000/svg"
						viewBox="0 0 24 24"
						width="24"
						height="24"
						aria-hidden="true"
						focusable="false"
						fill="white"
					>
						<path d="m13.06 12 6.47-6.47-1.06-1.06L12 10.94 5.53 4.47 4.47 5.53 10.94 12l-6.47 6.47 1.06 1.06L12 13.06l6.47 6.47 1.06-1.06L13.06 12Z"/>
					</svg>
				</button>
				<template data-wp-each="context.popupList" data-wp-watch="callbacks.updatePopupList">
					<div data-wp-each-key="index" class="presto-popup__content">
						<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				</template>
				<div data-wp-text="state.screenReaderText" aria-live="polite" aria-atomic="true" class="presto-popup__speak screen-reader-text"></div>
				<div class="presto-popup__scrim" aria-hidden="true" data-wp-on--click="actions.hidePopup"></div>
			</div>
		<?php
		return ob_get_clean();
	}
}
