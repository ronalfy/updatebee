<?php
/**
 * Read-only ability callbacks.
 *
 * @package updatebee
 */

namespace UpdateBee\Abilities;

use UpdateBee\Functions;
use UpdateBee\Options;
use UpdateBee\Slack\Slack_Webhook;
use UpdateBee\Tables\Table_Logs;
use UpdateBee\Tables\Table_Plugins;
use UpdateBee\Tables\Table_Themes;
use UpdateBee\Updates\Fatal_Detection;
use UpdateBee\Updates\Hide_Blocked_Updates;
use UpdateBee\Updates\Init as Updates_Init;

/**
 * Payloads returned by read abilities.
 */
class Read {

	/**
	 * Site-wide update policy, without secrets or branding.
	 *
	 * @return array<string, mixed> Policy flags and slack_configured.
	 */
	public static function get_update_policy(): array {
		$options = Options::get_options();
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		$policy = array();
		foreach ( Policy::writable_on_off_keys() as $key ) {
			$policy[ $key ] = self::on_off_value( $options[ $key ] ?? 'off' );
		}

		$policy['delay_updates']                   = self::int_value( $options['delay_updates'] ?? 0 );
		$policy['upgrade_notice_keywords']         = isset( $options['upgrade_notice_keywords'] ) && is_string( $options['upgrade_notice_keywords'] ) ? $options['upgrade_notice_keywords'] : '';
		$policy['upgrade_notice_keywords_enabled'] = self::bool_value( $options['upgrade_notice_keywords_enabled'] ?? false );
		$policy['fatal_error_detection']           = self::bool_value( $options['fatal_error_detection'] ?? false );
		$policy['enable_core_emails']              = self::bool_value( $options['enable_core_emails'] ?? false );
		$policy['slack_configured']                = Slack_Webhook::is_configured();

		return $policy;
	}

	/**
	 * Installed plugins and their update policy.
	 *
	 * @return array{plugins: array<int, array<string, mixed>>} Plugin rows.
	 */
	public static function list_plugins(): array {
		$installed = Functions::get_installed_plugin_files();
		$auto      = Functions::get_auto_updates_plugins();
		if ( ! is_array( $auto ) ) {
			$auto = array();
		}

		$active = Functions::get_active_plugins();
		if ( ! is_array( $active ) ) {
			$active = array();
		}

		$notes   = Table_Plugins::get_all_notes();
		$delays  = Table_Plugins::get_all_delay_settings();
		$pending = self::transient_response( 'update_plugins' );

		$plugins = array();
		foreach ( $installed as $file => $plugin ) {
			if ( ! is_string( $file ) ) {
				continue;
			}

			if ( ! is_array( $plugin ) ) {
				$plugin = array();
			}

			$settings  = isset( $delays[ $file ] ) && is_array( $delays[ $file ] ) ? $delays[ $file ] : array();
			$plugins[] = array(
				'file'             => $file,
				'name'             => isset( $plugin['Name'] ) ? (string) $plugin['Name'] : $file,
				'version'          => isset( $plugin['Version'] ) ? (string) $plugin['Version'] : '',
				'active'           => in_array( $file, $active, true ),
				'update_available' => isset( $pending[ $file ] ),
				'auto_update'      => isset( $auto[ $file ] ),
				'blocked'          => Functions::auto_updates_blocked( $file, 'plugin' ),
				'patches_only'     => Functions::auto_updates_patches_only( $file, 'plugin' ),
				'delay_days'       => self::delay_days_from_settings( $settings ),
				'allow_untested'   => ! empty( $settings['allow_untested'] ),
				'notes'            => isset( $notes[ $file ] ) ? (string) $notes[ $file ] : '',
			);
		}

		return array(
			'plugins' => $plugins,
		);
	}

	/**
	 * One installed plugin row.
	 *
	 * @param string $file Plugin basename.
	 * @return array<string, mixed>|null Plugin row, or null when it is not installed.
	 */
	public static function plugin_by_file( string $file ): ?array {
		$listed = self::list_plugins();
		foreach ( $listed['plugins'] as $plugin ) {
			if ( isset( $plugin['file'] ) && $plugin['file'] === $file ) {
				return $plugin;
			}
		}

		return null;
	}

