<?php
/**
 * WordPress update check cron schedules (plugins, themes, core).
 *
 * @package updatebee
 */

namespace UpdateBee\Cron;

use UpdateBee\Admin\Cron_Ajax;

/**
 * Per-context update cron options and rescheduling.
 */
class Updates_Cron {

	public const CONTEXT_PLUGINS = 'plugins';
	public const CONTEXT_THEMES  = 'themes';
	public const CONTEXT_CORE    = 'core';

	public const NONCE_ACTION = 'updatebee-save-cron-schedules';

	/**
	 * Context slug to internal context key.
	 *
	 * @var array<string, string>
	 */
	private const SLUG_TO_CONTEXT = array(
		'plugin_updates' => self::CONTEXT_PLUGINS,
		'theme_updates'  => self::CONTEXT_THEMES,
		'core_updates'   => self::CONTEXT_CORE,
	);

	/**
	 * Register hooks and AJAX.
	 */
	public function run(): void {
		$cron_ajax = new Cron_Ajax();
		$cron_ajax->run();

		$days_schedule = new Days_Schedule();
		$days_schedule->run();
	}

	/**
	 * Resolve ajax slug to context.
	 *
	 * @param string $slug plugin_updates|theme_updates|core_updates.
	 * @return string|null Internal context or null.
	 */
	public static function context_from_slug( string $slug ): ?string {
		return self::SLUG_TO_CONTEXT[ $slug ] ?? null;
	}

	/**
	 * Valid internal contexts.
	 *
	 * @return string[]
	 */
	public static function get_contexts(): array {
		return array(
			self::CONTEXT_PLUGINS,
			self::CONTEXT_THEMES,
			self::CONTEXT_CORE,
		);
	}

	/**
	 * Site option key for a context.
	 *
	 * @param string $context plugins|themes|core.
	 */
	public static function get_option_key( string $context ): string {
		switch ( $context ) {
			case self::CONTEXT_PLUGINS:
				return 'updatebee_plugin_cron_schedule';
			case self::CONTEXT_THEMES:
				return 'updatebee_theme_cron_schedule';
			case self::CONTEXT_CORE:
				return 'updatebee_core_cron_schedule';
			default:
				return '';
		}
	}

	/**
	 * Legacy UAU option key for migration.
	 *
	 * @param string $context plugins|themes|core.
	 */
	private static function get_uau_option_key( string $context ): string {
		switch ( $context ) {
			case self::CONTEXT_PLUGINS:
				return 'uau_plugin_cron_schedule';
			case self::CONTEXT_THEMES:
				return 'uau_theme_cron_schedule';
			case self::CONTEXT_CORE:
				return 'uau_core_cron_schedule';
			default:
				return '';
		}
	}

	/**
	 * WordPress cron hook for a context.
	 *
	 * @param string $context plugins|themes|core.
	 */
	public static function get_cron_hook( string $context ): string {
		switch ( $context ) {
			case self::CONTEXT_PLUGINS:
				return 'wp_update_plugins';
			case self::CONTEXT_THEMES:
				return 'wp_update_themes';
			case self::CONTEXT_CORE:
				return 'wp_version_check';
			default:
				return '';
		}
	}

	/**
	 * Default schedule option shape.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_cron_defaults(): array {
		return array(
			'schedule'   => 'twelve-hours',
			'day'        => 'sunday',
			'week'       => '1',
			'start_time' => '00:00',
			'day_num'    => '1',
			'days_list'  => array( 1, 2, 3, 4, 5, 6, 7 ),
		);
	}

	/**
	 * Normalize days_list defaults for a schedule.
	 *
	 * @param string               $schedule Schedule slug.
	 * @param array<string, mixed> $options  Options array.
	 * @return array<string, mixed>
	 */
	public static function normalize_days_list_defaults( string $schedule, array $options ): array {
		if ( ! isset( $options['days_list'] ) || ! is_array( $options['days_list'] ) ) {
			if ( 'daily' === $schedule ) {
				$options['days_list'] = array();
			} else {
				$options['days_list'] = array( 1, 2, 3, 4, 5, 6, 7 );
			}
		}

		$options['days_list'] = array_values(
			array_unique(
				array_map(
					static function ( $day ) {
						return absint( $day );
					},
					$options['days_list']
				)
			)
		);

		return $options;
	}

	/**
	 * Retrieve saved cron options for a context (with lazy UAU migration).
	 *
	 * @param string $context plugins|themes|core.
	 * @return array<string, mixed>
	 */
	public static function get_cron_options( string $context ): array {
		$key     = self::get_option_key( $context );
		$options = get_site_option( $key, array() );

		if ( ! is_array( $options ) ) {
			$options = array();
		}

		if ( empty( $options ) ) {
			$uau_key = self::get_uau_option_key( $context );
			$legacy  = get_site_option( $uau_key, array() );
			if ( is_array( $legacy ) && ! empty( $legacy ) ) {
				$options = $legacy;
				update_site_option( $key, $options );
				self::set_cron_events( $context );
			}
		}

		$options  = wp_parse_args( $options, self::get_cron_defaults() );
		$schedule = isset( $options['schedule'] ) ? (string) $options['schedule'] : 'twelve-hours';
		$options  = self::normalize_days_list_defaults( $schedule, $options );

		return $options;
	}

