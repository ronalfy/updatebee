<?php
/**
 * Available updates email reports.
 *
 * @package updatebee
 */

namespace UpdateBee\Emails;

use UpdateBee\Functions;
use UpdateBee\Slack\Slack_Available_Updates;

/**
 * Scheduled available updates reports (email and optional Slack).
 */
class Email_Updates_Available extends Email_Report {

	public const CRON_HOOK     = 'updatebee_email_updates_available_report';
	public const OPTIONS_KEY   = 'updatebee_email_updates_available';
	public const LAST_SEND_KEY = 'updatebee_email_updates_available_last_send';

	/**
	 * {@inheritDoc}
	 */
	public static function get_defaults(): array {
		$defaults                 = parent::get_defaults();
		$defaults['notify_slack'] = false;

		return $defaults;
	}

	/**
	 * Register Send now ajax handler.
	 */
	public function run(): void {
		parent::run();
		add_action( 'wp_ajax_updatebee_send_email_updates_available_now', array( $this, 'ajax_send_now' ) );
	}

	/**
	 * Available updates are not tied to automatic update completion logs.
	 *
	 * @param array<int, array<string, mixed>> $updates Update batch from logs.
	 */
	public function maybe_send_immediate_report( array $updates ): void {
		unset( $updates );
	}

	/**
	 * Send scheduled email and optional Slack digest.
	 */
	public function cron_send_report(): void {
		$options = static::get_options();
		if ( 'custom' !== $options['send_schedule'] ) {
			return;
		}

		$this->deliver_report( $options, true, true );
	}

	/**
	 * Send the available-updates report immediately from the admin UI.
	 */
	public function ajax_send_now(): void {
		if ( ! Functions::can_access_options() ) {
			wp_send_json_error();
		}

		check_ajax_referer( 'updatebee-send-email-updates-available-now', 'nonce' );

		$options = $this->options_from_send_now_request();
		if ( is_wp_error( $options ) ) {
			wp_send_json_error(
				array(
					'message' => $options->get_error_message(),
				)
			);
			return;
		}

		$result = $this->deliver_report( $options, true, true );
		if ( empty( $result['delivered'] ) ) {
			wp_send_json_error(
				array(
					'message' => $result['message'],
				)
			);
			return;
		}

		wp_send_json_success(
			array(
				'message' => $result['message'],
			)
		);
	}

