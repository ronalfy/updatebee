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
	 * Output tools placeholder content.
	 *
	 * @param string      $tab     Current tab.
	 * @param string|null $sub_tab Current sub-tab.
	 */
	public function output_tools_content( string $tab, $sub_tab = '' ): void {
		if ( 'tools' !== $tab ) {
			return;
		}

		unset( $sub_tab );
		?>
		<div class="updatebee-tools-placeholder">
			<p><?php esc_html_e( 'Maintenance tools will appear here.', 'updatebee' ); ?></p>
		</div>
		<?php
	}
}
