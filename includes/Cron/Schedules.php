<?php
/**
 * Custom cron schedules and scheduling helpers for email reports.
 *
 * @package updatebee
 */

namespace UpdateBee\Cron;

/**
 * Cron schedule utilities (forked from Easy Updates Manager / UAU patterns).
 */
class Schedules {

	/**
	 * Register custom cron intervals.
	 */
	public function run(): void {
		add_filter( 'cron_schedules', array( $this, 'set_cron_schedules' ) );
	}

	/**
	 * Add UpdateBee cron schedules.
	 *
	 * @param array<string, array<string, int|string>> $schedules Existing schedules.
	 * @return array<string, array<string, int|string>>
	 */
	public function set_cron_schedules( array $schedules ): array {
		$schedules['updatebee-hourly']       = array(
			'interval' => HOUR_IN_SECONDS,
			'display'  => __( 'Every hour', 'updatebee' ),
		);
		$schedules['updatebee-two-hours']    = array(
			'interval' => HOUR_IN_SECONDS * 2,
			'display'  => __( 'Every two hours', 'updatebee' ),
		);
		$schedules['updatebee-three-hours']  = array(
			'interval' => HOUR_IN_SECONDS * 3,
			'display'  => __( 'Every three hours', 'updatebee' ),
		);
		$schedules['updatebee-six-hours']    = array(
			'interval' => HOUR_IN_SECONDS * 6,
			'display'  => __( 'Every six hours', 'updatebee' ),
		);
		$schedules['updatebee-twelve-hours'] = array(
			'interval' => HOUR_IN_SECONDS * 12,
			'display'  => __( 'Every twelve hours', 'updatebee' ),
		);
		$schedules['updatebee-daily']        = array(
			'interval' => DAY_IN_SECONDS,
			'display'  => __( 'Once daily', 'updatebee' ),
		);
		$schedules['updatebee-weekly']       = array(
			'interval' => WEEK_IN_SECONDS,
			'display'  => __( 'Every week', 'updatebee' ),
		);
		$schedules['updatebee-two-weeks']    = array(
			'interval' => DAY_IN_SECONDS * 14,
			'display'  => __( 'Every two weeks', 'updatebee' ),
		);
		$schedules['updatebee-monthly']      = array(
			'interval' => MONTH_IN_SECONDS,
			'display'  => __( 'Every month', 'updatebee' ),
		);

		return $schedules;
	}

