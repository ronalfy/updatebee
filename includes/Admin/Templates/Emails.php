<?php
/**
 * Register the Emails tab.
 *
 * @package updatebee
 */

namespace UpdateBee\Admin\Templates;

use UpdateBee\Functions;

/**
 * Output the emails tab and content.
 */
class Emails {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'updatebee_admin_tabs', array( $this, 'add_emails_tab' ), 6 );
		add_action( 'updatebee_output_emails', array( $this, 'output_emails_content' ), 1, 2 );
	}

	/**
	 * Add the emails tab and callback actions.
	 *
	 * @param array<int, array<string, string>> $tabs Array of tabs.
	 * @return array<int, array<string, string>> Array of tabs.
	 */
	public function add_emails_tab( array $tabs ): array {
		$tabs[] = array(
			'get'    => 'emails',
			'action' => 'updatebee_output_emails',
			'url'    => Functions::get_settings_url( 'emails' ),
			'label'  => _x( 'Emails', 'Tab label as Emails', 'updatebee' ),
			'icon'   => 'icon-email',
		);

		return $tabs;
	}

	/**
	 * Output emails placeholder content.
	 *
	 * @param string      $tab     Current tab.
	 * @param string|null $sub_tab Current sub-tab.
	 */
	public function output_emails_content( string $tab, $sub_tab = '' ): void {
		if ( 'emails' !== $tab ) {
			return;
		}

		unset( $sub_tab );
		?>
		<div class="updatebee-emails-placeholder">
			<p><?php esc_html_e( 'Email notification settings will appear here.', 'updatebee' ); ?></p>
		</div>
		<?php
	}
}
