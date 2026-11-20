<?php
/**
 * Tests for the Dashboard Upcoming Updates shortcut.
 *
 * @package updatebee
 */

namespace UpdateBee\Tests;

use Brain\Monkey\Functions as MonkeyFunctions;
use UpdateBee\Admin\Upcoming_Menu;

/**
 * Upcoming_Menu order test case.
 */
class UpcomingMenuTest extends TestCase {

	/**
	 * Shortcut row used by the order tests.
	 *
	 * @return array{0: string, 1: string, 2: string, 3: string}
	 */
	private function shortcut(): array {
		return array(
			'Upcoming Updates',
			'manage_options',
			'tools.php?page=updatebee&amp;tab=dashboard&amp;subtab=upcoming',
			'Upcoming Updates',
		);
	}

	/**
	 * Visible labels in menu order.
	 *
	 * @param array<int|string, mixed> $rows Submenu rows.
	 * @return string[] Labels.
	 */
	private function labels( array $rows ): array {
		$labels = array();
		foreach ( $rows as $row ) {
			if ( is_array( $row ) && isset( $row[0] ) ) {
				$labels[] = (string) $row[0];
			}
		}

		return $labels;
	}

	/**
	 * Single-site order places the shortcut directly under Updates.
	 */
	public function test_single_site_order_is_home_updates_shortcut(): void {
		$rows = Upcoming_Menu::insert_shortcut(
			array(
				0  => array( 'Home', 'read', 'index.php' ),
				10 => array( 'Updates', 'update_core', 'update-core.php' ),
			),
			$this->shortcut()
		);

		$this->assertSame(
			array( 'Home', 'Updates', 'Upcoming Updates' ),
			$this->labels( $rows )
		);
	}

	/**
	 * Network order keeps Upgrade Network after the shortcut.
	 */
	public function test_network_order_keeps_upgrade_network_after_shortcut(): void {
		$rows = Upcoming_Menu::insert_shortcut(
			array(
				0  => array( 'Home', 'read', 'index.php' ),
				10 => array( 'Updates', 'update_core', 'update-core.php' ),
				15 => array( 'Upgrade Network', 'upgrade_network', 'upgrade.php' ),
			),
			$this->shortcut()
		);

		$this->assertSame(
			array( 'Home', 'Updates', 'Upcoming Updates', 'Upgrade Network' ),
			$this->labels( $rows )
		);
	}

	/**
	 * A missing Updates row appends the shortcut.
	 */
	public function test_missing_updates_appends_shortcut(): void {
		$rows = Upcoming_Menu::insert_shortcut(
			array(
				0 => array( 'Home', 'read', 'index.php' ),
			),
			$this->shortcut()
		);

		$this->assertSame(
			array( 'Home', 'Upcoming Updates' ),
			$this->labels( $rows )
		);
	}

	/**
	 * A second insert does not duplicate the link.
	 */
	public function test_second_insert_does_not_duplicate_link(): void {
		$once  = Upcoming_Menu::insert_shortcut(
			array(
				0  => array( 'Home', 'read', 'index.php' ),
				10 => array( 'Updates', 'update_core', 'update-core.php' ),
			),
			$this->shortcut()
		);
		$twice = Upcoming_Menu::insert_shortcut( $once, $this->shortcut() );

		$this->assertSame( $once, $twice );
	}

	/**
	 * The menu slug is the settings screen with the Upcoming sub-tab selected.
	 */
	public function test_menu_slug_points_at_upcoming_subtab(): void {
		MonkeyFunctions\when( 'is_plugin_active_for_network' )->justReturn( false );
		MonkeyFunctions\when( 'is_multisite' )->justReturn( false );
		MonkeyFunctions\when( 'is_network_admin' )->justReturn( false );
		MonkeyFunctions\when( 'plugin_basename' )->justReturn( 'updatebee/updatebee.php' );
		MonkeyFunctions\when( 'admin_url' )->alias(
			static function ( $path = '' ) {
				return 'https://example.com/wp-admin/' . ltrim( (string) $path, '/' );
			}
		);
		MonkeyFunctions\when( 'sanitize_title' )->returnArg( 1 );
		MonkeyFunctions\when( 'add_query_arg' )->alias(
			static function ( $args, $url ) {
				return $url . '&' . http_build_query( $args );
			}
		);
		MonkeyFunctions\when( 'apply_filters' )->returnArg( 2 );
		MonkeyFunctions\when( 'esc_url_raw' )->returnArg( 1 );

		$item = Upcoming_Menu::menu_item();

		$this->assertSame( 'Upcoming Updates', $item[0] );
		$this->assertSame( 'manage_options', $item[1] );
		$this->assertSame(
			'tools.php?page=updatebee&amp;tab=dashboard&amp;subtab=upcoming',
			$item[2]
		);
	}
}
