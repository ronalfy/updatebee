<?php
/**
 * Post payloads to the configured Slack incoming webhook.
 *
 * @package updatebee
 */

namespace UpdateBee\Slack;

use UpdateBee\Options;

/**
 * Shared Slack webhook transport.
 */
class Slack_Webhook {

	/**
	 * Whether a valid Slack webhook URL is stored.
	 *
	 * @return bool
	 */
	public static function is_configured(): bool {
		$webhook = Options::get_options( 'slack_webhook_url' );
		return is_string( $webhook ) && '' !== $webhook && false !== filter_var( $webhook, FILTER_VALIDATE_URL );
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

		wp_remote_post(
			esc_url( $webhook ),
			array(
				'body'    => wp_json_encode( $message ),
				'headers' => array(
					'Content-Type' => 'application/json; charset=utf-8',
				),
			)
		);
	}
}
