<?php
/**
 * Post payloads to the configured Slack incoming webhook.
 *
 * @package updatebee
 */

namespace UpdateBee\Slack;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use UpdateBee\Options;

/**
 * Shared Slack webhook transport.
 */
class Slack_Webhook {

	/**
	 * Whether a valid Slack webhook URL is stored.
	 *
	 * Only HTTPS URLs are accepted so the webhook payload is never sent in clear text.
	 *
	 * @return bool
	 */
	public static function is_configured(): bool {
		$webhook = Options::get_options( 'slack_webhook_url' );
		return is_string( $webhook )
			&& str_starts_with( $webhook, 'https://' )
			&& false !== filter_var( $webhook, FILTER_VALIDATE_URL );
	}

	/**
	 * Post a Block Kit payload when a webhook URL is saved.
	 *
	 * @param array<string, mixed> $message Block Kit payload.
	 */
	public static function post( array $message ): void {
		if ( ! self::is_configured() || empty( $message ) ) {
			return;
		}

		$webhook = Options::get_options( 'slack_webhook_url' );

		// The safe variant rejects loopback and private-network hosts, so an admin-supplied URL cannot reach internal services (SSRF).
		wp_safe_remote_post(
			esc_url_raw( $webhook ),
			array(
				'body'        => wp_json_encode( $message ),
				// Do not follow redirects: a redirect could point at an http:// URL and send the report in clear text.
				'redirection' => 0,
				'headers'     => array(
					'Content-Type' => 'application/json; charset=utf-8',
				),
			)
		);
	}
}
