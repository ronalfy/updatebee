<?php
/**
 * Immediate fatal error email reports.
 *
 * @package updatebee
 */

namespace UpdateBee\Emails;

use UpdateBee\Functions;
use UpdateBee\Options;
use UpdateBee\Updates\Fatal_Detection;

/**
 * Send an immediate email when a post-update fatal is logged.
 */
class Email_Fatals {

	/**
	 * Register completion hook.
	 */
	public function run(): void {
		add_action( 'updatebee_automatic_updates_complete', array( $this, 'maybe_send_fatal_report' ), 9, 1 );
	}

	/**
	 * Send immediately when detection is on and the batch contains fatals.
	 *
	 * @param array<int, array<string, mixed>> $updates Logged update rows.
	 */
	public function maybe_send_fatal_report( array $updates ): void {
		if ( ! Fatal_Detection::is_enabled() ) {
			return;
		}

		$filtered = self::filter_fatal_updates( $updates );
		if ( empty( $filtered ) ) {
			return;
		}

		$recipients = self::get_recipients();
		if ( empty( $recipients ) ) {
			return;
		}

		$body = Emails::get_update_report_email(
			$filtered,
			__( 'Fatal error after automatic updates', 'updatebee' ),
			__( 'A PHP fatal error was detected after automatic updates. UpdateBee did not deactivate any plugins. WordPress Recovery Mode may still email a login link separately. WordPress may have restored a previous plugin version on its own.', 'updatebee' ),
			''
		);

		$this->send_email( $body, $recipients );
	}

	/**
	 * Keep only fatal log rows.
	 *
	 * @param array<int, array<string, mixed>> $updates Update rows.
	 * @return array<int, array<string, mixed>>
	 */
	public static function filter_fatal_updates( array $updates ): array {
		$filtered = array();
		foreach ( $updates as $update ) {
			if ( ! is_array( $update ) ) {
				continue;
			}
			if ( 'fatal' !== ( $update['update_status'] ?? '' ) ) {
				continue;
			}
			$filtered[] = $update;
		}

		return $filtered;
	}

	/**
	 * Recipients for fatal reports, falling back to the network admin email.
	 *
	 * @return array<int, string>
	 */
	public static function get_recipients(): array {
		$value  = Options::get_options( 'fatal_email_recipients' );
		$emails = array();
		if ( is_array( $value ) ) {
			foreach ( $value as $email ) {
				if ( ! is_string( $email ) ) {
					continue;
				}
				$email = sanitize_email( $email );
				if ( is_email( $email ) ) {
					$emails[] = $email;
				}
			}
		}

		$emails = array_values( array_unique( $emails ) );

		/**
		 * Filter fatal report recipients.
		 *
		 * @since 1.0.0
		 *
		 * @param array<int, string> $emails Recipient list.
		 */
		$filtered = apply_filters( 'updatebee_fatal_email_recipients', $emails );
		if ( is_array( $filtered ) ) {
			$emails = array();
			foreach ( $filtered as $email ) {
				if ( is_string( $email ) && is_email( $email ) ) {
					$emails[] = $email;
				}
			}
			$emails = array_values( array_unique( $emails ) );
		}

		if ( ! empty( $emails ) ) {
			return $emails;
		}

		$admin_email = sanitize_email( (string) get_site_option( 'admin_email' ) );
		if ( is_email( $admin_email ) ) {
			return array( $admin_email );
		}

		return array();
	}

	/**
	 * Recipients shown on the Emails card.
	 *
	 * @return array<int, string>
	 */
	public static function get_recipients_for_display(): array {
		return self::get_recipients();
	}

	/**
	 * Send the HTML fatal report.
	 *
	 * @param string             $email_body HTML body.
	 * @param array<int, string> $recipients To addresses.
	 */
	private function send_email( string $email_body, array $recipients ): void {
		if ( '' === trim( $email_body ) || empty( $recipients ) ) {
			return;
		}

		$subject = sprintf(
			/* translators: %s: site name. */
			__( 'Fatal error after automatic updates on %s', 'updatebee' ),
			get_bloginfo( 'name' )
		);

		$headers = array(
			sprintf(
				'From: %s <%s>',
				sanitize_text_field( get_bloginfo( 'name' ) ),
				sanitize_text_field( get_bloginfo( 'admin_email' ) )
			),
			'Content-Type: text/html; charset=UTF-8',
		);

		add_filter( 'safe_style_css', array( Emails::class, 'allow_email_styles' ) );
		$email_body = wp_kses( $email_body, Functions::get_kses_allowed_html( false, true ) );
		wp_mail( $recipients, $subject, $email_body, $headers );
		remove_filter( 'safe_style_css', array( Emails::class, 'allow_email_styles' ) );
	}
}