	/**
	 * Build report options from the Send now request body.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	private function options_from_send_now_request() {
		$saved = static::get_options();

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below.
		$to_field = isset( $_POST['toField'] ) ? wp_unslash( $_POST['toField'] ) : ( $saved['to_field'] ?? array() );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below.
		$bcc_field = isset( $_POST['bccField'] ) ? wp_unslash( $_POST['bccField'] ) : ( $saved['bcc_field'] ?? array() );

		if ( ! is_array( $to_field ) ) {
			$to_field = array();
		}
		if ( ! is_array( $bcc_field ) ) {
			$bcc_field = array();
		}

		$to_field  = array_values( array_filter( array_map( 'sanitize_email', $to_field ), 'is_email' ) );
		$bcc_field = array_values( array_filter( array_map( 'sanitize_email', $bcc_field ), 'is_email' ) );

		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : (string) ( $saved['subject'] ?? '' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Boolean flags validated below.
		$include_core = filter_var( wp_unslash( $_POST['includeCore'] ?? $saved['include_core'] ?? true ), FILTER_VALIDATE_BOOLEAN );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Boolean flags validated below.
		$include_plugins = filter_var( wp_unslash( $_POST['includePlugins'] ?? $saved['include_plugins'] ?? true ), FILTER_VALIDATE_BOOLEAN );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Boolean flags validated below.
		$include_themes = filter_var( wp_unslash( $_POST['includeThemes'] ?? $saved['include_themes'] ?? true ), FILTER_VALIDATE_BOOLEAN );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Boolean flags validated below.
		$include_translations = filter_var( wp_unslash( $_POST['includeTranslations'] ?? $saved['include_translations'] ?? true ), FILTER_VALIDATE_BOOLEAN );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Boolean flags validated below.
		$notify_slack = filter_var( wp_unslash( $_POST['notifySlack'] ?? $saved['notify_slack'] ?? false ), FILTER_VALIDATE_BOOLEAN );

		if ( empty( $to_field ) && empty( $bcc_field ) && ! $notify_slack ) {
			return new \WP_Error(
				'updatebee_no_recipients',
				__( 'Enter at least one email address or enable Slack notifications.', 'updatebee' )
			);
		}

		return array(
			'to_field'             => $to_field,
			'bcc_field'            => $bcc_field,
			'subject'              => $subject,
			'include_core'         => $include_core,
			'include_plugins'      => $include_plugins,
			'include_themes'       => $include_themes,
			'include_translations' => $include_translations,
			'notify_slack'         => $notify_slack,
			'send_schedule'        => $saved['send_schedule'] ?? 'none',
		);
	}

	/**
	 * Refresh updates, build rows, and send email and/or Slack.
	 *
	 * @param array<string, mixed> $options         Report options.
	 * @param bool                 $force_refresh   Whether to force an update check.
	 * @param bool                 $update_last_send Whether to store the last send timestamp.
	 * @return array{delivered: bool, message: string}
	 */
	private function deliver_report( array $options, bool $force_refresh, bool $update_last_send ): array {
		$data = $force_refresh ? Available_Updates::refresh_and_capture() : Available_Updates::current_transients();
		$rows = Available_Updates::build_rows_from_data( $data, static::get_supported_types( $options ) );
		if ( empty( $rows ) ) {
			return array(
				'delivered' => false,
				'message'   => __( 'There are no updates available right now to send.', 'updatebee' ),
			);
		}

		$sent_email = false;
		$sent_slack = false;

		if ( $this->should_send_email( $options ) ) {
			$body = Emails::get_update_report_email(
				$rows,
				static::get_report_title(),
				static::get_report_intro(),
				static::get_cron_hook()
			);
			$this->send_email( $body, $options, $update_last_send );
			$sent_email = true;
		}

		if ( ! empty( $options['notify_slack'] ) ) {
			$slack = new Slack_Available_Updates();
			$slack->send_report( $rows );
			$sent_slack = true;
		}

		if ( ! $sent_email && ! $sent_slack ) {
			return array(
				'delivered' => false,
				'message'   => __( 'Nothing was sent. Add recipients or enable Slack.', 'updatebee' ),
			);
		}

		if ( $sent_email && $sent_slack ) {
			$message = __( 'Available updates report sent by email and Slack.', 'updatebee' );
		} elseif ( $sent_slack ) {
			$message = __( 'Available updates report sent to Slack.', 'updatebee' );
		} else {
			$message = __( 'Available updates report sent by email.', 'updatebee' );
		}

		return array(
			'delivered' => true,
			'message'   => $message,
		);
	}

