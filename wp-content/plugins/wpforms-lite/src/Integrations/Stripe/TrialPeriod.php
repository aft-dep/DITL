<?php

namespace WPForms\Integrations\Stripe;

/**
 * Trial period of a recurring plan.
 *
 * Single source of truth for the trial units offered in the builder, their labels, the per-unit
 * maximum derived from Stripe's 730-day cap, and the conversion into Stripe subscription arguments.
 *
 * @since 2.0.4
 */
class TrialPeriod {

	/**
	 * Unit used when a plan has no saved unit, the unit Stripe's own `trial_period_days` speaks.
	 *
	 * @since 2.0.4
	 */
	public const DEFAULT_UNIT = 'day';

	/**
	 * Longest trial Stripe accepts, in days.
	 *
	 * @since 2.0.4
	 */
	public const MAX_DAYS = 730;

	/**
	 * Number of units, already clamped to the unit's maximum.
	 *
	 * @since 2.0.4
	 *
	 * @var int
	 */
	private $count;

	/**
	 * Trial unit: day, week, month or year.
	 *
	 * @since 2.0.4
	 *
	 * @var string
	 */
	private $unit;

	/**
	 * Constructor.
	 *
	 * An unknown unit falls back to days and the count is clamped to the unit's maximum.
	 *
	 * @since 2.0.4
	 *
	 * @param int    $count Number of units.
	 * @param string $unit  Trial unit.
	 */
	public function __construct( int $count, string $unit ) {

		$this->unit  = isset( self::get_units()[ $unit ] ) ? $unit : self::DEFAULT_UNIT;
		$this->count = max( 0, min( $count, self::get_max( $this->unit ) ) );
	}

	/**
	 * Build the trial of a recurring plan from its saved settings.
	 *
	 * @since 2.0.4
	 *
	 * @param array $plan Recurring plan settings.
	 *
	 * @return TrialPeriod|null Null when the plan has no positive trial count.
	 */
	public static function from_plan( array $plan ): ?TrialPeriod {

		$count = $plan['trial_period'] ?? '';

		if ( ! is_numeric( $count ) || (int) $count <= 0 ) {
			return null;
		}

		return new self( (int) $count, (string) ( $plan['trial_period_unit'] ?? self::DEFAULT_UNIT ) );
	}

	/**
	 * Get the trial units in display order, each with its label and the maximum count Stripe allows.
	 *
	 * @since 2.0.4
	 *
	 * @return array
	 */
	public static function get_units(): array {

		return [
			'day'   => [
				'label' => esc_html__( 'Days', 'wpforms-lite' ),
				'max'   => self::MAX_DAYS,
			],
			'week'  => [
				'label' => esc_html__( 'Weeks', 'wpforms-lite' ),
				'max'   => 104,
			],
			'month' => [
				'label' => esc_html__( 'Months', 'wpforms-lite' ),
				'max'   => 24,
			],
			'year'  => [
				'label' => esc_html__( 'Years', 'wpforms-lite' ),
				'max'   => 2,
			],
		];
	}

	/**
	 * Get the maximum count allowed for a unit, falling back to the day cap for an unknown unit.
	 *
	 * @since 2.0.4
	 *
	 * @param string $unit Trial unit.
	 *
	 * @return int
	 */
	public static function get_max( string $unit ): int {

		$units = self::get_units();

		return $units[ $unit ]['max'] ?? $units[ self::DEFAULT_UNIT ]['max'];
	}

	/**
	 * Get the number of units.
	 *
	 * @since 2.0.4
	 *
	 * @return int
	 */
	public function get_count(): int {

		return $this->count;
	}

	/**
	 * Get the unit.
	 *
	 * @since 2.0.4
	 *
	 * @return string
	 */
	public function get_unit(): string {

		return $this->unit;
	}

	/**
	 * Get the moment the trial ends, never more than Stripe's cap after the given start.
	 *
	 * Days and weeks are exact multiples of a day; months and years follow the calendar, so
	 * "1 month" lands on the same day of the next month.
	 *
	 * @since 2.0.4
	 *
	 * @param int $now Unix timestamp the trial starts at.
	 *
	 * @return int
	 */
	public function get_end_timestamp( int $now ): int {

		$cap = $now + self::MAX_DAYS * DAY_IN_SECONDS;

		if ( $this->is_counted_in_days() ) {
			return min( $now + $this->get_days() * DAY_IN_SECONDS, $cap );
		}

		return min( (int) strtotime( sprintf( '+%d %s', $this->count, $this->unit ), $now ), $cap );
	}

	/**
	 * Get the Stripe subscription arguments that start this trial.
	 *
	 * Days and weeks travel as `trial_period_days`, the unit Stripe counts in; months and years as a
	 * calendar `trial_end` timestamp.
	 *
	 * @since 2.0.4
	 *
	 * @param int $now Unix timestamp the trial starts at.
	 *
	 * @return array
	 */
	public function get_subscription_args( int $now ): array {

		if ( $this->count === 0 ) {
			return [];
		}

		if ( $this->is_counted_in_days() ) {
			return [ 'trial_period_days' => $this->get_days() ];
		}

		return [ 'trial_end' => $this->get_end_timestamp( $now ) ];
	}

	/**
	 * Whether the unit is an exact multiple of a day.
	 *
	 * @since 2.0.4
	 *
	 * @return bool
	 */
	private function is_counted_in_days(): bool {

		return in_array( $this->unit, [ 'day', 'week' ], true );
	}

	/**
	 * Get the trial length in days for a day- or week-based trial.
	 *
	 * @since 2.0.4
	 *
	 * @return int
	 */
	private function get_days(): int {

		return $this->unit === 'week' ? $this->count * 7 : $this->count;
	}
}
