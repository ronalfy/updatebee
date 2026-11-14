<?php
/**
 * Report whether wp-config.php has set DISABLE_WP_CRON.
 *
 * UpdateBee does not define that constant.
 *
 * @package updatebee
 */

namespace UpdateBee\Cron;

/**
 * Read-only status for the Schedules screen.
 */
class Wp_Cron_Status {

	/**
	 * Whether DISABLE_WP_CRON is defined.
	 *
	 * @var bool|null
	 */
	private static $constant_defined = null;

	/**
	 * Value of DISABLE_WP_CRON when defined, else null.
	 *
	 * @var bool|null
	 */
	private static $constant_value = null;

	/**
	 * Reset the captured constant snapshot (for tests).
	 */
	public static function reset_state(): void {
		self::$constant_defined = null;
		self::$constant_value   = null;
	}

	/**
	 * Admin UI payload for the Schedules screen.
	 *
	 * @return array{constantDefined: bool, constantValue: bool|null}
	 */
	public static function get_admin_state(): array {
		self::capture_constant_state();

		return array(
			'constantDefined' => (bool) self::$constant_defined,
			'constantValue'   => self::$constant_value,
		);
	}

	/**
	 * Whether WordPress cron is disabled by DISABLE_WP_CRON.
	 */
	public static function is_wp_cron_disabled(): bool {
		self::capture_constant_state();

		return true === self::$constant_value;
	}

	/**
	 * Remember the constant WordPress loaded from wp-config.php.
	 */
	private static function capture_constant_state(): void {
		if ( null !== self::$constant_defined ) {
			return;
		}

		self::$constant_defined = defined( 'DISABLE_WP_CRON' );
		self::$constant_value   = self::$constant_defined
			? (bool) constant( 'DISABLE_WP_CRON' )
			: null;
	}
}
