<?php
/**
 * Base class for success/failure email reports.
 *
 * @package updatebee
 */

namespace UpdateBee\Emails;

use UpdateBee\Cron\Schedules;
use UpdateBee\Functions;
use UpdateBee\Logs;

/**
 * Shared email report behavior.
 */
abstract class Email_Report {

	/**
	 * Site option key for report settings.
	 */
	abstract protected static function get_options_key(): string;

	/**
	 * Site option key for last send timestamp.
	 */
	abstract protected static function get_last_send_key(): string;

	/**
	 * Cron hook name.
	 */
	abstract protected static function get_cron_hook(): string;

	/**
	 * Log update_status value for this report.
	 */
	abstract protected static function get_log_status(): string;

	/**
	 * Report email title.
	 */
	abstract protected static function get_report_title(): string;

	/**
	 * Report intro text.
	 */
	abstract protected static function get_report_intro(): string;

	/**
	 * Default subject when empty.
	 */
	abstract protected static function get_default_subject(): string;

	/**
	 * Ajax action for preview.
	 */
	abstract protected static function get_preview_action(): string;

	/**
	 * Nonce action for preview.
	 */
	abstract protected static function get_preview_nonce_action(): string;

	/**
	 * Register hooks and ajax handlers.
	 */
	public function run(): void {
		add_action( 'wp_ajax_' . static::get_retrieve_action(), array( $this, 'ajax_get_options' ) );
		add_action( 'wp_ajax_' . static::get_save_action(), array( $this, 'ajax_save_options' ) );
		add_action( 'wp_ajax_' . static::get_reset_action(), array( $this, 'ajax_reset_options' ) );
		add_action( 'wp_ajax_' . static::get_preview_action(), array( $this, 'ajax_preview_email' ) );
		add_action( static::get_cron_hook(), array( $this, 'cron_send_report' ) );
		add_action( 'updatebee_automatic_updates_complete', array( $this, 'maybe_send_immediate_report' ), 10, 1 );
	}

	/**
	 * Retrieve ajax action slug.
	 */
	abstract protected static function get_retrieve_action(): string;

	/**
	 * Save ajax action slug.
	 */
	abstract protected static function get_save_action(): string;

	/**
	 * Reset ajax action slug.
	 */
	abstract protected static function get_reset_action(): string;

	/**
	 * Retrieve nonce action name.
	 */
	abstract protected static function get_retrieve_nonce_action(): string;

	/**
	 * Save nonce action name.
	 */
	abstract protected static function get_save_nonce_action(): string;

	/**
	 * Reset nonce action name.
	 */
	abstract protected static function get_reset_nonce_action(): string;

	/**
	 * Default option values.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_defaults(): array {
		return array(
			'to_field'             => array(),
			'bcc_field'            => array(),
			'subject'              => '',
			'include_core'         => true,
			'include_plugins'      => true,
			'include_themes'       => true,
			'include_translations' => true,
			'send_schedule'        => 'none',
			'schedule'             => 'twelve-hours',
			'day'                  => 'sunday',
			'week'                 => '1',
			'start_time'           => '00:00',
			'day_num'              => '1',
			'log_cutoff_days'      => 7,
		);
	}

	/**
	 * Load merged options.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_options(): array {
		$defaults = static::get_defaults();
		$options  = get_site_option( static::get_options_key(), $defaults );
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		return wp_parse_args( $options, $defaults );
	}

	/**
	 * Whether this report type is enabled (not off).
	 *
	 * @param array<string, mixed>|null $options Optional options array.
	 * @return bool
	 */
	public static function is_enabled( ?array $options = null ): bool {
		$options = $options ?? static::get_options();
		return isset( $options['send_schedule'] ) && 'none' !== $options['send_schedule'];
	}

	/**
	 * Supported update types from options.
	 *
	 * @param array<string, mixed> $options Report options.
	 * @return array<string, bool>
	 */
	protected static function get_supported_types( array $options ): array {
		return array(
			'plugins'      => ! empty( $options['include_plugins'] ),
			'themes'       => ! empty( $options['include_themes'] ),
			'core'         => ! empty( $options['include_core'] ),
			'translations' => ! empty( $options['include_translations'] ),
		);
	}

