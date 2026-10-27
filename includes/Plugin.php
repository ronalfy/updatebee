<?php
/**
 * Main plugin bootstrap.
 *
 * @package updatebee
 */

namespace UpdateBee;

use UpdateBee\Admin\Ajax;
use UpdateBee\Admin\Plugin_Settings_Links;
use UpdateBee\Admin\Register_Options_Page;
use UpdateBee\Cron\Clear_Logs as Clear_Logs_Cron;
use UpdateBee\Cron\Disable_Wp_Cron;
use UpdateBee\Cron\Schedules as Cron_Schedules;
use UpdateBee\Cron\Updates_Cron;
use UpdateBee\Emails\Email_Failures;
use UpdateBee\Emails\Email_Successes;
use UpdateBee\Emails\Email_Updates_Available;
use UpdateBee\Slack\Slack_Report;
use UpdateBee\Tables\{
	Tables,
	Table_Logs,
	Table_Plugins,
	Table_Themes,
};
use UpdateBee\Logs as Update_Logs;
use UpdateBee\Updates\Init as Updates_Init;

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
	 * Ajax handlers.
	 *
	 * @var Ajax
	 */
	private Ajax $ajax;

	/**
	 * Update runtime hooks (core, plugins, themes, translations).
	 *
	 * @var Updates_Init
	 */
	private Updates_Init $updates_init;

	/**
	 * Automatic update logging.
	 *
	 * @var Update_Logs
	 */
	private Update_Logs $update_logs;

	/**
	 * Expired log cleanup cron.
	 *
	 * @var Clear_Logs_Cron
	 */
	private Clear_Logs_Cron $clear_logs_cron;

	/**
	 * Custom cron schedules for email digests.
	 *
	 * @var Cron_Schedules
	 */
	private Cron_Schedules $cron_schedules;

	/**
	 * Update check cron schedules (plugins, themes, core).
	 *
	 * @var Updates_Cron
	 */
	private Updates_Cron $updates_cron;

	/**
	 * Success email reports.
	 *
	 * @var Email_Successes
	 */
	private Email_Successes $email_successes;

	/**
	 * Failure email reports.
	 *
	 * @var Email_Failures
	 */
	private Email_Failures $email_failures;

	/**
	 * Slack reports after updates finish.
	 *
	 * @var Slack_Report
	 */
	private Slack_Report $slack_report;

	/**
	 * Email updates available reports.
	 *
	 * @var Email_Updates_Available
	 */
	private Email_Updates_Available $email_updates_available;

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
		// Define DISABLE_WP_CRON before init / wp_cron when the network option is on.
		Disable_Wp_Cron::bootstrap();

		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ), 20 );
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Fired when the init action for WordPress is triggered.
	 */
	public function init(): void {

		// Initialize tables.
		Tables::register_table( Table_Plugins::class );
		Tables::register_table( Table_Themes::class );
		Tables::register_table( Table_Logs::class );
		Tables::init_tables();
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

		$this->ajax = new Ajax();
		$this->ajax->run();

		$this->updates_init = new Updates_Init();
		$this->updates_init->run();

		$this->update_logs = new Update_Logs();
		$this->update_logs->run();

		$this->clear_logs_cron = new Clear_Logs_Cron();
		$this->clear_logs_cron->run();

		$this->cron_schedules = new Cron_Schedules();
		$this->cron_schedules->run();

		$this->updates_cron = new Updates_Cron();
		$this->updates_cron->run();

		$this->email_successes = new Email_Successes();
		$this->email_successes->run();

		$this->email_failures = new Email_Failures();
		$this->email_failures->run();

		$this->email_updates_available = new Email_Updates_Available();
		$this->email_updates_available->run();

		$this->slack_report = new Slack_Report();
		$this->slack_report->run();
	}
}
