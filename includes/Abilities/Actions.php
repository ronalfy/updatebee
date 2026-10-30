<?php
/**
 * Operational ability callbacks.
 *
 * @package updatebee
 */

namespace UpdateBee\Abilities;

use UpdateBee\Functions;
use UpdateBee\Updates\Init as Updates_Init;

/**
 * Update checks and automatic updates shared with the tools screen.
 */
class Actions {

	/**
	 * Clear the update cache and check for new versions.
	 *
	 * @return array{message: string} Completion message.
	 */
	public static function refresh_updates(): array {
		Functions::clear_update_cache( true );

		return array(
			'message' => __( 'Updates have been force refreshed.', 'updatebee' ),
		);
	}

	/**
	 * Schedule plugin, theme, and core version checks.
	 *
	 * @return array{message: string} Completion message.
	 */
	public static function schedule_update_checks(): array {
		Updates_Init::schedule_update_checks();

		return array(
			'message' => __( 'Update cron events have been scheduled.', 'updatebee' ),
		);
	}

	/**
	 * Run automatic updates allowed by the current policy.
	 *
	 * This can change the site by updating core, plugins, themes, and translations.
	 *
	 * @return array{message: string} Completion message.
	 */
	public static function run_auto_updates(): array {
		Updates_Init::run_auto_updates();

		return array(
			'message' => __( 'Force updates completed.', 'updatebee' ),
		);
	}
}
