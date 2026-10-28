<?php
/**
 * Migrate settings from Easy Updates Manager to UpdateBee.
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
use UpdateBee\Updates\Delay_Updates;

/**
 * Class EasyUpdatesManager.
 */
class EasyUpdatesManager {

	/**
	 * Known EUM option keys.
	 *
	 * @var array<int, string>
	 */
	private const OPTION_KEYS = array(
		'MPSUM',
	);

	/**
	 * Known EUM plugin file paths.
	 *
	 * @var array<int, string>
	 */
	private const EUM_PLUGIN_FILES = array(
		'stops-core-theme-and-plugin-updates-premium/main.php',
		'stops-core-theme-and-plugin-updates/main.php',
	);

	/**
	 * EUM weekday numbers (Sunday = 1) to UpdateBee day slugs.
	 *
	 * @var array<int, string>
	 */
	private const WEEKDAY_SLUGS = array(
		1 => 'sunday',
		2 => 'monday',
		3 => 'tuesday',
		4 => 'wednesday',
		5 => 'thursday',
		6 => 'friday',
		7 => 'saturday',
	);

	/**
	 * EUM cron slugs to UpdateBee schedule slugs.
	 *
	 * @var array<string, string>
	 */
	private const CRON_SCHEDULE_MAP = array(
		'every_3_hours' => 'three-hours',
		'every_6_hours' => 'six-hours',
		'twicedaily'    => 'twelve-hours',
		'daily'         => 'daily',
		'weekly'        => 'weekly',
		'fortnightly'   => 'two-weeks',
		'monthly'       => 'monthly',
	);

