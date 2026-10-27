<?php
/**
 * Add UpdateBee items to the Network Admin toolbar menu.
 *
 * @package updatebee
 */

namespace UpdateBee\Admin;

use UpdateBee\Functions;
use WP_Admin_Bar;

/**
 * Network admin bar menu items.
 */
class Network_Admin_Bar {

	/**
	 * Class runner.
	 */
	public function run(): void {
		// Priority 30 ensures core's network-admin nodes are registered.
		add_action( 'admin_bar_menu', array( $this, 'add_menu_items' ), 30 );
		// Late priority to reorder after all plugins have added their nodes.
		add_action( 'admin_bar_menu', array( $this, 'reorder_menu_items' ), 999 );
	}

	/**
	 * Inject UpdateBee links into the Network Admin flyout.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 */
	public function add_menu_items( WP_Admin_Bar $wp_admin_bar ): void {
		if ( ! Functions::is_multisite() ) {
			return;
		}

		// Mirror core's visibility check for the Network Admin menu.
		if ( ! current_user_can( 'manage_network' )
			&& ! current_user_can( 'manage_sites' )
			&& ! current_user_can( 'manage_network_users' )
			&& ! current_user_can( 'manage_network_themes' )
			&& ! current_user_can( 'manage_network_plugins' ) ) {
			return;
		}

		if ( ! $wp_admin_bar->get_node( 'network-admin' ) ) {
			return;
		}

		// --- "Updates" ---
		if ( current_user_can( 'update_core' ) ) {
			$wp_admin_bar->add_node(
				array(
					'id'     => 'updatebee-network-updates',
					'parent' => 'network-admin',
					'title'  => _x( 'Updates', 'Toolbar menu item', 'updatebee' ),
					'href'   => network_admin_url( 'update-core.php' ),
				)
			);
		}

		// --- "Automatic Updates" at the very bottom ---
		if ( current_user_can( 'manage_network' ) ) {
			$wp_admin_bar->add_node(
				array(
					'id'     => 'updatebee-network-automatic-updates',
					'parent' => 'network-admin',
					'title'  => _x( 'Automatic Updates', 'Toolbar menu item', 'updatebee' ),
					'href'   => Functions::get_settings_url(),
				)
			);
		}
	}

	/**
	 * Move "Updates" directly below "Dashboard" in the Network Admin flyout.
	 *
	 * WP_Admin_Bar stores nodes in a protected array in insertion order.
	 * We use reflection to reposition our node after core's Dashboard node.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 */
	public function reorder_menu_items( WP_Admin_Bar $wp_admin_bar ): void {
		if ( ! Functions::is_multisite() || ! current_user_can( 'update_core' ) ) {
			return;
		}

		$reflection = new \ReflectionClass( $wp_admin_bar );
		$property   = $reflection->getProperty( 'nodes' );
		$property->setAccessible( true );
		$nodes = $property->getValue( $wp_admin_bar );

		if ( ! isset( $nodes['updatebee-network-updates'], $nodes['network-admin-d'] ) ) {
			return;
		}

		$node = $nodes['updatebee-network-updates'];
		unset( $nodes['updatebee-network-updates'] );

		$reordered = array();
		foreach ( $nodes as $id => $n ) {
			$reordered[ $id ] = $n;
			if ( 'network-admin-d' === $id ) {
				$reordered['updatebee-network-updates'] = $node;
			}
		}

		$property->setValue( $wp_admin_bar, $reordered );
	}
}
