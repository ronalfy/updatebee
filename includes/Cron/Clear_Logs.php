<?php
/**
 * Daily cron to expire old logs.
 *
 * @package updatebee
 */

namespace UpdateBee\Cron;

use UpdateBee\Options;
use UpdateBee\Tables\Table_Logs;

/**
 * Schedule and run expired-log cleanup.
 */
class Clear_Logs {

	/**
	 * Cron hook name.
	 *
	 * @var string
	 */
	public const HOOK = 'updatebee_clear_expired_logs';

	/**
	 * Register cron hooks.
	 */
	public function run(): void {
		add_action( 'init', array( $this, 'sync_schedule' ) );
		add_action( self::HOOK, array( $this, 'clear_expired_logs' ) );
	}

	/**
	 * Keep the daily event aligned with the retention setting.
	 */
	public static function sync_schedule(): void {
		$days = self::get_retention_days();

		if ( $days > 0 ) {
			if ( ! wp_next_scheduled( self::HOOK ) ) {
				wp_schedule_event( time(), 'daily', self::HOOK );
			}

			return;
		}

		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Delete logs older than the configured retention window.
	 */
	public function clear_expired_logs(): void {
		$days = self::get_retention_days();
		if ( $days < 1 ) {
			return;
		}

		Table_Logs::clear_logs_older_than( $days );
	}

	/**
	 * Return the stored retention days.
	 *
	 * @return int Days to keep logs.
	 */
	public static function get_retention_days(): int {
		$days = Options::get_options( 'clear_logs_days' );

		return absint( $days );
	}
}
