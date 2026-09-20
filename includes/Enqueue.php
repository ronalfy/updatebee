<?php
/**
 * Enqueue actions for the plugin.
 *
 * @package updatebee
 */

namespace UpdateBee;

/**
 * Class Enqueue.
 */
class Enqueue {

	/**
	 * Class runner.
	 */
	public function run(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'output_admin_scripts_styles' ) );
	}

	/**
	 * Register scripts and styles for the admin panel.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public function output_admin_scripts_styles( string $hook_suffix = '' ): void {
		$screen    = get_current_screen();
		$screen_id = $screen instanceof \WP_Screen ? $screen->id : '';

		$allowed_screens = array(
			'tools_page_updatebee',
			'settings_page_updatebee',
			'tools_page_updatebee-network',
			'settings_page_updatebee-network',
		);

		// Network admin appends `-network` to the screen ID, while the enqueue hook stays unprefixed.
		if ( ! in_array( $hook_suffix, $allowed_screens, true ) && ! in_array( $screen_id, $allowed_screens, true ) ) {
			return;
		}

		$asset_file = Functions::get_plugin_dir( '/build/admin.asset.php' );
		$asset      = array(
			'dependencies' => array(),
			'version'      => Functions::get_plugin_version(),
		);

		if ( file_exists( $asset_file ) ) {
			$loaded_asset = require $asset_file;
			if ( is_array( $loaded_asset ) ) {
				$asset = wp_parse_args( $loaded_asset, $asset );
			}
		}

		$script_path = Functions::get_plugin_dir( '/build/admin.js' );
		if ( file_exists( $script_path ) ) {
			wp_enqueue_script(
				'updatebee-admin',
				Functions::get_plugin_url( '/build/admin.js' ),
				is_array( $asset['dependencies'] ) ? $asset['dependencies'] : array(),
				is_string( $asset['version'] ) ? $asset['version'] : Functions::get_plugin_version(),
				true
			);
		}

		$style_path = Functions::get_plugin_dir( '/build/admin.css' );
		if ( file_exists( $style_path ) ) {
			wp_enqueue_style(
				'updatebee-admin',
				Functions::get_plugin_url( '/build/admin.css' ),
				array(),
				is_string( $asset['version'] ) ? $asset['version'] : Functions::get_plugin_version(),
				'all'
			);
		}
	}
}
