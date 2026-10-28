<?php
/**
 * Migrate settings from Companion Auto Update to UpdateBee.
 *
 * @package updatebee
 */

namespace UpdateBee\Admin\Migrate;

use UpdateBee\Cron\Schedules;
use UpdateBee\Cron\Updates_Cron;
use UpdateBee\Emails\Email_Successes;
use UpdateBee\Emails\Email_Updates_Available;
use UpdateBee\Functions;
use UpdateBee\Options;

/**
 * Class CompanionAutoUpdate.
 */
class CompanionAutoUpdate {

	/**
	 * Site option that hides the Companion Auto Update importer.
	 *
	 * @var string
	 */
	public const HIDE_OPTION = 'updatebee_hide_cau_importer';

	/**
	 * Companion Auto Update plugin file.
	 *
	 * @var string
	 */
	private const PLUGIN_FILE = 'companion-auto-update/companion-auto-update.php';

	/**
	 * Settings table suffix. The current site prefix is added at read time.
	 *
	 * @var string
	 */
	private const TABLE_SUFFIX = 'auto_updates';

	/**
	 * Mail cron hook Companion Auto Update uses for update reports.
	 *
	 * @var string
	 */
	private const MAIL_HOOK = 'cau_set_schedule_mail';

	/**
	 * Companion Auto Update recurrence slugs to UpdateBee schedule slugs.
	 *
	 * @var array<string, string>
	 */
	private const SCHEDULE_MAP = array(
		'hourly'        => 'hourly',
		'twicedaily'    => 'twelve-hours',
		'daily'         => 'daily',
		'weekly'        => 'weekly',
		'twice_monthly' => 'two-weeks',
		'once_monthly'  => 'monthly',
	);

	/**
	 * Whether Companion Auto Update is detected (active or settings table present).
	 *
	 * @return bool
	 */
	public static function is_detected(): bool {
		if ( ! empty( self::get_source_rows() ) ) {
			return true;
		}

		return self::is_plugin_active();
	}

	/**
	 * Whether the importer has been hidden by the user.
	 *
	 * @return bool
	 */
	public static function is_importer_hidden(): bool {
		return (bool) get_site_option( self::HIDE_OPTION, false );
	}

	/**
	 * Persist the importer hide flag.
	 *
	 * @return bool True when the option is saved.
	 */
	public static function hide_importer(): bool {
		return (bool) update_site_option( self::HIDE_OPTION, 1 );
	}

	/**
	 * Clear the importer hide flag so the card can appear again.
	 */
	public static function clear_importer_hide(): void {
		delete_site_option( self::HIDE_OPTION );
	}

	/**
	 * Whether the Tools importer card should be shown.
	 *
	 * @return bool
	 */
	public static function should_show_importer(): bool {
		if ( self::is_importer_hidden() ) {
			return false;
		}

		return self::is_detected();
	}

