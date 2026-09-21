<?php
/**
 * Register the Cron tab.
 *
 * @package updatebee
 */

namespace UpdateBee\Admin\Templates;

use UpdateBee\Functions;

/**
 * Output the cron tab and content.
 */
class Cron {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'updatebee_admin_tabs', array( $this, 'add_cron_tab' ), 7 );
		add_action( 'updatebee_output_cron', array( $this, 'output_cron_content' ), 1, 2 );
	}

	/**
	 * Add the cron tab and callback actions.
	 *
	 * @param array<int, array<string, string>> $tabs Array of tabs.
	 * @return array<int, array<string, string>> Array of tabs.
	 */
	public function add_cron_tab( array $tabs ): array {
		$tabs[] = array(
			'get'    => 'cron',
			'action' => 'updatebee_output_cron',
			'url'    => Functions::get_settings_url( 'cron' ),
			'label'  => _x( 'Cron', 'Tab label as Cron', 'updatebee' ),
			'icon'   => 'icon-timer',
		);

		return $tabs;
	}

	/**
	 * Output cron placeholder content.
	 *
	 * @param string      $tab     Current tab.
	 * @param string|null $sub_tab Current sub-tab.
	 */
	public function output_cron_content( string $tab, $sub_tab = '' ): void {
		if ( 'cron' !== $tab ) {
			return;
		}

		unset( $sub_tab );
		?>
		<div class="updatebee-cron-placeholder">
			<p><?php esc_html_e( 'Cron and scheduling settings will appear here.', 'updatebee' ); ?></p>
		</div>
		<?php
	}
}
