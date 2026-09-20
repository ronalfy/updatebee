<?php
/**
 * Register the Themes tab.
 *
 * @package updatebee
 */

namespace UpdateBee\Admin\Templates;

use UpdateBee\Functions;

/**
 * Output the themes tab and content.
 */
class Themes {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'updatebee_admin_tabs', array( $this, 'add_themes_tab' ), 5 );
		add_action( 'updatebee_output_themes', array( $this, 'output_themes_content' ), 1, 2 );
	}

	/**
	 * Add the themes tab and callback actions.
	 *
	 * @param array<int, array<string, string>> $tabs Array of tabs.
	 * @return array<int, array<string, string>> Array of tabs.
	 */
	public function add_themes_tab( array $tabs ): array {
		$tabs[] = array(
			'get'    => 'themes',
			'action' => 'updatebee_output_themes',
			'url'    => Functions::get_settings_url( 'themes' ),
			'label'  => _x( 'Themes', 'Tab label as Themes', 'updatebee' ),
			'icon'   => 'icon-roller-brush',
		);

		return $tabs;
	}

	/**
	 * Output themes placeholder content.
	 *
	 * @param string      $tab     Current tab.
	 * @param string|null $sub_tab Current sub-tab.
	 */
	public function output_themes_content( string $tab, $sub_tab = '' ): void {
		if ( 'themes' !== $tab ) {
			return;
		}

		unset( $sub_tab );
		?>
		<div class="updatebee-themes-placeholder">
			<p><?php esc_html_e( 'Theme update controls will appear here.', 'updatebee' ); ?></p>
		</div>
		<?php
	}
}
