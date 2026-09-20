<?php
/**
 * Initialize tab classes.
 *
 * @package updatebee
 */

namespace UpdateBee\Admin\Templates;

/**
 * Init all the classes and sub-tabs.
 */
class Init {

	/**
	 * Class runner.
	 */
	public function run(): void {
		new Dashboard();
	}
}
