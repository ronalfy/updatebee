<?php
/**
 * Plugin options helper.
 *
 * @package updatebee
 */

namespace UpdateBee;

use UpdateBee\Admin\Migrate\CompanionAutoUpdate;
use UpdateBee\Admin\Migrate\EasyUpdatesManager;

/**
 * Class Options.
 */
class Options {


	/**
	 * A static cached version of the main options for the plugin.
	 *
	 * @var array<string, mixed>
	 */
	private static $options = array();

	/**
	 * Options key.
	 *
	 * @var string
	 */
	private static $options_key = 'updatebee_options';

	/**
	 * Retrieve default options for the plugin.
	 *
	 * @return array<string, mixed>
	 */
	private static function get_defaults(): array {
		$defaults = array(
			'major_auto_updates'               => 'off',
			'minor_auto_updates'               => 'on',
			'plugin_auto_updates'              => 'off',
			'plugin_auto_updates_custom'       => 'off', /* choose per plugin */
			'plugin_manual_updates_only'       => 'off',
			'plugin_auto_updates_patches_only' => 'off',
			'plugin_updates_disabled'          => 'off', /* all plugin updates disabled */
			'theme_auto_updates'               => 'off',
			'theme_auto_updates_custom'        => 'off', /* choose per theme */
			'theme_manual_updates_only'        => 'off',
			'theme_auto_updates_patches_only'  => 'off',
			'theme_updates_disabled'           => 'off', /* all theme updates disabled */
			'translation_auto_updates'         => 'on',
			'translation_auto_updates_custom'  => 'off', /* choose per translation */
			'translation_manual_updates_only'  => 'off',
			'translation_updates_disabled'     => 'off', /* all translation updates disabled */
			'auto_update_plugins'              => array(),
			'auto_update_plugins_patches'      => array(), /* plugin files limited to patch auto-updates. */
			'blocked_plugins'                  => array(),
			'auto_update_themes'               => array(),
			'auto_update_themes_patches'       => array(), /* theme slugs limited to patch auto-updates. */
			'blocked_themes'                   => array(),
			'auto_update_translations'         => array(),
			'blocked_translations'             => array(),
			'enable_core_emails'               => true,
			'core_email_recipients'            => array(),
			'clear_logs_days'                  => 0,
			'slack_webhook_url'                => '',
			'slack_enabled'                    => false,
			'delay_updates'                    => 0,
			'upgrade_notice_keywords'          => implode(
				"\n",
				array(
					'security',
					'CSRF',
					'XSS',
					'CVE',
					'SSRF',
				)
			),
			'upgrade_notice_keywords_enabled'  => false,
			'block_untested_updates'           => 'off',
			'disable_wp_cron'                  => false,
			'fatal_error_detection'            => true,
			'fatal_email_recipients'           => array(),
			'fatal_notify_slack'               => false,
		);

		/**
		 * Filter for option defaults.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $defaults Option defaults.
		 */
		return (array) apply_filters( 'updatebee_options_defaults', $defaults );
	}

	/**
	 * Reset the in-memory options cache.
	 */
	public static function flush_cache(): void {
		self::$options = array();
	}

	/**
	 * Retrieve the plugin's options.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key          Array key to retrieve (if left empty, returns all options).
	 * @param bool   $force_reload Whether to retrieve cached options or forcefully retrieve from the database.
	 * @return mixed All options for key, value for $option, empty array on failure.
	 */
	public static function get_options( string $key = '', bool $force_reload = false ) {
		$options = self::$options;

		if ( empty( $options ) || true === $force_reload ) {
			$options = get_site_option( self::$options_key, array() );
			if ( ! is_array( $options ) ) {
				$options = array();
			}
		}

		$options       = wp_parse_args( $options, self::get_defaults() );
		self::$options = $options;

		if ( ! empty( $key ) && array_key_exists( $key, $options ) ) {
			return $options[ $key ];
		}

		if ( ! empty( $key ) ) {
			return array();
		}

		return $options;
	}