	/**
	 * Whether the report has at least one email recipient configured.
	 *
	 * @param array<string, mixed> $options Report options.
	 * @return bool
	 */
	private function should_send_email( array $options ): bool {
		$to  = isset( $options['to_field'] ) && is_array( $options['to_field'] ) ? $options['to_field'] : array();
		$bcc = isset( $options['bcc_field'] ) && is_array( $options['bcc_field'] ) ? $options['bcc_field'] : array();

		foreach ( array_merge( $to, $bcc ) as $email ) {
			if ( is_email( $email ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Preview HTML from current update transients.
	 */
	public function ajax_preview_email(): void {
		if ( ! Functions::can_access_options() ) {
			wp_die( esc_html__( 'Security check failed.', 'updatebee' ) );
		}

		$nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, static::get_preview_nonce_action() ) ) {
			wp_die( esc_html__( 'Security check failed.', 'updatebee' ) );
		}

		$options = static::get_options();
		$rows    = Available_Updates::build_rows_from_data(
			Available_Updates::refresh_and_capture(),
			static::get_supported_types( $options )
		);

		if ( empty( $rows ) ) {
			wp_die( esc_html__( 'There are no updates available right now to send.', 'updatebee' ) );
		}

		$email_body = Emails::get_update_report_email(
			$rows,
			static::get_report_title(),
			static::get_report_intro(),
			static::get_cron_hook()
		);

		add_filter( 'safe_style_css', array( Emails::class, 'allow_email_styles' ) );
		echo wp_kses( $email_body, Functions::get_kses_allowed_html( false, true ) );
		remove_filter( 'safe_style_css', array( Emails::class, 'allow_email_styles' ) );
		exit;
	}

	/**
	 * {@inheritDoc}
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

		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Boolean flags validated below.
		$include_core = filter_var( wp_unslash( $_POST['includeCore'] ?? false ), FILTER_VALIDATE_BOOLEAN );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Boolean flags validated below.
		$include_plugins = filter_var( wp_unslash( $_POST['includePlugins'] ?? false ), FILTER_VALIDATE_BOOLEAN );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Boolean flags validated below.
		$include_themes = filter_var( wp_unslash( $_POST['includeThemes'] ?? false ), FILTER_VALIDATE_BOOLEAN );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Boolean flags validated below.
		$include_translations = filter_var( wp_unslash( $_POST['includeTranslations'] ?? false ), FILTER_VALIDATE_BOOLEAN );
		$send_schedule        = isset( $_POST['sendSchedule'] ) ? sanitize_text_field( wp_unslash( $_POST['sendSchedule'] ) ) : 'none';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Boolean flags validated below.
		$notify_slack = filter_var( wp_unslash( $_POST['notifySlack'] ?? false ), FILTER_VALIDATE_BOOLEAN );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below.
		$schedule_options = isset( $_POST['scheduleOptions'] ) ? wp_unslash( $_POST['scheduleOptions'] ) : array();
		if ( ! is_array( $schedule_options ) ) {
			$schedule_options = array();
		}

		$to_field  = array_values( array_filter( array_map( 'sanitize_email', $to_field ), 'is_email' ) );
		$bcc_field = array_values( array_filter( array_map( 'sanitize_email', $bcc_field ), 'is_email' ) );

		if ( 'custom' === $send_schedule && empty( $to_field ) && empty( $bcc_field ) && ! $notify_slack ) {
			wp_send_json_error(
				array(
					'message' => __( 'Enter at least one email address or enable Slack notifications.', 'updatebee' ),
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
			'notify_slack'         => $notify_slack,
			'schedule'             => $schedule_options['schedule'] ?? 'twelve-hours',
			'day'                  => $schedule_options['day'] ?? 'sunday',
			'week'                 => $week,
			'start_time'           => $schedule_options['startTime'] ?? '00:00',
			'day_num'              => $schedule_options['dayNum'] ?? '1',
		);

		update_site_option( static::get_options_key(), $options_to_save );

		wp_clear_scheduled_hook( static::get_cron_hook() );
		if ( 'custom' === $send_schedule ) {
			\UpdateBee\Cron\Schedules::set_cron_events( $options_to_save, static::get_cron_hook() );
		}

		wp_send_json_success( array() );
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function get_options_key(): string {
		return self::OPTIONS_KEY;
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function get_last_send_key(): string {
		return self::LAST_SEND_KEY;
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function get_cron_hook(): string {
		return self::CRON_HOOK;
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function get_log_status(): string {
		return 'available';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function get_report_title(): string {
		return __( 'Available updates', 'updatebee' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function get_report_intro(): string {
		return __( 'Your site has available updates. Here is what needs upgrading.', 'updatebee' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function get_default_subject(): string {
		/* translators: %s: site name. */
		return sprintf( __( 'Available updates on %s', 'updatebee' ), get_bloginfo( 'name' ) );
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function get_preview_action(): string {
		return 'updatebee_preview_email_updates_available';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function get_preview_nonce_action(): string {
		return 'updatebee-preview-email-updates-available';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function get_retrieve_action(): string {
		return 'updatebee_retrieve_email_updates_available_options';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function get_save_action(): string {
		return 'updatebee_save_email_updates_available_options';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function get_reset_action(): string {
		return 'updatebee_reset_email_updates_available_options';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function get_retrieve_nonce_action(): string {
		return 'updatebee-retrieve-email-updates-available-settings';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function get_save_nonce_action(): string {
		return 'updatebee-save-email-updates-available-settings';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function get_reset_nonce_action(): string {
		return 'updatebee-reset-email-updates-available-settings';
	}
}
