<?php
/**
 * Import and export UpdateBee settings.
 *
 * @package updatebee
 */

namespace UpdateBee\Admin;

use UpdateBee\Cron\Schedules;
use UpdateBee\Cron\Updates_Cron;
use UpdateBee\Emails\Email_Failures;
use UpdateBee\Emails\Email_Successes;
use UpdateBee\Emails\Email_Updates_Available;
use UpdateBee\Functions;
use UpdateBee\Options;
use UpdateBee\Tables\Table_Plugins;
use UpdateBee\Tables\Table_Themes;
use UpdateBee\Updates\Upgrade_Notice_Keywords;

/**
 * JSON settings transfer. The Slack webhook is never exported or overwritten.
 */
class Settings_Transfer {

	/**
	 * Export nonce action.
	 */
	public const EXPORT_NONCE_ACTION = 'updatebee-export-settings';

	/**
	 * Import nonce action.
	 */
	public const IMPORT_NONCE_ACTION = 'updatebee-import-settings';

	/**
	 * Document format identifier.
	 */
	private const FORMAT = 'updatebee-settings';

	/**
	 * Supported document version.
	 */
	private const VERSION = 1;

	/**
	 * Option key that must never be transferred.
	 */
	private const EXCLUDED_OPTION = 'slack_webhook_url';

	/**
	 * Maximum uploaded file size in bytes.
	 */
	private const MAX_BYTES = 1048576;

	/**
	 * Maximum JSON nesting depth.
	 */
	private const MAX_DEPTH = 8;

	/**
	 * Maximum items in a list or map.
	 */
	private const MAX_LIST = 500;

	/**
	 * Maximum email addresses in one list.
	 */
	private const MAX_EMAILS = 50;

	/**
	 * Maximum day count stored for delays and log retention.
	 */
	private const MAX_DAYS = 3650;

	/**
	 * Maximum note or keyword text length.
	 */
	private const MAX_NOTE = 5000;

	/**
	 * Maximum plain-text setting length.
	 */
	private const MAX_TEXT = 200;

	/**
	 * Maximum subject length.
	 */
	private const MAX_SUBJECT = 200;

	/**
	 * Maximum branding description length.
	 */
	private const MAX_DESCRIPTION = 500;

	/**
	 * Ajax actions for export and import.
	 */
	public function run(): void {
		add_action( 'wp_ajax_updatebee_export_settings', array( $this, 'ajax_export' ) );
		add_action( 'wp_ajax_updatebee_import_settings', array( $this, 'ajax_import' ) );
	}

	/**
	 * Download the settings document.
	 */
	public function ajax_export(): void {
		if ( ! Functions::can_access_options() ) {
			wp_die( esc_html__( 'You do not have permission to export settings.', 'updatebee' ) );
		}

		check_ajax_referer( self::EXPORT_NONCE_ACTION, 'nonce' );

		$encoded = wp_json_encode( self::build_document(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $encoded ) ) {
			wp_die( esc_html__( 'Settings could not be exported.', 'updatebee' ) );
		}

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=updatebee-settings.json' );
		header( 'X-Content-Type-Options: nosniff' );
		echo $encoded; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download, not HTML.
		exit;
	}

