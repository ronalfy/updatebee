<?php
/**
 * AJAX handlers for update cron schedules.
 *
 * @package updatebee
 */

namespace UpdateBee\Admin;

use UpdateBee\Cron\Cron_Events;
use UpdateBee\Cron\Updates_Cron;
use UpdateBee\Functions;

/**
 * Cron schedule admin AJAX.
 */
class Cron_Ajax {

	/**
	 * Ajax slug suffixes.
	 *
	 * @var string[]
	 */
	private const SLUGS = array(
		'plugin_updates',
		'theme_updates',
		'core_updates',
	);

	/**
	 * Register ajax actions.
	 */
	public function run(): void {
		foreach ( self::SLUGS as $slug ) {
			add_action( 'wp_ajax_updatebee_get_cron_schedule_' . $slug, array( $this, 'ajax_get_cron_schedule' ) );
			add_action( 'wp_ajax_updatebee_save_cron_schedule_' . $slug, array( $this, 'ajax_save_cron_schedule' ) );
			add_action( 'wp_ajax_updatebee_reset_cron_schedule_' . $slug, array( $this, 'ajax_reset_cron_schedule' ) );
		}

		add_action( 'wp_ajax_updatebee_get_cron_time', array( $this, 'ajax_get_time' ) );

		add_action( 'wp_ajax_updatebee_list_cron_events', array( $this, 'ajax_list_cron_events' ) );
		add_action( 'wp_ajax_updatebee_delete_cron_event', array( $this, 'ajax_delete_cron_event' ) );
		add_action( 'wp_ajax_updatebee_run_cron_event', array( $this, 'ajax_run_cron_event' ) );
	}

	/**
	 * Verify cron events nonce and capability.
	 */
	private function verify_events_request(): bool {
		if ( ! Functions::can_access_options() ) {
			wp_send_json_error();
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, Cron_Events::EVENTS_NONCE_ACTION ) ) {
			wp_send_json_error();
		}

		return true;
	}

	/**
	 * Verify nonce and capability.
	 */
	private function verify_request(): bool {
		if ( ! Functions::can_access_options() ) {
			wp_send_json_error();
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, Updates_Cron::NONCE_ACTION ) ) {
			wp_send_json_error();
		}

		return true;
	}

	/**
	 * Resolve context from current ajax action suffix.
	 */
	private function get_context_from_action(): ?string {
		$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';
		foreach ( self::SLUGS as $slug ) {
			if ( str_ends_with( $action, $slug ) ) {
				return Updates_Cron::context_from_slug( $slug );
			}
		}

		return null;
	}

	/**
	 * Retrieve schedule for one context.
	 */
	public function ajax_get_cron_schedule(): void {
		$this->verify_request();

		$context = $this->get_context_from_action();
		if ( null === $context ) {
			wp_send_json_error();
		}

		wp_send_json_success( Updates_Cron::get_schedule_payload( $context ) );
	}

	/**
	 * Save schedule for one context.
	 */
	public function ajax_save_cron_schedule(): void {
		$this->verify_request();

		$context = $this->get_context_from_action();
		if ( null === $context ) {
			wp_send_json_error();
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in Updates_Cron.
		$days_list = isset( $_POST['days_list'] ) ? wp_unslash( $_POST['days_list'] ) : array();
		if ( ! is_array( $days_list ) ) {
			$days_list = array();
		}

		$input = array(
			'schedule'   => isset( $_POST['schedule'] ) ? wp_unslash( $_POST['schedule'] ) : '',
			'day'        => isset( $_POST['day'] ) ? wp_unslash( $_POST['day'] ) : '',
			'week'       => isset( $_POST['week'] ) ? wp_unslash( $_POST['week'] ) : '',
			'start_time' => isset( $_POST['start_time'] ) ? wp_unslash( $_POST['start_time'] ) : '',
			'day_num'    => isset( $_POST['day_num'] ) ? wp_unslash( $_POST['day_num'] ) : '',
			'days_list'  => $days_list,
		);

		$options = Updates_Cron::sanitize_schedule_input( $input );
		Updates_Cron::save_cron_options( $context, $options );

		wp_send_json_success( Updates_Cron::get_schedule_payload( $context ) );
	}

	/**
	 * Reset schedule for one context.
	 */
	public function ajax_reset_cron_schedule(): void {
		$this->verify_request();

		$context = $this->get_context_from_action();
		if ( null === $context ) {
			wp_send_json_error();
		}

		$defaults = Updates_Cron::get_cron_defaults();
		update_site_option( Updates_Cron::get_option_key( $context ), $defaults );
		Updates_Cron::set_default_crons( $context );

		wp_send_json_success( Updates_Cron::get_schedule_payload( $context ) );
	}

	/**
	 * Return universal and local time strings.
	 */
	public function ajax_get_time(): void {
		$this->verify_request();

		$current_offset = get_option( 'gmt_offset' );

		/* translators: Date and time format for exact current time, mainly about timezones, see https://www.php.net/manual/datetime.format.php */
		$timezone_format = _x( 'Y-m-d H:i:s', 'timezone date format', 'updatebee' );
		$universal_time  = date_i18n( $timezone_format, false, true );
		$local_time      = false;

		if ( get_option( 'timezone_string' ) || ! empty( $current_offset ) ) {
			$local_time = date_i18n( $timezone_format );
		}

		wp_send_json_success(
			array(
				'universal_time' => $universal_time,
				'local_time'     => $local_time,
			)
		);
	}

	/**
	 * List all scheduled cron events.
	 */
	public function ajax_list_cron_events(): void {
		$this->verify_events_request();

		wp_send_json_success(
			array(
				'events' => Cron_Events::list_events(),
			)
		);
	}

	/**
	 * Delete a cron event.
	 */
	public function ajax_delete_cron_event(): void {
		$this->verify_events_request();

		$hook      = isset( $_POST['hook'] ) ? sanitize_text_field( wp_unslash( $_POST['hook'] ) ) : '';
		$sig       = isset( $_POST['sig'] ) ? sanitize_text_field( wp_unslash( $_POST['sig'] ) ) : '';
		$timestamp = isset( $_POST['timestamp'] ) ? absint( wp_unslash( $_POST['timestamp'] ) ) : 0;

		if ( '' === $hook || '' === $sig || $timestamp <= 0 ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid cron event.', 'updatebee' ),
				)
			);
		}

		$result = Cron_Events::delete_event( $hook, $sig, $timestamp );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
				)
			);
		}

		wp_send_json_success(
			array(
				'message' => __( 'Cron event deleted.', 'updatebee' ),
				'events'  => Cron_Events::list_events(),
			)
		);
	}

	/**
	 * Run a cron event immediately.
	 */
	public function ajax_run_cron_event(): void {
		$this->verify_events_request();

		$hook = isset( $_POST['hook'] ) ? sanitize_text_field( wp_unslash( $_POST['hook'] ) ) : '';
		$sig  = isset( $_POST['sig'] ) ? sanitize_text_field( wp_unslash( $_POST['sig'] ) ) : '';

		if ( '' === $hook || '' === $sig ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid cron event.', 'updatebee' ),
				)
			);
		}

		$result = Cron_Events::run_event( $hook, $sig );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
				)
			);
		}

		wp_send_json_success(
			array(
				'message' => __( 'Cron event was scheduled to run. It may take a moment to complete depending on server loopback.', 'updatebee' ),
				'events'  => Cron_Events::list_events(),
			)
		);
	}
}
