<?php
/**
 * Weekday gating for automatic updates (EUM-style allow/except lists).
 *
 * @package updatebee
 */

namespace UpdateBee\Cron;

/**
 * Filters auto_update_* when sub-daily or daily weekday rules apply.
 */
class Days_Schedule {

	/**
	 * Register filters.
	 */
	public function run(): void {
		add_filter( 'auto_update_core', array( $this, 'maybe_auto_update_on_scheduled_days' ), PHP_INT_MAX - 10, 2 );
		add_filter( 'auto_update_plugin', array( $this, 'maybe_auto_update_on_scheduled_days' ), PHP_INT_MAX - 10, 2 );
		add_filter( 'auto_update_theme', array( $this, 'maybe_auto_update_on_scheduled_days' ), PHP_INT_MAX - 10, 2 );
		add_filter( 'auto_update_translation', array( $this, 'maybe_auto_update_on_scheduled_days' ), PHP_INT_MAX - 10, 2 );
	}

	/**
	 * Whether schedule uses weekday lists.
	 *
	 * @param string $schedule Schedule slug.
	 */
	public static function schedule_uses_weekdays( string $schedule ): bool {
		return in_array(
			$schedule,
			array(
				'hourly',
				'two-hours',
				'three-hours',
				'six-hours',
				'twelve-hours',
				'daily',
			),
			true
		);
	}

	/**
	 * Apply weekday rules for a context option set.
	 *
	 * @param bool                 $update  Whether WordPress would auto-update.
	 * @param array<string, mixed> $options Saved cron options.
	 */
	public static function apply_weekday_rules( bool $update, array $options ): bool {
		if ( ! $update ) {
			return $update;
		}

		$schedule = isset( $options['schedule'] ) ? (string) $options['schedule'] : '';
		if ( ! self::schedule_uses_weekdays( $schedule ) ) {
			return $update;
		}

		$options  = Updates_Cron::normalize_days_list_defaults( $schedule, $options );
		$days_list = $options['days_list'];

		$current_day = (int) get_date_from_gmt( gmdate( 'Y-m-d H:i:s' ), 'w' ) + 1;

		if ( 'daily' === $schedule ) {
			if ( ! empty( $days_list ) && in_array( $current_day, $days_list, true ) ) {
				return false;
			}
		} elseif ( isset( $days_list ) && ! in_array( $current_day, $days_list, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Filter callback for auto update hooks.
	 *
	 * @param bool  $update Whether to update.
	 * @param mixed $item   Update item.
	 */
	public function maybe_auto_update_on_scheduled_days( $update, $item ) {
		unset( $item );

		if ( ! $update ) {
			return $update;
		}

		$context = current_filter();
		switch ( $context ) {
			case 'auto_update_core':
				$options = Updates_Cron::get_cron_options( Updates_Cron::CONTEXT_CORE );
				break;
			case 'auto_update_theme':
				$options = Updates_Cron::get_cron_options( Updates_Cron::CONTEXT_THEMES );
				break;
			case 'auto_update_plugin':
			case 'auto_update_translation':
				$options = Updates_Cron::get_cron_options( Updates_Cron::CONTEXT_PLUGINS );
				break;
			default:
				return $update;
		}

		return self::apply_weekday_rules( (bool) $update, $options );
	}
}
