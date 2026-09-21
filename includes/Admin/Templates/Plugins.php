<?php
/**
 * Register the Plugins tab.
 *
 * @package updatebee
 */

namespace UpdateBee\Admin\Templates;

use UpdateBee\Functions;

/**
 * Output the plugins tab and content.
 */
class Plugins {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'updatebee_admin_tabs', array( $this, 'add_plugins_tab' ), 2 );
		add_action( 'updatebee_output_plugins', array( $this, 'output_plugins_content' ), 1, 2 );
	}

	/**
	 * Add the plugins tab and callback actions.
	 *
	 * @param array<int, array<string, string>> $tabs Array of tabs.
	 * @return array<int, array<string, string>> Array of tabs.
	 */
	public function add_plugins_tab( array $tabs ): array {
		$tabs[] = array(
			'get'    => 'plugins',
			'action' => 'updatebee_output_plugins',
			'url'    => Functions::get_settings_url( 'plugins' ),
			'label'  => _x( 'Plugins', 'Tab label as Plugins', 'updatebee' ),
			'icon'   => 'icon-plug',
		);

		return $tabs;
	}

	/**
	 * Output plugins screen content.
	 *
	 * @param string      $tab     Current tab.
	 * @param string|null $sub_tab Current sub-tab.
	 */
	public function output_plugins_content( string $tab, $sub_tab = '' ): void {
		if ( 'plugins' !== $tab ) {
			return;
		}

		$asset_file = Functions::get_plugin_dir( 'build/plugins.asset.php' );
		$deps       = array(
			'dependencies' => array(),
			'version'      => Functions::get_plugin_version(),
		);
		if ( file_exists( $asset_file ) ) {
			$loaded = require $asset_file;
			if ( is_array( $loaded ) ) {
				$deps = wp_parse_args( $loaded, $deps );
			}
		}

		add_thickbox();
		wp_enqueue_script( 'plugin-install' );

		wp_enqueue_script(
			'updatebee-plugins',
			Functions::get_plugin_url( 'build/plugins.js' ),
			$deps['dependencies'],
			$deps['version'],
			true
		);
		wp_enqueue_style(
			'updatebee-plugins',
			Functions::get_plugin_url( 'build/plugins.css' ),
			array( 'updatebee-admin' ),
			is_string( $deps['version'] ) ? $deps['version'] : Functions::get_plugin_version()
		);
		wp_localize_script(
			'updatebee-plugins',
			'updatebeePlugins',
			array(
				'retrieveNonce' => wp_create_nonce( 'updatebee-retrieve-plugins' ),
				'bulkNonce'     => wp_create_nonce( 'updatebee-plugin-bulk' ),
				'isNetworkAdmin' => is_network_admin(),
			)
		);

		unset( $sub_tab );
		?>
		<div id="updatebee-plugins-container"></div>
		<?php
	}
}
