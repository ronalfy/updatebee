<?php
/**
 * Register the License tab.
 *
 * @package updatebee
 */

namespace UpdateBee\Admin\Templates;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use UpdateBee\Branding;
use UpdateBee\Functions;
use UpdateBee\Updater\License_Details;

/**
 * Output the license tab and content.
 */
class License {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'updatebee_admin_tabs', array( $this, 'add_license_tab' ), 9 );
		add_action( 'updatebee_output_license', array( $this, 'output_license_content' ), 1, 2 );
	}

	/**
	 * Add the license tab and callback actions.
	 *
	 * @param array<int, array<string, string>> $tabs Array of tabs.
	 * @return array<int, array<string, string>> Array of tabs.
	 */
	public function add_license_tab( array $tabs ): array {
		if ( Branding::hides_license_tab() ) {
			return $tabs;
		}

		$tabs[] = array(
			'get'    => 'license',
			'action' => 'updatebee_output_license',
			'url'    => Functions::get_settings_url( 'license' ),
			'label'  => _x( 'License', 'Tab label as License', 'updatebee' ),
			'icon'   => 'icon-key',
		);

		return $tabs;
	}

	/**
	 * Output license screen content.
	 *
	 * @param string      $tab     Current tab.
	 * @param string|null $sub_tab Current sub-tab.
	 */
	public function output_license_content( string $tab, $sub_tab = '' ): void {
		if ( 'license' !== $tab ) {
			return;
		}

		$asset_file = Functions::get_plugin_dir( 'build/license.asset.php' );
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
			'updatebee-license',
			Functions::get_plugin_url( 'build/license.js' ),
			$deps['dependencies'],
			$deps['version'],
			true
		);
		Functions::set_script_translations( 'updatebee-license' );
		wp_enqueue_style(
			'updatebee-license',
			Functions::get_plugin_url( 'build/license.css' ),
			array( 'updatebee-admin' ),
			is_string( $deps['version'] ) ? $deps['version'] : Functions::get_plugin_version()
		);

		wp_localize_script(
			'updatebee-license',
			'updatebeeLicense',
			self::get_script_data()
		);

		unset( $sub_tab );
		?>
		<div id="updatebee-license-container"></div>
		<?php
	}

	/**
	 * Localized data for the License script.
	 *
	 * The license key is not included.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_script_data(): array {
		return array(
			'configured'    => License_Details::is_configured(),
			'activateNonce' => wp_create_nonce( 'updatebee-activate-license' ),
			'checkNonce'    => wp_create_nonce( 'updatebee-check-license' ),
			'revokeNonce'   => wp_create_nonce( 'updatebee-revoke-license' ),
			'license'       => License_Details::from_stored(),
			'upsellUrl'     => License_Details::UPSELL_URL,
		);
	}
}
