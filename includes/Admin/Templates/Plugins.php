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
	 * Output plugins placeholder content.
	 *
	 * @param string      $tab     Current tab.
	 * @param string|null $sub_tab Current sub-tab.
	 */
	public function output_plugins_content( string $tab, $sub_tab = '' ): void {
		if ( 'plugins' !== $tab ) {
			return;
		}

		unset( $sub_tab );
		?>
		<div class="updatebee-plugins-placeholder">
			<p><?php esc_html_e( 'Plugin update controls will appear here.', 'updatebee' ); ?></p>
		</div>
		<?php
	}
}
