<?php
/**
 * Dashboard menu shortcut for upcoming updates.
 *
 * @package updatebee
 */

namespace UpdateBee\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use UpdateBee\Functions;

/**
 * Link the Dashboard menu to the Upcoming tab.
 */
class Upcoming_Menu {

	/**
	 * Class runner.
	 */
	public function run(): void {
		$hook = Functions::is_multisite() ? 'network_admin_menu' : 'admin_menu';
		add_action( $hook, array( $this, 'register_shortcut' ), 99 );
	}

	/**
	 * Insert the shortcut under Updates.
	 */
	public function register_shortcut(): void {
		if ( ! Functions::can_access_options() || Feature_Visibility::is_hidden( 'upcoming' ) ) {
			return;
		}

		global $submenu;

		if ( ! isset( $submenu['index.php'] ) || ! is_array( $submenu['index.php'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Shortcut is inserted into the Dashboard submenu.
		$submenu['index.php'] = self::insert_shortcut( $submenu['index.php'], self::menu_item() );
	}

	/**
	 * Menu row for the Upcoming Updates shortcut.
	 *
	 * @return array{0: string, 1: string, 2: string, 3: string} Title, capability, slug, and page title.
	 */
	public static function menu_item(): array {
		$capability = Functions::is_multisite() ? 'manage_network' : 'manage_options';

		/**
		 * Filter: Change capability types for managing options.
		 *
		 * @since 1.0.0
		 *
		 * @param string $capability Capability required to manage options.
		 */
		$capability = apply_filters( 'updatebee_manage_options_capabilities', $capability );
		if ( ! is_string( $capability ) || '' === $capability ) {
			$capability = Functions::is_multisite() ? 'manage_network' : 'manage_options';
		}

		$label = __( 'Upcoming Updates', 'updatebee' );

		return array(
			$label,
			$capability,
			self::menu_slug(),
			$label,
		);
	}

	/**
	 * Relative admin URL for the Upcoming tab.
	 *
	 * Ampersands are encoded the way core stores query links in the menu.
	 *
	 * @return string Menu slug.
	 */
	public static function menu_slug(): string {
		$url  = Functions::get_settings_url( 'upcoming' );
		$base = Functions::is_multisite() ? network_admin_url() : admin_url();
		if ( is_string( $base ) && '' !== $base && str_starts_with( $url, $base ) ) {
			$url = substr( $url, strlen( $base ) );
		}

		return esc_url_raw( str_replace( '&', '&amp;', $url ) );
	}

	/**
	 * Place the shortcut immediately after Updates.
	 *
	 * When Updates is missing, the shortcut is appended. A slug that is already
	 * present is left in place.
	 *
	 * @param array<int|string, mixed>                          $dashboard_submenu Dashboard submenu rows.
	 * @param array{0: string, 1: string, 2: string, 3: string} $item              Shortcut row.
	 * @return array<int|string, mixed> Rows in display order.
	 */
	public static function insert_shortcut( array $dashboard_submenu, array $item ): array {
		$slug = $item[2];
		foreach ( $dashboard_submenu as $row ) {
			if ( is_array( $row ) && isset( $row[2] ) && (string) $row[2] === $slug ) {
				return $dashboard_submenu;
			}
		}

		$updates_offset = null;
		$offset         = 0;
		foreach ( $dashboard_submenu as $row ) {
			if ( is_array( $row ) && isset( $row[2] ) && self::is_updates_slug( (string) $row[2] ) ) {
				$updates_offset = $offset;
				break;
			}
			++$offset;
		}

		if ( null === $updates_offset ) {
			$dashboard_submenu[] = $item;
			return $dashboard_submenu;
		}

		$rows = array_values( $dashboard_submenu );
		array_splice( $rows, $updates_offset + 1, 0, array( $item ) );

		return $rows;
	}

	/**
	 * Whether a menu slug points at the Updates screen.
	 *
	 * @param string $slug Menu slug.
	 * @return bool True when the slug is update-core.php.
	 */
	private static function is_updates_slug( string $slug ): bool {
		$slug  = str_replace( '&amp;', '&', $slug );
		$query = strpos( $slug, '?' );
		if ( false !== $query ) {
			$slug = substr( $slug, 0, $query );
		}

		return 'update-core.php' === $slug;
	}
}