	/**
	 * Import an uploaded settings document.
	 */
	public function ajax_import(): void {
		if ( ! Functions::can_access_options() ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to import settings.', 'updatebee' ),
				)
			);
		}

		check_ajax_referer( self::IMPORT_NONCE_ACTION, 'nonce' );

		$raw = self::read_uploaded_json();
		if ( is_wp_error( $raw ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Settings file was rejected.', 'updatebee' ),
				)
			);
		}

		$result = self::import_json( $raw );
		if ( is_wp_error( $result ) ) {
			$message = 'updatebee_settings_save_failed' === $result->get_error_code()
				? __( 'Settings could not be imported.', 'updatebee' )
				: __( 'Settings file was rejected.', 'updatebee' );
			wp_send_json_error(
				array(
					'message' => $message,
				)
			);
		}

		wp_send_json_success(
			array(
				'message' => __( 'Settings have been imported. The Slack webhook was left unchanged.', 'updatebee' ),
			)
		);
	}

	/**
	 * Build the export document from current settings.
	 *
	 * @return array<string, mixed> Settings document without the Slack webhook.
	 */
	public static function build_document(): array {
		return array(
			'format'    => self::FORMAT,
			'version'   => self::VERSION,
			'options'   => self::export_options(),
			'emails'    => array(
				'successes'         => self::export_email_group( 'successes' ),
				'failures'          => self::export_email_group( 'failures' ),
				'updates_available' => self::export_email_group( 'updates_available' ),
			),
			'schedules' => array(
				'plugins' => self::export_schedule( Updates_Cron::CONTEXT_PLUGINS ),
				'themes'  => self::export_schedule( Updates_Cron::CONTEXT_THEMES ),
				'core'    => self::export_schedule( Updates_Cron::CONTEXT_CORE ),
			),
			'plugins'   => self::export_plugins(),
			'themes'    => self::export_themes(),
		);
	}

	/**
	 * Parse a JSON document and save it.
	 *
	 * The document is validated in full before any setting is written.
	 *
	 * @param string $raw JSON document.
	 * @return true|\WP_Error True on success.
	 */
	public static function import_json( string $raw ) {
		$document = self::parse_document( $raw );
		if ( is_wp_error( $document ) ) {
			return $document;
		}

		return self::apply_document( $document );
	}

	/**
	 * Read the uploaded JSON file after the request is authorized.
	 *
	 * @return string|\WP_Error File contents, or an error.
	 */
	private static function read_uploaded_json() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Caller verifies the import nonce before this runs.
		if ( ! isset( $_FILES['settings_file'] ) || ! is_array( $_FILES['settings_file'] ) ) {
			return self::rejected();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Upload metadata is checked below; the body is parsed as JSON.
		$file  = wp_unslash( $_FILES['settings_file'] );
		$error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
		$size  = isset( $file['size'] ) ? (int) $file['size'] : 0;
		$tmp   = isset( $file['tmp_name'] ) && is_string( $file['tmp_name'] ) ? $file['tmp_name'] : '';

		if ( UPLOAD_ERR_OK !== $error || $size < 1 || $size > self::MAX_BYTES || '' === $tmp || ! is_uploaded_file( $tmp ) ) {
			return self::rejected();
		}

		$bytes = filesize( $tmp );
		if ( false === $bytes || $bytes < 1 || $bytes > self::MAX_BYTES ) {
			return self::rejected();
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a verified uploaded temp file.
		$raw = file_get_contents( $tmp );
		if ( ! is_string( $raw ) || '' === $raw || strlen( $raw ) > self::MAX_BYTES ) {
			return self::rejected();
		}

		return $raw;
	}

	/**
	 * Decode and validate a settings document.
	 *
	 * @param string $raw JSON document.
	 * @return array<string, mixed>|\WP_Error Sanitized document sections, or an error.
	 */
	private static function parse_document( string $raw ) {
		if ( '' === $raw || strlen( $raw ) > self::MAX_BYTES ) {
			return self::rejected();
		}

		try {
			$decoded = json_decode( $raw, true, self::MAX_DEPTH, JSON_THROW_ON_ERROR );
		} catch ( \JsonException ) {
			return self::rejected();
		}

		if ( ! is_array( $decoded ) || ! self::is_map( $decoded ) ) {
			return self::rejected();
		}

		if ( ! isset( $decoded['format'] ) || self::FORMAT !== $decoded['format'] ) {
			return self::rejected();
		}

		if ( ! isset( $decoded['version'] ) || self::VERSION !== $decoded['version'] ) {
			return self::rejected();
		}

		$document = array();

		if ( array_key_exists( 'options', $decoded ) ) {
			$options = self::parse_options_section( $decoded['options'] );
			if ( is_wp_error( $options ) ) {
				return $options;
			}
			$document['options'] = $options;
		}

		if ( array_key_exists( 'emails', $decoded ) ) {
			$emails = self::parse_group_section( $decoded['emails'], array( 'successes', 'failures', 'updates_available' ), 'email' );
			if ( is_wp_error( $emails ) ) {
				return $emails;
			}
			$document['emails'] = $emails;
		}

		if ( array_key_exists( 'schedules', $decoded ) ) {
			$schedules = self::parse_group_section(
				$decoded['schedules'],
				array( Updates_Cron::CONTEXT_PLUGINS, Updates_Cron::CONTEXT_THEMES, Updates_Cron::CONTEXT_CORE ),
				'schedule'
			);
			if ( is_wp_error( $schedules ) ) {
				return $schedules;
			}
			$document['schedules'] = $schedules;
		}

		if ( array_key_exists( 'plugins', $decoded ) ) {
			$plugins = self::parse_asset_section( $decoded['plugins'], 'plugin' );
			if ( is_wp_error( $plugins ) ) {
				return $plugins;
			}
			$document['plugins'] = $plugins;
		}

		if ( array_key_exists( 'themes', $decoded ) ) {
			$themes = self::parse_asset_section( $decoded['themes'], 'theme' );
			if ( is_wp_error( $themes ) ) {
				return $themes;
			}
			$document['themes'] = $themes;
		}

		return $document;
	}

	/**
	 * Save a validated document.
	 *
	 * @param array<string, mixed> $document Sanitized sections.
	 * @return true|\WP_Error True on success.
	 */
	private static function apply_document( array $document ) {
		if ( isset( $document['options'] ) && is_array( $document['options'] ) ) {
			foreach ( $document['options'] as $key => $value ) {
				if ( ! is_string( $key ) || self::EXCLUDED_OPTION === $key ) {
					continue;
				}

				$updated = Options::update_options( $key, array( $key => $value ) );
				if ( null === $updated ) {
					return self::save_failed();
				}
			}
		}

		if ( isset( $document['emails'] ) && is_array( $document['emails'] ) ) {
			foreach ( $document['emails'] as $group => $values ) {
				if ( ! is_string( $group ) || ! is_array( $values ) ) {
					continue;
				}

				$saved = self::save_email_group( $group, $values );
				if ( is_wp_error( $saved ) ) {
					return $saved;
				}
			}
		}

		if ( isset( $document['schedules'] ) && is_array( $document['schedules'] ) ) {
			foreach ( $document['schedules'] as $context => $values ) {
				if ( ! is_string( $context ) || ! is_array( $values ) ) {
					continue;
				}

				$saved = self::save_schedule( $context, $values );
				if ( is_wp_error( $saved ) ) {
					return $saved;
				}
			}
		}

		if ( isset( $document['plugins'] ) && is_array( $document['plugins'] ) ) {
			$saved = self::save_plugin_rows( $document['plugins'] );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
		}

		if ( isset( $document['themes'] ) && is_array( $document['themes'] ) ) {
			$saved = self::save_theme_rows( $document['themes'] );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
		}

		return true;
	}

	/**
	 * Export allowlisted main options.
	 *
	 * @return array<string, mixed> Option values.
	 */
	private static function export_options(): array {
		$exported = array();
		$current  = Options::get_options();
		if ( ! is_array( $current ) ) {
			return $exported;
		}

		foreach ( self::option_types() as $key => $type ) {
			if ( ! array_key_exists( $key, $current ) ) {
				continue;
			}

			$parsed = self::parse_option_value( $key, $current[ $key ], $type );
			if ( is_wp_error( $parsed ) ) {
				continue;
			}

			$exported[ $key ] = $parsed;
		}

		unset( $exported[ self::EXCLUDED_OPTION ] );

		return $exported;
	}

	/**
	 * Export one email report group.
	 *
	 * @param string $group Email group slug.
	 * @return array<string, mixed> Email settings.
	 */
	private static function export_email_group( string $group ): array {
		$defaults = self::email_defaults( $group );
		$current  = self::email_options( $group );
		$exported = array();

		foreach ( $defaults as $field => $fallback ) {
			$value  = array_key_exists( $field, $current ) ? $current[ $field ] : $fallback;
			$parsed = self::parse_email_field( (string) $field, $value, $fallback );
			if ( is_wp_error( $parsed ) ) {
				$parsed = $fallback;
			}
			$exported[ $field ] = $parsed;
		}

		return $exported;
	}

	/**
	 * Export one update-check schedule.
	 *
	 * @param string $context plugins, themes, or core.
	 * @return array<string, mixed> Schedule settings.
	 */
	private static function export_schedule( string $context ): array {
		$defaults = self::schedule_defaults();
		$current  = Updates_Cron::get_cron_options( $context );
		$exported = array();

		foreach ( $defaults as $field => $fallback ) {
			$value  = array_key_exists( $field, $current ) ? $current[ $field ] : $fallback;
			$parsed = self::parse_schedule_field( (string) $field, $value );
			if ( is_wp_error( $parsed ) ) {
				$parsed = $fallback;
			}
			$exported[ $field ] = $parsed;
		}

		return $exported;
	}

	/**
	 * Export plugin notes and per-plugin overrides.
	 *
	 * @return array<string, array<string, mixed>> Plugin file => row.
	 */
	private static function export_plugins(): array {
		$notes  = Table_Plugins::get_all_notes();
		$delays = Table_Plugins::get_all_delay_settings();

		return self::export_asset_rows( $notes, $delays, 'plugin' );
	}

	/**
	 * Export theme notes and per-theme overrides.
	 *
	 * @return array<string, array<string, mixed>> Stylesheet => row.
	 */
	private static function export_themes(): array {
		$notes  = Table_Themes::get_all_notes();
		$delays = Table_Themes::get_all_delay_settings();

		return self::export_asset_rows( $notes, $delays, 'theme' );
	}

	/**
	 * Merge notes and delay rows into export records.
	 *
	 * @param array<string, string>               $notes  Asset id => notes.
	 * @param array<string, array<string, mixed>> $delays Asset id => delay settings.
	 * @param string                              $kind   plugin or theme.
	 * @return array<string, array<string, mixed>> Export rows.
	 */
	private static function export_asset_rows( array $notes, array $delays, string $kind ): array {
		$ids  = array_unique( array_merge( array_keys( $notes ), array_keys( $delays ) ) );
		$rows = array();

		foreach ( $ids as $id ) {
			if ( ! is_string( $id ) ) {
				continue;
			}

			$validated = 'plugin' === $kind ? self::parse_plugin_file( $id ) : self::parse_theme_stylesheet( $id );
			if ( is_wp_error( $validated ) ) {
				continue;
			}

			$note    = isset( $notes[ $id ] ) && is_string( $notes[ $id ] ) ? $notes[ $id ] : '';
			$delay   = isset( $delays[ $id ]['delay_days'] ) && is_int( $delays[ $id ]['delay_days'] ) ? $delays[ $id ]['delay_days'] : null;
			$allow   = ! empty( $delays[ $id ]['allow_untested'] ) ? true : null;
			$has_row = '' !== trim( $note ) || null !== $delay || true === $allow;
			if ( ! $has_row ) {
				continue;
			}

			if ( strlen( $note ) > self::MAX_NOTE ) {
				$note = substr( $note, 0, self::MAX_NOTE );
			}

			$rows[ $validated ] = array(
				'notes'          => $note,
				'delay_days'     => $delay,
				'allow_untested' => $allow,
			);
		}

		return $rows;
	}

	/**
	 * Validate the main options object.
	 *
	 * @param mixed $section Raw options section.
	 * @return array<string, mixed>|\WP_Error Sanitized options.
	 */
	private static function parse_options_section( $section ) {
		if ( ! is_array( $section ) || ! self::is_map( $section ) ) {
			return self::rejected();
		}

		$clean = array();
		$types = self::option_types();
		foreach ( $section as $key => $value ) {
			if ( ! is_string( $key ) || self::EXCLUDED_OPTION === $key || ! isset( $types[ $key ] ) ) {
				continue;
			}

			$parsed = self::parse_option_value( $key, $value, $types[ $key ] );
			if ( is_wp_error( $parsed ) ) {
				return $parsed;
			}
			$clean[ $key ] = $parsed;
		}

		unset( $clean[ self::EXCLUDED_OPTION ] );

		return $clean;
	}

	/**
	 * Validate a named group section such as emails or schedules.
	 *
	 * @param mixed              $section Raw section.
	 * @param array<int, string> $groups  Allowed group names.
	 * @param string             $kind    email or schedule.
	 * @return array<string, array<string, mixed>>|\WP_Error Sanitized groups.
	 */
	private static function parse_group_section( $section, array $groups, string $kind ) {
		if ( ! is_array( $section ) || ! self::is_map( $section ) ) {
			return self::rejected();
		}

		$clean = array();
		foreach ( $section as $name => $value ) {
			if ( ! is_string( $name ) || ! in_array( $name, $groups, true ) ) {
				continue;
			}

			if ( ! is_array( $value ) || ! self::is_map( $value ) ) {
				return self::rejected();
			}

			$parsed = 'email' === $kind ? self::parse_email_group( $name, $value ) : self::parse_schedule_group( $value );
			if ( is_wp_error( $parsed ) ) {
				return $parsed;
			}
			$clean[ $name ] = $parsed;
		}

		return $clean;
	}

	/**
	 * Validate plugin or theme rows.
	 *
	 * @param mixed  $section Raw section.
	 * @param string $kind    plugin or theme.
	 * @return array<string, array<string, mixed>>|\WP_Error Sanitized rows.
	 */
	private static function parse_asset_section( $section, string $kind ) {
		if ( ! is_array( $section ) || ! self::is_map( $section ) || count( $section ) > self::MAX_LIST ) {
			return self::rejected();
		}

		$clean = array();
		foreach ( $section as $id => $row ) {
			if ( ! is_string( $id ) ) {
				return self::rejected();
			}

			$validated = 'plugin' === $kind ? self::parse_plugin_file( $id ) : self::parse_theme_stylesheet( $id );
			if ( is_wp_error( $validated ) ) {
				return $validated;
			}

			if ( ! is_array( $row ) || ! self::is_map( $row ) ) {
				return self::rejected();
			}

			$parsed = self::parse_asset_row( $row );
			if ( is_wp_error( $parsed ) ) {
				return $parsed;
			}
			$clean[ $validated ] = $parsed;
		}

		return $clean;
	}

	/**
	 * Validate one option value.
	 *
	 * @param string $key   Option key.
	 * @param mixed  $value Raw value.
	 * @param string $type  Expected type.
	 * @return mixed|\WP_Error Sanitized value.
	 */
	private static function parse_option_value( string $key, $value, string $type ) {
		unset( $key );

		switch ( $type ) {
			case 'on_off':
				return self::parse_on_off( $value );
			case 'bool':
				return self::parse_bool( $value );
			case 'days':
				return self::parse_int( $value, 0, self::MAX_DAYS );
			case 'keywords':
				return self::parse_keywords( $value );
			case 'text':
				return self::parse_text( $value, self::MAX_TEXT );
			case 'description':
				return self::parse_text( $value, self::MAX_DESCRIPTION );
			case 'url':
				return self::parse_url( $value );
			case 'plugin_list':
				return self::parse_plugin_list( $value );
			case 'theme_list':
				return self::parse_theme_list( $value );
			case 'translation_list':
				return self::parse_translation_list( $value );
			case 'email_list':
				return self::parse_email_list( $value );
			case 'plugin_version_map':
				return self::parse_version_map( $value, 'plugin' );
			case 'theme_version_map':
				return self::parse_version_map( $value, 'theme' );
			default:
				return self::rejected();
		}
	}

	/**
	 * Validate one email group.
	 *
	 * @param string               $group  Email group slug.
	 * @param array<string, mixed> $values Raw fields.
	 * @return array<string, mixed>|\WP_Error Sanitized fields.
	 */
	private static function parse_email_group( string $group, array $values ) {
		$defaults = self::email_defaults( $group );
		$clean    = array();

		foreach ( $values as $field => $value ) {
			if ( ! is_string( $field ) || ! array_key_exists( $field, $defaults ) ) {
				continue;
			}

			$parsed = self::parse_email_field( $field, $value, $defaults[ $field ] );
			if ( is_wp_error( $parsed ) ) {
				return $parsed;
			}
			$clean[ $field ] = $parsed;
		}

		return $clean;
	}

	/**
	 * Validate one email field.
	 *
	 * @param string $field   Field name.
	 * @param mixed  $value   Raw value.
	 * @param mixed  $fallback Default value, used to recognize booleans.
	 * @return mixed|\WP_Error Sanitized value.
	 */
	private static function parse_email_field( string $field, $value, $fallback ) {
		switch ( $field ) {
			case 'to_field':
			case 'bcc_field':
				return self::parse_email_list( $value );
			case 'send_schedule':
				if ( ! is_string( $value ) || ! in_array( $value, array( 'none', 'immediately', 'custom' ), true ) ) {
					return self::rejected();
				}
				return $value;
			case 'schedule':
				return self::parse_schedule_slug( $value );
			case 'day':
				return self::parse_weekday( $value );
			case 'week':
				return self::parse_week( $value );
			case 'start_time':
				return self::parse_start_time( $value );
			case 'day_num':
				return self::parse_day_num( $value );
			case 'subject':
				return self::parse_text( $value, self::MAX_SUBJECT );
			case 'log_cutoff_days':
				return self::parse_int( $value, 1, self::MAX_DAYS );
			default:
				if ( is_bool( $fallback ) ) {
					return self::parse_bool( $value );
				}
				return self::rejected();
		}
	}

	/**
	 * Validate schedule fields that are present.
	 *
	 * @param array<string, mixed> $values Raw fields.
	 * @return array<string, mixed>|\WP_Error Sanitized fields.
	 */
	private static function parse_schedule_group( array $values ) {
		$defaults = self::schedule_defaults();
		$clean    = array();

		foreach ( $values as $field => $value ) {
			if ( ! is_string( $field ) || ! array_key_exists( $field, $defaults ) ) {
				continue;
			}

			$parsed = self::parse_schedule_field( $field, $value );
			if ( is_wp_error( $parsed ) ) {
				return $parsed;
			}
			$clean[ $field ] = $parsed;
		}

		return $clean;
	}

	/**
	 * Validate one schedule field.
	 *
	 * @param string $field Field name.
	 * @param mixed  $value Raw value.
	 * @return mixed|\WP_Error Sanitized value.
	 */
	private static function parse_schedule_field( string $field, $value ) {
		switch ( $field ) {
			case 'schedule':
				return self::parse_schedule_slug( $value );
			case 'day':
				return self::parse_weekday( $value );
			case 'week':
				return self::parse_week( $value );
			case 'start_time':
				return self::parse_start_time( $value );
			case 'day_num':
				return self::parse_day_num( $value );
			case 'days_list':
				return self::parse_days_list( $value );
			default:
				return self::rejected();
		}
	}

	/**
	 * Validate notes, delay, and untested-update fields.
	 *
	 * @param array<string, mixed> $row Raw row.
	 * @return array<string, mixed>|\WP_Error Sanitized row.
	 */
	private static function parse_asset_row( array $row ) {
		$clean = array();

		foreach ( $row as $field => $value ) {
			if ( ! is_string( $field ) ) {
				return self::rejected();
			}

			if ( 'notes' === $field ) {
				$notes = self::parse_notes( $value );
				if ( is_wp_error( $notes ) ) {
					return $notes;
				}
				$clean['notes'] = $notes;
				continue;
			}

			if ( 'delay_days' === $field ) {
				if ( null === $value ) {
					$clean['delay_days'] = null;
					continue;
				}
				$days = self::parse_int( $value, 0, self::MAX_DAYS );
				if ( is_wp_error( $days ) ) {
					return $days;
				}
				$clean['delay_days'] = $days;
				continue;
			}

			if ( 'allow_untested' === $field ) {
				if ( null === $value ) {
					$clean['allow_untested'] = null;
					continue;
				}
				if ( true !== $value ) {
					return self::rejected();
				}
				$clean['allow_untested'] = true;
				continue;
			}
		}

		return $clean;
	}

	/**
	 * Save one email group and reschedule it.
	 *
	 * @param string               $group  Email group slug.
	 * @param array<string, mixed> $values Sanitized fields to replace.
	 * @return true|\WP_Error True on success.
	 */
	private static function save_email_group( string $group, array $values ) {
		$defaults = self::email_defaults( $group );
		$current  = self::email_options( $group );
		$clean    = array();

		foreach ( $defaults as $field => $fallback ) {
			if ( array_key_exists( $field, $values ) ) {
				$clean[ $field ] = $values[ $field ];
				continue;
			}
			$clean[ $field ] = array_key_exists( $field, $current ) ? $current[ $field ] : $fallback;
		}

		$key  = self::email_option_key( $group );
		$hook = self::email_cron_hook( $group );
		if ( '' === $key || '' === $hook ) {
			return self::save_failed();
		}

		update_site_option( $key, $clean );
		wp_clear_scheduled_hook( $hook );
		if ( isset( $clean['send_schedule'] ) && 'custom' === $clean['send_schedule'] ) {
			Schedules::set_cron_events( $clean, $hook );
		}

		return true;
	}

	/**
	 * Save one cron schedule and reschedule its hook.
	 *
	 * @param string               $context plugins, themes, or core.
	 * @param array<string, mixed> $values  Sanitized fields to replace.
	 * @return true|\WP_Error True on success.
	 */
	private static function save_schedule( string $context, array $values ) {
		if ( ! in_array( $context, Updates_Cron::get_contexts(), true ) ) {
			return self::save_failed();
		}

		$current = Updates_Cron::get_cron_options( $context );
		foreach ( $values as $field => $value ) {
			$current[ $field ] = $value;
		}

		$current = Updates_Cron::sanitize_schedule_input( $current );
		Updates_Cron::save_cron_options( $context, $current );

		return true;
	}

	/**
	 * Save plugin rows that are present in the file.
	 *
	 * @param array<string, array<string, mixed>> $rows Plugin rows.
	 * @return true|\WP_Error True on success.
	 */
	private static function save_plugin_rows( array $rows ) {
		foreach ( $rows as $file => $row ) {
			if ( ! is_string( $file ) || ! is_array( $row ) ) {
				return self::save_failed();
			}

			if ( array_key_exists( 'notes', $row ) ) {
				$saved = Table_Plugins::save_notes( $file, (string) $row['notes'] );
				if ( is_wp_error( $saved ) ) {
					return self::save_failed();
				}
			}

			if ( array_key_exists( 'delay_days', $row ) ) {
				$delay = $row['delay_days'];
				$saved = Table_Plugins::save_delay_settings( $file, is_int( $delay ) ? $delay : null );
				if ( is_wp_error( $saved ) ) {
					return self::save_failed();
				}
			}

			if ( array_key_exists( 'allow_untested', $row ) ) {
				$allow = true === $row['allow_untested'] ? true : null;
				$saved = Table_Plugins::save_untested_setting( $file, $allow );
				if ( is_wp_error( $saved ) ) {
					return self::save_failed();
				}
			}
		}

		return true;
	}

	/**
	 * Save theme rows that are present in the file.
	 *
	 * @param array<string, array<string, mixed>> $rows Theme rows.
	 * @return true|\WP_Error True on success.
	 */
	private static function save_theme_rows( array $rows ) {
		foreach ( $rows as $stylesheet => $row ) {
			if ( ! is_string( $stylesheet ) || ! is_array( $row ) ) {
				return self::save_failed();
			}

			if ( array_key_exists( 'notes', $row ) ) {
				$saved = Table_Themes::save_notes( $stylesheet, (string) $row['notes'] );
				if ( is_wp_error( $saved ) ) {
					return self::save_failed();
				}
			}

			if ( array_key_exists( 'delay_days', $row ) ) {
				$delay = $row['delay_days'];
				$saved = Table_Themes::save_delay_settings( $stylesheet, is_int( $delay ) ? $delay : null );
				if ( is_wp_error( $saved ) ) {
					return self::save_failed();
				}
			}

			if ( array_key_exists( 'allow_untested', $row ) ) {
				$allow = true === $row['allow_untested'] ? true : null;
				$saved = Table_Themes::save_untested_setting( $stylesheet, $allow );
				if ( is_wp_error( $saved ) ) {
					return self::save_failed();
				}
			}
		}

		return true;
	}

	/**
	 * Option keys that can be transferred, with their value types.
	 *
	 * @return array<string, string> Option key => type.
	 */
	private static function option_types(): array {
		return array(
			'major_auto_updates'               => 'on_off',
			'minor_auto_updates'               => 'on_off',
			'plugin_auto_updates'              => 'on_off',
			'plugin_auto_updates_custom'       => 'on_off',
			'plugin_manual_updates_only'       => 'on_off',
			'plugin_auto_updates_patches_only' => 'on_off',
			'plugin_updates_disabled'          => 'on_off',
			'theme_auto_updates'               => 'on_off',
			'theme_auto_updates_custom'        => 'on_off',
			'theme_manual_updates_only'        => 'on_off',
			'theme_auto_updates_patches_only'  => 'on_off',
			'theme_updates_disabled'           => 'on_off',
			'translation_auto_updates'         => 'on_off',
			'translation_auto_updates_custom'  => 'on_off',
			'translation_manual_updates_only'  => 'on_off',
			'translation_updates_disabled'     => 'on_off',
			'auto_update_plugins'              => 'plugin_list',
			'auto_update_plugins_patches'      => 'plugin_list',
			'blocked_plugins'                  => 'plugin_list',
			'skipped_plugin_versions'          => 'plugin_version_map',
			'auto_update_themes'               => 'theme_list',
			'auto_update_themes_patches'       => 'theme_list',
			'blocked_themes'                   => 'theme_list',
			'skipped_theme_versions'           => 'theme_version_map',
			'auto_update_translations'         => 'translation_list',
			'blocked_translations'             => 'translation_list',
			'enable_core_emails'               => 'bool',
			'core_email_recipients'            => 'email_list',
			'clear_logs_days'                  => 'days',
			'slack_enabled'                    => 'bool',
			'delay_updates'                    => 'days',
			'upgrade_notice_keywords'          => 'keywords',
			'upgrade_notice_keywords_enabled'  => 'bool',
			'block_untested_updates'           => 'on_off',
			'fatal_error_detection'            => 'bool',
			'fatal_email_recipients'           => 'email_list',
			'fatal_notify_slack'               => 'bool',
			'branding_name'                    => 'text',
			'branding_description'             => 'description',
			'branding_author'                  => 'text',
			'branding_url'                     => 'url',
			'admin_bar_enabled'                => 'bool',
			'admin_bar_show_admin'             => 'bool',
			'admin_bar_show_frontend'          => 'bool',
		);
	}

	/**
	 * Default fields for an email group.
	 *
	 * @param string $group Email group slug.
	 * @return array<string, mixed> Defaults.
	 */
	private static function email_defaults( string $group ): array {
		switch ( $group ) {
			case 'successes':
				return Email_Successes::get_defaults();
			case 'failures':
				return Email_Failures::get_defaults();
			case 'updates_available':
				return Email_Updates_Available::get_defaults();
			default:
				return array();
		}
	}

	/**
	 * Current settings for an email group.
	 *
	 * @param string $group Email group slug.
	 * @return array<string, mixed> Settings.
	 */
	private static function email_options( string $group ): array {
		switch ( $group ) {
			case 'successes':
				return Email_Successes::get_options();
			case 'failures':
				return Email_Failures::get_options();
			case 'updates_available':
				return Email_Updates_Available::get_options();
			default:
				return array();
		}
	}

	/**
	 * Site option key for an email group.
	 *
	 * @param string $group Email group slug.
	 * @return string Option key.
	 */
	private static function email_option_key( string $group ): string {
		switch ( $group ) {
			case 'successes':
				return Email_Successes::OPTIONS_KEY;
			case 'failures':
				return Email_Failures::OPTIONS_KEY;
			case 'updates_available':
				return Email_Updates_Available::OPTIONS_KEY;
			default:
				return '';
		}
	}

	/**
	 * Cron hook for an email group.
	 *
	 * @param string $group Email group slug.
	 * @return string Hook name.
	 */
	private static function email_cron_hook( string $group ): string {
		switch ( $group ) {
			case 'successes':
				return Email_Successes::CRON_HOOK;
			case 'failures':
				return Email_Failures::CRON_HOOK;
			case 'updates_available':
				return Email_Updates_Available::CRON_HOOK;
			default:
				return '';
		}
	}

	/**
	 * Schedule fields and their defaults.
	 *
	 * @return array<string, mixed> Defaults.
	 */
	private static function schedule_defaults(): array {
		return Updates_Cron::get_cron_defaults();
	}

	/**
	 * Whether an array came from a JSON object.
	 *
	 * An empty array is accepted because JSON `{}` and `[]` decode the same way.
	 *
	 * @param array<mixed> $value Decoded value.
	 * @return bool True when the value is empty or keyed by strings.
	 */
	private static function is_map( array $value ): bool {
		return array() === $value || ! array_is_list( $value );
	}

	/**
	 * Validate an on/off policy value.
	 *
	 * @param mixed $value Raw value.
	 * @return string|\WP_Error `on` or `off`.
	 */
	private static function parse_on_off( $value ) {
		if ( ! is_string( $value ) || ! in_array( $value, array( 'on', 'off' ), true ) ) {
			return self::rejected();
		}

		return $value;
	}

	/**
	 * Validate a JSON boolean.
	 *
	 * @param mixed $value Raw value.
	 * @return bool|\WP_Error Boolean value.
	 */
	private static function parse_bool( $value ) {
		if ( ! is_bool( $value ) ) {
			return self::rejected();
		}

		return $value;
	}

	/**
	 * Validate a JSON integer inside a range.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $min   Minimum value.
	 * @param int   $max   Maximum value.
	 * @return int|\WP_Error Integer value.
	 */
	private static function parse_int( $value, int $min, int $max ) {
		if ( ! is_int( $value ) || $value < $min || $value > $max ) {
			return self::rejected();
		}

		return $value;
	}

	/**
	 * Validate upgrade-notice keywords.
	 *
	 * @param mixed $value Raw value.
	 * @return string|\WP_Error Sanitized keywords.
	 */
	private static function parse_keywords( $value ) {
		if ( ! is_string( $value ) || strlen( $value ) > self::MAX_NOTE || str_contains( $value, "\0" ) ) {
			return self::rejected();
		}

		$clean = Upgrade_Notice_Keywords::sanitize( $value );
		if ( strlen( $clean ) > self::MAX_NOTE ) {
			return self::rejected();
		}

		return $clean;
	}

	/**
	 * Validate plain text.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $max   Maximum length.
	 * @return string|\WP_Error Sanitized text.
	 */
	private static function parse_text( $value, int $max ) {
		if ( ! is_string( $value ) || strlen( $value ) > $max || str_contains( $value, "\0" ) ) {
			return self::rejected();
		}

		$clean = sanitize_text_field( $value );
		if ( ! is_string( $clean ) || strlen( $clean ) > $max ) {
			return self::rejected();
		}

		return $clean;
	}

	/**
	 * Validate a branding URL.
	 *
	 * @param mixed $value Raw value.
	 * @return string|\WP_Error Sanitized URL, or an empty string.
	 */
	private static function parse_url( $value ) {
		if ( ! is_string( $value ) || strlen( $value ) > self::MAX_TEXT || str_contains( $value, "\0" ) ) {
			return self::rejected();
		}

		if ( '' === $value ) {
			return '';
		}

		$clean = esc_url_raw( $value );
		if ( ! is_string( $clean ) || '' === $clean ) {
			return self::rejected();
		}

		$scheme = wp_parse_url( $clean, PHP_URL_SCHEME );
		if ( ! is_string( $scheme ) || ! in_array( strtolower( $scheme ), array( 'http', 'https' ), true ) ) {
			return self::rejected();
		}

		return $clean;
	}

	/**
	 * Validate notes text.
	 *
	 * @param mixed $value Raw value.
	 * @return string|\WP_Error Sanitized notes.
	 */
	private static function parse_notes( $value ) {
		if ( ! is_string( $value ) || strlen( $value ) > self::MAX_NOTE || str_contains( $value, "\0" ) ) {
			return self::rejected();
		}

		$clean = sanitize_textarea_field( $value );
		if ( ! is_string( $clean ) || strlen( $clean ) > self::MAX_NOTE ) {
			return self::rejected();
		}

		return $clean;
	}

	/**
	 * Validate a list of plugin basenames.
	 *
	 * @param mixed $value Raw value.
	 * @return array<int, string>|\WP_Error Plugin files.
	 */
	private static function parse_plugin_list( $value ) {
		return self::parse_identifier_list( $value, 'plugin' );
	}

	/**
	 * Validate a list of theme stylesheets.
	 *
	 * @param mixed $value Raw value.
	 * @return array<int, string>|\WP_Error Theme stylesheets.
	 */
	private static function parse_theme_list( $value ) {
		return self::parse_identifier_list( $value, 'theme' );
	}

	/**
	 * Validate a list of translation slugs.
	 *
	 * @param mixed $value Raw value.
	 * @return array<int, string>|\WP_Error Translation slugs.
	 */
	private static function parse_translation_list( $value ) {
		return self::parse_identifier_list( $value, 'translation' );
	}

	/**
	 * Validate a list of identifiers.
	 *
	 * @param mixed  $value Raw value.
	 * @param string $kind  plugin, theme, or translation.
	 * @return array<int, string>|\WP_Error Identifiers.
	 */
	private static function parse_identifier_list( $value, string $kind ) {
		if ( ! is_array( $value ) || ! array_is_list( $value ) || count( $value ) > self::MAX_LIST ) {
			return self::rejected();
		}

		$clean = array();
		foreach ( $value as $item ) {
			if ( ! is_string( $item ) ) {
				return self::rejected();
			}

			$parsed = self::parse_identifier( $item, $kind );
			if ( is_wp_error( $parsed ) ) {
				return $parsed;
			}
			$clean[] = $parsed;
		}

		return array_values( array_unique( $clean ) );
	}

	/**
	 * Validate an email list.
	 *
	 * @param mixed $value Raw value.
	 * @return array<int, string>|\WP_Error Email addresses.
	 */
	private static function parse_email_list( $value ) {
		if ( ! is_array( $value ) || ! array_is_list( $value ) || count( $value ) > self::MAX_EMAILS ) {
			return self::rejected();
		}

		$clean = array();
		foreach ( $value as $email ) {
			if ( ! is_string( $email ) || strlen( $email ) > 254 || str_contains( $email, "\0" ) ) {
				return self::rejected();
			}

			$sanitized = sanitize_email( $email );
			if ( ! is_string( $sanitized ) || ! is_email( $sanitized ) ) {
				return self::rejected();
			}
			$clean[] = $sanitized;
		}

		return array_values( array_unique( $clean ) );
	}

	/**
	 * Validate a skipped-version map.
	 *
	 * @param mixed  $value Raw value.
	 * @param string $kind  plugin or theme.
	 * @return array<string, string>|\WP_Error Asset id => version.
	 */
	private static function parse_version_map( $value, string $kind ) {
		if ( ! is_array( $value ) || ! self::is_map( $value ) || count( $value ) > self::MAX_LIST ) {
			return self::rejected();
		}

		$clean = array();
		foreach ( $value as $id => $version ) {
			if ( ! is_string( $id ) || ! is_string( $version ) ) {
				return self::rejected();
			}

			$parsed_id = self::parse_identifier( $id, $kind );
			if ( is_wp_error( $parsed_id ) ) {
				return $parsed_id;
			}

			if ( strlen( $version ) > 32 || 1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9._+-]*$/', $version ) ) {
				return self::rejected();
			}

			$clean[ $parsed_id ] = $version;
		}

		return $clean;
	}

	/**
	 * Validate one asset identifier.
	 *
	 * @param string $value Identifier.
	 * @param string $kind  plugin, theme, or translation.
	 * @return string|\WP_Error Sanitized identifier.
	 */
	private static function parse_identifier( string $value, string $kind ) {
		if ( 'plugin' === $kind ) {
			return self::parse_plugin_file( $value );
		}

		if ( 'theme' === $kind ) {
			return self::parse_theme_stylesheet( $value );
		}

		return self::parse_translation_slug( $value );
	}

	/**
	 * Validate a plugin basename.
	 *
	 * @param string $file Plugin file.
	 * @return string|\WP_Error Plugin file.
	 */
	private static function parse_plugin_file( string $file ) {
		if ( strlen( $file ) > 200 || str_contains( $file, "\0" ) || str_contains( $file, '..' ) || str_contains( $file, '\\' ) ) {
			return self::rejected();
		}

		if ( substr_count( $file, '/' ) > 1 ) {
			return self::rejected();
		}

		if ( 1 !== preg_match( '/^(?:[A-Za-z0-9][A-Za-z0-9_-]*\/)?[A-Za-z0-9][A-Za-z0-9_.-]*\.php$/', $file ) ) {
			return self::rejected();
		}

		return $file;
	}

	/**
	 * Validate a theme stylesheet directory.
	 *
	 * @param string $stylesheet Theme stylesheet.
	 * @return string|\WP_Error Stylesheet.
	 */
	private static function parse_theme_stylesheet( string $stylesheet ) {
		if ( strlen( $stylesheet ) > 100 || str_contains( $stylesheet, "\0" ) || str_contains( $stylesheet, '..' ) || str_contains( $stylesheet, '/' ) || str_contains( $stylesheet, '\\' ) ) {
			return self::rejected();
		}

		if ( 1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9_-]*$/', $stylesheet ) ) {
			return self::rejected();
		}

		return $stylesheet;
	}

	/**
	 * Validate a translation locale slug.
	 *
	 * @param string $slug Locale slug.
	 * @return string|\WP_Error Locale slug.
	 */
	private static function parse_translation_slug( string $slug ) {
		if ( strlen( $slug ) > 32 || str_contains( $slug, "\0" ) ) {
			return self::rejected();
		}

		if ( 1 !== preg_match( '/^[A-Za-z]{2,3}(?:_[A-Za-z0-9]+){0,2}$/', $slug ) ) {
			return self::rejected();
		}

		return $slug;
	}

	/**
	 * Validate a cron frequency slug.
	 *
	 * @param mixed $value Raw value.
	 * @return string|\WP_Error Schedule slug.
	 */
	private static function parse_schedule_slug( $value ) {
		$allowed = array(
			'hourly',
			'two-hours',
			'three-hours',
			'six-hours',
			'twelve-hours',
			'daily',
			'weekly',
			'two-weeks',
			'monthly',
		);
		if ( ! is_string( $value ) || ! in_array( $value, $allowed, true ) ) {
			return self::rejected();
		}

		return $value;
	}

	/**
	 * Validate a weekday name.
	 *
	 * @param mixed $value Raw value.
	 * @return string|\WP_Error Weekday.
	 */
	private static function parse_weekday( $value ) {
		$allowed = array( 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday' );
		if ( ! is_string( $value ) || ! in_array( $value, $allowed, true ) ) {
			return self::rejected();
		}

		return $value;
	}

	/**
	 * Validate a two-week schedule week.
	 *
	 * @param mixed $value Raw value.
	 * @return string|\WP_Error Week number.
	 */
	private static function parse_week( $value ) {
		if ( ! is_string( $value ) ) {
			return self::rejected();
		}

		if ( 'week-one' === $value ) {
			$value = '1';
		} elseif ( 'week-two' === $value ) {
			$value = '2';
		}

		if ( ! in_array( $value, array( '1', '2' ), true ) ) {
			return self::rejected();
		}

		return $value;
	}

	/**
	 * Validate a HH:MM start time.
	 *
	 * @param mixed $value Raw value.
	 * @return string|\WP_Error Start time.
	 */
	private static function parse_start_time( $value ) {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $value ) ) {
			return self::rejected();
		}

		return $value;
	}

	/**
	 * Validate a day-of-month string.
	 *
	 * @param mixed $value Raw value.
	 * @return string|\WP_Error Day number.
	 */
	private static function parse_day_num( $value ) {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^([1-9]|[12][0-9]|3[01])$/', $value ) ) {
			return self::rejected();
		}

		return $value;
	}

	/**
	 * Validate a weekday list.
	 *
	 * @param mixed $value Raw value.
	 * @return array<int, int>|\WP_Error Weekday numbers from 1 to 7.
	 */
	private static function parse_days_list( $value ) {
		if ( ! is_array( $value ) || ! array_is_list( $value ) || count( $value ) > 7 ) {
			return self::rejected();
		}

		$days = array();
		foreach ( $value as $day ) {
			if ( ! is_int( $day ) || $day < 1 || $day > 7 ) {
				return self::rejected();
			}
			$days[] = $day;
		}

		$days = array_values( array_unique( $days ) );
		sort( $days );

		return $days;
	}

	/**
	 * Generic rejection for a document that failed validation.
	 *
	 * @return \WP_Error Rejection error.
	 */
	private static function rejected(): \WP_Error {
		return new \WP_Error( 'updatebee_settings_rejected', __( 'Settings file was rejected.', 'updatebee' ) );
	}

	/**
	 * Failure while saving an already validated document.
	 *
	 * @return \WP_Error Save error.
	 */
	private static function save_failed(): \WP_Error {
		return new \WP_Error( 'updatebee_settings_save_failed', __( 'Settings could not be imported.', 'updatebee' ) );
	}
}
