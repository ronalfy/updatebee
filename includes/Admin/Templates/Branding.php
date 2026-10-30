<?php
/**
 * Register the Branding tab.
 *
 * @package updatebee
 */

namespace UpdateBee\Admin\Templates;

use UpdateBee\Branding as Branding_Resolver;
use UpdateBee\Functions;

/**
 * Output the branding tab and content.
 */
class Branding {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'updatebee_admin_tabs', array( $this, 'add_branding_tab' ), 8 );
		add_action( 'updatebee_output_branding', array( $this, 'output_branding_content' ), 1, 2 );
	}

	/**
	 * Add the branding tab and callback actions.
	 *
	 * @param array<int, array<string, string>> $tabs Array of tabs.
	 * @return array<int, array<string, string>> Array of tabs.
	 */
	public function add_branding_tab( array $tabs ): array {
		$tabs[] = array(
			'get'    => 'branding',
			'action' => 'updatebee_output_branding',
			'url'    => Functions::get_settings_url( 'branding' ),
			'label'  => _x( 'Branding', 'Tab label as Branding', 'updatebee' ),
			'icon'   => 'icon-branding',
		);

		return $tabs;
	}

	/**
	 * Output branding screen content.
	 *
	 * @param string      $tab     Current tab.
	 * @param string|null $sub_tab Current sub-tab.
	 */
	public function output_branding_content( string $tab, $sub_tab = '' ): void {
		if ( 'branding' !== $tab ) {
			return;
		}

		$asset_file = Functions::get_plugin_dir( 'build/branding.asset.php' );
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
			'updatebee-branding',
			Functions::get_plugin_url( 'build/branding.js' ),
			$deps['dependencies'],
			$deps['version'],
			true
		);
		wp_enqueue_style(
			'updatebee-branding',
			Functions::get_plugin_url( 'build/branding.css' ),
			array( 'updatebee-admin' ),
			is_string( $deps['version'] ) ? $deps['version'] : Functions::get_plugin_version()
		);
		wp_localize_script(
			'updatebee-branding',
			'updatebeeBranding',
			self::get_script_data()
		);

		unset( $sub_tab );
		?>
		<div id="updatebee-branding-container"></div>
		<?php
	}

	/**
	 * Localized data for the Branding script.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_script_data(): array {
		$defaults = Branding_Resolver::get_header_defaults();

		return array(
			'saveNonce'   => wp_create_nonce( 'updatebee-save-branding-settings' ),
			'name'        => Branding_Resolver::get_saved_name(),
			'description' => Branding_Resolver::get_saved_description(),
			'author'      => Branding_Resolver::get_saved_author(),
			'url'         => Branding_Resolver::get_saved_url(),
			'defaults'    => array(
				'name'        => $defaults['name'],
				'description' => $defaults['description'],
				'author'      => $defaults['author'],
				'url'         => $defaults['url'],
			),
		);
	}
}
