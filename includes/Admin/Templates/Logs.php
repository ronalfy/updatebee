<?php
/**
 * Register the Logs tab.
 *
 * @package updatebee
 */

namespace UpdateBee\Admin\Templates;

use UpdateBee\Functions;

/**
 * Output the logs tab and content.
 */
class Logs {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'updatebee_admin_tabs', array( $this, 'add_logs_tab' ), 7 );
		add_action( 'updatebee_output_logs', array( $this, 'output_logs_content' ), 1, 2 );
	}

	/**
	 * Add the logs tab and callback actions.
	 *
	 * @param array<int, array<string, string>> $tabs Array of tabs.
	 * @return array<int, array<string, string>> Array of tabs.
	 */
	public function add_logs_tab( array $tabs ): array {
		$tabs[] = array(
			'get'    => 'logs',
			'action' => 'updatebee_output_logs',
			'url'    => Functions::get_settings_url( 'logs' ),
			'label'  => _x( 'Logs', 'Tab label as Logs', 'updatebee' ),
			'icon'   => 'icon-database',
		);

		return $tabs;
	}

	/**
	 * Output logs placeholder content.
	 *
	 * @param string      $tab     Current tab.
	 * @param string|null $sub_tab Current sub-tab.
	 */
	public function output_logs_content( string $tab, $sub_tab = '' ): void {
		if ( 'logs' !== $tab ) {
			return;
		}

		unset( $sub_tab );
		?>
		<div class="updatebee-logs-placeholder">
			<p><?php esc_html_e( 'Update logs will appear here.', 'updatebee' ); ?></p>
		</div>
		<?php
	}
}
