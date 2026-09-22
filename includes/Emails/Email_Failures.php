<?php
/**
 * Failure email reports.
 *
 * @package updatebee
 */

namespace UpdateBee\Emails;

/**
 * Automatic update failure reports.
 */
class Email_Failures extends Email_Report {

	public const CRON_HOOK     = 'updatebee_email_failure_report';
	public const OPTIONS_KEY   = 'updatebee_email_failures';
	public const LAST_SEND_KEY = 'updatebee_email_failure_last_send';

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
		return 'failure';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function get_report_title(): string {
		return __( 'Automatic update failures', 'updatebee' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function get_report_intro(): string {
		return __( 'Your site has had some recent automatic update failures. Here is what changed.', 'updatebee' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function get_default_subject(): string {
		/* translators: %s: site name. */
		return sprintf( __( 'Automatic update failures on %s', 'updatebee' ), get_bloginfo( 'name' ) );
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function get_preview_action(): string {
		return 'updatebee_preview_email_failures';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function get_preview_nonce_action(): string {
		return 'updatebee-preview-email-failures';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function get_retrieve_action(): string {
		return 'updatebee_retrieve_email_failure_options';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function get_save_action(): string {
		return 'updatebee_save_email_failure_options';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function get_reset_action(): string {
		return 'updatebee_reset_email_failure_options';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function get_retrieve_nonce_action(): string {
		return 'updatebee-retrieve-email-failure-settings';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function get_save_nonce_action(): string {
		return 'updatebee-save-email-failure-settings';
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function get_reset_nonce_action(): string {
		return 'updatebee-reset-email-failure-settings';
	}
}
