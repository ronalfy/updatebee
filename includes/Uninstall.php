<?php
/**
 * Remove plugin data on uninstall or an explicit clear.
 *
 * @package updatebee
 */

namespace UpdateBee;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use UpdateBee\Admin\Migrate\CompanionAutoUpdate;
use UpdateBee\Admin\Migrate\EasyUpdatesManager;
use UpdateBee\Cron\Clear_Logs;
use UpdateBee\Cron\Updates_Cron;
use UpdateBee\Emails\Available_Updates;
use UpdateBee\Emails\Email_Failures;
use UpdateBee\Emails\Email_Successes;
use UpdateBee\Emails\Email_Updates_Available;
use UpdateBee\Tables\Table_Logs;
use UpdateBee\Tables\Table_Plugins;
use UpdateBee\Tables\Table_Themes;
use UpdateBee\Updates\Delay_Updates;

/**
 * Delete this plugin's options, tables, cron events, and transients.
 */
class Uninstall {

	/**
	 * Remove stored plugin data.
	 *
	 * Called from Advanced when the user chooses to remove all plugin data.
	 * Does not deactivate or delete the plugin files. Deleting the plugin does not call this.
	 */
	public static function clear_data(): void {
		self::delete_site_options();
		self::delete_tables();
		self::clear_cron();
		self::delete_transients();
		Options::flush_cache();
	}

	/**
	 * Whether the current user can deactivate this plugin.
	 */
	public static function user_can_deactivate(): bool {
		if ( Functions::is_multisite() ) {
			return current_user_can( 'manage_network_plugins' );
		}

		return current_user_can( 'activate_plugins' );
	}

	/**
	 * Deactivate the plugin and leave its files installed.
	 */
	public static function deactivate(): void {
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		deactivate_plugins( Functions::get_plugin_file(), false, Functions::is_multisite() );
	}

	/**
	 * Plugins screen to open after deactivation.
	 *
	 * Call this before deactivate(). On multisite, network activation is what
	 * selects the network Plugins screen, and that flag is gone once the plugin is inactive.
	 */
	public static function plugins_screen_url(): string {
		if ( is_multisite() && ( is_network_admin() || Functions::is_multisite() ) ) {
			return network_admin_url( 'plugins.php' );
		}

		return admin_url( 'plugins.php' );
	}

	/**
	 * Site option keys owned by the plugin.
	 *
	 * @return string[]
	 */
	private static function site_option_keys(): array {
		return array(
			Options::OPTIONS_KEY,
			Logs::CORE_VERSION_OPTION,
			Delay_Updates::PLUGIN_OPTION,
			Delay_Updates::THEME_OPTION,
			Updates_Cron::get_option_key( Updates_Cron::CONTEXT_PLUGINS ),
			Updates_Cron::get_option_key( Updates_Cron::CONTEXT_THEMES ),
			Updates_Cron::get_option_key( Updates_Cron::CONTEXT_CORE ),
			Email_Successes::OPTIONS_KEY,
			Email_Successes::LAST_SEND_KEY,
			Email_Failures::OPTIONS_KEY,
			Email_Failures::LAST_SEND_KEY,
			Email_Updates_Available::OPTIONS_KEY,
			Email_Updates_Available::LAST_SEND_KEY,
			EasyUpdatesManager::HIDE_OPTION,
			CompanionAutoUpdate::HIDE_OPTION,
		);
	}

	/**
	 * Cron hooks the plugin schedules, including core update checks it reschedules.
	 *
	 * @return string[]
	 */
	private static function cron_hooks(): array {
		return array(
			Clear_Logs::HOOK,
			Email_Successes::CRON_HOOK,
			Email_Failures::CRON_HOOK,
			Email_Updates_Available::CRON_HOOK,
			Updates_Cron::get_cron_hook( Updates_Cron::CONTEXT_PLUGINS ),
			Updates_Cron::get_cron_hook( Updates_Cron::CONTEXT_THEMES ),
			Updates_Cron::get_cron_hook( Updates_Cron::CONTEXT_CORE ),
		);
	}

	/**
	 * Delete network and single-site copies of plugin options.
	 */
	private static function delete_site_options(): void {
		foreach ( self::site_option_keys() as $key ) {
			if ( '' === $key ) {
				continue;
			}

			delete_site_option( $key );
		}
	}

	/**
	 * Drop custom tables and their per-site version options.
	 */
	private static function delete_tables(): void {
		if ( ! is_multisite() ) {
			self::drop_tables();
			return;
		}

		$site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		);

		if ( ! is_array( $site_ids ) || array() === $site_ids ) {
			self::drop_tables();
			return;
		}

		$dropped = false;
		foreach ( $site_ids as $site_id ) {
			if ( ! is_numeric( $site_id ) ) {
				continue;
			}

			switch_to_blog( (int) $site_id );
			self::drop_tables();
			restore_current_blog();
			$dropped = true;
		}

		if ( ! $dropped ) {
			self::drop_tables();
		}
	}

	/**
	 * Drop the plugin tables for the current site.
	 */
	private static function drop_tables(): void {
		Table_Plugins::drop_table();
		Table_Themes::drop_table();
		Table_Logs::drop_table();
	}

	/**
	 * Unschedule plugin cron events.
	 *
	 * Core update hooks are cleared so WordPress can restore its default schedule
	 * on the next request, after this plugin's custom intervals are gone.
	 */
	private static function clear_cron(): void {
		foreach ( self::cron_hooks() as $hook ) {
			if ( '' === $hook ) {
				continue;
			}

			wp_clear_scheduled_hook( $hook );
		}
	}

	/**
	 * Delete temporary update captures.
	 */
	private static function delete_transients(): void {
		delete_site_transient( Available_Updates::PLUGIN_CAPTURE_TRANSIENT );
		delete_site_transient( Available_Updates::THEME_CAPTURE_TRANSIENT );
		delete_site_transient( Available_Updates::CORE_CAPTURE_TRANSIENT );
	}
}
