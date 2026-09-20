<?php
/**
 * Ajax handlers for the plugin.
 *
 * @package updatebee
 */

namespace UpdateBee\Admin;

use UpdateBee\Functions;
use UpdateBee\Options;

/**
 * Class Ajax.
 */
class Ajax {

	/**
	 * Class runner.
	 */
	public function run(): void {
		add_action( 'wp_ajax_updatebee_get_dashboard_updates', array( $this, 'get_dashboard_updates' ) );
		add_action( 'wp_ajax_updatebee_save_dashboard_settings', array( $this, 'save_dashboard_settings' ) );
		add_action( 'wp_ajax_updatebee_reset_dashboard_settings', array( $this, 'reset_dashboard_settings' ) );
	}

	/**
	 * Return dashboard automatic-update options.
	 */
	public function get_dashboard_updates(): void {
		if ( ! Functions::can_access_options() ) {
			wp_send_json_error();
		}

		check_ajax_referer( 'updatebee-retrieve-dashboard-settings', 'nonce' );

		wp_send_json_success( Options::get_options() );
	}

	/**
	 * Save dashboard automatic-update options.
	 */
	public function save_dashboard_settings(): void {
		if ( ! Functions::can_access_options() ) {
			wp_send_json_error();
		}

		check_ajax_referer( 'updatebee-save-dashboard-settings', 'nonce' );

		$options = array();
		foreach ( $this->get_dashboard_option_keys() as $key ) {
			$value           = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
			$options[ $key ] = $this->sanitize_on_off( $value );
		}

		$updated = Options::update_options( '', $options );
		if ( null === $updated ) {
			wp_send_json_error();
		}

		wp_send_json_success( $updated );
	}

	/**
	 * Reset dashboard automatic-update options to defaults.
	 */
	public function reset_dashboard_settings(): void {
		if ( ! Functions::can_access_options() ) {
			wp_send_json_error();
		}

		check_ajax_referer( 'updatebee-reset-dashboard-settings', 'nonce' );

		// Match Options defaults for the dashboard toggles only.
		$defaults = array(
			'major_updates'       => 'off',
			'minor_updates'       => 'on',
			'plugin_updates'      => 'off',
			'theme_updates'       => 'off',
			'translation_updates' => 'on',
		);

		$updated = Options::update_options( '', $defaults );
		if ( null === $updated ) {
			wp_send_json_error();
		}

		wp_send_json_success( $updated );
	}

	/**
	 * Allowed dashboard automatic-update option keys.
	 *
	 * @return array<int, string>
	 */
	private function get_dashboard_option_keys(): array {
		return array(
			'major_updates',
			'minor_updates',
			'plugin_updates',
			'theme_updates',
			'translation_updates',
		);
	}

	/**
	 * Sanitize an on/off option value.
	 *
	 * @param string $value Incoming value.
	 * @return string `on` or `off`.
	 */
	private function sanitize_on_off( string $value ): string {
		return 'on' === $value ? 'on' : 'off';
	}
}
