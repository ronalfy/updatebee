<?php
/**
 * Slack reports for post-update fatal errors.
 *
 * @package updatebee
 */

namespace UpdateBee\Slack;

use UpdateBee\Functions;

/**
 * Post a dedicated Slack alert when fatals are detected after automatic updates.
 */
class Slack_Fatals {

	/**
	 * Send a Block Kit fatal-detection alert.
	 *
	 * @param array<int, array<string, mixed>> $updates Logged update rows.
	 */
	public function send_report( array $updates ): void {
		$rows = $this->normalize_rows( $updates );
		if ( empty( $rows ) ) {
			return;
		}

		$message = $this->build_message( $rows );

		/**
		 * Filter the Slack payload before it is sent.
		 *
		 * @param array<string, mixed>              $message Block Kit payload.
		 * @param array<int, array<string, string>> $rows    Normalized fatal rows.
		 */
		$message = apply_filters( 'updatebee_slack_fatal_message', $message, $rows );
		if ( ! is_array( $message ) || empty( $message ) ) {
			return;
		}

		Slack_Webhook::post( $message );
	}

	/**
	 * Build the Block Kit payload for fatal rows.
	 *
	 * @param array<int, array<string, string>> $rows Normalized fatal rows.
	 * @return array<string, mixed>
	 */
	public function build_message( array $rows ): array {
		$site_name = (string) get_bloginfo( 'name' );
		$site_url  = home_url( '/' );
		$logs_url  = Functions::get_settings_url( 'logs' );
		$total     = count( $rows );

		$fields = array(
			array(
				'type' => 'mrkdwn',
				'text' => __( '*Site:*', 'updatebee' ) . ' ' . esc_html( $site_name ),
			),
			array(
				'type' => 'mrkdwn',
				'text' => __( '*Trigger:*', 'updatebee' ) . ' ' . __( 'Automatic', 'updatebee' ),
			),
			array(
				'type' => 'mrkdwn',
				'text' => __( '*Fatals:*', 'updatebee' ) . ' ' . (string) $total,
			),
		);

		$blocks = array(
			array(
				'type' => 'section',
				'text' => array(
					'type' => 'mrkdwn',
					'text' => sprintf(
						/* translators: 1: Site URL, 2: Site name. */
						__( '*Fatal error after automatic updates on <%1$s|%2$s>*', 'updatebee' ),
						esc_url( $site_url ),
						esc_html( $site_name )
					),
				),
			),
			array(
				'type' => 'header',
				'text' => array(
					'type' => 'plain_text',
					'text' => __( 'Fatals', 'updatebee' ),
				),
			),
			array(
				'type' => 'divider',
			),
			array(
				'type'   => 'section',
				'fields' => $fields,
			),
			array(
				'type' => 'section',
				'text' => array(
					'type' => 'mrkdwn',
					'text' => __( 'This is a fatal detection alert. UpdateBee did not deactivate any plugins. WordPress Recovery Mode may still email a login link separately. WordPress may have restored a previous plugin version on its own.', 'updatebee' ),
				),
			),
		);

		$shown = 0;
		$lines = array();
		foreach ( $rows as $row ) {
			if ( $shown >= Slack_Row_Groups::ITEM_LIMIT ) {
				break;
			}

			$type_label = Slack_Row_Groups::type_heading( $row['update_type'] );
			$line       = sprintf(
				'*%1$s* — %2$s — %3$s → %4$s',
				esc_html( $row['name'] ),
				esc_html( $type_label ),
				esc_html( '' !== $row['from_version'] ? $row['from_version'] : '—' ),
				esc_html( '' !== $row['to_version'] ? $row['to_version'] : '—' )
			);
			if ( '' !== $row['fatal_message'] ) {
				$line .= "\n" . esc_html( $row['fatal_message'] );
			}
			$lines[] = $line;
			++$shown;
		}

		if ( ! empty( $lines ) ) {
			$blocks[] = array(
				'type' => 'divider',
			);
			$blocks[] = array(
				'type' => 'section',
				'text' => array(
					'type' => 'mrkdwn',
					'text' => implode( "\n\n", $lines ),
				),
			);
		}

		$hidden = count( $rows ) - $shown;
		if ( $hidden > 0 ) {
			$blocks[] = array(
				'type' => 'section',
				'text' => array(
					'type' => 'mrkdwn',
					'text' => sprintf(
						/* translators: %d: Number of fatals omitted from the Slack message. */
						_n( 'and %d more', 'and %d more', $hidden, 'updatebee' ),
						$hidden
					),
				),
			);
		}

		$blocks[] = array(
			'type' => 'divider',
		);
		$blocks[] = array(
			'type'     => 'actions',
			'elements' => array(
				array(
					'type'      => 'button',
					'text'      => array(
						'type'  => 'plain_text',
						'text'  => __( 'View logs', 'updatebee' ),
						'emoji' => true,
					),
					'style'     => 'primary',
					'value'     => 'view_logs',
					'action_id' => 'view_logs',
					'url'       => esc_url_raw( $logs_url ),
				),
			),
		);

		return array(
			'text'   => sprintf(
				/* translators: 1: Site name, 2: Fatal count. */
				__( 'Fatal detection alert after automatic updates on %1$s. %2$d fatal(s) detected.', 'updatebee' ),
				$site_name,
				$total
			),
			'blocks' => $blocks,
		);
	}

	/**
	 * Keep fatal rows that have a name.
	 *
	 * @param array<int, array<string, mixed>> $updates Logged update rows.
	 * @return array<int, array<string, string>>
	 */
	private function normalize_rows( array $updates ): array {
		$rows = array();
		foreach ( $updates as $update ) {
			if ( ! is_array( $update ) ) {
				continue;
			}
			if ( 'fatal' !== ( $update['update_status'] ?? '' ) ) {
				continue;
			}

			$name = isset( $update['name'] ) ? (string) $update['name'] : '';
			if ( '' === $name ) {
				continue;
			}

			$fatal_message = isset( $update['fatal_message'] ) ? trim( (string) $update['fatal_message'] ) : '';
			if ( '' === $fatal_message && isset( $update['raw'] ) ) {
				$fatal_message = self::first_line( (string) $update['raw'] );
			}

			$rows[] = array(
				'name'          => $name,
				'update_type'   => isset( $update['update_type'] ) ? (string) $update['update_type'] : '',
				'from_version'  => isset( $update['from_version'] ) ? (string) $update['from_version'] : '',
				'to_version'    => isset( $update['to_version'] ) ? (string) $update['to_version'] : '',
				'fatal_message' => $fatal_message,
				'update_status' => 'fatal',
			);
		}

		return $rows;
	}

	/**
	 * First non-empty line of raw notes.
	 *
	 * @param string $notes Raw upgrade notes.
	 * @return string
	 */
	private static function first_line( string $notes ): string {
		$notes = trim( $notes );
		if ( '' === $notes ) {
			return '';
		}

		$lines = preg_split( '/\r\n|\r|\n/', $notes );
		if ( ! is_array( $lines ) || empty( $lines[0] ) ) {
			return '';
		}

		return (string) $lines[0];
	}
}
