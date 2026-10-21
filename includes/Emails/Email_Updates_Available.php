<?php
/**
 * Available updates email reports.
 *
 * @package updatebee
 */

namespace UpdateBee\Emails;

/**
 * Automatic available updates reports.
 */
class Email_Updates_Available extends Email_Report {

	public const CRON_HOOK     = 'updatebee_email_updates_available_report';
	public const OPTIONS_KEY   = 'updatebee_email_updates_available';
	public const LAST_SEND_KEY = 'updatebee_email_updates_available_last_send';

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
		return __( 'Automatic available updates', 'updatebee' );
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
