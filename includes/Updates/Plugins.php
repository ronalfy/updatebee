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

		$plugin_file = (string) $item->plugin;

		if ( ! Functions::auto_updates_enabled( $plugin_file, 'plugin' ) ) {
			return false;
		}

		if ( Functions::auto_updates_patches_only( $plugin_file, 'plugin' ) ) {
			$new_version     = isset( $item->new_version ) ? (string) $item->new_version : '';
			$current_version = Functions::get_plugin_current_version( $plugin_file );

			if ( $new_version === $current_version ) {
				// Verions are the same, so return update value.
				return true;
			}

			if ( ! Functions::is_patch_update( $current_version, $new_version ) ) {
				$reason = '';
				if ( ! Functions::is_version_parseable( $current_version ) && ! Functions::is_version_parseable( $new_version ) ) {
					$reason = __( 'Installed and offered versions cannot be parsed', 'updatebee' );
				} elseif ( ! Functions::is_version_parseable( $current_version ) ) {
					$reason = __( 'Installed version cannot be parsed', 'updatebee' );
				} elseif ( ! Functions::is_version_parseable( $new_version ) ) {
					$reason = __( 'Offered version cannot be parsed', 'updatebee' );
				} else {
					$reason = __( 'Update is not a patch update', 'updatebee' );
				}
				Functions::log_patch_update_skipped( $plugin_file, 'plugin', $current_version, $new_version, $reason );

				return false;
			}
		}

		return true;
	}
}
