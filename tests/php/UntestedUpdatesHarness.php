<?php
/**
 * Test double for untested update checks.
 *
 * @package updatebee
 */

namespace UpdateBee\Tests;

use UpdateBee\Updates\Untested_Updates;

/**
 * Records blocks and exemptions without touching the database.
 */
class UntestedUpdatesHarness extends Untested_Updates {

	/**
	 * Items allowed to update while untested, as kind:id.
	 *
	 * @var array<int, string>
	 */
	public array $exempt = array();

	/**
	 * Number of blocked updates recorded.
	 *
	 * @var int
	 */
	public int $logs = 0;

	/**
	 * Whether this item is allowed to update even when it is untested.
	 *
	 * @param string $kind plugin|theme.
	 * @param string $id   Plugin file or theme stylesheet.
	 * @return bool True when the item opts out.
	 */
	protected function item_allows_untested( string $kind, string $id ): bool {
		return in_array( $kind . ':' . $id, $this->exempt, true );
	}

	/**
	 * Record a blocked automatic update.
	 *
	 * @param string $kind plugin|theme.
	 * @param string $id   Plugin file or theme stylesheet.
	 * @param object $item Update offer.
	 */
	protected function log_block( string $kind, string $id, $item ): void {
		unset( $kind, $id, $item );
		++$this->logs;
	}
}
