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
}
