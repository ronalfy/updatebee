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
		add_filter( 'updatebee_admin_tabs', array( $this, 'add_themes_tab' ), 3 );
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
	 * Output themes screen content.
	 *
	 * @param string      $tab     Current tab.
	 * @param string|null $sub_tab Current sub-tab.
	 */
	public function output_themes_content( string $tab, $sub_tab = '' ): void {
		if ( 'themes' !== $tab ) {
			return;
		}

		$asset_file = Functions::get_plugin_dir( 'build/themes.asset.php' );
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
			'updatebee-themes',
			Functions::get_plugin_url( 'build/themes.js' ),
			$deps['dependencies'],
			$deps['version'],
			true
		);
		wp_enqueue_style(
			'updatebee-themes',
			Functions::get_plugin_url( 'build/themes.css' ),
			array( 'updatebee-admin' ),
			is_string( $deps['version'] ) ? $deps['version'] : Functions::get_plugin_version()
		);
		wp_localize_script(
			'updatebee-themes',
			'updatebeeThemes',
			array(
				'retrieveNonce'  => wp_create_nonce( 'updatebee-retrieve-themes' ),
				'bulkNonce'      => wp_create_nonce( 'updatebee-theme-bulk' ),
				'isNetworkAdmin' => is_network_admin(),
			)
		);

		unset( $sub_tab );
		?>
		<div id="updatebee-themes-container"></div>
		<?php
	}
}
