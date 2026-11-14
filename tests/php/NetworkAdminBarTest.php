<?php
/**
 * Tests for Network Admin toolbar ordering.
 *
 * @package updatebee
 */

namespace UpdateBee\Tests;

use Brain\Monkey\Functions as MonkeyFunctions;
use UpdateBee\Admin\Network_Admin_Bar;
use WP_Admin_Bar;

/**
 * Network_Admin_Bar reorder test case.
 */
class NetworkAdminBarTest extends TestCase {

	/**
	 * Updates moves directly under Dashboard, and a second call keeps that order.
	 */
	public function test_moves_updates_directly_after_dashboard(): void {
		$this->stub_reorder_access( true, true );
		$bar = $this->bar_with_object_nodes();
		$this->seed_nodes( $bar, $this->network_nodes() );

		$menu = new Network_Admin_Bar();
		$menu->reorder_menu_items( $bar );

		$expected = array(
			'my-sites',
			'network-admin',
			'network-admin-d',
			'updatebee-network-updates',
			'network-admin-s',
			'network-admin-s-child',
			'updatebee-network-automatic-updates',
		);
		$this->assertSame( $expected, array_keys( $bar->nodes ) );

		$updates = $bar->nodes['updatebee-network-updates'];
		$this->assertSame( 'Updates', $updates['title'] );
		$this->assertSame( 'https://example.com/wp-admin/network/update-core.php', $updates['href'] );
		$this->assertSame( 'network-admin', $updates['parent'] );

		$sites_index = array_search( 'network-admin-s', array_keys( $bar->nodes ), true );
		$child_index = array_search( 'network-admin-s-child', array_keys( $bar->nodes ), true );
		$this->assertIsInt( $sites_index );
		$this->assertSame( $sites_index + 1, $child_index );

		$menu->reorder_menu_items( $bar );

		$this->assertSame( $expected, array_keys( $bar->nodes ) );
		$this->assertSame( $updates, $bar->nodes['updatebee-network-updates'] );
	}

	/**
	 * Missing Dashboard or Updates leaves the registered order alone.
	 */
	public function test_missing_node_leaves_order_alone(): void {
		$this->stub_reorder_access( true, true );
		$menu = new Network_Admin_Bar();

		$without_dashboard = $this->nodes_except( 'network-admin-d' );
		$bar               = new WP_Admin_Bar();
		$this->seed_nodes( $bar, $without_dashboard );
		$before = array_keys( $bar->nodes );

		$menu->reorder_menu_items( $bar );

		$this->assertSame( $before, array_keys( $bar->nodes ) );

		$without_updates = $this->nodes_except( 'updatebee-network-updates' );
		$bar             = new WP_Admin_Bar();
		$this->seed_nodes( $bar, $without_updates );
		$before = array_keys( $bar->nodes );

		$menu->reorder_menu_items( $bar );

		$this->assertSame( $before, array_keys( $bar->nodes ) );
	}

	/**
	 * Users who cannot update core do not change toolbar order.
	 */
	public function test_skips_reorder_without_update_core(): void {
		$this->stub_reorder_access( true, false );
		$bar = new WP_Admin_Bar();
		$this->seed_nodes( $bar, $this->network_nodes() );
		$before = array_keys( $bar->nodes );

		( new Network_Admin_Bar() )->reorder_menu_items( $bar );

		$this->assertSame( $before, array_keys( $bar->nodes ) );
	}

	/**
	 * Single-site requests do not change toolbar order.
	 */
	public function test_skips_reorder_when_not_multisite(): void {
		$this->stub_reorder_access( false, true );
		$bar = new WP_Admin_Bar();
		$this->seed_nodes( $bar, $this->network_nodes() );
		$before = array_keys( $bar->nodes );

		( new Network_Admin_Bar() )->reorder_menu_items( $bar );

		$this->assertSame( $before, array_keys( $bar->nodes ) );
	}

	/**
	 * Stub the checks used by reorder_menu_items().
	 *
	 * @param bool $is_multisite Whether Functions::is_multisite() should pass.
	 * @param bool $can_update   Whether the user has update_core.
	 */
	private function stub_reorder_access( bool $is_multisite, bool $can_update ): void {
		MonkeyFunctions\when( 'is_multisite' )->justReturn( $is_multisite );
		MonkeyFunctions\when( 'is_plugin_active_for_network' )->justReturn( $is_multisite );
		MonkeyFunctions\when( 'plugin_basename' )->justReturn( 'updatebee/updatebee.php' );
		MonkeyFunctions\when( 'current_user_can' )->alias(
			static function ( string $cap ) use ( $can_update ): bool {
				return $can_update && 'update_core' === $cap;
			}
		);
	}

	/**
	 * Toolbar double whose get_nodes() returns objects, as core does.
	 *
	 * @return WP_Admin_Bar Admin bar double.
	 */
	private function bar_with_object_nodes(): WP_Admin_Bar {
		return new class() extends WP_Admin_Bar {
			/**
			 * Retrieve nodes as objects.
			 *
			 * @return array<string, object>|null
			 */
			public function get_nodes() {
				$nodes = parent::get_nodes();
				if ( ! is_array( $nodes ) ) {
					return null;
				}

				foreach ( $nodes as $id => $node ) {
					$nodes[ $id ] = (object) $node;
				}

				return $nodes;
			}
		};
	}

	/**
	 * Register nodes in the given order.
	 *
	 * @param WP_Admin_Bar                     $bar   Admin bar double.
	 * @param array<int, array<string, mixed>> $nodes Node arguments.
	 */
	private function seed_nodes( WP_Admin_Bar $bar, array $nodes ): void {
		foreach ( $nodes as $node ) {
			$bar->add_node( $node );
		}
	}

	/**
	 * Network nodes without one id.
	 *
	 * @param string $id Node id to drop.
	 * @return array<int, array<string, string>>
	 */
	private function nodes_except( string $id ): array {
		return array_values(
			array_filter(
				$this->network_nodes(),
				static function ( array $node ) use ( $id ): bool {
					return $id !== $node['id'];
				}
			)
		);
	}

	/**
	 * Network Admin nodes in core registration order, plus UpdateBee's links.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function network_nodes(): array {
		return array(
			array(
				'id'    => 'my-sites',
				'title' => 'My Sites',
				'href'  => 'https://example.com/wp-admin/my-sites.php',
			),
			array(
				'id'     => 'network-admin',
				'parent' => 'my-sites',
				'title'  => 'Network Admin',
				'href'   => 'https://example.com/wp-admin/network/',
			),
			array(
				'id'     => 'network-admin-d',
				'parent' => 'network-admin',
				'title'  => 'Dashboard',
				'href'   => 'https://example.com/wp-admin/network/',
			),
			array(
				'id'     => 'network-admin-s',
				'parent' => 'network-admin',
				'title'  => 'Sites',
				'href'   => 'https://example.com/wp-admin/network/sites.php',
			),
			array(
				'id'     => 'network-admin-s-child',
				'parent' => 'network-admin-s',
				'title'  => 'All Sites',
				'href'   => 'https://example.com/wp-admin/network/sites.php',
			),
			array(
				'id'     => 'updatebee-network-updates',
				'parent' => 'network-admin',
				'title'  => 'Updates',
				'href'   => 'https://example.com/wp-admin/network/update-core.php',
			),
			array(
				'id'     => 'updatebee-network-automatic-updates',
				'parent' => 'network-admin',
				'title'  => 'Automatic Updates',
				'href'   => 'https://example.com/wp-admin/network/settings.php?page=updatebee',
			),
		);
	}
}
