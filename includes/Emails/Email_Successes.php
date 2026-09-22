<?php
/**
 * Success email reports.
 *
 * @package updatebee
 */

namespace UpdateBee\Emails;

/**
 * Automatic update success reports.
 */
class Email_Successes extends Email_Report {

	public const CRON_HOOK     = 'updatebee_email_success_report';
	public const OPTIONS_KEY   = 'updatebee_email_successes';
	public const LAST_SEND_KEY = 'updatebee_email_success_last_send';

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
		return 'success';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function get_report_title(): string {
		return __( 'Automatic update successes', 'updatebee' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function get_report_intro(): string {
		return __( 'Your site has had some recent automatic updates. Here is what changed.', 'updatebee' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function get_default_subject(): string {
		/* translators: %s: site name. */
		return sprintf( __( 'Automatic update successes on %s', 'updatebee' ), get_bloginfo( 'name' ) );
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function get_preview_action(): string {
		return 'updatebee_preview_email_successes';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function get_preview_nonce_action(): string {
		return 'updatebee-preview-email-successes';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function get_retrieve_action(): string {
		return 'updatebee_retrieve_email_success_options';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function get_save_action(): string {
		return 'updatebee_save_email_success_options';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function get_reset_action(): string {
		return 'updatebee_reset_email_success_options';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function get_retrieve_nonce_action(): string {
		return 'updatebee-retrieve-email-success-settings';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function get_save_nonce_action(): string {
		return 'updatebee-save-email-success-settings';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function get_reset_nonce_action(): string {
		return 'updatebee-reset-email-success-settings';
	}
}
