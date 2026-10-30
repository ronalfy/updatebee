<?php
/**
 * Slack Block Kit reports after updates finish.
 *
 * @package updatebee
 */

namespace UpdateBee\Slack;

use UpdateBee\Functions;
use UpdateBee\Options;

/**
 * Post one Slack report for a finished automatic or manual update batch.
 */
class Slack_Report {

	/**
	 * Register completion hooks.
	 */
	public function run(): void {
		add_action( 'updatebee_automatic_updates_complete', array( $this, 'send_automatic_report' ), 10, 1 );
		add_action( 'updatebee_manual_updates_complete', array( $this, 'send_manual_report' ), 10, 1 );
	}

	/**
	 * Send the report for an automatic batch.
	 *
	 * @param array<int, array<string, mixed>> $updates Log rows from this request.
	 */
	public function send_automatic_report( array $updates ): void {
		$this->send_report( $updates, 'automatic' );
	}

	/**
	 * Send the report for a manual batch.
	 *
	 * @param array<int, array<string, mixed>> $updates Log rows from this request.
	 */
	public function send_manual_report( array $updates ): void {
		$this->send_report( $updates, 'manual' );
	}

	/**
	 * Post a Block Kit message when a webhook is saved.
	 *
	 * @param array<int, array<string, mixed>> $updates Log rows from this request.
	 * @param string                           $offer   automatic|manual.
	 */
	public function send_report( array $updates, string $offer ): void {
		if ( ! Slack_Webhook::is_configured() || ! Options::get_options( 'slack_enabled' ) ) {
			return;
		}

		$rows = $this->normalize_rows( $updates );
		if ( empty( $rows ) ) {
			return;
		}

		$message = $this->build_message( $rows, $offer );

		/**
		 * Filter the Slack payload before it is sent.
		 *
		 * @param array<string, mixed>         $message Block Kit payload.
		 * @param array<int, array<string, string>> $rows    Normalized log rows.
		 * @param string                       $offer   automatic|manual.
		 */
		$message = apply_filters( 'updatebee_slack_message', $message, $rows, $offer );
		if ( ! is_array( $message ) || empty( $message ) ) {
			return;
		}

		Slack_Webhook::post( $message );
	}

	/**
	 * Build the Block Kit payload.
	 *
	 * @param array<int, array<string, string>> $rows  Normalized log rows.
	 * @param string                            $offer automatic|manual.
	 * @return array<string, mixed>
	 */
	public function build_message( array $rows, string $offer ): array {
		$site_name = (string) get_bloginfo( 'name' );
		$site_url  = home_url( '/' );
		$logs_url  = Functions::get_settings_url( 'logs' );
		$successes = 0;
		$failures  = 0;
		$fatals    = 0;

		foreach ( $rows as $row ) {
			if ( 'success' === $row['update_status'] ) {
				++$successes;
			} elseif ( 'failure' === $row['update_status'] ) {
				++$failures;
			} elseif ( 'fatal' === $row['update_status'] ) {
				++$fatals;
			}
		}

		$trigger = ( 'manual' === $offer )
			? __( 'Manual', 'updatebee' )
			: __( 'Automatic', 'updatebee' );

		$fields = array(
			array(
				'type' => 'mrkdwn',
				'text' => __( '*Site:*', 'updatebee' ) . ' ' . esc_html( $site_name ),
			),
			array(
				'type' => 'mrkdwn',
				'text' => __( '*Trigger:*', 'updatebee' ) . ' ' . esc_html( $trigger ),
			),
			array(
				'type' => 'mrkdwn',
				'text' => __( '*Succeeded:*', 'updatebee' ) . ' ' . (string) $successes,
			),
			array(
				'type' => 'mrkdwn',
				'text' => __( '*Failed:*', 'updatebee' ) . ' ' . (string) $failures,
			),
		);

		if ( $fatals > 0 ) {
			$fields[] = array(
				'type' => 'mrkdwn',
				'text' => __( '*Fatals:*', 'updatebee' ) . ' ' . (string) $fatals,
			);
		}

		if ( 'manual' === $offer ) {
			$fields[] = array(
				'type' => 'mrkdwn',
				'text' => __( '*User:*', 'updatebee' ) . ' ' . esc_html( $this->manual_user_label( $rows ) ),
			);
		}

		$blocks = array(
			array(
				'type' => 'section',
				'text' => array(
					'type' => 'mrkdwn',
					'text' => sprintf(
						/* translators: 1: Site URL, 2: Site name. */
						__( '*Updates finished on <%1$s|%2$s>*', 'updatebee' ),
						esc_url( $site_url ),
						esc_html( $site_name )
					),
				),
			),
			array(
				'type' => 'header',
				'text' => array(
					'type' => 'plain_text',
					'text' => __( 'Update details', 'updatebee' ),
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
				$lines[] = sprintf(
					'*%1$s* — %2$s — %3$s → %4$s',
					esc_html( $row['name'] ),
					esc_html( $row['update_status'] ),
					esc_html( $row['from_version'] ),
					esc_html( $row['to_version'] )
				);
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
				/* translators: 1: Site name, 2: Automatic or Manual, 3: Success count, 4: Failure count. */
				__( 'Updates finished on %1$s (%2$s). %3$d succeeded, %4$d failed.', 'updatebee' ),
				$site_name,
				$trigger,
				$successes,
				$failures
			),
			'blocks' => $blocks,
		);
	}

	/**
	 * Keep rows that have a name.
	 *
	 * @param array<int, array<string, mixed>> $updates Log rows.
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
				'update_status' => isset( $update['update_status'] ) ? (string) $update['update_status'] : '',
				'user_id'       => isset( $update['user_id'] ) ? (string) $update['user_id'] : '0',
			);
		}

		return $rows;
	}

	/**
	 * Display name for the user who ran a manual update.
	 *
	 * @param array<int, array<string, string>> $rows Normalized rows.
	 * @return string
	 */
	private function manual_user_label( array $rows ): string {
		$user_id = 0;
		foreach ( $rows as $row ) {
			$user_id = (int) $row['user_id'];
			if ( $user_id > 0 ) {
				break;
			}
		}

		if ( $user_id < 1 ) {
			return __( 'Unknown', 'updatebee' );
		}

		$user = get_userdata( $user_id );
		if ( is_object( $user ) && isset( $user->display_name ) && '' !== (string) $user->display_name ) {
			return (string) $user->display_name;
		}

		return __( 'Unknown', 'updatebee' );
	}
}
