<?php
/**
 * Disable WordPress visitor-triggered (pseudo) cron network-wide.
 *
 * @package updatebee
 */

namespace UpdateBee\Cron;

use UpdateBee\Options;

/**
 * Applies DISABLE_WP_CRON from a network option without blocking system cron.
 */
class Disable_Wp_Cron {

	/**
	 * Option key in updatebee_options.
	 */
	public const OPTION_KEY = 'disable_wp_cron';

	/**
	 * Whether DISABLE_WP_CRON was already defined before this plugin ran.
	 *
	 * @var bool|null
	 */
	private static $constant_was_predefined = null;

	/**
	 * Value of DISABLE_WP_CRON when it was predefined, else null.
	 *
	 * @var bool|null
	 */
	private static $predefined_value = null;

	/**
	 * Capture constant state, define DISABLE_WP_CRON when the option allows it.
	 */
	public static function bootstrap(): void {
		self::capture_predefined_constant_state();
		self::maybe_define_constant();
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_remove_wp_cron_action' ), 1 );
	}

	/**
	 * Reset static state (for tests).
	 */
	public static function reset_state(): void {
		self::$constant_was_predefined = null;
		self::$predefined_value        = null;
	}

	/**
	 * Whether the network option is enabled.
	 */
	public static function is_option_enabled(): bool {
		return (bool) Options::get_options( self::OPTION_KEY );
	}

	/**
	 * Whether DISABLE_WP_CRON was defined before UpdateBee could set it.
	 */
	public static function was_constant_predefined(): bool {
		self::capture_predefined_constant_state();

		return (bool) self::$constant_was_predefined;
	}

	/**
	 * Predefined constant value when present.
	 */
	public static function get_predefined_constant_value(): ?bool {
		self::capture_predefined_constant_state();

		return self::$predefined_value;
	}

	/**
	 * Admin UI payload for the Schedules screen.
	 *
	 * @return array{enabled: bool, constantDefined: bool, constantValue: bool|null}
	 */
	public static function get_admin_state(): array {
		self::capture_predefined_constant_state();

		return array(
			'enabled'         => self::is_option_enabled(),
			'constantDefined' => (bool) self::$constant_was_predefined,
			'constantValue'   => self::$predefined_value,
		);
	}

	/**
	 * Whether the Schedules UI may toggle the option.
	 */
	public static function can_manage_option(): bool {
		return ! self::was_constant_predefined();
	}

	/**
	 * Define DISABLE_WP_CRON when the option is on and the constant is free.
	 */
	public static function maybe_define_constant(): void {
		self::capture_predefined_constant_state();

		if ( self::$constant_was_predefined ) {
			return;
		}

		if ( self::is_option_enabled() && ! defined( 'DISABLE_WP_CRON' ) ) {
			define( 'DISABLE_WP_CRON', true );
		}
	}

	/**
	 * Backup: remove core's init cron spawn when the constant could not disable it.
	 */
	public static function maybe_remove_wp_cron_action(): void {
		self::capture_predefined_constant_state();

		if ( self::$constant_was_predefined ) {
			return;
		}

		if ( ! self::is_option_enabled() ) {
			return;
		}

		if ( ! defined( 'DISABLE_WP_CRON' ) || ! DISABLE_WP_CRON ) {
			remove_action( 'init', 'wp_cron' );
		}
	}

	/**
	 * Remember whether wp-config (or another early define) already set the constant.
	 */
	private static function capture_predefined_constant_state(): void {
		if ( null !== self::$constant_was_predefined ) {
			return;
		}

		self::$constant_was_predefined = defined( 'DISABLE_WP_CRON' );
		self::$predefined_value        = self::$constant_was_predefined
			? (bool) constant( 'DISABLE_WP_CRON' )
			: null;
	}
}