	/**
	 * Installed themes and their update policy.
	 *
	 * @return array{themes: array<int, array<string, mixed>>} Theme rows.
	 */
	public static function list_themes(): array {
		$installed = Functions::get_installed_themes();
		$auto      = Functions::get_auto_updates_themes();
		if ( ! is_array( $auto ) ) {
			$auto = array();
		}

		$notes   = Table_Themes::get_all_notes();
		$delays  = Table_Themes::get_all_delay_settings();
		$pending = self::transient_response( 'update_themes' );
		$current = function_exists( 'get_stylesheet' ) ? (string) get_stylesheet() : '';
		$themes  = array();

		foreach ( $installed as $theme ) {
			if ( ! $theme instanceof \WP_Theme ) {
				continue;
			}

			$stylesheet = $theme->get_stylesheet();
			if ( ! is_string( $stylesheet ) || '' === $stylesheet ) {
				continue;
			}

			$settings = isset( $delays[ $stylesheet ] ) && is_array( $delays[ $stylesheet ] ) ? $delays[ $stylesheet ] : array();
			$themes[] = array(
				'stylesheet'       => $stylesheet,
				'name'             => (string) $theme->get( 'Name' ),
				'version'          => (string) $theme->get( 'Version' ),
				'active'           => $stylesheet === $current,
				'update_available' => isset( $pending[ $stylesheet ] ),
				'auto_update'      => isset( $auto[ $stylesheet ] ),
				'blocked'          => Functions::auto_updates_blocked( $stylesheet, 'theme' ),
				'patches_only'     => Functions::auto_updates_patches_only( $stylesheet, 'theme' ),
				'delay_days'       => self::delay_days_from_settings( $settings ),
				'allow_untested'   => ! empty( $settings['allow_untested'] ),
				'notes'            => isset( $notes[ $stylesheet ] ) ? (string) $notes[ $stylesheet ] : '',
			);
		}

		return array(
			'themes' => $themes,
		);
	}

	/**
	 * One installed theme row.
	 *
	 * @param string $stylesheet Theme stylesheet.
	 * @return array<string, mixed>|null Theme row, or null when it is not installed.
	 */
	public static function theme_by_stylesheet( string $stylesheet ): ?array {
		$listed = self::list_themes();
		foreach ( $listed['themes'] as $theme ) {
			if ( isset( $theme['stylesheet'] ) && $theme['stylesheet'] === $stylesheet ) {
				return $theme;
			}
		}

		return null;
	}

	/**
	 * WordPress language catalog and translation update policy.
	 *
	 * Slug is the locale, for example da_DK. Type is core because this catalog is WordPress core languages.
	 *
	 * @return array{translations: array<int, array<string, mixed>>} Translation rows.
	 */
	public static function list_translations(): array {
		$catalog = Functions::get_available_translations();
		$auto    = Functions::get_auto_updates_translations();
		if ( ! is_array( $auto ) ) {
			$auto = array();
		}

		$translations = array();
		foreach ( $catalog as $locale => $translation ) {
			if ( ! is_string( $locale ) || '' === $locale ) {
				continue;
			}

			$language = $locale;
			if ( is_array( $translation ) && isset( $translation['english_name'] ) ) {
				$language = (string) $translation['english_name'];
			}

			$translations[] = array(
				'slug'        => $locale,
				'language'    => $language,
				'type'        => 'core',
				'auto_update' => isset( $auto[ $locale ] ),
				'blocked'     => Functions::auto_updates_blocked( $locale, 'translation' ),
			);
		}

		return array(
			'translations' => $translations,
		);
	}

	/**
	 * One translation row from the language catalog.
	 *
	 * @param string $slug Locale slug.
	 * @return array<string, mixed>|null Translation row, or null when the locale is not listed.
	 */
	public static function translation_by_slug( string $slug ): ?array {
		$listed = self::list_translations();
		foreach ( $listed['translations'] as $translation ) {
			if ( isset( $translation['slug'] ) && $translation['slug'] === $slug ) {
				return $translation;
			}
		}

		return null;
	}

	/**
	 * Pending core, plugin, theme, and translation updates.
	 *
	 * @return array<string, array<int, array<string, string>>> Available updates.
	 */
	public static function get_available_updates(): array {
		return array(
			'core'         => self::core_updates(),
			'plugins'      => self::plugin_updates(),
			'themes'       => self::theme_updates(),
			'translations' => self::translation_updates(),
		);
	}

