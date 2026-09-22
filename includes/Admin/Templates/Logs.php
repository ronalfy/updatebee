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
		add_filter( 'updatebee_admin_tabs', array( $this, 'add_logs_tab' ), 5 );
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
	 * Output logs screen content.
	 *
	 * @param string      $tab     Current tab.
	 * @param string|null $sub_tab Current sub-tab.
	 */
	public function output_logs_content( string $tab, $sub_tab = '' ): void {
		if ( 'logs' !== $tab ) {
			return;
		}

		$asset_file = Functions::get_plugin_dir( 'build/logs.asset.php' );
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
			'updatebee-logs',
			Functions::get_plugin_url( 'build/logs.js' ),
			$deps['dependencies'],
			$deps['version'],
			true
		);
		wp_enqueue_style(
			'updatebee-logs',
			Functions::get_plugin_url( 'build/logs.css' ),
			$style_deps,
			is_string( $deps['version'] ) ? $deps['version'] : Functions::get_plugin_version()
		);
		wp_localize_script(
			'updatebee-logs',
			'updatebeeLogs',
			array(
				'retrieveNonce' => wp_create_nonce( 'updatebee-retrieve-logs' ),
			)
		);

		unset( $sub_tab );
		?>
		<div id="updatebee-logs-container"></div>
		<?php
	}
}
