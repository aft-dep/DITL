<?php

namespace WPForms\Forms\Fields\Traits;

/**
 * Limit Length option of the Single Line Text and Paragraph Text fields, designed for use with `WPForms_Field`.
 *
 * @since 2.0.4
 */
trait TextLengthLimit {

	/**
	 * Get the minimum and the maximum length, 0 for a bound that is not set.
	 *
	 * @since 2.0.4
	 *
	 * @param array $field Field settings.
	 *
	 * @return array Minimum and maximum.
	 */
	private function get_length_limits( array $field ): array {

		// Bounds saved while the option is off are leftovers, such as the builder's former default Max of 1.
		if ( empty( $field['limit_enabled'] ) ) {
			return [ 0, 0 ];
		}

		$min = ! empty( $field['limit_min'] ) ? absint( $field['limit_min'] ) : 0;
		$max = ! empty( $field['limit_count'] ) ? absint( $field['limit_count'] ) : 0;

		// The builder keeps Min <= Max only for its own edits, so an imported form can still hold a Min above its Max.
		return [ $max > 0 ? min( $min, $max ) : $min, $max ];
	}

	/**
	 * Get the minimum and the maximum length a visitor's value is checked against.
	 *
	 * @since 2.0.4
	 *
	 * @param array $field Field settings.
	 *
	 * @return array Minimum and maximum.
	 */
	private function get_enforced_length_limits( array $field ): array {

		[ $min, $max ] = $this->get_length_limits( $field );

		// The visitor cannot edit a Read-Only or calculated value, and the Address Autocomplete shows no limit,
		// so a minimum there would only block the form.
		if ( ! empty( $field['read_only'] ) || ! empty( $field['calculation_is_enabled'] ) || ! empty( $field['enable_address_autocomplete'] ) ) {
			$min = 0;
		}

		return [ $min, $max ];
	}

	/**
	 * Get the length limit mode.
	 *
	 * @since 2.0.4
	 *
	 * @param array $field Field settings.
	 *
	 * @return string Limit mode, `characters` or `words`.
	 */
	private function get_length_limit_mode( array $field ): string {

		return ! empty( $field['limit_mode'] ) && sanitize_key( $field['limit_mode'] ) !== 'characters' ? 'words' : 'characters';
	}

	/**
	 * Add the length limit classes and attributes to the primary input.
	 *
	 * @since 2.0.4
	 *
	 * @param array $primary   Primary input properties.
	 * @param array $field     Field settings.
	 * @param array $form_data Form data and settings.
	 *
	 * @return array Primary input properties.
	 */
	private function add_length_limit_attributes( array $primary, array $field, array $form_data ): array {

		[ $min, $max ] = $this->get_enforced_length_limits( $field );

		if ( $min === 0 && $max === 0 ) {
			return $primary;
		}

		$mode = $this->get_length_limit_mode( $field );

		$primary['data']['form-id']  = $form_data['id'];
		$primary['data']['field-id'] = $field['id'];

		// Older text-limit scripts, which a browser can keep after an update, read this class as a maximum and
		// take a missing one as 0, breaking a Min-only field. That field gets a class they never select.
		$primary['class'][] = $max > 0 ? "wpforms-limit-{$mode}-enabled" : "wpforms-limit-min-{$mode}-enabled";

		if ( $max > 0 ) {
			$primary['data']['text-limit'] = $max;
		}

		if ( $max > 0 && $mode === 'characters' ) {
			$primary['attr']['maxlength'] = $max;
		}

		if ( $min > 0 ) {
			$primary['data']['rule-text-limit-min'] = $min;
		}

		return $primary;
	}

	/**
	 * Validate the submitted value against the minimum and the maximum length.
	 *
	 * @since 2.0.4
	 *
	 * @param int    $field_id  Field ID.
	 * @param string $value     Sanitized submitted value.
	 * @param array  $field     Field settings.
	 * @param array  $form_data Form data and settings.
	 */
	private function validate_length_limit( $field_id, string $value, array $field, array $form_data ): void {

		[ $min, $max ] = $this->get_enforced_length_limits( $field );

		// An empty value is left to the required check, so its error is never overwritten.
		if ( $value === '' ) {
			// The required check tests the raw value, so a whitespace-only one slips past it.
			if ( $min > 0 && ! empty( $field['required'] ) ) {
				wpforms()->obj( 'process' )->errors[ $form_data['id'] ][ $field_id ] = wpforms_get_required_label();
			}

			return;
		}

		$mode   = $this->get_length_limit_mode( $field );
		$length = $mode === 'characters' ? mb_strlen( str_replace( "\r\n", "\n", $value ) ) : wpforms_count_words( $value );
		$error  = $this->get_length_limit_error( $mode, $length, $min, $max );

		if ( $error !== '' ) {
			wpforms()->obj( 'process' )->errors[ $form_data['id'] ][ $field_id ] = $error;
		}
	}

	/**
	 * Get the error for a value that is too long or too short.
	 *
	 * @since 2.0.4
	 *
	 * @param string $mode   Limit mode, `characters` or `words`.
	 * @param int    $length Value length in the limit mode.
	 * @param int    $min    Minimum length, 0 when not set.
	 * @param int    $max    Maximum length, 0 when not set.
	 *
	 * @return string Error, empty when the length is within the limits.
	 */
	private function get_length_limit_error( string $mode, int $length, int $min, int $max ): string {

		if ( $max > 0 && $length > $max ) {
			if ( $mode === 'characters' ) {
				/* translators: %s - limit characters number. */
				return sprintf( _n( 'Text can\'t exceed %d character.', 'Text can\'t exceed %d characters.', $max, 'wpforms-lite' ), $max );
			}

			/* translators: %s - limit words number. */
			return sprintf( _n( 'Text can\'t exceed %d word.', 'Text can\'t exceed %d words.', $max, 'wpforms-lite' ), $max );
		}

		if ( $length >= $min ) {
			return '';
		}

		$message = $mode === 'characters'
			? wpforms_setting( 'validation-character-minimum', sprintf( /* translators: %1$s - minimum characters count, %2$s - characters needed count. */ esc_html__( 'Minimum is %1$s characters. Characters needed: %2$s.', 'wpforms-lite' ), '{min}', '{needed}' ) )
			: wpforms_setting( 'validation-word-minimum', sprintf( /* translators: %1$s - minimum words count, %2$s - words needed count. */ esc_html__( 'Minimum is %1$s words. Words needed: %2$s.', 'wpforms-lite' ), '{min}', '{needed}' ) );

		return str_replace( [ '{min}', '{needed}' ], [ $min, $min - $length ], $message );
	}
}