	/**
	 * Send immediately after automatic updates when configured.
	 *
	 * @param array<int, array<string, mixed>> $updates Update batch from logs.
	 */
	public function maybe_send_immediate_report( array $updates ): void {
		$options = static::get_options();
		if ( 'immediately' !== $options['send_schedule'] ) {
			return;
		}

		$filtered = static::filter_updates_for_report( $updates, $options );
		if ( empty( $filtered ) ) {
			return;
		}

		$body = Emails::get_update_report_email(
			$filtered,
			static::get_report_title(),
			static::get_report_intro(),
			static::get_cron_hook()
		);

		$this->send_email( $body, $options );
	}

	/**
	 * Cron digest sender.
	 */
	public function cron_send_report(): void {
		$last_send = (int) get_site_option( static::get_last_send_key(), 0 );
		$options   = static::get_options();
		$supported = static::get_supported_types( $options );

		$cutoff_days = absint( $options['log_cutoff_days'] ?? 7 );
		if ( $cutoff_days < 1 ) {
			$cutoff_days = 7;
		}

		/**
		 * Filter the maximum number of days to look back for log entries in email reports.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $cutoff_days Number of days.
		 * @param string $log_status  Report status: success|failure.
		 */
		$cutoff_days = (int) apply_filters( 'updatebee_email_log_cutoff_days', $cutoff_days, static::get_log_status() );

		$effective_cutoff = $last_send;
		if ( $cutoff_days > 0 ) {
			$max_cutoff       = time() - ( $cutoff_days * DAY_IN_SECONDS );
			$effective_cutoff = max( $effective_cutoff, $max_cutoff );
		}

		if ( 'success' === static::get_log_status() ) {
			$updates = Logs::get_success_logs( $supported, $effective_cutoff );
		} else {
			$updates = Logs::get_failure_logs( $supported, $effective_cutoff );
		}

		if ( empty( $updates ) ) {
			return;
		}

		$body = Emails::get_update_report_email(
			$updates,
			static::get_report_title(),
			static::get_report_intro(),
			static::get_cron_hook()
		);

		$this->send_email( $body, $options );
	}

	/**
	 * Filter updates by status and include flags.
	 *
	 * @param array<int, array<string, mixed>> $updates Update rows.
	 * @param array<string, mixed>             $options Report options.
	 * @return array<int, array<string, mixed>>
	 */
	protected static function filter_updates_for_report( array $updates, array $options ): array {
		$status    = static::get_log_status();
		$supported = static::get_supported_types( $options );
		$filtered  = array();

		foreach ( $updates as $update ) {
			if ( ! is_array( $update ) ) {
				continue;
			}
			$row = Emails::normalize_log_row( $update );
			if ( empty( $row ) || $status !== $row['update_status'] ) {
				continue;
			}
			$type_key = static::map_update_type_to_supported_key( $row['update_type'] );
			if ( ! $type_key || empty( $supported[ $type_key ] ) ) {
				continue;
			}
			$filtered[] = $update;
		}

		return $filtered;
	}

	/**
	 * Map log update_type to supported array key.
	 *
	 * @param string $update_type Log type slug.
	 * @return string|null
	 */
	protected static function map_update_type_to_supported_key( string $update_type ): ?string {
		switch ( $update_type ) {
			case 'core':
				return 'core';
			case 'plugin':
				return 'plugins';
			case 'theme':
				return 'themes';
			case 'translation':
				return 'translations';
			default:
				return null;
		}
	}

