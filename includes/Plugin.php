<?php
/**
 * Main plugin bootstrap.
 *
 * @package updatebee
 */

namespace UpdateBee;

use UpdateBee\Admin\Plugin_Settings_Links;
use UpdateBee\Admin\Register_Options_Page;

/**
 * Plugin singleton.
 */
class Plugin {

	/**
	 * Plugin instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Plugin settings links.
	 *
	 * @var Plugin_Settings_Links
	 */
	private Plugin_Settings_Links $admin_settings_links;

	/**
	 * Options page registration.
	 *
	 * @var Register_Options_Page
	 */
	private Register_Options_Page $admin_register_options_page;

	/**
	 * Script and style enqueue.
	 *
	 * @var Enqueue
	 */
	private Enqueue $enqueue;

	/**
	 * Return a class instance.
	 *
	 * @return Plugin
	 */
	public static function get_instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Class constructor.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ), 20 );
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Fired when the init action for WordPress is triggered.
	 */
	public function init(): void {
		load_plugin_textdomain(
			'updatebee',
			false,
			dirname( plugin_basename( UPDATEBEE_FILE ) ) . '/languages/'
		);
	}

	/**
	 * Fired when the plugins for WordPress have finished loading.
	 */
	public function plugins_loaded(): void {
		$this->admin_settings_links = new Plugin_Settings_Links();
		$this->admin_settings_links->run();

		$this->admin_register_options_page = new Register_Options_Page();
		$this->admin_register_options_page->run();

		$this->enqueue = new Enqueue();
		$this->enqueue->run();
	}
}