	/**
	 * Whether Easy Updates Manager is detected (active or options present).
	 *
	 * @return bool
	 */
	public static function is_detected(): bool {
		if ( ! empty( self::get_source_options() ) ) {
			return true;
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( self::EUM_PLUGIN_FILES as $file ) {
			if ( is_plugin_active( $file ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Retrieve the raw EUM options array.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_source_options(): array {
		foreach ( self::OPTION_KEYS as $key ) {
			$options = get_site_option( $key );
			if ( is_array( $options ) && ! empty( $options ) ) {
				return $options;
			}

			$options = get_option( $key );
			if ( is_array( $options ) && ! empty( $options ) ) {
				return $options;
			}
		}

		return array();
	}

	/**
	 * Run the migration.
	 *
	 * @return bool True when a non-empty MPSUM source was mapped.
	 */
	public static function run(): bool {
		$source = self::get_source_options();
		if ( empty( $source ) ) {
			return false;
		}

		$source = self::normalize_source( $source );
		$mapped = self::map_options( $source );

		Options::update_options( '', $mapped );
		Options::flush_cache();

		$cron = self::map_cron_options( $source );
		if ( is_array( $cron ) ) {
			self::save_cron_to_all_contexts( $cron );
		}

		$delay = isset( $mapped['delay_updates'] ) ? absint( $mapped['delay_updates'] ) : 0;
		if ( $delay > 0 ) {
			self::save_delay_stamps( $source );
		}

		self::save_email_reports( $source, $mapped );

		return true;
	}

	/**
	 * Map EUM options to UpdateBee option keys.
	 *
	 * @param array<string, mixed> $source Raw or normalized EUM options.
	 * @return array<string, mixed> Mapped UpdateBee options.
	 */
	public static function map_options( array $source ): array {
		$source = self::normalize_source( $source );
		$core   = isset( $source['core'] ) && is_array( $source['core'] ) ? $source['core'] : array();

		$all_updates_off = 'off' === self::string_value( $core['all_updates'] ?? 'on' );

		$plugin_mode      = $all_updates_off ? 'off' : self::string_value( $core['plugin_updates'] ?? 'on' );
		$theme_mode       = $all_updates_off ? 'off' : self::string_value( $core['theme_updates'] ?? 'on' );
		$translation_mode = $all_updates_off ? 'off' : self::string_value( $core['translation_updates'] ?? 'on' );
		$core_mode        = $all_updates_off ? 'off' : self::string_value( $core['core_updates'] ?? 'on' );
		$dev_updates_on   = self::is_on( $core['automatic_development_updates'] ?? 'off' );

		$plugin_flags      = self::map_asset_mode( $plugin_mode );
		$theme_flags       = self::map_asset_mode( $theme_mode );
		$translation_flags = self::map_translation_mode( $translation_mode );
		$core_flags        = self::map_core_mode( $core_mode, $dev_updates_on );

		$auto_plugins     = self::collect_plugin_files( $source['plugins_automatic'] ?? array() );
		$semantic_plugins = self::collect_plugin_files( $source['plugins_semantic'] ?? array() );
		$blocked_plugins  = self::collect_blocked_plugin_slugs( $source['plugins'] ?? array() );

		if ( 'individual' === $plugin_mode && ! empty( $semantic_plugins ) ) {
			$auto_plugins = array_values( array_unique( array_merge( $auto_plugins, $semantic_plugins ) ) );
		}

		$auto_themes     = self::collect_theme_slugs( $source['themes_automatic'] ?? array() );
		$semantic_themes = self::collect_theme_slugs( $source['themes_semantic'] ?? array() );
		$blocked_themes  = self::collect_theme_slugs( $source['themes'] ?? array() );

		if ( 'individual' === $theme_mode && ! empty( $semantic_themes ) ) {
			$auto_themes = array_values( array_unique( array_merge( $auto_themes, $semantic_themes ) ) );
		}

		$mapped = array(
			'major_auto_updates'               => $core_flags['major'],
			'minor_auto_updates'               => $core_flags['minor'],
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
			'translation_auto_updates'         => $translation_flags['auto'],
			'translation_auto_updates_custom'  => 'off',
			'translation_manual_updates_only'  => $translation_flags['manual'],
			'translation_updates_disabled'     => $translation_flags['disabled'],
			'auto_update_plugins'              => $auto_plugins,
			'auto_update_plugins_patches'      => $semantic_plugins,
			'blocked_plugins'                  => $blocked_plugins,
			'auto_update_themes'               => $auto_themes,
			'auto_update_themes_patches'       => $semantic_themes,
			'blocked_themes'                   => $blocked_themes,
			'auto_update_translations'         => array(),
			'blocked_translations'             => array(),
			'enable_core_emails'               => self::is_on( $core['notification_core_update_emails'] ?? 'on' ),
			'core_email_recipients'            => self::map_email_recipients( $core['email_addresses'] ?? array() ),
			'clear_logs_days'                  => self::map_clear_logs_days( $source ),
			'delay_updates'                    => self::map_delay_days( $source ),
		);

		$slack  = self::map_slack_settings( $source );
		$mapped = array_merge( $mapped, $slack );

		/**
		 * Filter the mapped options before they are saved.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $mapped Mapped UpdateBee options.
		 * @param array<string, mixed> $source Normalized EUM options.
		 */
		return (array) apply_filters( 'updatebee_migrate_eum_mapped_options', $mapped, $source );
	}

	/**
	 * Map EUM advanced cron keys to one UpdateBee schedule array.
	 *
	 * @param array<string, mixed> $source Raw or normalized EUM options.
	 * @return array<string, mixed>|null Schedule options, or null when advanced cron is absent.
	 */
	public static function map_cron_options( array $source ): ?array {
		$source   = self::normalize_source( $source );
		$advanced = isset( $source['advanced'] ) && is_array( $source['advanced'] ) ? $source['advanced'] : array();

		$cron_keys = array( 'cron_schedule', 'cron_time', 'cron_week_day', 'cron_week', 'cron_day_number', 'cron_days_list' );
		$has_cron  = false;
		foreach ( $cron_keys as $key ) {
			if ( isset( $advanced[ $key ] ) ) {
				$has_cron = true;
				break;
			}
		}

		if ( ! $has_cron ) {
			return null;
		}

		$eum_schedule = self::string_value( $advanced['cron_schedule'] ?? 'twicedaily' );
		$schedule     = isset( self::CRON_SCHEDULE_MAP[ $eum_schedule ] ) ? self::CRON_SCHEDULE_MAP[ $eum_schedule ] : 'twelve-hours';

		$options = array(
			'schedule'   => $schedule,
			'day'        => self::map_week_day( $advanced['cron_week_day'] ?? 1 ),
			'week'       => self::map_week_number( $advanced['cron_week'] ?? '1' ),
			'start_time' => self::normalize_time( (string) ( $advanced['cron_time'] ?? '00:00' ) ),
			'day_num'    => (string) max( 1, min( 28, absint( $advanced['cron_day_number'] ?? 1 ) ) ),
			'days_list'  => self::map_days_list( $advanced['cron_days_list'] ?? array() ),
		);

		return Updates_Cron::normalize_days_list_defaults( $schedule, $options );
	}

	/**
	 * Map EUM delay stamps to UpdateBee stamp option payloads.
	 *
	 * @param array<string, mixed> $source Raw or normalized EUM options.
	 * @return array<string, array<string, mixed>> Stamps keyed by UpdateBee option name.
	 */
	public static function map_delay_stamps( array $source ): array {
		$source = self::normalize_source( $source );

		return array(
			Delay_Updates::PLUGIN_OPTION => self::normalize_stamps( $source['plugin_updates'] ?? array() ),
			Delay_Updates::THEME_OPTION  => self::normalize_stamps( $source['theme_updates'] ?? array() ),
		);
	}

	/**
	 * Map EUM auto-update notification emails to Email_Successes options.
	 *
	 * @param array<string, mixed> $source Raw or normalized EUM options.
	 * @return array<string, mixed>|null Options or null when no EUM notifications are on.
	 */
	public static function map_success_email_options( array $source ): ?array {
		$source = self::normalize_source( $source );
		$core   = isset( $source['core'] ) && is_array( $source['core'] ) ? $source['core'] : array();

		$include_plugins      = self::is_on( $core['plugin_auto_updates_notification_emails'] ?? 'off' );
		$include_themes       = self::is_on( $core['theme_auto_updates_notification_emails'] ?? 'off' );
		$include_translations = self::is_on( $core['translation_auto_updates_notification_emails'] ?? 'off' );

		if ( ! $include_plugins && ! $include_themes && ! $include_translations ) {
			return null;
		}

		$options                         = Email_Successes::get_defaults();
		$options['send_schedule']        = 'immediately';
		$options['include_plugins']      = $include_plugins;
		$options['include_themes']       = $include_themes;
		$options['include_translations'] = $include_translations;
		$options['include_core']         = false;
		$options['to_field']             = self::map_email_recipients( $core['email_addresses'] ?? array() );

		return $options;
	}

	/**
	 * Map EUM available-update digest to Email_Updates_Available options.
	 *
	 * @param array<string, mixed> $source Raw or normalized EUM options.
	 * @return array<string, mixed>|null Options or null when the digest is off.
	 */
	public static function map_available_email_options( array $source ): ?array {
		$source  = self::normalize_source( $source );
		$core    = isset( $source['core'] ) && is_array( $source['core'] ) ? $source['core'] : array();
		$cadence = self::string_value( $core['update_notification_updates'] ?? 'off' );

		if ( ! in_array( $cadence, array( 'weekly', 'monthly' ), true ) ) {
			return null;
		}

		$options                  = Email_Updates_Available::get_defaults();
		$options['send_schedule'] = 'custom';
		$options['schedule']      = $cadence;
		$options['to_field']      = self::map_email_recipients( $core['email_addresses'] ?? array() );

		return $options;
	}

	/**
	 * Normalize legacy EUM keys into the current MPSUM shape.
	 *
	 * @param array<string, mixed> $source Raw EUM options.
	 * @return array<string, mixed>
	 */
	private static function normalize_source( array $source ): array {
		if ( ! isset( $source['core'] ) || ! is_array( $source['core'] ) ) {
			$source['core'] = array();
		}

		$core = $source['core'];

		if ( isset( $core['automatic_major_updates'] ) && isset( $core['automatic_minor_updates'] ) ) {
			if ( self::is_on( $core['automatic_major_updates'] ) && self::is_on( $core['automatic_minor_updates'] ) ) {
				$core['core_updates'] = 'automatic';
			} elseif ( self::is_on( $core['automatic_minor_updates'] ) ) {
				$core['core_updates'] = 'automatic_minor';
			} elseif ( self::is_on( $core['automatic_major_updates'] ) ) {
				$core['core_updates'] = 'automatic';
			} else {
				$core['core_updates'] = 'on';
			}
			unset( $core['automatic_major_updates'], $core['automatic_minor_updates'] );
		}

		if ( isset( $core['automatic_plugin_updates'] ) ) {
			$core['plugin_updates'] = self::map_legacy_automatic_asset( $core['automatic_plugin_updates'] );
			unset( $core['automatic_plugin_updates'] );
		}

		if ( isset( $core['automatic_theme_updates'] ) ) {
			$core['theme_updates'] = self::map_legacy_automatic_asset( $core['automatic_theme_updates'] );
			unset( $core['automatic_theme_updates'] );
		}

		if ( isset( $core['automatic_translation_updates'] ) ) {
			if ( self::is_on( $core['automatic_translation_updates'] ) ) {
				$core['translation_updates'] = 'automatic';
			} else {
				$core['translation_updates'] = 'on';
			}
			unset( $core['automatic_translation_updates'] );
		}

		if ( isset( $core['core_updates'] ) ) {
			$core_updates = self::string_value( $core['core_updates'] );
			if ( 'automatic_off' === $core_updates || 'disable_auto_updates_migrated_from_9_0_9' === $core_updates ) {
				$core['core_updates'] = 'on';
			} elseif ( 'manually_update_migrated_from_9_0_9' === $core_updates ) {
				$core['core_updates'] = 'automatic_minor';
			}
		}

		if ( isset( $core['translation_updates'] ) && 'automatic_off' === self::string_value( $core['translation_updates'] ) ) {
			$core['translation_updates'] = 'on';
		}

		$source['core'] = $core;

		foreach ( array( 'advanced', 'logs', 'plugins', 'themes', 'plugins_automatic', 'themes_automatic', 'plugins_semantic', 'themes_semantic', 'plugin_updates', 'theme_updates' ) as $key ) {
			if ( ! isset( $source[ $key ] ) || ! is_array( $source[ $key ] ) ) {
				$source[ $key ] = array();
			}
		}

		return $source;
	}

	/**
	 * Map a legacy automatic_* asset value to a current EUM mode.
	 *
	 * @param mixed $value Legacy value.
	 * @return string
	 */
	private static function map_legacy_automatic_asset( $value ): string {
		$value = self::string_value( $value );
		if ( 'on' === $value ) {
			return 'automatic';
		}
		if ( 'custom' === $value || 'individual' === $value ) {
			return 'individual';
		}

		return 'automatic_off';
	}

	/**
	 * Map an EUM plugin/theme mode to UpdateBee flags.
	 *
	 * @param string $mode EUM mode.
	 * @return array<string, string>
	 */
	private static function map_asset_mode( string $mode ): array {
		$flags = array(
			'auto'     => 'off',
			'custom'   => 'off',
			'manual'   => 'off',
			'patches'  => 'off',
			'disabled' => 'off',
		);

		switch ( $mode ) {
			case 'individual':
				$flags['auto']   = 'on';
				$flags['custom'] = 'on';
				break;
			case 'automatic':
				$flags['auto'] = 'on';
				break;
			case 'automatic_patch_releases':
				$flags['auto']    = 'on';
				$flags['patches'] = 'on';
				break;
			case 'off':
				$flags['disabled'] = 'on';
				break;
			case 'on':
			case 'automatic_off':
			default:
				$flags['manual'] = 'on';
				break;
		}

		return $flags;
	}

	/**
	 * Map an EUM translation mode to UpdateBee flags.
	 *
	 * @param string $mode EUM mode.
	 * @return array<string, string>
	 */
	private static function map_translation_mode( string $mode ): array {
		$flags = array(
			'auto'     => 'off',
			'manual'   => 'off',
			'disabled' => 'off',
		);

		switch ( $mode ) {
			case 'automatic':
				$flags['auto'] = 'on';
				break;
			case 'off':
				$flags['disabled'] = 'on';
				break;
			case 'on':
			default:
				$flags['manual'] = 'on';
				break;
		}

		return $flags;
	}

	/**
	 * Map EUM core update mode to major/minor auto flags.
	 *
	 * @param string $mode           EUM core_updates value.
	 * @param bool   $dev_updates_on Whether development auto-updates are on.
	 * @return array<string, string>
	 */
	private static function map_core_mode( string $mode, bool $dev_updates_on ): array {
		$flags = array(
			'major' => 'off',
			'minor' => 'off',
		);

		if ( 'automatic' === $mode ) {
			$flags['major'] = 'on';
			$flags['minor'] = 'on';
		} elseif ( 'automatic_minor' === $mode ) {
			$flags['minor'] = 'on';
		}

		if ( $dev_updates_on ) {
			$flags['major'] = 'on';
		}

		return $flags;
	}

	/**
	 * Collect plugin file paths from an EUM numeric list.
	 *
	 * @param mixed $items EUM list.
	 * @return array<int, string>
	 */
	private static function collect_plugin_files( $items ): array {
		$files = array();

		foreach ( self::collect_list_values( $items ) as $identifier ) {
			if ( str_contains( $identifier, '.php' ) || str_contains( $identifier, '/' ) ) {
				$files[] = $identifier;
				continue;
			}

			$resolved = self::resolve_plugin_file( $identifier );
			if ( null !== $resolved ) {
				$files[] = $resolved;
			}
		}

		return array_values( array_unique( $files ) );
	}

	/**
	 * Collect blocked plugin slugs from EUM plugin file paths.
	 *
	 * @param mixed $items EUM list.
	 * @return array<int, string>
	 */
	private static function collect_blocked_plugin_slugs( $items ): array {
		$slugs = array();

		foreach ( self::collect_list_values( $items ) as $identifier ) {
			if ( str_contains( $identifier, '/' ) || str_contains( $identifier, '.php' ) ) {
				$slug = sanitize_title( dirname( $identifier ) );
			} else {
				$slug = sanitize_title( $identifier );
			}

			if ( '' !== $slug && '.' !== $slug ) {
				$slugs[] = $slug;
			}
		}

		return array_values( array_unique( $slugs ) );
	}

	/**
	 * Collect theme stylesheet slugs from an EUM numeric list.
	 *
	 * @param mixed $items EUM list.
	 * @return array<int, string>
	 */
	private static function collect_theme_slugs( $items ): array {
		$slugs = array();

		foreach ( self::collect_list_values( $items ) as $identifier ) {
			$slug = sanitize_title( $identifier );
			if ( '' !== $slug ) {
				$slugs[] = $slug;
			}
		}

		return array_values( array_unique( $slugs ) );
	}

	/**
	 * Read identifiers from a numeric EUM list.
	 *
	 * @param mixed $items EUM list.
	 * @return array<int, string>
	 */
	private static function collect_list_values( $items ): array {
		if ( ! is_array( $items ) ) {
			return array();
		}

		$values = array();

		foreach ( $items as $key => $value ) {
			if ( is_int( $key ) || ( is_string( $key ) && is_numeric( $key ) ) ) {
				$identifier = $value;
			} elseif ( is_string( $value ) && ! self::is_toggle_value( $value ) ) {
				$identifier = $value;
			} else {
				$identifier = $key;
			}

			if ( ! is_scalar( $identifier ) ) {
				continue;
			}

			$identifier = sanitize_text_field( (string) $identifier );
			if ( '' === $identifier ) {
				continue;
			}

			$values[] = $identifier;
		}

		return array_values( array_unique( $values ) );
	}

	/**
	 * Whether a string is an on/off style toggle rather than an identifier.
	 *
	 * @param string $value Raw value.
	 * @return bool
	 */
	private static function is_toggle_value( string $value ): bool {
		return in_array( strtolower( $value ), array( 'on', 'off', '1', '0', 'true', 'false' ), true );
	}

	/**
	 * Try to find a plugin file path from a slug.
	 *
	 * @param string $slug Plugin slug.
	 * @return string|null File path if found.
	 */
	private static function resolve_plugin_file( string $slug ): ?string {
		$plugins = Functions::get_installed_plugin_files();

		foreach ( $plugins as $file => $data ) {
			unset( $data );
			$plugin_slug = sanitize_title( dirname( (string) $file ) );
			if ( sanitize_title( $slug ) === $plugin_slug || dirname( (string) $file ) === $slug ) {
				return (string) $file;
			}
		}

		return null;
	}

	/**
	 * Map EUM delay days.
	 *
	 * @param array<string, mixed> $source Normalized EUM options.
	 * @return int
	 */
	private static function map_delay_days( array $source ): int {
		$advanced = isset( $source['advanced'] ) && is_array( $source['advanced'] ) ? $source['advanced'] : array();
		if ( isset( $advanced['delay_updates'] ) && is_numeric( $advanced['delay_updates'] ) ) {
			return absint( $advanced['delay_updates'] );
		}

		return 0;
	}

	/**
	 * Map EUM log retention days.
	 *
	 * @param array<string, mixed> $source Normalized EUM options.
	 * @return int
	 */
	private static function map_clear_logs_days( array $source ): int {
		$logs = isset( $source['logs'] ) && is_array( $source['logs'] ) ? $source['logs'] : array();
		if ( isset( $logs['clear_logs'] ) && is_numeric( $logs['clear_logs'] ) ) {
			return absint( $logs['clear_logs'] );
		}

		return 0;
	}

	/**
	 * Map Slack logger settings.
	 *
	 * @param array<string, mixed> $source Normalized EUM options.
	 * @return array<string, mixed>
	 */
	private static function map_slack_settings( array $source ): array {
		$logs    = isset( $source['logs'] ) && is_array( $source['logs'] ) ? $source['logs'] : array();
		$types   = isset( $logs['eum_logger_type'] ) && is_array( $logs['eum_logger_type'] ) ? $logs['eum_logger_type'] : array();
		$extras  = isset( $logs['logger_additional_options'] ) && is_array( $logs['logger_additional_options'] ) ? $logs['logger_additional_options'] : array();
		$enabled = false;

		foreach ( $types as $logger_id => $value ) {
			if ( is_string( $logger_id ) && str_contains( strtolower( $logger_id ), 'slack' ) && self::is_on( $value ) ) {
				$enabled = true;
				break;
			}
		}

		$webhook = '';
		if ( isset( $extras['slack_webhook_url'] ) && is_string( $extras['slack_webhook_url'] ) ) {
			$webhook = esc_url_raw( $extras['slack_webhook_url'] );
		}

		return array(
			'slack_enabled'     => $enabled && '' !== $webhook,
			'slack_webhook_url' => $webhook,
		);
	}

	/**
	 * Sanitize a list of email addresses.
	 *
	 * @param mixed $addresses Raw addresses.
	 * @return array<int, string>
	 */
	private static function map_email_recipients( $addresses ): array {
		if ( ! is_array( $addresses ) ) {
			return array();
		}

		$emails = array();
		foreach ( $addresses as $address ) {
			$email = sanitize_email( (string) $address );
			if ( is_email( $email ) ) {
				$emails[] = $email;
			}
		}

		return array_values( array_unique( $emails ) );
	}

	/**
	 * Normalize delay stamp maps.
	 *
	 * @param mixed $stamps Raw stamp map.
	 * @return array<string, array<string, mixed>>
	 */
	private static function normalize_stamps( $stamps ): array {
		if ( ! is_array( $stamps ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $stamps as $id => $stamp ) {
			if ( ! is_array( $stamp ) ) {
				continue;
			}

			$id      = sanitize_text_field( (string) $id );
			$version = isset( $stamp['version'] ) ? sanitize_text_field( (string) $stamp['version'] ) : '';
			$time    = isset( $stamp['time'] ) ? absint( $stamp['time'] ) : 0;
			if ( '' === $id || '' === $version || $time < 1 ) {
				continue;
			}

			$normalized[ $id ] = array(
				'version' => $version,
				'time'    => $time,
			);
		}

		return $normalized;
	}

	/**
	 * Save the mapped cron schedule to plugin, theme, and core contexts.
	 *
	 * @param array<string, mixed> $options Mapped cron options.
	 */
	private static function save_cron_to_all_contexts( array $options ): void {
		foreach ( array( Updates_Cron::CONTEXT_PLUGINS, Updates_Cron::CONTEXT_THEMES, Updates_Cron::CONTEXT_CORE ) as $context ) {
			Updates_Cron::save_cron_options( $context, $options );
		}
	}

	/**
	 * Persist delay stamps from EUM.
	 *
	 * @param array<string, mixed> $source Normalized EUM options.
	 */
	private static function save_delay_stamps( array $source ): void {
		$stamps = self::map_delay_stamps( $source );

		if ( ! empty( $stamps[ Delay_Updates::PLUGIN_OPTION ] ) ) {
			update_site_option( Delay_Updates::PLUGIN_OPTION, $stamps[ Delay_Updates::PLUGIN_OPTION ] );
		}

		if ( ! empty( $stamps[ Delay_Updates::THEME_OPTION ] ) ) {
			update_site_option( Delay_Updates::THEME_OPTION, $stamps[ Delay_Updates::THEME_OPTION ] );
		}
	}

	/**
	 * Persist mapped email report options.
	 *
	 * @param array<string, mixed> $source Normalized EUM options.
	 * @param array<string, mixed> $mapped Mapped UpdateBee options.
	 */
	private static function save_email_reports( array $source, array $mapped ): void {
		unset( $mapped );

		$success = self::map_success_email_options( $source );
		if ( is_array( $success ) ) {
			update_site_option( Email_Successes::OPTIONS_KEY, $success );
		}

		$available = self::map_available_email_options( $source );
		if ( is_array( $available ) ) {
			update_site_option( Email_Updates_Available::OPTIONS_KEY, $available );
			wp_clear_scheduled_hook( Email_Updates_Available::CRON_HOOK );
			Schedules::set_cron_events( $available, Email_Updates_Available::CRON_HOOK );
		}
	}

	/**
	 * Map an EUM weekday number to an UpdateBee day slug.
	 *
	 * @param mixed $value EUM weekday (1–7, Sunday = 1).
	 * @return string
	 */
	private static function map_week_day( $value ): string {
		$day = absint( $value );
		if ( isset( self::WEEKDAY_SLUGS[ $day ] ) ) {
			return self::WEEKDAY_SLUGS[ $day ];
		}

		return 'sunday';
	}

	/**
	 * Map an EUM week number to UpdateBee's 1/2 string.
	 *
	 * @param mixed $value EUM week value.
	 * @return string
	 */
	private static function map_week_number( $value ): string {
		$value = strtolower( (string) $value );
		if ( in_array( $value, array( '2', '2nd', 'week-two', 'second' ), true ) ) {
			return '2';
		}

		return '1';
	}

	/**
	 * Normalize EUM cron_days_list values to 1–7 integers.
	 *
	 * @param mixed $days Raw days list.
	 * @return array<int, int>
	 */
	private static function map_days_list( $days ): array {
		if ( ! is_array( $days ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $days as $day ) {
			$day_int = absint( $day );
			if ( $day_int >= 1 && $day_int <= 7 ) {
				$normalized[] = $day_int;
			}
		}

		return array_values( array_unique( $normalized ) );
	}

	/**
	 * Normalize a time string to HH:MM (24-hour).
	 *
	 * @param string $time Raw time value.
	 * @return string Time in HH:MM format.
	 */
	private static function normalize_time( string $time ): string {
		$time = trim( $time );

		if ( preg_match( '/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $time, $matches ) ) {
			$hour   = str_pad( $matches[1], 2, '0', STR_PAD_LEFT );
			$minute = $matches[2];
			return "{$hour}:{$minute}";
		}

		if ( preg_match( '/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i', $time, $matches ) ) {
			$hour   = (int) $matches[1];
			$minute = $matches[2];
			$ampm   = strtoupper( $matches[3] );
			if ( 'PM' === $ampm && 12 !== $hour ) {
				$hour += 12;
			}
			if ( 'AM' === $ampm && 12 === $hour ) {
				$hour = 0;
			}
			$hour = str_pad( (string) $hour, 2, '0', STR_PAD_LEFT );
			return "{$hour}:{$minute}";
		}

		return '00:00';
	}

	/**
	 * Whether an EUM value means on.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	private static function is_on( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_int( $value ) ) {
			return 1 === $value;
		}

		return in_array( strtolower( (string) $value ), array( 'on', '1', 'true' ), true );
	}

	/**
	 * Normalize a scalar option to a lowercase string.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function string_value( $value ): string {
		return strtolower( trim( (string) $value ) );
	}
}