	/**
	 * Send HTML email and update last-send timestamp.
	 *
	 * @param string               $email_body HTML body.
	 * @param array<string, mixed> $options    Report options.
	 * @param bool                 $update_last_send Whether to update the last-send timestamp.
	 */
	protected function send_email( string $email_body, array $options, bool $update_last_send = true ): void {
		if ( '' === trim( $email_body ) ) {
			return;
		}

		$to_emails  = isset( $options['to_field'] ) && is_array( $options['to_field'] ) ? $options['to_field'] : array();
		$bcc_emails = isset( $options['bcc_field'] ) && is_array( $options['bcc_field'] ) ? $options['bcc_field'] : array();

		$subject = isset( $options['subject'] ) ? sanitize_text_field( (string) $options['subject'] ) : '';
		if ( '' === $subject ) {
			$subject = static::get_default_subject();
		}

		$headers = array(
			sprintf(
				'From: %s <%s>',
				sanitize_text_field( get_bloginfo( 'name' ) ),
				sanitize_text_field( get_bloginfo( 'admin_email' ) )
			),
			'Content-Type: text/html; charset=UTF-8',
		);

		foreach ( $bcc_emails as $email ) {
			if ( is_email( $email ) ) {
				$headers[] = 'Bcc: ' . $email;
			}
		}

		$recipients = array();
		foreach ( $to_emails as $email ) {
			if ( is_email( $email ) ) {
				$recipients[] = $email;
			}
		}

		if ( empty( $recipients ) ) {
			return;
		}

		add_filter( 'safe_style_css', array( Emails::class, 'allow_email_styles' ) );
		$email_body = wp_kses( $email_body, Functions::get_kses_allowed_html( false, true ) );
		wp_mail( $recipients, $subject, $email_body, $headers );
		remove_filter( 'safe_style_css', array( Emails::class, 'allow_email_styles' ) );

		if ( $update_last_send ) {
			update_site_option( static::get_last_send_key(), time() );
		}
	}

	/**
	 * Ajax: retrieve options for the admin UI.
	 */
	public function ajax_get_options(): void {
		if ( ! Functions::can_access_options() ) {
			wp_send_json_error();
		}

		check_ajax_referer( static::get_retrieve_nonce_action(), 'nonce' );

		$options = static::get_options();

		$options['log_cutoff_days'] = absint( $options['log_cutoff_days'] ?? 7 );

		$options['schedule_options'] = array(
			'schedule'  => $options['schedule'],
			'day'       => $options['day'],
			'dayNum'    => $options['day_num'],
			'startTime' => $options['start_time'],
			'week'      => $options['week'],
		);

		$options['previewurl'] = esc_url_raw(
			add_query_arg(
				array(
					'nonce'  => wp_create_nonce( static::get_preview_nonce_action() ),
					'action' => static::get_preview_action(),
				),
				admin_url( 'admin-ajax.php' )
			)
		);

		wp_send_json_success( $options );
	}

