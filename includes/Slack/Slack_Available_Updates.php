<?php
/**
 * Slack reports for scheduled available-updates digests.
 *
 * @package updatebee
 */

namespace UpdateBee\Slack;

use UpdateBee\Functions;

/**
 * Post available updates to Slack when enabled on the report.
 */
class Slack_Available_Updates {

	/**
	 * Send a Block Kit digest for pending updates.
	 *
	 * @param array<int, array<string, mixed>> $updates Report rows.
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
		 * @param array<int, array<string, string>> $rows    Normalized rows.
		 */
		$message = apply_filters( 'updatebee_slack_available_updates_message', $message, $rows );
		if ( ! is_array( $message ) || empty( $message ) ) {
			return;
		}

		Slack_Webhook::post( $message );
	}

	/**
	 * Build the Block Kit payload.
	 *
	 * @param array<int, array<string, string>> $rows Normalized rows.
	 * @return array<string, mixed>
	 */
	public function build_message( array $rows ): array {
		$site_name = (string) get_bloginfo( 'name' );
		$site_url  = home_url( '/' );
		$total     = count( $rows );

		$counts = array(
			'core'        => 0,
			'plugin'      => 0,
			'theme'       => 0,
			'translation' => 0,
		);
		foreach ( $rows as $row ) {
			$type = $row['update_type'];
			if ( isset( $counts[ $type ] ) ) {
				++$counts[ $type ];
			}
		}

		$fields = array(
			array(
				'type' => 'mrkdwn',
				'text' => __( '*Site:*', 'updatebee' ) . ' ' . esc_html( $site_name ),
			),
			array(
				'type' => 'mrkdwn',
				'text' => __( '*Total updates:*', 'updatebee' ) . ' ' . (string) $total,
			),
			array(
				'type' => 'mrkdwn',
				'text' => __( '*Plugins:*', 'updatebee' ) . ' ' . (string) $counts['plugin'],
			),
			array(
				'type' => 'mrkdwn',
				'text' => __( '*Themes:*', 'updatebee' ) . ' ' . (string) $counts['theme'],
			),
		);

		$blocks = array(
			array(
				'type' => 'section',
				'text' => array(
					'type' => 'mrkdwn',
					'text' => sprintf(
						/* translators: 1: Site URL, 2: Site name. */
						__( '*Updates available on <%1$s|%2$s>*', 'updatebee' ),
						esc_url( $site_url ),
						esc_html( $site_name )
					),
				),
			),
			array(
				'type' => 'header',
				'text' => array(
					'type' => 'plain_text',
					'text' => __( 'Available updates', 'updatebee' ),
				),
			),
			array(
				'type' => 'divider',
			),
			array(
				'type'   => 'section',
				'fields' => $fields,
			),
		);

		$shown = 0;
		foreach ( Slack_Row_Groups::group_rows( $rows ) as $update_type => $type_rows ) {
			$lines = array();
			foreach ( $type_rows as $row ) {
				if ( $shown >= Slack_Row_Groups::ITEM_LIMIT ) {
					break;
				}
				$line = sprintf(
					'*%1$s* — %2$s → %3$s',
					esc_html( $row['name'] ),
					esc_html( $row['from_version'] ? $row['from_version'] : '—' ),
					esc_html( $row['to_version'] )
				);
				if ( '' !== $row['detail_url'] ) {
					$label = '' !== $row['detail_label'] ? $row['detail_label'] : __( 'Details', 'updatebee' );
					$line .= ' — <' . esc_url( $row['detail_url'] ) . '|' . esc_html( $label ) . '>';
				}
				$lines[] = $line;
				++$shown;
			}

			if ( empty( $lines ) ) {
				continue;
			}

			$blocks[] = array(
				'type' => 'divider',
			);
			$blocks[] = array(
				'type' => 'header',
				'text' => array(
					'type' => 'plain_text',
					'text' => Slack_Row_Groups::type_heading( (string) $update_type ),
				),
			);
			$blocks[] = array(
				'type' => 'section',
				'text' => array(
					'type' => 'mrkdwn',
					'text' => implode( "\n", $lines ),
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
						/* translators: %d: Number of updates omitted from the Slack message. */
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
						'text'  => __( 'Updates in admin', 'updatebee' ),
						'emoji' => true,
					),
					'style'     => 'primary',
					'value'     => 'view_updates',
					'action_id' => 'view_updates',
					'url'       => esc_url_raw( admin_url( 'update-core.php' ) ),
				),
				array(
					'type'      => 'button',
					'text'      => array(
						'type'  => 'plain_text',
						'text'  => Functions::get_plugin_name(),
						'emoji' => true,
					),
					'value'     => 'view_updatebee',
					'action_id' => 'view_updatebee',
					'url'       => esc_url_raw( Functions::get_settings_url() ),
				),
			),
		);

		return array(
			'text'   => sprintf(
				/* translators: 1: Site name, 2: Total update count. */
				__( '%1$d updates available on %2$s.', 'updatebee' ),
				$total,
				$site_name
			),
			'blocks' => $blocks,
		);
	}

	/**
	 * Keep rows that have a name.
	 *
	 * @param array<int, array<string, mixed>> $updates Report rows.
	 * @return array<int, array<string, string>>
	 */
	private function normalize_rows( array $updates ): array {
		$rows = array();
		foreach ( $updates as $update ) {
			if ( ! is_array( $update ) ) {
				continue;
			}
			$name = isset( $update['name'] ) ? (string) $update['name'] : '';
			if ( '' === $name ) {
				continue;
			}
			$rows[] = array(
				'name'          => $name,
				'update_type'   => isset( $update['update_type'] ) ? (string) $update['update_type'] : '',
				'from_version'  => isset( $update['from_version'] ) ? (string) $update['from_version'] : '',
				'to_version'    => isset( $update['to_version'] ) ? (string) $update['to_version'] : '',
				'detail_url'    => isset( $update['detail_url'] ) ? (string) $update['detail_url'] : '',
				'detail_label'  => isset( $update['detail_label'] ) ? (string) $update['detail_label'] : '',
				'update_status' => isset( $update['update_status'] ) ? (string) $update['update_status'] : '',
			);
		}

		return $rows;
	}
}
