<?php
/**
 * Register the Advanced tab.
 *
 * @package updatebee
 */

namespace UpdateBee\Admin\Templates;

use UpdateBee\Admin\Admin_Bar_Menu;
use UpdateBee\Functions;
use UpdateBee\Options;
use UpdateBee\Updates\Upgrade_Notice_Keywords;

/**
 * Output the advanced tab and content.
 */
class Advanced {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'updatebee_admin_tabs', array( $this, 'add_advanced_tab' ), 9 );
		add_action( 'updatebee_output_advanced', array( $this, 'output_advanced_content' ), 1, 2 );
	}

	/**
	 * Add the advanced tab and callback actions.
	 *
	 * @param array<int, array<string, string>> $tabs Array of tabs.
	 * @return array<int, array<string, string>> Array of tabs.
	 */
	public function add_advanced_tab( array $tabs ): array {
		$tabs[] = array(
			'get'    => 'advanced',
			'action' => 'updatebee_output_advanced',
			'url'    => Functions::get_settings_url( 'advanced' ),
			'label'  => _x( 'Advanced', 'Tab label as Advanced', 'updatebee' ),
			'icon'   => 'icon-labs',
		);

		return $tabs;
	}

	/**
	 * Output advanced screen content.
	 *
	 * @param string      $tab     Current tab.
	 * @param string|null $sub_tab Current sub-tab.
	 */
	public function output_advanced_content( string $tab, $sub_tab = '' ): void {
		if ( 'advanced' !== $tab ) {
			return;
		}

		$asset_file = Functions::get_plugin_dir( 'build/advanced.asset.php' );
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
			'updatebee-advanced',
			Functions::get_plugin_url( 'build/advanced.js' ),
			$deps['dependencies'],
			$deps['version'],
			true
		);
		wp_enqueue_style(
			'updatebee-advanced',
			Functions::get_plugin_url( 'build/advanced.css' ),
			array( 'updatebee-admin' ),
			is_string( $deps['version'] ) ? $deps['version'] : Functions::get_plugin_version()
		);
		wp_localize_script(
			'updatebee-advanced',
			'updatebeeAdvanced',
			array(
				'pluginName'                   => Functions::get_plugin_name(),
				'saveNonce'                    => wp_create_nonce( 'updatebee-save-advanced-settings' ),
				'resetNonce'                   => wp_create_nonce( 'updatebee-reset-options' ),
				'delayUpdates'                 => absint( Options::get_options( 'delay_updates' ) ),
				'blockUntestedUpdates'         => 'on' === Options::get_options( 'block_untested_updates' ),
				'upgradeNoticeKeywords'        => (string) Options::get_options( 'upgrade_notice_keywords' ),
				'upgradeNoticeKeywordsEnabled' => Upgrade_Notice_Keywords::is_enabled(),
				'slackWebhookUrl'              => (string) Options::get_options( 'slack_webhook_url' ),
				'adminBarEnabled'              => (bool) Options::get_options( Admin_Bar_Menu::OPTION_ENABLED ),
				'adminBarShowAdmin'            => (bool) Options::get_options( Admin_Bar_Menu::OPTION_SHOW_ADMIN ),
				'adminBarShowFrontend'         => (bool) Options::get_options( Admin_Bar_Menu::OPTION_SHOW_FRONTEND ),
			)
		);

		unset( $sub_tab );
		?>
		<div id="updatebee-advanced-container"></div>
		<?php
	}
}