	/**
	 * Save options for the plugin.
	 *
	 * @param string                      $key     Key to update.
	 * @param array<string, mixed>|string $options Options to save.
	 * @param bool                        $force   True to forcefully retrieve fresh options.
	 * @return array<string, mixed>|null Options.
	 */
	public static function update_options( string $key = '', array|string $options = array(), bool $force = false ) {
		$cached_options = self::get_options( '', $force );

		if ( ! is_array( $cached_options ) ) {
			return null;
		}

		if ( array_key_exists( $key, $cached_options ) && is_array( $options ) && array_key_exists( $key, $options ) ) {
			$cached_options[ $key ] = $options[ $key ];
			/**
			 * Filter for saving options.
			 *
			 * @since 1.0.0
			 *
			 * @param array<string, mixed> $cached_options Options.
			 * @param string               $key            Option key.
			 * @param mixed                $options        Incoming options.
			 * @param bool                 $force          Force reload.
			 */
			$cached_options = apply_filters( 'updatebee_options_save_pre', $cached_options, $key, $options, $force );
			$cached_options = Functions::parse_args_r( $cached_options, self::get_defaults() );
			update_site_option( self::$options_key, $cached_options );
		} elseif ( is_array( $options ) && empty( $key ) ) {
			$cached_options = Functions::parse_args_r( $options, $cached_options );
			$cached_options = apply_filters( 'updatebee_options_save_pre', $cached_options, $key, $options, $force );
			$cached_options = Functions::parse_args_r( $cached_options, self::get_defaults() );
			update_site_option( self::$options_key, $cached_options );
		} elseif ( is_array( $options ) && array_key_exists( $key, $cached_options ) ) {
			$cached_options[ $key ] = $options;
			$cached_options         = apply_filters( 'updatebee_options_save_pre', $cached_options, $key, $options, $force );
			$cached_options         = Functions::parse_args_r( $cached_options, self::get_defaults() );
			update_site_option( self::$options_key, $cached_options );
		} else {
			return null;
		}

		self::$options = $cached_options;

		return self::$options;
	}

	/**
	 * Clear plugin auto-update and patch lists.
	 */
	public static function reset_plugin_auto_updates(): void {
		self::update_options( 'auto_update_plugins', array() );
		self::update_options( 'auto_update_plugins_patches', array() );
	}

	/**
	 * Clear blocked plugin updates.
	 */
	public static function reset_plugin_blocked_updates(): void {
		self::update_options( 'blocked_plugins', array() );
	}

	/**
	 * Clear theme auto-update and patch lists.
	 */
	public static function reset_theme_auto_updates(): void {
		self::update_options( 'auto_update_themes', array() );
		self::update_options( 'auto_update_themes_patches', array() );
	}

	/**
	 * Clear blocked theme updates.
	 */
	public static function reset_theme_blocked_updates(): void {
		self::update_options( 'blocked_themes', array() );
	}

	/**
	 * Clear translation auto-update selections.
	 */
	public static function reset_translation_auto_updates(): void {
		self::update_options( 'auto_update_translations', array() );
	}

	/**
	 * Clear blocked translation updates.
	 */
	public static function reset_translation_blocked_updates(): void {
		self::update_options( 'blocked_translations', array() );
	}

	/**
	 * Clear hide flags for all hidden importers without resetting UpdateBee options.
	 */
	public static function reset_importer_options(): void {
		// Clear importer hide flags so hidden Tools cards can return.
		EasyUpdatesManager::clear_importer_hide();
		CompanionAutoUpdate::clear_importer_hide();
	}

	/**
	 * Clear/Reset all options to defaults and restore hidden importers.
	 */
	public static function reset_options(): void {
		self::update_options( '', self::get_defaults() );
		// Restore all hidden importers.
		EasyUpdatesManager::clear_importer_hide();
		CompanionAutoUpdate::clear_importer_hide();
	}
}
