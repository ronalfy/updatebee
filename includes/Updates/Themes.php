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

		$theme_stylesheet = (string) $item->theme;

		if ( ! Functions::auto_updates_enabled( $theme_stylesheet, 'theme' ) ) {
			return false;
		}

		if ( Functions::auto_updates_patches_only( $theme_stylesheet, 'theme' ) ) {
			$new_version     = isset( $item->new_version ) ? (string) $item->new_version : '';
			$current_version = Functions::get_theme_current_version( $theme_stylesheet );

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
				Functions::log_patch_update_skipped( $theme_stylesheet, 'theme', $current_version, $new_version, $reason );

				return false;
			}
		}

		return true;
	}
}
