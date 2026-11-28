<?php
/**
 * Base test case.
 *
 * @package updatebee
 */

namespace UpdateBee\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions as MonkeyFunctions;
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
		MonkeyFunctions\when( 'wp_remote_retrieve_response_code' )->alias(
			static function ( $response ) {
				return is_array( $response ) ? ( $response['response']['code'] ?? '' ) : '';
			}
		);
		MonkeyFunctions\when( 'maybe_serialize' )->alias(
			static function ( $value ) {
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Mirrors WordPress serialization.
				return is_array( $value ) || is_object( $value ) ? serialize( $value ) : $value;
			}
		);
	}

	/**
	 * Tear down Brain\Monkey.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
}
