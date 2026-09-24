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

		$options            = Updates_Cron::normalize_days_list_defaults( $schedule, $options );
		$allowed_days       = $options['days_list'];
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

	/**
	 * Build labeled full crontab lines for a saved schedule (site-local time).
	 *
	 * Each entry is one pasteable crontab line: schedule expression + command.
	 *
	 * @param array<string, mixed> $options   Saved schedule options.
	 * @param string               $cron_hook WordPress cron hook name.
	 * @return array<int, array{label: string, command: string}>
	 */
	public static function get_crontab_commands( array $options, string $cron_hook ): array {
		$schedule = isset( $options['schedule'] ) ? (string) $options['schedule'] : 'twelve-hours';
		$options  = Updates_Cron::normalize_days_list_defaults( $schedule, $options );

		$time_parts = self::parse_start_time( isset( $options['start_time'] ) ? (string) $options['start_time'] : '00:00' );
		$minute     = $time_parts['minute'];
		$hour       = $time_parts['hour'];

		$expression = self::get_crontab_schedule_expression( $options, $minute, $hour );
		if ( '' === $expression ) {
			return array();
		}

		$wp_root     = self::get_wordpress_root_path();
		$wp_cron_php = $wp_root . '/wp-cron.php';
		$cron_url    = home_url( 'wp-cron.php?doing_wp_cron' );
		$cd_path     = self::quote_crontab_path( $wp_root );
		$php_path    = self::quote_crontab_path( $wp_cron_php );

		$approx_suffix = 'two-weeks' === $schedule
			? ' ' . __( '(weekly approximation)', 'updatebee' )
			: '';

		return array(
			array(
				'label'   => __( 'WP-CLI (preferred)', 'updatebee' ) . $approx_suffix,
				'command' => sprintf(
					'%s cd %s && wp cron event run %s >/dev/null 2>&1',
					$expression,
					$cd_path,
					$cron_hook
				),
			),
			array(
				'label'   => __( 'PHP (wp-cron.php)', 'updatebee' ) . $approx_suffix,
				'command' => sprintf(
					'%s php -q %s >/dev/null 2>&1',
					$expression,
					$php_path
				),
			),
			array(
				'label'   => __( 'curl', 'updatebee' ) . $approx_suffix,
				'command' => sprintf(
					'%s curl -s "%s" >/dev/null 2>&1',
					$expression,
					$cron_url
				),
			),
		);
	}

	/**
	 * Build copyable crontab text for a saved schedule (site-local time).
	 *
	 * Kept for backward compatibility with consumers of crontab_equivalent.
	 *
	 * @param array<string, mixed> $options   Saved schedule options.
	 * @param string               $cron_hook WordPress cron hook name.
	 * @return string Multiline crontab-style text with command hints.
	 */
	public static function get_crontab_equivalent( array $options, string $cron_hook ): string {
		$lines    = array( '# Times use the WordPress site timezone.' );
		$commands = self::get_crontab_commands( $options, $cron_hook );

		if ( empty( $commands ) ) {
			$lines[] = '# No valid run days are configured for this schedule.';
			return implode( "\n", $lines );
		}

		foreach ( $commands as $item ) {
			$lines[] = '# ' . $item['label'];
			$lines[] = $item['command'];
		}

		return implode( "\n", $lines );
	}

	/**
	 * Resolve the WordPress root filesystem path for crontab examples.
	 */
	private static function get_wordpress_root_path(): string {
		if ( defined( 'ABSPATH' ) && is_string( ABSPATH ) && '' !== ABSPATH ) {
			return rtrim( str_replace( '\\', '/', ABSPATH ), '/' );
		}

		return '/path/to/wordpress';
	}

	/**
	 * Quote a filesystem path when it contains spaces (crontab-safe).
	 *
	 * @param string $path Absolute path.
	 */
	private static function quote_crontab_path( string $path ): string {
		if ( false === strpos( $path, ' ' ) ) {
			return $path;
		}

		return '"' . $path . '"';
	}

	/**
	 * First non-comment five-field crontab expression for the schedule.
	 *
	 * @param array<string, mixed> $options Schedule options.
	 * @param int                  $minute  Minute field.
	 * @param int                  $hour    Hour field.
	 */
	private static function get_crontab_schedule_expression( array $options, int $minute, int $hour ): string {
		foreach ( self::build_crontab_schedule_lines( $options, $minute, $hour ) as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line || str_starts_with( $line, '#' ) ) {
				continue;
			}

			return $line;
		}

		return '';
	}

	/**
	 * Parse HH:MM start time into hour and minute.
	 *
	 * @param string $start_time Time string.
	 * @return array{hour: int, minute: int}
	 */
	private static function parse_start_time( string $start_time ): array {
		$parts = explode( ':', $start_time );

		return array(
			'hour'   => isset( $parts[0] ) ? max( 0, min( 23, (int) $parts[0] ) ) : 0,
			'minute' => isset( $parts[1] ) ? max( 0, min( 59, (int) $parts[1] ) ) : 0,
		);
	}

	/**
	 * Produce one or more five-field crontab lines for saved options.
	 *
	 * @param array<string, mixed> $options Schedule options.
	 * @param int                  $minute  Minute field.
	 * @param int                  $hour    Hour field (daily and slower schedules).
	 * @return string[]
	 */
	private static function build_crontab_schedule_lines( array $options, int $minute, int $hour ): array {
		$schedule = isset( $options['schedule'] ) ? (string) $options['schedule'] : 'twelve-hours';

		switch ( $schedule ) {
			case 'hourly':
				return self::build_subdaily_crontab_lines( $options, 1, $minute, $hour );
			case 'two-hours':
				return self::build_subdaily_crontab_lines( $options, 2, $minute, $hour );
			case 'three-hours':
				return self::build_subdaily_crontab_lines( $options, 3, $minute, $hour );
			case 'six-hours':
				return self::build_subdaily_crontab_lines( $options, 6, $minute, $hour );
			case 'twelve-hours':
				return self::build_subdaily_crontab_lines( $options, 12, $minute, $hour );
			case 'daily':
				return self::build_daily_crontab_lines( $options, $minute, $hour );
			case 'weekly':
				return array(
					self::format_crontab_line(
						$minute,
						$hour,
						'*',
						'*',
						self::weekday_name_to_cron_dow( isset( $options['day'] ) ? (string) $options['day'] : 'sunday' )
					),
				);
			case 'two-weeks':
				$dow = self::weekday_name_to_cron_dow( isset( $options['day'] ) ? (string) $options['day'] : 'sunday' );
				return array(
					'# Bi-weekly cadence cannot be expressed in one crontab line; weekly approximation:',
					self::format_crontab_line( $minute, $hour, '*', '*', $dow ),
				);
			case 'monthly':
				$day_num = absint( $options['day_num'] ?? 1 );
				return array(
					self::format_crontab_line( $minute, $hour, (string) max( 1, min( 28, $day_num ) ), '*', '*' ),
				);
			default:
				return array();
		}
	}

	/**
	 * Sub-daily schedules with EUM allow-list weekdays.
	 *
	 * @param array<string, mixed> $options        Options.
	 * @param int                  $interval_hours Interval in hours.
	 * @param int                  $minute         Start minute.
	 * @param int                  $start_hour     Start hour.
	 * @return string[]
	 */
	private static function build_subdaily_crontab_lines( array $options, int $interval_hours, int $minute, int $start_hour ): array {
		$allowed = array_values( array_map( 'absint', (array) ( $options['days_list'] ?? array() ) ) );
		$dow     = self::format_cron_dow_from_eum_days( $allowed );
		if ( '' === $dow ) {
			return array();
		}

		$hours      = self::subdaily_cron_hours( $interval_hours, $start_hour, $minute );
		$hour_field = self::format_cron_number_list_field( $hours, 24 );

		return array(
			self::format_crontab_line( $minute, $hour_field, '*', '*', $dow ),
		);
	}

	/**
	 * Daily schedule with EUM except-list weekdays.
	 *
	 * @param array<string, mixed> $options Options.
	 * @param int                  $minute  Minute.
	 * @param int                  $hour    Hour.
	 * @return string[]
	 */
	private static function build_daily_crontab_lines( array $options, int $minute, int $hour ): array {
		$except  = array_map( 'absint', (array) ( $options['days_list'] ?? array() ) );
		$allowed = array_values(
			array_diff(
				array( 1, 2, 3, 4, 5, 6, 7 ),
				$except
			)
		);

		$dow = self::format_cron_dow_from_eum_days( $allowed );
		if ( '' === $dow ) {
			return array();
		}

		return array(
			self::format_crontab_line( $minute, $hour, '*', '*', $dow ),
		);
	}

	/**
	 * Hours (0–23) that fire for a sub-daily interval anchored at start time.
	 *
	 * @param int $interval_hours Interval.
	 * @param int $start_hour     Start hour.
	 * @param int $start_minute   Start minute.
	 * @return int[]
	 */
	private static function subdaily_cron_hours( int $interval_hours, int $start_hour, int $start_minute ): array {
		$hours       = array();
		$step        = max( 1, $interval_hours ) * 60;
		$start_total = $start_hour * 60 + $start_minute;

		for ( $total = $start_total; $total < 24 * 60; $total += $step ) {
			$hours[] = (int) floor( $total / 60 );
		}

		return array_values( array_unique( $hours ) );
	}

	/**
	 * Map EUM weekday allow-list (1=Sunday) to crontab DOW field (0=Sunday).
	 *
	 * @param int[] $eum_days Allowed EUM day numbers.
	 * @return string Empty when no days remain.
	 */
	private static function format_cron_dow_from_eum_days( array $eum_days ): string {
		$eum_days = array_values(
			array_unique(
				array_filter(
					array_map( 'absint', $eum_days ),
					static function ( $day ) {
						return $day >= 1 && $day <= 7;
					}
				)
			)
		);

		if ( empty( $eum_days ) ) {
			return '';
		}

		if ( count( $eum_days ) === 7 ) {
			return '*';
		}

		$cron_days = array_map(
			static function ( $eum_day ) {
				return $eum_day - 1;
			},
			$eum_days
		);

		sort( $cron_days );

		return self::compress_number_ranges( $cron_days );
	}

	/**
	 * Map lowercase weekday name to cron DOW (0=Sunday).
	 *
	 * @param string $day_name Day slug.
	 */
	private static function weekday_name_to_cron_dow( string $day_name ): string {
		$map = array(
			'sunday'    => '0',
			'monday'    => '1',
			'tuesday'   => '2',
			'wednesday' => '3',
			'thursday'  => '4',
			'friday'    => '5',
			'saturday'  => '6',
		);

		return $map[ strtolower( $day_name ) ] ?? '0';
	}

	/**
	 * Format cron list field or asterisk when complete.
	 *
	 * @param int[] $values    Selected values.
	 * @param int   $full_count Count when all values selected.
	 */
	private static function format_cron_number_list_field( array $values, int $full_count ): string {
		$values = array_values( array_unique( array_map( 'intval', $values ) ) );
		sort( $values );

		if ( count( $values ) >= $full_count ) {
			return '*';
		}

		return self::compress_number_ranges( $values );
	}

	/**
	 * Collapse sorted integers into cron ranges (e.g. 1,3-5).
	 *
	 * @param int[] $numbers Sorted unique integers.
	 */
	private static function compress_number_ranges( array $numbers ): string {
		if ( empty( $numbers ) ) {
			return '';
		}

		$ranges = array();
		$start  = $numbers[0];
		$prev   = $numbers[0];

		for ( $i = 1, $len = count( $numbers ); $i < $len; $i++ ) {
			if ( $numbers[ $i ] === $prev + 1 ) {
				$prev = $numbers[ $i ];
				continue;
			}
			$ranges[] = $start === $prev ? (string) $start : $start . '-' . $prev;
			$start    = $numbers[ $i ];
			$prev     = $numbers[ $i ];
		}

		$ranges[] = $start === $prev ? (string) $start : $start . '-' . $prev;

		return implode( ',', $ranges );
	}

	/**
	 * Assemble a five-field crontab expression.
	 *
	 * @param int|string $minute Minute.
	 * @param int|string $hour   Hour.
	 * @param string     $dom    Day of month.
	 * @param string     $month  Month.
	 * @param string     $dow    Day of week.
	 */
	private static function format_crontab_line( $minute, $hour, string $dom, string $month, string $dow ): string {
		return sprintf( '%s %s %s %s %s', $minute, $hour, $dom, $month, $dow );
	}
}
