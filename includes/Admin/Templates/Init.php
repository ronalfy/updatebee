<?php
/**
 * Initialize tab classes.
 *
 * @package updatebee
 */

namespace UpdateBee\Admin\Templates;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use UpdateBee\Admin\Feature_Visibility;

/**
 * Init all the classes and sub-tabs.
 */
class Init {

	/**
	 * Class runner.
	 */
	public function run(): void {
		$feature_visibility = new Feature_Visibility();
		$feature_visibility->run();

		new Dashboard();
		new Upcoming();
		new Plugins();
		new Themes();
		new Translations();
		new Logs();
		new Emails();
		new Webhooks();
		new Cron();
		new Tools();
		new License();
		new Advanced();
	}
}
