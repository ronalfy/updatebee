<?php
/**
 * Base test case.
 *
 * @package updatebee
 */

namespace UpdateBee\Tests;

use Brain\Monkey;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Shared PHPUnit test case with Brain\Monkey.
 */
abstract class TestCase extends PHPUnitTestCase {

	/**
	 * Set up Brain\Monkey.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	/**
	 * Tear down Brain\Monkey.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
}
