<?php
/**
 * Add UpdateBee items to the Network Admin toolbar menu.
 *
 * @package updatebee
 */

namespace UpdateBee\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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

		// --- "Upcoming Updates" directly under Updates once the flyout is reordered ---
		if ( Functions::can_access_options() && ! Feature_Visibility::is_hidden( 'upcoming' ) ) {
			$wp_admin_bar->add_node(
				array(
					'id'     => 'updatebee-network-upcoming',
					'parent' => 'network-admin',
					'title'  => _x( 'Upcoming Updates', 'Toolbar menu item', 'updatebee' ),
					'href'   => Functions::get_settings_url( 'upcoming' ),
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
	 * Toolbar children render in registration order. Core adds every Network
	 * Admin item in one callback, so hook priority cannot insert Updates
	 * between Dashboard and Sites. When Updates is not already next to
	 * Dashboard, remove the nodes registered after Dashboard and add Updates
	 * back first through the public toolbar API. Upcoming Updates, when
	 * present, is placed directly under Updates.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 */
	public function reorder_menu_items( WP_Admin_Bar $wp_admin_bar ): void {
		if ( ! Functions::is_multisite() || ! current_user_can( 'update_core' ) ) {
			return;
		}

		$nodes = $wp_admin_bar->get_nodes();
		if ( ! is_array( $nodes ) || ! isset( $nodes['updatebee-network-updates'], $nodes['network-admin-d'] ) ) {
			return;
		}

		$ids               = array_keys( $nodes );
		$dashboard_index   = array_search( 'network-admin-d', $ids, true );
		$updates_index     = array_search( 'updatebee-network-updates', $ids, true );
		$upcoming_index    = array_search( 'updatebee-network-upcoming', $ids, true );
		$updates_in_place  = is_int( $dashboard_index ) && $updates_index === $dashboard_index + 1;
		$upcoming_in_place = ! is_int( $upcoming_index ) || ( is_int( $updates_index ) && $upcoming_index === $updates_index + 1 );
		if ( $updates_in_place && $upcoming_in_place ) {
			return;
		}

		$updates_args = $this->node_to_args( $nodes['updatebee-network-updates'] );
		if ( array() === $updates_args ) {
			return;
		}

		$upcoming_args = array();
		if ( isset( $nodes['updatebee-network-upcoming'] ) ) {
			$upcoming_args = $this->node_to_args( $nodes['updatebee-network-upcoming'] );
			if ( array() === $upcoming_args ) {
				return;
			}
		}

		$after            = array();
		$passed_dashboard = false;
		foreach ( $nodes as $id => $node ) {
			if ( 'updatebee-network-updates' === $id || 'updatebee-network-upcoming' === $id ) {
				continue;
			}
			if ( ! $passed_dashboard ) {
				if ( 'network-admin-d' === $id ) {
					$passed_dashboard = true;
				}
				continue;
			}

			$args = $this->node_to_args( $node );
			if ( array() === $args ) {
				return;
			}
			$after[ $id ] = $args;
		}

		$wp_admin_bar->remove_node( 'updatebee-network-updates' );
		$wp_admin_bar->remove_node( 'updatebee-network-upcoming' );
		foreach ( array_keys( $after ) as $id ) {
			$wp_admin_bar->remove_node( $id );
		}

		$wp_admin_bar->add_node( $updates_args );
		if ( array() !== $upcoming_args ) {
			$wp_admin_bar->add_node( $upcoming_args );
		}
		foreach ( $after as $args ) {
			$wp_admin_bar->add_node( $args );
		}
	}

	/**
	 * Normalize a toolbar node for add_node().
	 *
	 * Core's get_nodes() returns objects. The test double stores arrays.
	 *
	 * @param object|array<string, mixed> $node Toolbar node.
	 * @return array<string, mixed> Arguments for add_node(), or an empty array when the node has no id.
	 */
	private function node_to_args( object|array $node ): array {
		if ( is_object( $node ) ) {
			$node = get_object_vars( $node );
		}

		if ( empty( $node['id'] ) ) {
			return array();
		}

		return $node;
	}
}
