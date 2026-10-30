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
		Upgrade_Notice_Keywords::get_instance();
		Untested_Updates::get_instance();

		if ( ! self::core_emails_enabled() ) {
			$priority = Functions::get_highest_priority( 10 );
			add_filter( 'auto_core_update_send_email', '__return_false', $priority );
			add_filter( 'send_core_update_notification_email', '__return_false', $priority );
			add_filter( 'automatic_updates_send_debug_email', '__return_false', $priority );
			add_filter( 'auto_theme_update_send_email', '__return_false', $priority );
			add_filter( 'auto_plugin_update_send_email', '__return_false', $priority );
		} else {
			add_filter( 'auto_core_update_email', array( $this, 'filter_update_email_recipients' ) );
			add_filter( 'auto_plugin_theme_update_email', array( $this, 'filter_update_email_recipients' ) );
			add_filter( 'automatic_updates_debug_email', array( $this, 'filter_update_email_recipients' ) );
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
	 * Stored recipients for WordPress automatic update emails.
	 *
	 * An empty list means WordPress should keep its own admin address.
	 *
	 * @return array<int, string> Valid email addresses.
	 */
	public static function get_core_email_recipients(): array {
		$value = Options::get_options( 'core_email_recipients' );
		if ( ! is_array( $value ) ) {
			return array();
		}

		$recipients = array();
		foreach ( $value as $email ) {
			if ( ! is_string( $email ) ) {
				continue;
			}

			$email = sanitize_email( $email );
			if ( is_email( $email ) ) {
				$recipients[] = $email;
			}
		}

		return array_values( array_unique( $recipients ) );
	}

	/**
	 * Recipients shown on the WordPress email card.
	 *
	 * Falls back to the site admin email when none are stored.
	 *
	 * @return array<int, string> Valid email addresses.
	 */
	public static function get_core_email_recipients_for_display(): array {
		$recipients = self::get_core_email_recipients();
		if ( ! empty( $recipients ) ) {
			return $recipients;
		}

		$admin_email = sanitize_email( (string) get_site_option( 'admin_email' ) );
		if ( is_email( $admin_email ) ) {
			return array( $admin_email );
		}

		return array();
	}

	/**
	 * Replace the WordPress update email recipient list.
	 *
	 * @param array<string, mixed> $email Email arguments passed to wp_mail().
	 * @return array<string, mixed> Email arguments.
	 */
	public function filter_update_email_recipients( array $email ): array {
		$recipients = self::get_core_email_recipients();
		if ( ! empty( $recipients ) ) {
			$email['to'] = $recipients;
		}

		return $email;
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
	 * Schedule plugin, theme, and core version checks to run immediately.
	 *
	 * Shared by the tools screen and the updatebee/schedule-update-checks ability.
	 */
	public static function schedule_update_checks(): void {
		if ( ! defined( 'DOING_CRON' ) ) {
			define( 'DOING_CRON', true );
		}

		wp_schedule_single_event( time(), 'wp_update_plugins' );
		wp_schedule_single_event( time(), 'wp_update_themes' );
		wp_schedule_single_event( time(), 'wp_version_check', array( array(), true ) );
	}

	/**
	 * Run automatic updates for items the current policy allows.
	 *
	 * Shared by the tools screen and the updatebee/run-auto-updates ability.
	 * This can change the site by updating core, plugins, themes, and translations.
	 */
	public static function run_auto_updates(): void {
		if ( ! defined( 'UPDATEBEE_DOING_FORCE_UPDATES' ) ) {
			define( 'UPDATEBEE_DOING_FORCE_UPDATES', true );
		}

		// Disable auto-backups from occurring with UpdraftPlus Premium during force updates.
		add_filter( 'updraftplus_boot_backup', '__return_false', 10, 1 );

		$updater_lock = get_option( 'auto_updater.lock' );
		/**
		 * Whether to delete the auto update lock file.
		 *
		 * @since 1.0.0
		 *
		 * @param bool   $disable_lock Ignore deleting the lock file (default true).
		 * @param string $updater_lock The lock file expiration if exists.
		 */
		$disable_lock = apply_filters( 'updatebee_force_updates_disable_lock', true, $updater_lock );
		if ( true === $disable_lock ) {
			delete_option( 'auto_updater.lock' );
		}

		add_filter( 'wp_doing_cron', '__return_true' );
		wp_maybe_auto_update();
		remove_filter( 'wp_doing_cron', '__return_true' );
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
