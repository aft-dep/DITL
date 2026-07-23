<?php
/**
 * Popup Trigger Service.
 *
 * @package PrestoPlayer\Services\Blocks
 */

namespace PrestoPlayer\Services\Blocks;

/**
 * Service to extend core/button blocks with popup trigger functionality.
 */
class PopupTriggerService {
	/**
	 * Register the service.
	 */
	public function register() {
		add_filter( 'render_block_core/button', array( $this, 'render_button_with_popup_directives' ), 10, 2 );
		add_filter( 'register_block_type_args', array( $this, 'add_popup_trigger_attribute' ), 10, 2 );
	}

	/**
	 * Add popup directives to core/button blocks that have the popup trigger attribute set.
	 *
	 * @param string $block_content The block content.
	 * @param array  $block         The block data.
	 * @return string The modified block content.
	 */
	public function render_button_with_popup_directives( $block_content, $block ) {
		// Check if this button has the popup trigger attribute set to true.
		$popup_trigger = $block['attrs']['prestoPopupTrigger'] ?? false;
		if ( ! $popup_trigger ) {
			return $block_content;
		}

		// Add the popup directives to the button content since it's a button trigger.
		return $this->add_popup_directives_to_button( $block_content );
	}

	/**
	 * Add popup trigger attribute to core/button blocks.
	 *
	 * @param array  $args       Block type registration arguments.
	 * @param string $block_type Block type name.
	 * @return array Modified block type arguments.
	 */
	public function add_popup_trigger_attribute( $args, $block_type ) {

		// Only modify core/button blocks.
		if ( 'core/button' !== $block_type ) {
			return $args;
		}

		// Add the popup trigger attribute.
		$args['attributes']['prestoPopupTrigger'] = array(
			'type'    => 'boolean',
			'default' => false,
		);

		return $args;
	}

	/**
	 * Add popup directives to button content using WP_HTML_Tag_Processor.
	 * Uses the same directives as PopupTriggerBlock.
	 *
	 * @param string $content The button content.
	 * @return string The modified content with popup directives.
	 */
	private function add_popup_directives_to_button( $content ) {
		if ( empty( $content ) ) {
			return $content;
		}

		$processor = new \WP_HTML_Tag_Processor( $content );

		// Find the anchor tag within the button.
		if ( $processor->next_tag( array( 'tag_name' => 'a' ) ) ) {
			// Add the same popup directives as PopupTriggerBlock.
			$processor->set_attribute( 'data-wp-on--click', 'actions.showPopup' );
			$processor->set_attribute( 'data-wp-init', 'callbacks.initTriggerButton' );
			$processor->set_attribute( 'tabindex', '0' );
			$processor->set_attribute( 'data-wp-on--keydown', 'actions.handleKeydown' );
			$processor->set_attribute( 'aria-label', __( 'Open Presto Popup dialog', 'presto-player' ) );
			$processor->set_attribute( 'aria-haspopup', 'dialog' );
			$processor->set_attribute( 'data-wp-bind--aria-expanded', 'state.overlayEnabled' );
			$processor->set_attribute( 'data-wp-on-document--keydown', 'callbacks.handleKeydown' );

			return $processor->get_updated_html();
		}

		return $content;
	}
}
