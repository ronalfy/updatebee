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
	 * Post a Block Kit payload when a webhook URL is saved.
	 *
	 * @param array<string, mixed> $message Block Kit payload.
	 */
	public static function post( array $message ): void {
		$webhook = Options::get_options( 'slack_webhook_url' );
		if ( ! is_string( $webhook ) || '' === $webhook || empty( $message ) ) {
			return;
		}

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
