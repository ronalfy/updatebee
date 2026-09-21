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
		add_action( 'wp_ajax_updatebee_retrieve_plugins', array( $this, 'retrieve_plugins' ) );
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
		$updated = Options::update_options( '', $this->get_dashboard_option_defaults() );
		if ( null === $updated ) {
			wp_send_json_error();
		}

		wp_send_json_success( $updated );
	}

	/**
	 * Default values for dashboard automatic-update options.
	 *
	 * @return array<string, string>
	 */
	private function get_dashboard_option_defaults(): array {
		return array(
			'major_auto_updates'               => 'off',
			'minor_auto_updates'               => 'on',
			'plugin_auto_updates'              => 'off',
			'plugin_auto_updates_custom'       => 'off',
			'plugin_updates_disabled'          => 'off',
			'plugin_manual_updates_only'       => 'off',
			'plugin_auto_updates_patches_only' => 'off',
			'theme_auto_updates'               => 'off',
			'theme_auto_updates_custom'        => 'off',
			'theme_updates_disabled'           => 'off',
			'theme_manual_updates_only'        => 'off',
			'theme_auto_updates_patches_only'  => 'off',
			'translation_auto_updates'         => 'on',
			'translation_auto_updates_custom'  => 'off',
			'translation_updates_disabled'     => 'off',
			'translation_manual_updates_only'  => 'off',
		);
	}

	/**
	 * Allowed dashboard automatic-update option keys.
	 *
	 * @return array<int, string>
	 */
	private function get_dashboard_option_keys(): array {
		return array_keys( $this->get_dashboard_option_defaults() );
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

	/**
	 * Return plugin list data for the Plugins screen.
	 */
	public function retrieve_plugins(): void {
		if ( ! Functions::can_access_options() ) {
			wp_send_json_error();
		}

		check_ajax_referer( 'updatebee-retrieve-plugins', 'nonce' );

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = apply_filters( 'all_plugins', get_plugins() );
		if ( ! is_array( $all_plugins ) ) {
			$all_plugins = array();
		}

		$active_plugin_slugs = Functions::get_active_plugins();
		if ( ! is_array( $active_plugin_slugs ) ) {
			$active_plugin_slugs = array();
		}

		$auto_updated_plugins = Functions::get_auto_updates_plugins();
		if ( ! is_array( $auto_updated_plugins ) ) {
			$auto_updated_plugins = array();
		}

		$blocked_plugins = Options::get_options( 'blocked_plugins' );
		if ( ! is_array( $blocked_plugins ) ) {
			$blocked_plugins = array();
		}

		foreach ( $all_plugins as $file => $plugin ) {
			if ( ! is_string( $file ) ) {
				continue;
			}

			if ( ! is_array( $plugin ) ) {
				$plugin = array();
			}

			$plugin['File']        = $file;
			$plugin['Slug']        = dirname( $file );
			$plugin['EnableNonce'] = wp_create_nonce( 'updatebee-plugin-enable-' . $file );
			$plugin['BlockNonce']  = wp_create_nonce( 'updatebee-plugin-block-' . $file );
			$plugin['PatchNonce']  = wp_create_nonce( 'updatebee-plugin-patches-' . $file );
			$plugin['Active']      = in_array( $file, $active_plugin_slugs, true );
			$plugin['Autoupdates'] = isset( $auto_updated_plugins[ $file ] );
			$plugin['Blocked']     = in_array( dirname( $file ), $blocked_plugins, true );
			$plugin['PatchesOnly'] = Functions::auto_updates_patches_only( $file, 'plugin' );

			$plugin_file_path = WP_PLUGIN_DIR . '/' . $file;
			$mtime            = file_exists( $plugin_file_path ) ? filemtime( $plugin_file_path ) : false;
			if ( false !== $mtime ) {
				$date_format           = (string) get_option( 'date_format' );
				$time_format           = (string) get_option( 'time_format' );
				$plugin['LastUpdated'] = gmdate( $date_format, $mtime ) . ' ' . _x( 'at', 'Date and time separator', 'updatebee' ) . ' ' . gmdate( $time_format, $mtime );
			} else {
				$plugin['LastUpdated'] = __( 'Unknown', 'updatebee' );
			}

			$all_plugins[ $file ] = $plugin;
		}

		$plugin_info = get_site_transient( 'update_plugins' );
		if ( is_object( $plugin_info ) ) {
			foreach ( $all_plugins as $plugin_file => $plugin_data ) {
				if ( ! is_string( $plugin_file ) || ! is_array( $plugin_data ) ) {
					continue;
				}
				if ( isset( $plugin_data['Description'] ) ) {
					$plugin_data['Description'] = wp_trim_words( wp_strip_all_tags( $plugin_data['Description'] ), 20, '...' );
				}

				$extra = null;
				if ( isset( $plugin_info->response[ $plugin_file ] ) ) {
					$extra = $plugin_info->response[ $plugin_file ];
				} elseif ( isset( $plugin_info->no_update[ $plugin_file ] ) ) {
					$extra = $plugin_info->no_update[ $plugin_file ];
				}

				if ( is_object( $extra ) || is_array( $extra ) ) {
					$all_plugins[ $plugin_file ] = array_merge( (array) $extra, $plugin_data );
				}

				$slug = $all_plugins[ $plugin_file ]['slug'] ?? '';
				if ( is_string( $slug ) && '' !== $slug ) {
					// AJAX is not a network admin request, so self_admin_url() cannot be used here.
					$plugin_install_url                     = Functions::is_multisite() ? network_admin_url( 'plugin-install.php' ) : admin_url( 'plugin-install.php' );
					$all_plugins[ $plugin_file ]['Details'] = esc_url_raw(
						add_query_arg(
							array(
								'tab'       => 'plugin-information',
								'plugin'    => $slug,
								'TB_iframe' => 'true',
								'width'     => 600,
								'height'    => 550,
							),
							$plugin_install_url
						)
					);
				}
			}
		}

		$options = Options::get_options();
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		$status = 'disabled';
		if ( isset( $options['plugin_updates_disabled'] ) && 'on' === $options['plugin_updates_disabled'] ) {
			$status = 'blocked';
		} elseif ( isset( $options['plugin_auto_updates'] ) && 'on' === $options['plugin_auto_updates'] ) {
			if ( isset( $options['plugin_auto_updates_custom'] ) && 'on' === $options['plugin_auto_updates_custom'] ) {
				$status = 'custom';
			} else {
				$status = 'enabled';
			}
		}

		wp_send_json_success(
			array(
				'plugins'              => $all_plugins,
				'auto_updates_enabled' => $status,
			)
		);
	}
}
