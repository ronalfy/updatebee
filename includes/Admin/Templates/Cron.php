<?php
/**
 * Register the Cron tab.
 *
 * @package updatebee
 */

namespace UpdateBee\Admin\Templates;

use UpdateBee\Cron\Cron_Events;
use UpdateBee\Cron\Updates_Cron;
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
	 * Output cron screen content.
	 *
	 * @param string      $tab     Current tab.
	 * @param string|null $sub_tab Current sub-tab.
	 */
	public function output_cron_content( string $tab, $sub_tab = '' ): void {
		if ( 'cron' !== $tab ) {
			return;
		}

		$asset_file = Functions::get_plugin_dir( 'build/cron.asset.php' );
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

		$style_deps = array( 'updatebee-admin' );
		if ( wp_style_is( 'wp-dataviews', 'registered' ) ) {
			$style_deps[] = 'wp-dataviews';
		}

		wp_enqueue_script(
			'updatebee-cron',
			Functions::get_plugin_url( 'build/cron.js' ),
			$deps['dependencies'],
			$deps['version'],
			true
		);
		wp_enqueue_style(
			'updatebee-cron',
			Functions::get_plugin_url( 'build/cron.css' ),
			$style_deps,
			is_string( $deps['version'] ) ? $deps['version'] : Functions::get_plugin_version()
		);

		wp_localize_script(
			'updatebee-cron',
			'updatebeeCron',
			array(
				'nonce'         => wp_create_nonce( Updates_Cron::NONCE_ACTION ),
				'eventsNonce'   => wp_create_nonce( Cron_Events::EVENTS_NONCE_ACTION ),
				'initialSubtab' => Functions::get_admin_sub_tab() ?: 'schedule',
				'settingsUrl'   => esc_url_raw( Functions::get_settings_url( 'cron' ) ),
			)
		);

		unset( $sub_tab );
		?>
		<div id="updatebee-cron-container"></div>
		<?php
	}
}