	/**
	 * Paginated update logs.
	 *
	 * @param mixed $input Page, per_page, search, and status filters.
	 * @return array<string, mixed> Logs and pagination.
	 */
	public static function get_logs( $input = null ): array {
		$input    = is_array( $input ) ? $input : array();
		$page     = isset( $input['page'] ) ? max( 1, (int) $input['page'] ) : 1;
		$per_page = isset( $input['per_page'] ) ? (int) $input['per_page'] : 20;
		$per_page = max( 1, min( 50, $per_page ) );
		$search   = isset( $input['search'] ) && is_string( $input['search'] ) ? $input['search'] : '';
		$filters  = array();

		foreach ( array( 'update_offer', 'update_type', 'update_status' ) as $key ) {
			if ( isset( $input[ $key ] ) && is_string( $input[ $key ] ) && '' !== $input[ $key ] ) {
				$filters[ $key ] = $input[ $key ];
			}
		}

		$result = Table_Logs::get_logs_paginated(
			array(
				'page'     => $page,
				'per_page' => $per_page,
				'search'   => $search,
				'filters'  => $filters,
			)
		);

		$logs = array();
		$rows = isset( $result['logs'] ) && is_array( $result['logs'] ) ? $result['logs'] : array();
		foreach ( $rows as $row ) {
			if ( is_array( $row ) ) {
				$logs[] = self::format_log_row( $row, false );
			}
		}

		return array(
			'logs'     => $logs,
			'total'    => isset( $result['total'] ) ? (int) $result['total'] : 0,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/**
	 * One log row, including notes and stack trace.
	 *
	 * @param mixed $input Input containing id.
	 * @return array<string, mixed>|\WP_Error Log row, or an error when it is missing.
	 */
	public static function get_log( $input = null ) {
		$input = is_array( $input ) ? $input : array();
		$id    = isset( $input['id'] ) ? (int) $input['id'] : 0;
		if ( $id < 1 ) {
			return new \WP_Error( 'updatebee_invalid_log', __( 'A log id is required.', 'updatebee' ) );
		}

		$row = Table_Logs::get_log_by_id( $id );
		if ( null === $row ) {
			return new \WP_Error( 'updatebee_log_not_found', __( 'Log entry not found.', 'updatebee' ) );
		}

		return self::format_log_row( $row, true );
	}

	/**
	 * Post-update fatal findings currently held for this request.
	 *
	 * @return array<string, mixed> Enabled flag, findings, and any unattributed fatal.
	 */
	public static function get_fatal_findings(): array {
		$findings = Fatal_Detection::get_findings();

		return array(
			'enabled'      => Fatal_Detection::is_enabled(),
			'findings'     => array_values( $findings ),
			'unattributed' => Fatal_Detection::get_unattributed(),
		);
	}

	/**
	 * Next core update-check events and whether this plugin disables WP-Cron.
	 *
	 * @return array<string, mixed> Schedule snapshot.
	 */
	public static function get_update_schedule(): array {
		$crons    = Updates_Init::get_update_crons();
		$disabled = Options::get_options( 'disable_wp_cron' );

		return array(
			'wp_cron_disabled' => self::bool_value( $disabled ),
			'events'           => array(
				self::format_scheduled_event( 'wp_update_plugins', $crons['update_plugins'] ?? false ),
				self::format_scheduled_event( 'wp_update_themes', $crons['update_themes'] ?? false ),
				self::format_scheduled_event( 'wp_version_check', $crons['version_check'] ?? false ),
			),
		);
	}

	/**
	 * Response map from an update transient.
	 *
	 * @param string $transient Transient name without the site prefix.
	 * @return array<string, mixed> Update offers keyed by file or stylesheet.
	 */
	private static function transient_response( string $transient ): array {
		$updates = Hide_Blocked_Updates::unfiltered(
			static function () use ( $transient ) {
				return get_site_transient( $transient );
			}
		);
		if ( ! is_object( $updates ) || ! isset( $updates->response ) || ! is_array( $updates->response ) ) {
			return array();
		}

		return $updates->response;
	}

	/**
	 * Pending core updates.
	 *
	 * @return array<int, array<string, string>> Core offers.
	 */
	private static function core_updates(): array {
		$core = Hide_Blocked_Updates::unfiltered(
			static function () {
				return get_site_transient( 'update_core' );
			}
		);
		if ( ! is_object( $core ) || empty( $core->updates ) || ! is_array( $core->updates ) ) {
			return array();
		}

		$from = '';
		if ( isset( $core->version_checked ) ) {
			$from = (string) $core->version_checked;
		} elseif ( isset( $GLOBALS['wp_version'] ) ) {
			$from = (string) $GLOBALS['wp_version'];
		}

		$rows = array();
		foreach ( $core->updates as $update ) {
			if ( ! is_object( $update ) ) {
				continue;
			}

			$response = isset( $update->response ) ? (string) $update->response : '';
			if ( ! in_array( $response, array( 'upgrade', 'development', 'autoupdate' ), true ) ) {
				continue;
			}

			$to = '';
			if ( isset( $update->current ) ) {
				$to = (string) $update->current;
			} elseif ( isset( $update->version ) ) {
				$to = (string) $update->version;
			}

			$rows[] = array(
				'slug'         => 'wordpress',
				'from_version' => $from,
				'to_version'   => $to,
				'response'     => $response,
				'locale'       => isset( $update->locale ) ? (string) $update->locale : '',
			);
		}

		return $rows;
	}

	/**
	 * Pending plugin updates.
	 *
	 * @return array<int, array<string, string>> Plugin offers.
	 */
	private static function plugin_updates(): array {
		$installed = Functions::get_installed_plugin_files();
		$rows      = array();

		foreach ( self::transient_response( 'update_plugins' ) as $file => $item ) {
			if ( ! is_string( $file ) ) {
				continue;
			}

			$from = '';
			if ( isset( $installed[ $file ] ) && is_array( $installed[ $file ] ) && isset( $installed[ $file ]['Version'] ) ) {
				$from = (string) $installed[ $file ]['Version'];
			}

			$rows[] = array(
				'file'         => $file,
				'slug'         => self::slug_from_item( $item, $file ),
				'from_version' => $from,
				'to_version'   => self::new_version_from_item( $item ),
			);
		}

		return $rows;
	}

	/**
	 * Pending theme updates.
	 *
	 * @return array<int, array<string, string>> Theme offers.
	 */
	private static function theme_updates(): array {
		$installed = Functions::get_installed_themes();
		$rows      = array();

		foreach ( self::transient_response( 'update_themes' ) as $stylesheet => $item ) {
			if ( ! is_string( $stylesheet ) ) {
				continue;
			}

			$from = '';
			if ( isset( $installed[ $stylesheet ] ) && $installed[ $stylesheet ] instanceof \WP_Theme ) {
				$from = (string) $installed[ $stylesheet ]->get( 'Version' );
			}

			$rows[] = array(
				'stylesheet'   => $stylesheet,
				'from_version' => $from,
				'to_version'   => self::new_version_from_item( $item ),
			);
		}

		return $rows;
	}

	/**
	 * Pending translation updates from the core, plugin, and theme transients.
	 *
	 * @return array<int, array<string, string>> Translation offers.
	 */
	private static function translation_updates(): array {
		$rows = array();
		$seen = array();

		foreach ( array( 'update_core', 'update_plugins', 'update_themes' ) as $transient ) {
			$updates = Hide_Blocked_Updates::unfiltered(
				static function () use ( $transient ) {
					return get_site_transient( $transient );
				}
			);
			if ( ! is_object( $updates ) || empty( $updates->translations ) || ! is_array( $updates->translations ) ) {
				continue;
			}

			foreach ( $updates->translations as $translation ) {
				$item = self::translation_item( $translation );
				if ( null === $item ) {
					continue;
				}

				$key = $item['type'] . '|' . $item['slug'] . '|' . $item['language'];
				if ( isset( $seen[ $key ] ) ) {
					continue;
				}

				$seen[ $key ] = true;
				$rows[]       = $item;
			}
		}

		return $rows;
	}

	/**
	 * Normalize one translation offer.
	 *
	 * @param mixed $translation Raw translation offer.
	 * @return array<string, string>|null Normalized offer, or null when the item is unusable.
	 */
	private static function translation_item( $translation ): ?array {
		if ( is_object( $translation ) ) {
			$translation = get_object_vars( $translation );
		}

		if ( ! is_array( $translation ) ) {
			return null;
		}

		return array(
			'type'         => isset( $translation['type'] ) ? (string) $translation['type'] : '',
			'slug'         => isset( $translation['slug'] ) ? (string) $translation['slug'] : '',
			'language'     => isset( $translation['language'] ) ? (string) $translation['language'] : '',
			'from_version' => '',
			'to_version'   => isset( $translation['version'] ) ? (string) $translation['version'] : '',
		);
	}

	/**
	 * Offered version from a plugin or theme update item.
	 *
	 * @param mixed $item Update item.
	 * @return string Offered version, or an empty string.
	 */
	private static function new_version_from_item( $item ): string {
		if ( is_object( $item ) && isset( $item->new_version ) ) {
			return (string) $item->new_version;
		}

		if ( is_array( $item ) && isset( $item['new_version'] ) ) {
			return (string) $item['new_version'];
		}

		return '';
	}

	/**
	 * Plugin slug from an update item, falling back to the plugin file.
	 *
	 * @param mixed  $item     Update item.
	 * @param string $fallback Plugin basename.
	 * @return string Slug.
	 */
	private static function slug_from_item( $item, string $fallback ): string {
		if ( is_object( $item ) && isset( $item->slug ) && is_string( $item->slug ) && '' !== $item->slug ) {
			return $item->slug;
		}

		if ( is_array( $item ) && isset( $item['slug'] ) && is_string( $item['slug'] ) && '' !== $item['slug'] ) {
			return $item['slug'];
		}

		$directory = dirname( $fallback );
		if ( '.' === $directory || '' === $directory ) {
			return basename( $fallback, '.php' );
		}

		return $directory;
	}

	/**
	 * Delay days stored for one extension, or null when it inherits the site delay.
	 *
	 * @param array<string, mixed> $settings Delay settings.
	 * @return int|null Delay in days.
	 */
	private static function delay_days_from_settings( array $settings ): ?int {
		if ( ! array_key_exists( 'delay_days', $settings ) || null === $settings['delay_days'] || '' === $settings['delay_days'] ) {
			return null;
		}

		return (int) $settings['delay_days'];
	}

	/**
	 * Format a log row for an agent.
	 *
	 * @param array<string, mixed> $row    Database row.
	 * @param bool                 $detail Whether to include notes and the stack trace.
	 * @return array<string, mixed> Log payload.
	 */
	private static function format_log_row( array $row, bool $detail ): array {
		$formatted = array(
			'id'            => isset( $row['id'] ) ? (int) $row['id'] : 0,
			'name'          => isset( $row['name'] ) ? (string) $row['name'] : '',
			'slug'          => isset( $row['slug'] ) ? (string) $row['slug'] : '',
			'user_id'       => isset( $row['user_id'] ) ? (int) $row['user_id'] : 0,
			'from_version'  => isset( $row['from_version'] ) ? (string) $row['from_version'] : '',
			'to_version'    => isset( $row['to_version'] ) ? (string) $row['to_version'] : '',
			'update_offer'  => isset( $row['update_offer'] ) ? (string) $row['update_offer'] : '',
			'update_type'   => isset( $row['update_type'] ) ? (string) $row['update_type'] : '',
			'update_status' => isset( $row['update_status'] ) ? (string) $row['update_status'] : '',
			'date'          => isset( $row['date'] ) ? (string) $row['date'] : '',
		);

		if ( $detail ) {
			$formatted['raw']        = isset( $row['raw'] ) ? (string) $row['raw'] : '';
			$formatted['stacktrace'] = isset( $row['stacktrace'] ) ? (string) $row['stacktrace'] : '';
		}

		return $formatted;
	}

	/**
	 * Format one scheduled event for the schedule ability.
	 *
	 * @param string       $hook  Hook name.
	 * @param object|false $event Scheduled event, or false when none is scheduled.
	 * @return array{hook: string, scheduled: bool, timestamp: int|null, schedule: string|null} Event row.
	 */
	private static function format_scheduled_event( string $hook, $event ): array {
		if ( ! is_object( $event ) ) {
			return array(
				'hook'      => $hook,
				'scheduled' => false,
				'timestamp' => null,
				'schedule'  => null,
			);
		}

		$schedule = null;
		if ( isset( $event->schedule ) && is_string( $event->schedule ) && '' !== $event->schedule ) {
			$schedule = $event->schedule;
		}

		return array(
			'hook'      => $hook,
			'scheduled' => true,
			'timestamp' => isset( $event->timestamp ) ? (int) $event->timestamp : null,
			'schedule'  => $schedule,
		);
	}

	/**
	 * Normalize an on/off option.
	 *
	 * @param mixed $value Stored value.
	 * @return string `on` or `off`.
	 */
	private static function on_off_value( $value ): string {
		return 'on' === $value ? 'on' : 'off';
	}

	/**
	 * Normalize an integer option.
	 *
	 * @param mixed $value Stored value.
	 * @return int Non-negative integer.
	 */
	private static function int_value( $value ): int {
		if ( function_exists( 'absint' ) ) {
			return absint( $value );
		}

		return abs( (int) $value );
	}

	/**
	 * Normalize a boolean option.
	 *
	 * @param mixed $value Stored value.
	 * @return bool Boolean value.
	 */
	private static function bool_value( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			return in_array( $value, array( 'on', '1', 'true' ), true );
		}

		return (bool) $value;
	}
}