	/**
	 * Read Companion Auto Update settings rows for the current site.
	 *
	 * @return array<string, string> Setting name to stored value.
	 */
	public static function get_source_rows(): array {
		global $wpdb;

		if ( ! is_object( $wpdb ) || ! isset( $wpdb->prefix ) || ! method_exists( $wpdb, 'get_results' ) ) {
			return array();
		}

		$table = $wpdb->prefix . self::TABLE_SUFFIX;
		if ( ! self::table_exists( $table ) ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name is the current site prefix plus a fixed suffix.
		$rows = $wpdb->get_results( "SELECT name, onoroff FROM `{$table}`", ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$source = array();
		foreach ( $rows as $row ) {
			if ( is_object( $row ) ) {
				$row = get_object_vars( $row );
			}
			if ( ! is_array( $row ) ) {
				continue;
			}

			$name = isset( $row['name'] ) ? sanitize_text_field( (string) $row['name'] ) : '';
			if ( '' === $name ) {
				continue;
			}

			$source[ $name ] = isset( $row['onoroff'] ) ? (string) $row['onoroff'] : '';
		}

		return $source;
	}

	/**
	 * Run the migration.
	 *
	 * @return bool True when settings rows were mapped.
	 */
	public static function run(): bool {
		$source = self::get_source_rows();
		if ( empty( $source ) ) {
			return false;
		}

		$mapped = self::map_options( $source );
		Options::update_options( '', $mapped );
		Options::flush_cache();

		$events = self::get_cron_events();
		foreach ( self::map_cron_options( $events ) as $context => $options ) {
			Updates_Cron::save_cron_options( $context, $options );
		}

		self::save_email_reports( $source, $events );

		return true;
	}

	/**
	 * Map Companion Auto Update rows to UpdateBee option keys.
	 *
	 * @param array<string, string> $source Setting name to stored value.
	 * @return array<string, mixed> Mapped UpdateBee options.
	 */
	public static function map_options( array $source ): array {
		$plugins_on      = self::is_on( $source['plugins'] ?? '' );
		$themes_on       = self::is_on( $source['themes'] ?? '' );
		$translations_on = self::is_on( $source['translations'] ?? '' );
		$plugin_flags    = self::map_auto_flags( $plugins_on );
		$theme_flags     = self::map_auto_flags( $themes_on );

		$mapped = array(
			'major_auto_updates'               => self::is_on( $source['major'] ?? '' ) ? 'on' : 'off',
			'minor_auto_updates'               => self::is_on( $source['minor'] ?? '' ) ? 'on' : 'off',
			'plugin_auto_updates'              => $plugin_flags['auto'],
			'plugin_auto_updates_custom'       => $plugin_flags['custom'],
			'plugin_manual_updates_only'       => $plugin_flags['manual'],
			'plugin_auto_updates_patches_only' => $plugin_flags['patches'],
			'plugin_updates_disabled'          => $plugin_flags['disabled'],
			'theme_auto_updates'               => $theme_flags['auto'],
			'theme_auto_updates_custom'        => $theme_flags['custom'],
			'theme_manual_updates_only'        => $theme_flags['manual'],
			'theme_auto_updates_patches_only'  => $theme_flags['patches'],
			'theme_updates_disabled'           => $theme_flags['disabled'],
			'translation_auto_updates'         => $translations_on ? 'on' : 'off',
			'translation_auto_updates_custom'  => 'off',
			'translation_manual_updates_only'  => $translations_on ? 'off' : 'on',
			'translation_updates_disabled'     => 'off',
			'auto_update_plugins'              => array(),
			'auto_update_plugins_patches'      => array(),
			'blocked_plugins'                  => self::collect_blocked_plugins( self::split_list( $source['notUpdateList'] ?? '' ) ),
			'auto_update_themes'               => array(),
			'auto_update_themes_patches'       => array(),
			'blocked_themes'                   => self::collect_blocked_themes( self::split_list( $source['notUpdateListTh'] ?? '' ) ),
			'auto_update_translations'         => array(),
			'blocked_translations'             => array(),
			'enable_core_emails'               => self::is_on( $source['wpemails'] ?? '' ),
			'delay_updates'                    => self::map_delay_days( $source ),
		);

		/**
		 * Filter the mapped options before they are saved.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed>  $mapped Mapped UpdateBee options.
		 * @param array<string, string> $source Companion Auto Update rows.
		 */
		return (array) apply_filters( 'updatebee_migrate_cau_mapped_options', $mapped, $source );
	}

	/**
	 * Map live WordPress cron events to per-context UpdateBee schedules.
	 *
	 * Contexts with a missing or unknown recurrence are omitted so the existing schedule stays.
	 *
	 * @param array<string, array<string, mixed>> $events Events keyed by cron hook.
	 * @return array<string, array<string, mixed>> Context to schedule options.
	 */
	public static function map_cron_options( array $events ): array {
		$hooks  = array(
			'wp_update_plugins' => Updates_Cron::CONTEXT_PLUGINS,
			'wp_update_themes'  => Updates_Cron::CONTEXT_THEMES,
			'wp_version_check'  => Updates_Cron::CONTEXT_CORE,
		);
		$mapped = array();

		foreach ( $hooks as $hook => $context ) {
			if ( ! isset( $events[ $hook ] ) || ! is_array( $events[ $hook ] ) ) {
				continue;
			}

			$options = self::map_single_schedule( $events[ $hook ] );
			if ( null === $options ) {
				continue;
			}

			$mapped[ $context ] = $options;
		}

		return $mapped;
	}

	/**
	 * Map the "something has been updated" email onto success reports.
	 *
	 * @param array<string, string>               $source Setting rows.
	 * @param array<string, array<string, mixed>> $events Cron events keyed by hook.
	 * @return array<string, mixed>|null Options, or null when the report is off.
	 */
	public static function map_success_email_options( array $source, array $events = array() ): ?array {
		if ( ! self::is_on( $source['sendupdate'] ?? '' ) ) {
			return null;
		}

		$options                         = Email_Successes::get_defaults();
		$options['send_schedule']        = 'custom';
		$options['include_plugins']      = true;
		$options['include_themes']       = true;
		$options['include_core']         = false;
		$options['include_translations'] = false;
		$options['to_field']             = self::map_email_recipients( $source['email'] ?? '' );

		return self::apply_mail_schedule( $options, $events );
	}

	/**
	 * Map the "an update is available" email onto available-update reports.
	 *
	 * @param array<string, string>               $source Setting rows.
	 * @param array<string, array<string, mixed>> $events Cron events keyed by hook.
	 * @return array<string, mixed>|null Options, or null when the report is off.
	 */
	public static function map_available_email_options( array $source, array $events = array() ): ?array {
		if ( ! self::is_on( $source['send'] ?? '' ) ) {
			return null;
		}

		$options                         = Email_Updates_Available::get_defaults();
		$options['send_schedule']        = 'custom';
		$options['include_plugins']      = true;
		$options['include_themes']       = true;
		$options['include_core']         = false;
		$options['include_translations'] = false;
		$options['to_field']             = self::map_email_recipients( $source['email'] ?? '' );

		return self::apply_mail_schedule( $options, $events );
	}

	/**
	 * Read the cron hooks this importer can map.
	 *
	 * @return array<string, array<string, mixed>> Events keyed by hook.
	 */
	public static function get_cron_events(): array {
		$hooks  = array(
			'wp_update_plugins',
			'wp_update_themes',
			'wp_version_check',
			self::MAIL_HOOK,
		);
		$events = array();

		foreach ( $hooks as $hook ) {
			$recurrence = wp_get_schedule( $hook );
			if ( ! is_string( $recurrence ) || '' === $recurrence ) {
				continue;
			}

			$timestamp       = wp_next_scheduled( $hook );
			$events[ $hook ] = array(
				'recurrence' => $recurrence,
				'timestamp'  => is_numeric( $timestamp ) ? (int) $timestamp : 0,
			);
		}

		return $events;
	}

	/**
	 * Whether the Companion Auto Update plugin is active.
	 *
	 * @return bool
	 */
	private static function is_plugin_active(): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			$plugin_api = ABSPATH . 'wp-admin/includes/plugin.php';
			if ( ! is_readable( $plugin_api ) ) {
				return false;
			}
			require_once $plugin_api;
		}

		return is_plugin_active( self::PLUGIN_FILE );
	}