	/**
	 * Schedule an hourly-style cron event.
	 *
	 * @param int                  $hours   Hours between runs (used to pick schedule slug).
	 * @param array<string, mixed> $options Saved schedule options.
	 * @param string               $action  Cron hook name.
	 */
	public static function set_hourly_cron( int $hours, array $options, string $action ): void {
		unset( $hours );
		$schedule = isset( $options['schedule'] ) ? (string) $options['schedule'] : 'twelve-hours';

		$cron_timestamp = self::calculate_next_event( $options, false );
		if ( $cron_timestamp <= 0 ) {
			$time           = isset( $options['start_time'] ) ? (string) $options['start_time'] : '00:00';
			$cron_timestamp = strtotime( gmdate( 'Y-m-d' ) . ' ' . $time ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
			$gmt_offset     = HOUR_IN_SECONDS * (float) get_option( 'gmt_offset' );
			$cron_timestamp = (int) ( $cron_timestamp - $gmt_offset );
			if ( $cron_timestamp < time() ) {
				$cron_timestamp += DAY_IN_SECONDS;
			}
		}

		self::schedule_event( $cron_timestamp, $schedule, $action );
	}

	/**
	 * Schedule a daily cron event.
	 *
	 * @param array<string, mixed> $options Saved schedule options.
	 * @param string               $action  Cron hook name.
	 */
	public static function set_daily_cron( array $options, string $action ): void {
		$schedule = isset( $options['schedule'] ) ? (string) $options['schedule'] : 'daily';

		$cron_date_time = self::calculate_next_event( $options, false );
		if ( $cron_date_time <= 0 ) {
			$time           = isset( $options['start_time'] ) ? (string) $options['start_time'] : '00:00';
			$date_time      = strtotime( gmdate( 'Y-m-d' ) . ' ' . $time ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
			$gmt_offset     = HOUR_IN_SECONDS * (float) get_option( 'gmt_offset' );
			$cron_date_time = (int) ( $date_time - $gmt_offset );
			if ( $cron_date_time < time() ) {
				$cron_date_time += DAY_IN_SECONDS;
			}
		}

		self::schedule_event( $cron_date_time, $schedule, $action );
	}

	/**
	 * Schedule a weekly cron event.
	 *
	 * @param array<string, mixed> $options Saved schedule options.
	 * @param string               $action  Cron hook name.
	 */
	public static function set_weekly_cron( array $options, string $action ): void {
		$schedule    = isset( $options['schedule'] ) ? (string) $options['schedule'] : 'weekly';
		$time        = isset( $options['start_time'] ) ? (string) $options['start_time'] : '00:00';
		$day_of_week = ucfirst( isset( $options['day'] ) ? (string) $options['day'] : 'sunday' );

		$cron_schedule_user_date_time = strtotime( "This {$day_of_week}, {$time}", strtotime( gmdate( 'Y-m-d' ) ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
		$gmt_offset                   = HOUR_IN_SECONDS * (float) get_option( 'gmt_offset' );
		$cron_schedule_date_time      = (int) ( $cron_schedule_user_date_time - $gmt_offset );

		if ( $cron_schedule_date_time < time() ) {
			$cron_schedule_date_time += 7 * DAY_IN_SECONDS;
		}

		self::schedule_event( $cron_schedule_date_time, $schedule, $action );
	}

	/**
	 * Schedule a bi-weekly cron event.
	 *
	 * @param array<string, mixed> $options Saved schedule options.
	 * @param string               $action  Cron hook name.
	 */
	public static function set_biweekly_cron( array $options, string $action ): void {
		$schedule         = isset( $options['schedule'] ) ? (string) $options['schedule'] : 'two-weeks';
		$time             = isset( $options['start_time'] ) ? (string) $options['start_time'] : '00:00';
		$user_week_number = absint( $options['week'] ?? 1 );
		$day_of_week      = ucfirst( isset( $options['day'] ) ? (string) $options['day'] : 'sunday' );

		$cron_schedule_user_date_time = strtotime( "+{$user_week_number} weeks", strtotime( gmdate( 'Y-m-d' ) . ' ' . $time ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
		$cron_schedule_user_date_time = strtotime( "this {$day_of_week}, {$time}", $cron_schedule_user_date_time );
		$gmt_offset                   = HOUR_IN_SECONDS * (float) get_option( 'gmt_offset' );
		$cron_schedule_date_time      = (int) ( $cron_schedule_user_date_time - $gmt_offset );

		if ( $cron_schedule_date_time < time() || 2 === $user_week_number ) {
			$cron_schedule_date_time += 7 * DAY_IN_SECONDS;
		}

		self::schedule_event( $cron_schedule_date_time, $schedule, $action );
	}

	/**
	 * Schedule a monthly cron event.
	 *
	 * @param array<string, mixed> $options Saved schedule options.
	 * @param string               $action  Cron hook name.
	 */
	public static function set_monthly_cron( array $options, string $action ): void {
		$schedule        = isset( $options['schedule'] ) ? (string) $options['schedule'] : 'monthly';
		$time            = isset( $options['start_time'] ) ? (string) $options['start_time'] : '00:00';
		$user_day_number = absint( $options['day_num'] ?? 1 );

		$cron_schedule_user_date_time = strtotime( '+1 month', strtotime( gmdate( 'Y-m-' . $user_day_number ) . ' ' . $time ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
		$gmt_offset                   = HOUR_IN_SECONDS * (float) get_option( 'gmt_offset' );
		$cron_schedule_date_time      = (int) ( $cron_schedule_user_date_time - $gmt_offset );

		self::schedule_event( $cron_schedule_date_time, $schedule, $action );
	}

	/**
	 * Schedule a WordPress cron event.
	 *
	 * @param int    $timestamp Unix timestamp for first run.
	 * @param string $schedule  Schedule slug without prefix.
	 * @param string $action    Cron hook name.
	 */
	public static function schedule_event( int $timestamp, string $schedule, string $action ): void {
		$schedule_name = 'updatebee-' . $schedule;
		wp_schedule_event( $timestamp, $schedule_name, $action );
	}

	/**
	 * Apply a saved schedule option to a cron hook.
	 *
	 * @param array<string, mixed> $options Saved email options.
	 * @param string               $action  Cron hook name.
	 */
	public static function set_cron_events( array $options, string $action ): void {
		wp_clear_scheduled_hook( $action );

		$schedule = isset( $options['schedule'] ) ? (string) $options['schedule'] : 'twelve-hours';

		switch ( $schedule ) {
			case 'hourly':
				self::set_hourly_cron( 1, $options, $action );
				break;
			case 'two-hours':
				self::set_hourly_cron( 2, $options, $action );
				break;
			case 'three-hours':
				self::set_hourly_cron( 3, $options, $action );
				break;
			case 'six-hours':
				self::set_hourly_cron( 6, $options, $action );
				break;
			case 'twelve-hours':
				self::set_hourly_cron( 12, $options, $action );
				break;
			case 'daily':
				self::set_daily_cron( $options, $action );
				break;
			case 'weekly':
				self::set_weekly_cron( $options, $action );
				break;
			case 'two-weeks':
				self::set_biweekly_cron( $options, $action );
				break;
			case 'monthly':
				self::set_monthly_cron( $options, $action );
				break;
			default:
				break;
		}
	}

	/**
	 * Calculate the next event timestamp from saved options (EUM-style weekday rules).
	 *
	 * @param array<string, mixed> $options    Schedule options.
	 * @param bool                 $allow_zero Return 0 when no valid weekday remains.
	 * @return int Unix timestamp or 0.
	 */
	public static function calculate_next_event( array $options, bool $allow_zero = false ): int {
		$schedule = isset( $options['schedule'] ) ? (string) $options['schedule'] : 'twelve-hours';
		$time     = isset( $options['start_time'] ) ? (string) $options['start_time'] : '00:00';

		$options      = Updates_Cron::normalize_days_list_defaults( $schedule, $options );
		$allowed_days = $options['days_list'];
		$use_selective_days = false;
		$interval           = 0;

		$gmt_offset = HOUR_IN_SECONDS * (float) get_option( 'gmt_offset' );
		$timestamp  = strtotime( gmdate( 'Y-m-d' ) . ' ' . $time ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
		$timestamp  = (int) ( $timestamp - $gmt_offset );

		switch ( $schedule ) {
			case 'hourly':
				$interval           = HOUR_IN_SECONDS;
				$use_selective_days = true;
				break;
			case 'two-hours':
				$interval           = HOUR_IN_SECONDS * 2;
				$use_selective_days = true;
				break;
			case 'three-hours':
				$interval           = HOUR_IN_SECONDS * 3;
				$use_selective_days = true;
				break;
			case 'six-hours':
				$interval           = HOUR_IN_SECONDS * 6;
				$use_selective_days = true;
				break;
			case 'twelve-hours':
				$interval           = HOUR_IN_SECONDS * 12;
				$use_selective_days = true;
				break;
			case 'daily':
				$interval           = DAY_IN_SECONDS;
				$allowed_days       = array_values(
					array_diff(
						array( 1, 2, 3, 4, 5, 6, 7 ),
						array_map( 'absint', (array) $allowed_days )
					)
				);
				$use_selective_days = true;
				break;
			case 'weekly':
				$day_of_week = ucfirst( isset( $options['day'] ) ? (string) $options['day'] : 'sunday' );
				$timestamp   = strtotime( "This {$day_of_week}, {$time}", strtotime( gmdate( 'Y-m-d' ) ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
				$timestamp   = (int) ( $timestamp - $gmt_offset );
				$interval    = WEEK_IN_SECONDS;
				break;
			case 'two-weeks':
				$user_week_number = absint( $options['week'] ?? 1 );
				$day_of_week      = ucfirst( isset( $options['day'] ) ? (string) $options['day'] : 'sunday' );
				$timestamp        = strtotime( "+{$user_week_number} weeks", strtotime( gmdate( 'Y-m-d' ) . ' ' . $time ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
				$timestamp        = strtotime( "this {$day_of_week}, {$time}", $timestamp );
				$timestamp        = (int) ( $timestamp - $gmt_offset );
				$interval         = DAY_IN_SECONDS * 14;
				break;
			case 'monthly':
				$user_day_number = absint( $options['day_num'] ?? 1 );
				$timestamp       = strtotime( gmdate( 'Y-m-' . $user_day_number ) . ' ' . $time ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
				$timestamp       = (int) ( $timestamp - $gmt_offset );
				$interval        = (int) ( strtotime( '+1 month', $timestamp ) - $timestamp );
				break;
			default:
				return 0;
		}

		$now = time();
		if ( $timestamp < $now ) {
			if ( $interval > 0 ) {
				$timestamp = $now + ( $interval - ( ( $now - $timestamp ) % $interval ) );
			}
		} elseif ( $interval > 0 ) {
			$timestamp = $now + ( ( $timestamp - $now ) % $interval );
		}

		if ( $use_selective_days ) {
			$guard = 0;
			while ( ! empty( $allowed_days ) && is_array( $allowed_days ) && array_intersect( $allowed_days, range( 1, 7 ) ) ) {
				$timestamp_day_in_number = (int) get_date_from_gmt( gmdate( 'Y-m-d H:i', $timestamp ), 'w' ) + 1;
				if ( in_array( $timestamp_day_in_number, $allowed_days, true ) ) {
					break;
				}
				$timestamp += $interval;
				++$guard;
				if ( $guard > 500 ) {
					break;
				}
			}
			if ( empty( $allowed_days ) && $allow_zero ) {
				return 0;
			}
		}

		if ( 'weekly' === $schedule && $timestamp < $now ) {
			$timestamp += WEEK_IN_SECONDS;
		}
		if ( 'two-weeks' === $schedule && ( $timestamp < $now || 2 === absint( $options['week'] ?? 1 ) ) ) {
			$timestamp += 7 * DAY_IN_SECONDS;
		}

		return (int) $timestamp;
	}

	/**
	 * Format next cron timestamp for the admin UI.
	 *
	 * @param int $next_cron Unix timestamp.
	 * @return array<string, mixed>
	 */
	public static function get_format_from_cron( int $next_cron ): array {
		$timezone   = get_option( 'timezone_string' );
		$gmt_offset = get_option( 'gmt_offset' );

		return array(
			'timezone'   => esc_js( is_string( $timezone ) ? $timezone : '' ),
			'gmt_offset' => esc_js( (string) $gmt_offset ),
			'datetime'   => esc_js( get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $next_cron ) ) ), // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
			'timestamp'  => $next_cron,
		);
	}
}
