<?php
/**
 * Register the Tools tab.
 *
 * @package updatebee
 */

namespace UpdateBee\Admin\Templates;

use UpdateBee\Functions;

/**
 * Output the tools tab and content.
 */
class Tools {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'updatebee_admin_tabs', array( $this, 'add_tools_tab' ), 8 );
		add_action( 'updatebee_output_tools', array( $this, 'output_tools_content' ), 1, 2 );
	}

	/**
	 * Add the tools tab and callback actions.
	 *
	 * @param array<int, array<string, string>> $tabs Array of tabs.
	 * @return array<int, array<string, string>> Array of tabs.
	 */
	public function add_tools_tab( array $tabs ): array {
		$tabs[] = array(
			'get'    => 'tools',
			'action' => 'updatebee_output_tools',
			'url'    => Functions::get_settings_url( 'tools' ),
			'label'  => _x( 'Tools', 'Tab label as Tools', 'updatebee' ),
			'icon'   => 'icon-maintenance',
		);

		return $tabs;
	}

	/**
	 * Output tools screen content.
	 *
	 * @param string      $tab     Current tab.
	 * @param string|null $sub_tab Current sub-tab.
	 */
	public function output_tools_content( string $tab, $sub_tab = '' ): void {
		if ( 'tools' !== $tab ) {
			return;
		}

		$asset_file = Functions::get_plugin_dir( 'build/tools.asset.php' );
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

		wp_enqueue_script(
			'updatebee-tools',
			Functions::get_plugin_url( 'build/tools.js' ),
			$deps['dependencies'],
			$deps['version'],
			true
		);
		wp_enqueue_style(
			'updatebee-tools',
			Functions::get_plugin_url( 'build/tools.css' ),
			array( 'updatebee-admin' ),
			is_string( $deps['version'] ) ? $deps['version'] : Functions::get_plugin_version()
		);
		wp_localize_script(
			'updatebee-tools',
			'updatebeeTools',
			array(
				'forceUpdatesNonce'   => wp_create_nonce( 'updatebee-force-updates' ),
				'triggerCronNonce'    => wp_create_nonce( 'updatebee-trigger-update-cron' ),
				'refreshUpdatesNonce' => wp_create_nonce( 'updatebee-refresh-updates' ),
			)
		);

		unset( $sub_tab );
		?>
		<div id="updatebee-tools-container"></div>
		<?php
	}
}