	/**
	 * Whether the settings table exists on the current site.
	 *
	 * @param string $table Full table name.
	 * @return bool
	 */
	private static function table_exists( string $table ): bool {
		global $wpdb;

		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_var' ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return false;
		}

		$like = method_exists( $wpdb, 'esc_like' ) ? $wpdb->esc_like( $table ) : $table;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Existence check for a third-party table.
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );

		return $table === $found;
	}

	/**
	 * Map an on/off auto-update toggle to UpdateBee mode flags.
	 *
	 * Off keeps manual updates available. Companion Auto Update does not disable the updates screen.
	 *
	 * @param bool $enabled Whether automatic updates are on.
	 * @return array<string, string>
	 */
	private static function map_auto_flags( bool $enabled ): array {
		return array(
			'auto'     => $enabled ? 'on' : 'off',
			'custom'   => 'off',
			'manual'   => $enabled ? 'off' : 'on',
			'patches'  => 'off',
			'disabled' => 'off',
		);
	}

	/**
	 * Map the delay toggle and day count.
	 *
	 * An enabled delay with an empty day count uses Companion Auto Update's runtime default of 2.
	 *
	 * @param array<string, string> $source Setting rows.
	 * @return int
	 */
	private static function map_delay_days( array $source ): int {
		if ( ! self::is_on( $source['update_delay'] ?? '' ) ) {
			return 0;
		}

		$raw = trim( (string) ( $source['update_delay_days'] ?? '' ) );
		if ( '' === $raw || ! is_numeric( $raw ) ) {
			return 2;
		}

		return min( 31, absint( $raw ) );
	}

	/**
	 * Resolve denylist tokens to UpdateBee blocked plugin ids.
	 *
	 * @param array<int, string> $tokens First path segments stored by Companion Auto Update.
	 * @return array<int, string>
	 */
	private static function collect_blocked_plugins( array $tokens ): array {
		$plugins = Functions::get_installed_plugin_files();
		$blocked = array();

		foreach ( $tokens as $token ) {
			$file = self::resolve_plugin_file( $token, $plugins );
			if ( null === $file ) {
				continue;
			}

			$block_id = sanitize_title( dirname( $file ) );
			if ( '' === $block_id || '.' === $block_id ) {
				continue;
			}

			$blocked[] = $block_id;
		}

		return array_values( array_unique( $blocked ) );
	}

	/**
	 * Find an installed plugin file for a Companion Auto Update slug token.
	 *
	 * @param string               $token   Stored slug or single-file name.
	 * @param array<string, mixed> $plugins Installed plugins keyed by file.
	 * @return string|null Plugin file, or null when it is not installed.
	 */
	private static function resolve_plugin_file( string $token, array $plugins ): ?string {
		$token_slug = sanitize_title( $token );

		foreach ( $plugins as $file => $data ) {
			unset( $data );
			$file   = (string) $file;
			$folder = dirname( $file );

			if ( '.' === $folder ) {
				if ( $token === $file || sanitize_title( $file ) === $token_slug ) {
					return $file;
				}
				continue;
			}

			if ( $token === $folder || sanitize_title( $folder ) === $token_slug ) {
				return $file;
			}
		}

		return null;
	}

	/**
	 * Sanitize theme stylesheet slugs from the denylist.
	 *
	 * @param array<int, string> $tokens Stored theme slugs.
	 * @return array<int, string>
	 */
	private static function collect_blocked_themes( array $tokens ): array {
		$slugs = array();

		foreach ( $tokens as $token ) {
			$slug = sanitize_title( $token );
			if ( '' !== $slug ) {
				$slugs[] = $slug;
			}
		}

		return array_values( array_unique( $slugs ) );
	}

	/**
	 * Split a comma-separated Companion Auto Update list.
	 *
	 * @param string $value Stored list.
	 * @return array<int, string>
	 */
	private static function split_list( string $value ): array {
		$parts = preg_split( '/\s*,\s*/', $value );
		if ( ! is_array( $parts ) ) {
			return array();
		}

		$items = array();
		foreach ( $parts as $part ) {
			$part = sanitize_text_field( trim( (string) $part ) );
			if ( '' !== $part ) {
				$items[] = $part;
			}
		}

		return array_values( array_unique( $items ) );
	}

	/**
	 * Map one cron event onto UpdateBee schedule options.
	 *
	 * @param array<string, mixed> $event Recurrence and timestamp.
	 * @return array<string, mixed>|null Null when the recurrence is unknown.
	 */
	private static function map_single_schedule( array $event ): ?array {
		$recurrence = strtolower( trim( (string) ( $event['recurrence'] ?? '' ) ) );
		if ( ! isset( self::SCHEDULE_MAP[ $recurrence ] ) ) {
			return null;
		}

		$schedule            = self::SCHEDULE_MAP[ $recurrence ];
		$options             = Updates_Cron::get_cron_defaults();
		$options['schedule'] = $schedule;

		if ( 'daily' === $schedule ) {
			$timestamp = absint( $event['timestamp'] ?? 0 );
			if ( $timestamp > 0 ) {
				$options['start_time'] = self::format_local_time( $timestamp );
			}
		}

		return Updates_Cron::normalize_days_list_defaults( $schedule, $options );
	}

	/**
	 * Copy the mail-hook schedule onto an email report.
	 *
	 * @param array<string, mixed>                $options Email options.
	 * @param array<string, array<string, mixed>> $events  Cron events keyed by hook.
	 * @return array<string, mixed>
	 */
	private static function apply_mail_schedule( array $options, array $events ): array {
		$event  = isset( $events[ self::MAIL_HOOK ] ) && is_array( $events[ self::MAIL_HOOK ] ) ? $events[ self::MAIL_HOOK ] : array();
		$mapped = self::map_single_schedule( $event );
		if ( null === $mapped ) {
			$mapped = Updates_Cron::get_cron_defaults();
		}

		foreach ( array( 'schedule', 'day', 'week', 'start_time', 'day_num', 'days_list' ) as $key ) {
			if ( array_key_exists( $key, $mapped ) ) {
				$options[ $key ] = $mapped[ $key ];
			}
		}

		return $options;
	}

	/**
	 * Site-local hour and minute for a unix timestamp.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return string Time in HH:MM.
	 */
	private static function format_local_time( int $timestamp ): string {
		$formatted = wp_date( 'H:i', $timestamp );
		if ( ! is_string( $formatted ) || ! preg_match( '/^\d{2}:\d{2}$/', $formatted ) ) {
			return '00:00';
		}

		return $formatted;
	}

	/**
	 * Sanitize a comma-separated recipient list.
	 *
	 * An empty stored address falls back to the site admin email, matching Companion Auto Update.
	 *
	 * @param string $raw Stored addresses.
	 * @return array<int, string>
	 */
	private static function map_email_recipients( string $raw ): array {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			$raw = (string) get_option( 'admin_email' );
		}

		$parts = preg_split( '/\s*,\s*/', $raw );
		if ( ! is_array( $parts ) ) {
			return array();
		}

		$emails = array();
		foreach ( $parts as $part ) {
			$email = sanitize_email( (string) $part );
			if ( is_email( $email ) ) {
				$emails[] = $email;
			}
		}

		return array_values( array_unique( $emails ) );
	}

	/**
	 * Persist mapped email report options and schedule them.
	 *
	 * @param array<string, string>               $source Setting rows.
	 * @param array<string, array<string, mixed>> $events Cron events keyed by hook.
	 */
	private static function save_email_reports( array $source, array $events ): void {
		$success = self::map_success_email_options( $source, $events );
		if ( is_array( $success ) ) {
			update_site_option( Email_Successes::OPTIONS_KEY, $success );
			wp_clear_scheduled_hook( Email_Successes::CRON_HOOK );
			Schedules::set_cron_events( $success, Email_Successes::CRON_HOOK );
		}

		$available = self::map_available_email_options( $source, $events );
		if ( is_array( $available ) ) {
			update_site_option( Email_Updates_Available::OPTIONS_KEY, $available );
			wp_clear_scheduled_hook( Email_Updates_Available::CRON_HOOK );
			Schedules::set_cron_events( $available, Email_Updates_Available::CRON_HOOK );
		}
	}

	/**
	 * Whether a stored value means on.
	 *
	 * Companion Auto Update writes the string "on" for checked settings and an empty string otherwise.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	private static function is_on( $value ): bool {
		return 'on' === strtolower( trim( (string) $value ) );
	}
}
