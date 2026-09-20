<?php
/**
 * Register the Translations tab.
 *
 * @package updatebee
 */

namespace UpdateBee\Admin\Templates;

use UpdateBee\Functions;

/**
 * Output the translations tab and content.
 */
class Translations {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'updatebee_admin_tabs', array( $this, 'add_translations_tab' ), 6 );
		add_action( 'updatebee_output_translations', array( $this, 'output_translations_content' ), 1, 2 );
	}

	/**
	 * Add the translations tab and callback actions.
	 *
	 * @param array<int, array<string, string>> $tabs Array of tabs.
	 * @return array<int, array<string, string>> Array of tabs.
	 */
	public function add_translations_tab( array $tabs ): array {
		$tabs[] = array(
			'get'    => 'translations',
			'action' => 'updatebee_output_translations',
			'url'    => Functions::get_settings_url( 'translations' ),
			'label'  => _x( 'Translations', 'Tab label as Translations', 'updatebee' ),
			'icon'   => 'icon-language',
		);

		return $tabs;
	}

	/**
	 * Output translations placeholder content.
	 *
	 * @param string      $tab     Current tab.
	 * @param string|null $sub_tab Current sub-tab.
	 */
	public function output_translations_content( string $tab, $sub_tab = '' ): void {
		if ( 'translations' !== $tab ) {
			return;
		}

		unset( $sub_tab );
		?>
		<div class="updatebee-translations-placeholder">
			<p><?php esc_html_e( 'Translation update controls will appear here.', 'updatebee' ); ?></p>
		</div>
		<?php
	}
}
