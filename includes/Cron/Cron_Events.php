<?php
/**
 * List, run, and delete WordPress cron events (simplified WP Crontrol parity).
 *
 * @package updatebee
 */

namespace UpdateBee\Cron;

/**
 * Cron event utilities for the admin Cron Events tab.
 */
class Cron_Events {

	public const EVENTS_NONCE_ACTION = 'updatebee-cron-events';

	/**
	 * Hooks WordPress core treats as persistent (not deletable via UI).
	 *
	 * List aligned with WP Crontrol get_persistent_core_hooks().
	 *
	 * @return string[]
	 */
	public static function get_persistent_core_hooks(): array {
		$hooks = array(
			'wp_update_plugins',
			'wp_update_themes',
			'wp_version_check',
			'wp_scheduled_delete',
			'update_network_counts',
			'wp_scheduled_auto_draft_delete',
			'delete_expired_transients',
			'wp_privacy_delete_old_export_files',
			'recovery_mode_clean_expired_keys',
			'wp_site_health_scheduled_check',
			'wp_https_detection',
			'wp_privacy_personal_data_cleanup_requests',
		);

		if ( ! \is_multisite() ) {
			$hooks[] = 'wp_update_user_counts';
		}

		return $hooks;
	}

	/**
	 * Flatten cron array into API rows.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function list_events(): array {
		$crons = \_get_cron_array();
		if ( ! is_array( $crons ) || empty( $crons ) ) {
			return array();
		}

		$rows = array();

		foreach ( $crons as $timestamp => $cron ) {
			if ( ! is_array( $cron ) ) {
				continue;
			}

			$timestamp = (int) $timestamp;

			foreach ( $cron as $hook => $dings ) {
				if ( ! is_string( $hook ) || ! is_array( $dings ) ) {
					continue;
				}

				foreach ( $dings as $sig => $data ) {
					if ( ! is_array( $data ) ) {
						continue;
					}

					$args     = isset( $data['args'] ) && is_array( $data['args'] ) ? $data['args'] : array();
					$schedule = isset( $data['schedule'] ) ? $data['schedule'] : false;
					$interval = isset( $data['interval'] ) ? (int) $data['interval'] : 0;

					$rows[] = self::format_event_row(
						$hook,
						$timestamp,
						(string) $sig,
						$args,
						$schedule,
						$interval
					);
				}
			}
		}

		usort(
			$rows,
			static function ( $a, $b ) {
				return (int) $a['timestamp'] <=> (int) $b['timestamp'];
			}
		);

		return array_values( $rows );
	}

	/**
	 * Build a single event row for the admin UI.
	 *
	 * @param string               $hook      Hook name.
	 * @param int                  $timestamp Next run (UTC).
	 * @param string               $sig       Event signature.
	 * @param array<int, mixed>    $args      Hook arguments.
	 * @param false|string         $schedule  Recurrence slug or false.
	 * @param int                  $interval  Interval in seconds.
	 * @return array<string, mixed>
	 */
	public static function format_event_row(
		string $hook,
		int $timestamp,
		string $sig,
		array $args,
		$schedule,
		int $interval
	): array {
		$is_persistent = in_array( $hook, self::get_persistent_core_hooks(), true );
		$has_callback  = self::hook_has_callback( $hook );

		$schedule_slug = is_string( $schedule ) ? $schedule : '';

		return array(
			'id'              => $hook . '-' . $sig . '-' . $timestamp,
			'hook'            => $hook,
			'sig'             => $sig,
			'timestamp'       => $timestamp,
			'nextRunUtc'      => gmdate( 'Y-m-d H:i:s', $timestamp ),
			'nextRunLocal'    => \get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $timestamp ) ),
			'schedule'        => $schedule_slug,
			'interval'        => $interval,
			'scheduleLabel'   => self::get_schedule_label( $schedule_slug ),
			'args'            => $args,
			'argsDisplay'     => self::format_args_display( $args ),
			'isPersistentCore' => $is_persistent,
			'canDelete'       => ! $is_persistent,
			'canRun'          => $has_callback,
			'hasCallback'     => $has_callback,
		);
	}

	/**
	 * Whether a hook has a registered callback.
	 *
	 * @param string $hook Hook name.
	 */
	public static function hook_has_callback( string $hook ): bool {
		return \has_action( $hook ) || \has_filter( $hook );
	}

	/**
	 * Localized schedule label.
	 *
	 * @param string $schedule Schedule slug.
	 */
	public static function get_schedule_label( string $schedule ): string {
		if ( '' === $schedule || false === $schedule ) {
			return \__( 'Non-repeating', 'updatebee' );
		}

		$labels = array(
			'hourly'                 => \__( 'Once hourly', 'updatebee' ),
			'twicedaily'               => \__( 'Twice daily', 'updatebee' ),
			'daily'                    => \__( 'Once daily', 'updatebee' ),
			'weekly'                   => \__( 'Once weekly', 'updatebee' ),
			'updatebee-hourly'         => \__( 'Every hour', 'updatebee' ),
			'updatebee-two-hours'      => \__( 'Every two hours', 'updatebee' ),
			'updatebee-three-hours'    => \__( 'Every three hours', 'updatebee' ),
			'updatebee-six-hours'      => \__( 'Every six hours', 'updatebee' ),
			'updatebee-twelve-hours'   => \__( 'Every twelve hours', 'updatebee' ),
			'updatebee-daily'          => \__( 'Once daily', 'updatebee' ),
			'updatebee-weekly'         => \__( 'Every week', 'updatebee' ),
			'updatebee-two-weeks'      => \__( 'Every two weeks', 'updatebee' ),
			'updatebee-monthly'        => \__( 'Every month', 'updatebee' ),
		);

		return $labels[ $schedule ] ?? $schedule;
	}

	/**
	 * JSON args for display (truncated).
	 *
	 * @param array<int, mixed> $args Hook arguments.
	 */
	public static function format_args_display( array $args ): string {
		if ( empty( $args ) ) {
			return '';
		}

		$json = \wp_json_encode( $args );
		if ( ! is_string( $json ) ) {
			return '';
		}

		if ( strlen( $json ) > 120 ) {
			return substr( $json, 0, 117 ) . '...';
		}

		return $json;
	}

	/**
	 * Find event data in the cron array.
	 *
	 * @param string $hook      Hook name.
	 * @param string $sig       Signature.
	 * @param int    $timestamp Optional exact timestamp.
	 * @return array{hook:string,sig:string,timestamp:int,args:array<int,mixed>}|\WP_Error
	 */
	public static function find_event( string $hook, string $sig, int $timestamp = 0 ) {
		$crons = \_get_cron_array();
		if ( ! is_array( $crons ) ) {
			return new \WP_Error(
				'not_found',
				\__( 'The cron event could not be found.', 'updatebee' )
			);
		}

		if ( $timestamp > 0 && isset( $crons[ $timestamp ][ $hook ][ $sig ] ) ) {
			$data = $crons[ $timestamp ][ $hook ][ $sig ];
			return array(
				'hook'      => $hook,
				'sig'       => $sig,
				'timestamp' => $timestamp,
				'args'      => isset( $data['args'] ) && is_array( $data['args'] ) ? $data['args'] : array(),
			);
		}

		foreach ( $crons as $time => $cron ) {
			if ( ! is_array( $cron ) || ! isset( $cron[ $hook ][ $sig ] ) ) {
				continue;
			}

			$data = $cron[ $hook ][ $sig ];
			return array(
				'hook'      => $hook,
				'sig'       => $sig,
				'timestamp' => (int) $time,
				'args'      => isset( $data['args'] ) && is_array( $data['args'] ) ? $data['args'] : array(),
			);
		}

		return new \WP_Error(
			'not_found',
			sprintf(
				/* translators: %s: Cron hook name. */
				\__( 'The cron event %s could not be found.', 'updatebee' ),
				$hook
			)
		);
	}

	/**
	 * Delete a cron event if allowed.
	 *
	 * @param string $hook      Hook name.
	 * @param string $sig       Signature.
	 * @param int    $timestamp Next run UTC timestamp.
	 * @return true|\WP_Error
	 */
	public static function delete_event( string $hook, string $sig, int $timestamp ) {
		if ( in_array( $hook, self::get_persistent_core_hooks(), true ) ) {
			return new \WP_Error(
				'not_deletable',
				\__( 'This WordPress core cron event cannot be deleted.', 'updatebee' )
			);
		}

		$event = self::find_event( $hook, $sig, $timestamp );
		if ( \is_wp_error( $event ) ) {
			return $event;
		}

		$result = \wp_unschedule_event( $event['timestamp'], $event['hook'], $event['args'], true );
		if ( \is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Run a cron event now (schedule immediate single run + spawn cron).
	 *
	 * @param string $hook Hook name.
	 * @param string $sig  Signature.
	 * @return true|\WP_Error
	 */
	public static function run_event( string $hook, string $sig ) {
		if ( ! self::hook_has_callback( $hook ) ) {
			return new \WP_Error(
				'not_runnable',
				\__( 'This cron event has no registered callback.', 'updatebee' )
			);
		}

		$event = self::find_event( $hook, $sig );
		if ( \is_wp_error( $event ) ) {
			return $event;
		}

		$scheduled = self::force_schedule_single_event( $hook, $event['args'] );
		if ( \is_wp_error( $scheduled ) ) {
			return $scheduled;
		}

		\delete_transient( 'doing_cron' );

		\add_filter(
			'cron_request',
			static function ( array $cron_request_array ) {
				$cron_request_array['url'] = \add_query_arg( 'updatebee-single-event', '1', $cron_request_array['url'] );
				return $cron_request_array;
			}
		);

		\spawn_cron();

		if ( function_exists( 'sleep' ) ) {
			sleep( 1 );
		}

		return true;
	}

	/**
	 * Schedule a single event at timestamp 1 without duplicate check.
	 *
	 * @param string              $hook Hook name.
	 * @param array<int, mixed>   $args Arguments.
	 * @return true|\WP_Error
	 */
	public static function force_schedule_single_event( string $hook, array $args = array() ) {
		$crons = \_get_cron_array();
		if ( ! is_array( $crons ) ) {
			$crons = array();
		}

		$event = (object) array(
			'hook'      => $hook,
			'timestamp' => 1,
			'schedule'  => false,
			'args'      => $args,
		);

		$key = md5( serialize( $event->args ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize

		$crons[ $event->timestamp ][ $event->hook ][ $key ] = array(
			'schedule' => $event->schedule,
			'args'     => $event->args,
		);

		ksort( $crons );

		$result = \_set_cron_array( $crons );
		if ( false === $result ) {
			return new \WP_Error(
				'could_not_add',
				sprintf(
					/* translators: %s: Cron hook name. */
					\__( 'Failed to schedule the cron event %s.', 'updatebee' ),
					$hook
				)
			);
		}

		return true;
	}
}