	/**
	 * Ajax: save options.
	 */
	public function ajax_save_options(): void {
		if ( ! Functions::can_access_options() ) {
			wp_send_json_error();
		}

		check_ajax_referer( static::get_save_nonce_action(), 'nonce' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below.
		$to_field = isset( $_POST['toField'] ) ? wp_unslash( $_POST['toField'] ) : array();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below.
		$bcc_field = isset( $_POST['bccField'] ) ? wp_unslash( $_POST['bccField'] ) : array();

		if ( ! is_array( $to_field ) ) {
			$to_field = array();
		}
		if ( ! is_array( $bcc_field ) ) {
			$bcc_field = array();
		}

		$subject              = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$include_core         = filter_var( $_POST['includeCore'] ?? false, FILTER_VALIDATE_BOOLEAN );
		$include_plugins      = filter_var( $_POST['includePlugins'] ?? false, FILTER_VALIDATE_BOOLEAN );
		$include_themes       = filter_var( $_POST['includeThemes'] ?? false, FILTER_VALIDATE_BOOLEAN );
		$include_translations = filter_var( $_POST['includeTranslations'] ?? false, FILTER_VALIDATE_BOOLEAN );
		$send_schedule        = isset( $_POST['sendSchedule'] ) ? sanitize_text_field( wp_unslash( $_POST['sendSchedule'] ) ) : 'none';
		$log_cutoff_days      = isset( $_POST['logCutoffDays'] ) ? absint( wp_unslash( $_POST['logCutoffDays'] ) ) : 7;
		if ( $log_cutoff_days < 1 ) {
			$log_cutoff_days = 7;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below.
		$schedule_options = isset( $_POST['scheduleOptions'] ) ? wp_unslash( $_POST['scheduleOptions'] ) : array();
		if ( ! is_array( $schedule_options ) ) {
			$schedule_options = array();
		}

		$to_field  = array_values( array_filter( array_map( 'sanitize_email', $to_field ), 'is_email' ) );
		$bcc_field = array_values( array_filter( array_map( 'sanitize_email', $bcc_field ), 'is_email' ) );

		if ( 'none' !== $send_schedule && empty( $to_field ) && empty( $bcc_field ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You must enter at least one email address.', 'updatebee' ),
				)
			);
		}

		$schedule_options = array_map(
			static function ( $value ) {
				return is_string( $value ) ? sanitize_text_field( $value ) : $value;
			},
			$schedule_options
		);

		$week = $schedule_options['week'] ?? '1';
		if ( 'week-one' === $week ) {
			$week = '1';
		} elseif ( 'week-two' === $week ) {
			$week = '2';
		}

		$options_to_save = array(
			'to_field'             => $to_field,
			'bcc_field'            => $bcc_field,
			'subject'              => $subject,
			'include_core'         => $include_core,
			'include_plugins'      => $include_plugins,
			'include_themes'       => $include_themes,
			'include_translations' => $include_translations,
			'send_schedule'        => $send_schedule,
			'schedule'             => $schedule_options['schedule'] ?? 'twelve-hours',
			'day'                  => $schedule_options['day'] ?? 'sunday',
			'week'                 => $week,
			'start_time'           => $schedule_options['startTime'] ?? '00:00',
			'day_num'              => $schedule_options['dayNum'] ?? '1',
			'log_cutoff_days'      => $log_cutoff_days,
		);

		update_site_option( static::get_options_key(), $options_to_save );

		wp_clear_scheduled_hook( static::get_cron_hook() );
		if ( 'custom' === $send_schedule ) {
			Schedules::set_cron_events( $options_to_save, static::get_cron_hook() );
		}

		wp_send_json_success( array() );
	}

	/**
	 * Ajax: reset to defaults.
	 */
	public function ajax_reset_options(): void {
		if ( ! Functions::can_access_options() ) {
			wp_send_json_error();
		}

		check_ajax_referer( static::get_reset_nonce_action(), 'nonce' );

		update_site_option( static::get_options_key(), static::get_defaults() );
		wp_clear_scheduled_hook( static::get_cron_hook() );

		$options_to_return = array();
		foreach ( static::get_defaults() as $field => $value ) {
			$options_to_return[ Functions::to_camelcase( $field ) ] = $value;
		}

		wp_send_json_success( $options_to_return );
	}

	/**
	 * Ajax: preview HTML in browser.
	 */
	public function ajax_preview_email(): void {
		if ( ! Functions::can_access_options() ) {
			wp_die( esc_html__( 'Security check failed.', 'updatebee' ) );
		}

		$nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, static::get_preview_nonce_action() ) ) {
			wp_die( esc_html__( 'Security check failed.', 'updatebee' ) );
		}

		$preview_options = static::get_options();
		$cutoff_days     = absint( $preview_options['log_cutoff_days'] ?? 7 );
		if ( $cutoff_days < 1 ) {
			$cutoff_days = 7;
		}
		$cutoff_days = (int) apply_filters( 'updatebee_email_log_cutoff_days', $cutoff_days, static::get_log_status() );

		$preview_last_send = (int) get_site_option( static::get_last_send_key(), 0 );
		$effective_cutoff  = $preview_last_send;
		if ( $cutoff_days > 0 ) {
			$max_cutoff       = time() - ( $cutoff_days * DAY_IN_SECONDS );
			$effective_cutoff = max( $effective_cutoff, $max_cutoff );
		}

		$supported = array(
			'plugins'      => true,
			'themes'       => true,
			'core'         => true,
			'translations' => true,
		);

		if ( 'success' === static::get_log_status() ) {
			$updates = Logs::get_success_logs( $supported, $effective_cutoff );
		} else {
			$updates = Logs::get_failure_logs( $supported, $effective_cutoff );
		}

		if ( empty( $updates ) ) {
			wp_die( esc_html__( 'There are no records to retrieve.', 'updatebee' ) );
		}

		$email_body = Emails::get_update_report_email(
			$updates,
			static::get_report_title(),
			static::get_report_intro(),
			static::get_cron_hook()
		);

		add_filter( 'safe_style_css', array( Emails::class, 'allow_email_styles' ) );
		echo wp_kses( $email_body, Functions::get_kses_allowed_html( false, true ) );
		remove_filter( 'safe_style_css', array( Emails::class, 'allow_email_styles' ) );
		exit;
	}
}