	/**
	 * Persist options and reschedule the context hook.
	 *
	 * @param string               $context plugins|themes|core.
	 * @param array<string, mixed> $options Options to save.
	 */
	public static function save_cron_options( string $context, array $options ): void {
		$key = self::get_option_key( $context );
		update_site_option( $key, $options );
		self::set_cron_events( $context );
	}

	/**
	 * Apply saved schedule to WordPress cron for one context.
	 *
	 * @param string $context plugins|themes|core.
	 */
	public static function set_cron_events( string $context ): void {
		$options = self::get_cron_options( $context );
		$hook    = self::get_cron_hook( $context );

		Schedules::set_cron_events( $options, $hook );
	}

	/**
	 * Clear scheduled events for a context hook.
	 *
	 * @param string $context plugins|themes|core.
	 */
	public static function clear_cron( string $context ): void {
		wp_clear_scheduled_hook( self::get_cron_hook( $context ) );
	}

	/**
	 * Restore WordPress default twicedaily schedule for one context.
	 *
	 * @param string $context plugins|themes|core.
	 */
	public static function set_default_crons( string $context ): void {
		switch ( $context ) {
			case self::CONTEXT_PLUGINS:
				self::clear_cron( $context );
				add_action( 'wp_update_plugins', 'wp_update_plugins' );
				wp_schedule_event( time(), 'twicedaily', 'wp_update_plugins' );
				break;
			case self::CONTEXT_THEMES:
				self::clear_cron( $context );
				add_action( 'wp_update_themes', 'wp_update_themes' );
				wp_schedule_event( time(), 'twicedaily', 'wp_update_themes' );
				break;
			case self::CONTEXT_CORE:
				self::clear_cron( $context );
				add_action( 'wp_version_check', 'wp_version_check' );
				wp_schedule_event( time(), 'twicedaily', 'wp_version_check' );
				break;
		}
	}

	/**
	 * Next timestamp for a context cron hook.
	 *
	 * @param string $context plugins|themes|core.
	 * @return int|false
	 */
	public static function get_next_cron_event( string $context ) {
		$hook = self::get_cron_hook( $context );
		return self::get_next_cron_event_for_hook( $hook );
	}

	/**
	 * Next timestamp for a cron hook name.
	 *
	 * @param string $hook Cron hook.
	 * @return int|false
	 */
	public static function get_next_cron_event_for_hook( string $hook ) {
		$cron_events = _get_cron_array();
		if ( ! is_array( $cron_events ) ) {
			return false;
		}

		ksort( $cron_events );

		$cron_event = array();
		foreach ( $cron_events as $timestamp => $schedule ) {
			if ( ! is_array( $schedule ) ) {
				continue;
			}
			foreach ( $schedule as $key => $value ) {
				if ( $key === $hook ) {
					$cron_event[ $timestamp ][ $key ] = $value;
				}
			}
		}

		$keys = array_keys( $cron_event );
		if ( ! empty( $keys ) ) {
			return (int) $keys[0];
		}

		return false;
	}

	/**
	 * Build API payload for admin UI (options + next run).
	 *
	 * @param string $context plugins|themes|core.
	 * @return array<string, mixed>
	 */
	public static function get_schedule_payload( string $context ): array {
		$options = self::get_cron_options( $context );

		$next_cron = self::get_next_cron_event( $context );
		if ( false === $next_cron || 0 === $next_cron ) {
			$preview = Schedules::calculate_next_event( $options, true );
			if ( $preview > 0 ) {
				$next_cron = $preview;
			} else {
				$next_cron = 0;
			}
		}

		$payload = $options;
		if ( is_int( $next_cron ) && $next_cron > 0 ) {
			$payload = array_merge( $payload, Schedules::get_format_from_cron( $next_cron ) );
		} else {
			$payload['timestamp'] = 0;
			$payload['datetime']  = '';
		}

		$payload['crontab_equivalent'] = Schedules::get_crontab_equivalent(
			$options,
			self::get_cron_hook( $context )
		);

		return $payload;
	}

	/**
	 * Sanitize schedule POST fields into an options array.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed>
	 */
	public static function sanitize_schedule_input( array $input ): array {
		$schedule   = isset( $input['schedule'] ) ? sanitize_text_field( (string) $input['schedule'] ) : 'twelve-hours';
		$day        = isset( $input['day'] ) ? sanitize_text_field( (string) $input['day'] ) : 'sunday';
		$week       = isset( $input['week'] ) ? sanitize_text_field( (string) $input['week'] ) : '1';
		$start_time = isset( $input['start_time'] ) ? sanitize_text_field( (string) $input['start_time'] ) : '00:00';
		$day_num    = isset( $input['day_num'] ) ? sanitize_text_field( (string) $input['day_num'] ) : '1';

		$days_list = array();
		if ( isset( $input['days_list'] ) && is_array( $input['days_list'] ) ) {
			foreach ( $input['days_list'] as $day_value ) {
				$day_int = absint( $day_value );
				if ( $day_int >= 1 && $day_int <= 7 ) {
					$days_list[] = $day_int;
				}
			}
		}

		$options = array(
			'schedule'   => $schedule,
			'day'        => $day,
			'week'       => $week,
			'start_time' => $start_time,
			'day_num'    => $day_num,
			'days_list'  => $days_list,
		);

		return self::normalize_days_list_defaults( $schedule, $options );
	}
}
