<?php
/**
 * Init class for all update runtime hooks.
 *
 * @package updatebee
 */

namespace UpdateBee\Updates;

use UpdateBee\Functions;
use UpdateBee\Options;

/**
 * Bootstrap update filters and cron fallbacks.
 */
class Init {

	/**
	 * Class runner.
	 */
	public function run(): void {
		add_action( 'init', array( $this, 'init' ) );

		Core::get_instance();
		Plugins::get_instance();
		Themes::get_instance();
		Translations::get_instance();
		Delay_Updates::get_instance();

		if ( ! self::core_emails_enabled() ) {
			$priority = Functions::get_highest_priority( 10 );
			add_filter( 'auto_core_update_send_email', '__return_false', $priority );
			add_filter( 'send_core_update_notification_email', '__return_false', $priority );
			add_filter( 'automatic_updates_send_debug_email', '__return_false', $priority );
			add_filter( 'auto_theme_update_send_email', '__return_false', $priority );
			add_filter( 'auto_plugin_update_send_email', '__return_false', $priority );
		}
	}

	/**
	 * Whether WordPress default auto-update emails should be sent.
	 *
	 * @return bool True when core emails are enabled in plugin options.
	 */
	public static function core_emails_enabled(): bool {
		$value = Options::get_options( 'enable_core_emails' );

		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			return 'on' === $value || '1' === $value || 'true' === $value;
		}

		return (bool) $value;
	}

	/**
	 * Return scheduled events for core update crons.
	 *
	 * @return array<string, object|false> Cron events keyed by context.
	 */
	public static function get_update_crons(): array {
		return array(
			'update_plugins' => wp_get_scheduled_event( 'wp_update_plugins' ),
			'update_themes'  => wp_get_scheduled_event( 'wp_update_themes' ),
			'version_check'  => wp_get_scheduled_event( 'wp_version_check' ),
		);
	}

	/**
	 * Checks to ensure that the Core cron is unmodified.
	 *
	 * @return bool True if scheduled, false if not.
	 */
	private function is_core_version_check_valid(): bool {
		if ( ! has_action( 'wp_version_check', 'wp_version_check' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Run on the init action.
	 */
	public function init(): void {
		if ( ! $this->is_core_version_check_valid() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			add_action( 'wp_update_plugins', array( $this, 'auto_update' ), 20 );
		}
	}

	/**
	 * Forces WordPress to run auto-updates manually during cron.
	 */
	public function auto_update(): void {
		if ( wp_doing_cron() && ! doing_action( 'wp_maybe_auto_update' ) ) {
			do_action( 'wp_maybe_auto_update' );
		}
	}
}
