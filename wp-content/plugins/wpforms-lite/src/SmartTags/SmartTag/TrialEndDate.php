<?php

namespace WPForms\SmartTags\SmartTag;

/**
 * Smart tag {trial_end_date}: the day the subscription trial ends and the first payment is taken.
 *
 * Accepts an optional `format` attribute with a PHP date format, e.g. {trial_end_date format="d.m.Y"}.
 *
 * @since 2.0.4
 */
class TrialEndDate extends TrialSmartTag {

	/**
	 * Get smart tag value.
	 *
	 * @since 2.0.4
	 *
	 * @param array  $form_data Form data.
	 * @param array  $fields    List of fields.
	 * @param string $entry_id  Entry ID.
	 *
	 * @return string
	 */
	public function get_value( $form_data, $fields = [], $entry_id = '' ): string {

		$trial = $this->get_trial( (array) $form_data, $entry_id );

		if ( ! $trial ) {
			return '';
		}

		$format = $this->get_attributes()['format'] ?? '';

		$date = $format === '' ? wpforms_date_format( $trial['end'], '', true ) : wpforms_datetime_format( $trial['end'], $format, true );

		return $this->format_value( $date );
	}
}
