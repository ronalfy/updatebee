<?php
/**
 * Register the Dashboard tab.
 *
 * @package updatebee
 */

namespace UpdateBee\Admin\Templates;

use UpdateBee\Functions;

/**
 * Output the dashboard tab and content.
 */
class Dashboard {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'updatebee_admin_tabs', array( $this, 'add_dashboard_tab' ), 1 );
		add_action( 'updatebee_output_dashboard', array( $this, 'output_dashboard_content' ), 1, 2 );
	}

	/**
	 * Add the dashboard tab and callback actions.
	 *
	 * @param array<int, array<string, string>> $tabs Array of tabs.
	 * @return array<int, array<string, string>> Array of tabs.
	 */
	public function add_dashboard_tab( array $tabs ): array {
		$tabs[] = array(
			'get'    => 'dashboard',
			'action' => 'updatebee_output_dashboard',
			'url'    => Functions::get_settings_url( 'dashboard' ),
			'label'  => _x( 'Settings', 'Tab label as Settings', 'updatebee' ),
			'icon'   => 'icon-settings',
		);

		return $tabs;
	}

	/**
	 * Output dashboard placeholder content.
	 *
	 * @param string      $tab     Current tab.
	 * @param string|null $sub_tab Current sub-tab.
	 */
	public function output_dashboard_content( string $tab, $sub_tab = '' ): void {
		if ( 'dashboard' !== $tab ) {
			return;
		}
		$deps = require Functions::get_plugin_dir( 'build/dashboard.asset.php' );
		wp_enqueue_script(
			'updatebee-dashboard',
			Functions::get_plugin_url( 'build/dashboard.js' ),
			$deps['dependencies'],
			$deps['version'],
			true
		);

		unset( $sub_tab );
		?>
		<div id="updatebee-dashboard-container">test</div>
		<?php
	}
}
