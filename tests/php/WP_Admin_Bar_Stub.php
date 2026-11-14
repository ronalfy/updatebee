<?php
/**
 * Admin bar double for unit tests.
 *
 * @package updatebee
 */

/**
 * Records toolbar nodes for unit tests.
 */
class WP_Admin_Bar {

	/**
	 * Nodes passed to add_node(), keyed by id.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	public $nodes = array();

	/**
	 * Store a toolbar node.
	 *
	 * @param array<string, mixed> $node Node arguments.
	 */
	public function add_node( $node ) {
		if ( is_array( $node ) && isset( $node['id'] ) ) {
			$this->nodes[ $node['id'] ] = $node;
		}
	}

	/**
	 * Retrieve one toolbar node.
	 *
	 * @param string $id Node id.
	 * @return array<string, mixed>|null Node arguments, or null when the id is missing.
	 */
	public function get_node( $id ) {
		if ( ! is_string( $id ) || ! isset( $this->nodes[ $id ] ) ) {
			return null;
		}

		return $this->nodes[ $id ];
	}

	/**
	 * Retrieve toolbar nodes in registration order.
	 *
	 * @return array<string, array<string, mixed>>|null Nodes keyed by id, or null when none are registered.
	 */
	public function get_nodes() {
		if ( array() === $this->nodes ) {
			return null;
		}

		return $this->nodes;
	}

	/**
	 * Remove a toolbar node.
	 *
	 * @param string $id Node id.
	 */
	public function remove_node( $id ) {
		if ( ! is_string( $id ) ) {
			return;
		}

		unset( $this->nodes[ $id ] );
	}
}
