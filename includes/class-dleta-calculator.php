<?php
/**
 * Turns a delivery rule into dates.
 *
 * @package UltimateEDD
 */

defined( 'ABSPATH' ) || exit;

/**
 * Date calculator.
 */
class Dleta_Calculator {

	/**
	 * Calculate the earliest and latest delivery dates for a rule.
	 *
	 * @param array                  $rule  Normalized rule (see Dleta_Rules::normalize()).
	 * @param DateTimeImmutable|null $start Start date. Defaults to today in the site timezone.
	 * @return array{min:DateTimeImmutable,max:DateTimeImmutable}|null Null when the rule hides the date.
	 */
	public static function calculate( $rule, $start = null ) {
		if ( empty( $rule ) || 'none' === $rule['mode'] ) {
			return null;
		}

		if ( null === $start ) {
			$start = current_datetime()->setTime( 0, 0 );
		}

		/**
		 * Filter the date counting starts from (for example to add a processing day
		 * or a same-day order cut-off).
		 *
		 * @param DateTimeImmutable $start Start date in the site timezone.
		 * @param array             $rule  Rule being calculated.
		 */
		$start = apply_filters( 'dleta_start_date', $start, $rule );

		if ( 'fixed' === $rule['mode'] ) {
			$min_days = $rule['fixed'];
			$max_days = $rule['fixed'];
		} else {
			$min_days = $rule['min'];
			$max_days = max( $rule['min'], $rule['max'] );
		}

		return array(
			'min' => self::add_days( $start, $min_days, $rule['exclude_weekends'] ),
			'max' => self::add_days( $start, $max_days, $rule['exclude_weekends'] ),
		);
	}

	/**
	 * Add a number of days to a date, optionally skipping weekends.
	 *
	 * @param DateTimeImmutable $date         Start date.
	 * @param int               $days         Days to add.
	 * @param bool              $skip_weekend Skip Saturday and Sunday.
	 * @return DateTimeImmutable
	 */
	public static function add_days( DateTimeImmutable $date, $days, $skip_weekend ) {
		$added = 0;
		$steps = 0;
		// Safety limit in case a filter marks every day as skipped.
		$limit = ( $days * 7 ) + 400;

		while ( $added < $days && $steps < $limit ) {
			$date = $date->modify( '+1 day' );
			++$steps;
			if ( self::is_skipped_day( $date, $skip_weekend ) ) {
				continue;
			}
			++$added;
		}

		// A zero-day estimate that lands on a skipped day moves to the next delivery day.
		$guard = 0;
		while ( self::is_skipped_day( $date, $skip_weekend ) && $guard < 30 ) {
			$date = $date->modify( '+1 day' );
			++$guard;
		}

		return $date;
	}

	/**
	 * Whether a date is not a delivery day.
	 *
	 * @param DateTimeImmutable $date         Date to check.
	 * @param bool              $skip_weekend Skip Saturday and Sunday.
	 * @return bool
	 */
	public static function is_skipped_day( DateTimeImmutable $date, $skip_weekend ) {
		$skipped = $skip_weekend && (int) $date->format( 'N' ) >= 6;

		/**
		 * Filter whether a date is skipped when counting delivery days.
		 * Useful for public holidays.
		 *
		 * @param bool              $skipped      Whether the day is skipped.
		 * @param DateTimeImmutable $date         Date being checked.
		 * @param bool              $skip_weekend Whether weekends are excluded for this rule.
		 */
		return (bool) apply_filters( 'dleta_is_skipped_day', $skipped, $date, $skip_weekend );
	}

	/**
	 * Format a date pair as display text.
	 *
	 * @param array{min:DateTimeImmutable,max:DateTimeImmutable} $dates  Dates.
	 * @param string|null                                        $format PHP date format. Defaults to the saved setting;
	 *                                                                   an empty string means the site date format.
	 * @return string Plain text (not escaped).
	 */
	public static function format( array $dates, $format = null ) {
		if ( null === $format ) {
			$format = Dleta_Rules::option( 'dleta_date_format' );
		}
		if ( '' === trim( (string) $format ) ) {
			$format = get_option( 'date_format' );
		}

		$min = wp_date( $format, $dates['min']->getTimestamp(), $dates['min']->getTimezone() );
		$max = wp_date( $format, $dates['max']->getTimestamp(), $dates['max']->getTimezone() );

		if ( $dates['min']->format( 'Y-m-d' ) === $dates['max']->format( 'Y-m-d' ) ) {
			$text = $min;
		} else {
			/* translators: 1: earliest delivery date, 2: latest delivery date. */
			$text = sprintf( __( '%1$s – %2$s', 'ultimate-estimated-delivery-date-by-bigloop' ), $min, $max );
		}

		/**
		 * Filter the formatted delivery date text.
		 *
		 * @param string $text  Formatted text.
		 * @param array  $dates Array with 'min' and 'max' DateTimeImmutable objects.
		 */
		return (string) apply_filters( 'dleta_formatted_dates', $text, $dates );
	}

	/**
	 * Formatted estimate text for a product, or empty string when hidden.
	 *
	 * @param WC_Product $product Product.
	 * @return string
	 */
	public static function text_for_product( $product ) {
		$resolved = Dleta_Rules::resolve( $product );
		$dates    = self::calculate( $resolved['rule'] );

		return null === $dates ? '' : self::format( $dates );
	}
}
