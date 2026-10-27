<?php
/**
 * Hide the updates column from the WordPress interface.
 *
 * @package updatebee
 */

namespace UpdateBee\Admin;

/**
 * Hide the updates column on the appropriate screens.
 */
class Hide_Updates_Column {
	/**
	 * Class runner.
	 */
	public function run() {
		add_action( 'current_screen', array( $this, 'maybe_remove_update_columns' ), 100 );
	}

	/**
	 * Force remove the auto-updates interface on certain screens.
	 */
	public function maybe_remove_update_columns() {
		// Double-checking we're in the admin.
		if ( ! is_admin() ) {
			return;
		}
		// Skip on Ajax and Cron requests.
		if ( ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
			return;
		}

		// Try to get the current screen.
		$screen = get_current_screen();
		if ( ! $screen || ! isset( $screen->id ) ) {
			return;
		}

		// Force hide the updates column on certain screens.
		switch ( $screen->id ) {
			case 'site-themes-network':
			case 'plugins-network':
			case 'themes-network':
			case 'site-health-network':
			case 'plugins':
			case 'themes':
			case 'site-health':
				add_filter( 'plugins_auto_update_enabled', '__return_false' );
				add_filter( 'themes_auto_update_enabled', '__return_false' );
				break;
		}
	}
}
