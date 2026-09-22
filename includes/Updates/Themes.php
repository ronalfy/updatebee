<?php
/**
 * Theme auto-update filter.
 *
 * @package updatebee
 */

namespace UpdateBee\Updates;

use UpdateBee\Functions;

/**
 * Enforce per-theme auto-update settings.
 */
class Themes {

	/**
	 * Themes instance.
	 *
	 * @var Themes|null
	 */
	private static $instance = null;

	/**
	 * Return a class instance.
	 *
	 * @return Themes
	 */
	public static function get_instance(): Themes {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Class constructor.
	 */
	private function __construct() {
		add_filter( 'auto_update_theme', array( $this, 'maybe_auto_update_theme' ), Functions::get_highest_priority( 10 ), 2 );
	}

	/**
	 * Maybe allow auto-updates on a theme.
	 *
	 * @param bool   $update Whether to update the theme.
	 * @param object $item   The item object.
	 * @return bool True to update, false to not.
	 */
	public function maybe_auto_update_theme( $update, $item ): bool {
		unset( $update );

		if ( ! is_object( $item ) || ! isset( $item->theme ) ) {
			return false;
		}

		if ( Functions::auto_updates_enabled( (string) $item->theme, 'theme' ) ) {
			return true;
		}

		return false;
	}
}
