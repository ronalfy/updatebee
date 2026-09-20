<?php
/**
 * Register the plugin settings page.
 *
 * @package updatebee
 */

namespace UpdateBee\Admin;

use UpdateBee\Admin\Templates\Init;
use UpdateBee\Functions;

/**
 * Register the admin menu option page for the plugin.
 */
class Register_Options_Page {

	/**
	 * Class runner.
	 */
	public function run(): void {
		if ( Functions::is_multisite() ) {
			add_action( 'network_admin_menu', array( $this, 'register_admin_menu' ) );
		} else {
			add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		}

		$tab_init = new Init();
		$tab_init->run();
	}

	/**
	 * Initialize the sub menu.
	 */
	public function register_admin_menu(): void {
		$pagename     = Functions::is_multisite() ? 'settings.php' : 'tools.php';
		$capabilities = Functions::is_multisite() ? 'manage_network' : 'manage_options';

		/**
		 * Filter: Change capability types for managing options.
		 *
		 * @since 1.0.0
		 *
		 * @param string $capabilities Capabilities to manage options.
		 */
		$capabilities = apply_filters( 'updatebee_manage_options_capabilities', $capabilities );

		add_submenu_page(
			$pagename,
			Functions::get_plugin_title(),
			Functions::get_plugin_menu_name(),
			$capabilities,
			'updatebee',
			array( Settings_Page::class, 'settings_page' )
		);
	}
}
