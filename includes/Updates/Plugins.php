<?php
/**
 * Plugin auto-update filter.
 *
 * @package updatebee
 */

namespace UpdateBee\Updates;

use UpdateBee\Functions;

/**
 * Enforce per-plugin auto-update settings.
 */
class Plugins {

	/**
	 * Plugins instance.
	 *
	 * @var Plugins|null
	 */
	private static $instance = null;

	/**
	 * Return a class instance.
	 *
	 * @return Plugins
	 */
	public static function get_instance(): Plugins {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Class constructor.
	 */
	private function __construct() {
		add_filter( 'auto_update_plugin', array( $this, 'maybe_auto_update_plugin' ), Functions::get_highest_priority( 10 ), 2 );
	}

	/**
	 * Maybe allow auto-updates on a plugin.
	 *
	 * @param bool   $update Whether to update the plugin.
	 * @param object $item   The item object.
	 * @return bool True to update, false to not.
	 */
	public function maybe_auto_update_plugin( $update, $item ): bool {
		unset( $update );

		if ( ! is_object( $item ) || ! isset( $item->plugin ) ) {
			return false;
		}

		if ( Functions::auto_updates_enabled( (string) $item->plugin, 'plugin' ) ) {
			return true;
		}

		return false;
	}
}
