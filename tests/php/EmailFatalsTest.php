<?php
/**
 * Tests for immediate fatal emails.
 *
 * @package updatebee
 */

namespace UpdateBee\Tests;

use Brain\Monkey\Functions as MonkeyFunctions;
use UpdateBee\Emails\Email_Fatals;
use UpdateBee\Options;
use UpdateBee\Updates\Fatal_Detection;

/**
 * Fatal email test case.
 */
class EmailFatalsTest extends TestCase {

	/**
	 * Reset caches.
	 */
	protected function tearDown(): void {
		Fatal_Detection::reset();
		Options::flush_cache();
		parent::tearDown();
	}

	/**
	 * Stub options helpers.
	 *
	 * @param array<string, mixed> $stored Stored option overrides.
	 */
	private function stub_options( array $stored = array() ): void {
		MonkeyFunctions\when( 'apply_filters' )->returnArg( 2 );
		MonkeyFunctions\when( 'wp_parse_args' )->alias(
			static function ( $args, $defaults ) {
				return array_merge( (array) $defaults, (array) $args );
			}
		);
		MonkeyFunctions\when( 'get_site_option' )->alias(
			static function ( $key, $fallback = false ) use ( $stored ) {
				if ( 'updatebee_options' === $key ) {
					return $stored;
				}
				if ( 'admin_email' === $key ) {
					return 'admin@example.com';
				}

				return $fallback;
			}
		);
	}

	/**
	 * Filter keeps only fatal rows.
	 */
	public function test_filter_fatal_updates(): void {
		$filtered = Email_Fatals::filter_fatal_updates(
			array(
				array(
					'name'          => 'Akismet',
					'update_status' => 'success',
				),
				array(
					'name'          => 'Hello Dolly',
					'update_status' => 'fatal',
				),
				array(
					'name'          => 'Broken',
					'update_status' => 'failure',
				),
			)
		);

		$this->assertCount( 1, $filtered );
		$this->assertSame( 'Hello Dolly', $filtered[0]['name'] );
	}

	/**
	 * Recipients fall back to the admin email.
	 */
	public function test_get_recipients_falls_back_to_admin_email(): void {
		$this->stub_options(
			array(
				'fatal_email_recipients' => array(),
			)
		);

		$this->assertSame( array( 'admin@example.com' ), Email_Fatals::get_recipients() );
	}

	/**
	 * Stored recipients are preferred.
	 */
	public function test_get_recipients_uses_stored_list(): void {
		$this->stub_options(
			array(
				'fatal_email_recipients' => array( 'ops@example.com' ),
			)
		);

		$this->assertSame( array( 'ops@example.com' ), Email_Fatals::get_recipients() );
	}

	/**
	 * Disabled detection never sends mail.
	 */
	public function test_maybe_send_skips_when_disabled(): void {
		$this->expectNotToPerformAssertions();
		$this->stub_options(
			array(
				'fatal_error_detection' => false,
			)
		);
		MonkeyFunctions\expect( 'wp_mail' )->never();

		$emails = new Email_Fatals();
		$emails->maybe_send_fatal_report(
			array(
				array(
					'name'          => 'Hello Dolly',
					'update_status' => 'fatal',
				),
			)
		);
	}
}
