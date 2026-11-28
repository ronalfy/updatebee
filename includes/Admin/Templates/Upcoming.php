<?php
/**
 * Register the Upcoming tab.
 *
 * @package updatebee
 */

namespace UpdateBee\Admin\Templates;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use UpdateBee\Admin\Feature_Visibility;
use UpdateBee\Functions;

/**
 * Output the upcoming tab and content.
 */
class Upcoming {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'updatebee_admin_tabs', array( $this, 'add_upcoming_tab' ), 11 );
		add_action( 'updatebee_output_upcoming', array( $this, 'output_upcoming_content' ), 1, 2 );
	}

	/**
	 * Add the upcoming tab directly under Settings.
	 *
	 * Priority 11 runs after the other tabs are registered. The item is then
	 * inserted after the Settings tab so its place does not depend on callback order.
	 *
	 * @param array<int, array<string, string>> $tabs Array of tabs.
	 * @return array<int, array<string, string>> Array of tabs.
	 */
	public function add_upcoming_tab( array $tabs ): array {
		$tab = array(
			'get'    => 'upcoming',
			'action' => 'updatebee_output_upcoming',
			'url'    => Functions::get_settings_url( 'upcoming' ),
			'label'  => _x( 'Upcoming', 'Tab label as Upcoming', 'updatebee' ),
			'icon'   => 'icon-upcoming',
		);

		$insert_at = count( $tabs );
		foreach ( $tabs as $index => $existing ) {
			if ( is_array( $existing ) && isset( $existing['get'] ) && 'dashboard' === $existing['get'] ) {
				$insert_at = (int) $index + 1;
				break;
			}
		}

		array_splice( $tabs, $insert_at, 0, array( $tab ) );

		return $tabs;
	}

	/**
	 * Output the upcoming updates screen.
	 *
	 * @param string      $tab     Current tab.
	 * @param string|null $sub_tab Current sub-tab.
	 */
	public function output_upcoming_content( string $tab, $sub_tab = '' ): void {
		if ( 'upcoming' !== $tab ) {
			return;
		}

		$asset_file = Functions::get_plugin_dir( 'build/upcoming.asset.php' );
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
			'updatebee-upcoming',
			Functions::get_plugin_url( 'build/upcoming.js' ),
			$deps['dependencies'],
			$deps['version'],
			true
		);
		Functions::set_script_translations( 'updatebee-upcoming' );
		wp_enqueue_style(
			'updatebee-upcoming',
			Functions::get_plugin_url( 'build/upcoming.css' ),
			$style_deps,
			is_string( $deps['version'] ) ? $deps['version'] : Functions::get_plugin_version()
		);
		wp_localize_script(
			'updatebee-upcoming',
			'updatebeeUpcoming',
			array(
				'retrieveUpcomingNonce' => wp_create_nonce( 'updatebee-retrieve-upcoming-updates' ),
				'saveDelayNonce'        => wp_create_nonce( 'updatebee-save-upcoming-delay' ),
				'refreshNonce'          => wp_create_nonce( 'updatebee-refresh-updates' ),
				'schedulesUrl'          => esc_url_raw( Functions::get_settings_url( 'cron' ) ),
				'showSchedules'         => ! Feature_Visibility::is_hidden( 'scheduling' ),
			)
		);

		unset( $sub_tab );
		?>
		<div id="updatebee-upcoming-container"></div>
		<?php
	}
}
