<?php

namespace WPForms\SmartTags\SmartTag;

/**
 * Smart tag {trial_first_payment}: the amount charged when the subscription trial ends.
 *
 * @since 2.0.4
 */
class TrialFirstPayment extends TrialSmartTag {

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

		if ( ! $trial || $trial['amount'] === '' ) {
			return '';
		}

		return $this->format_value( wpforms_format_amount( $trial['amount'], true, $trial['currency'] ) );
	}
}
