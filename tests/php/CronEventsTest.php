<?php
/**
 * Tests for cron event listing and permissions.
 *
 * @package updatebee
 */

namespace UpdateBee\Tests;

use Brain\Monkey\Functions as MonkeyFunctions;
use UpdateBee\Cron\Cron_Events;
use WP_Error;

/**
 * Cron events test case.
 */
class CronEventsTest extends TestCase {

	/**
	 * Stub multisite for persistent hook list.
	 */
	protected function setUp(): void {
		parent::setUp();
		MonkeyFunctions\when( 'is_multisite' )->justReturn( false );
	}

	/**
	 * Test persistent core hook is not deletable.
	 */
	public function test_delete_event_rejects_persistent_core_hook(): void {
		MonkeyFunctions\expect( 'wp_unschedule_event' )->never();

		$result = Cron_Events::delete_event( 'wp_update_plugins', 'abc123', 1000 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'not_deletable', $result->get_error_code() );
	}

	/**
	 * Test format row marks wp_update_plugins as not deletable.
	 */
	public function test_format_event_row_marks_persistent_hook(): void {
		MonkeyFunctions\when( 'get_date_from_gmt' )->returnArg( 1 );
		MonkeyFunctions\when( 'has_action' )->justReturn( true );
		MonkeyFunctions\when( 'has_filter' )->justReturn( false );

		$row = Cron_Events::format_event_row(
			'wp_update_plugins',
			1700000000,
			'sig1',
			array(),
			'twicedaily',
			43200
		);

		$this->assertFalse( $row['canDelete'] );
		$this->assertTrue( $row['isPersistentCore'] );
		$this->assertTrue( $row['canRun'] );
	}

	/**
	 * Test canRun is false without a callback.
	 */
	public function test_format_event_row_can_run_false_without_callback(): void {
		MonkeyFunctions\when( 'get_date_from_gmt' )->returnArg( 1 );
		MonkeyFunctions\when( 'has_action' )->justReturn( false );
		MonkeyFunctions\when( 'has_filter' )->justReturn( false );

		$row = Cron_Events::format_event_row(
			'custom_plugin_cron',
			1700000000,
			'sig1',
			array(),
			'hourly',
			3600
		);

		$this->assertTrue( $row['canDelete'] );
		$this->assertFalse( $row['canRun'] );
	}

	/**
	 * Test list_events flattens cron array.
	 */
	public function test_list_events_flattens_cron_array(): void {
		MonkeyFunctions\when( '_get_cron_array' )->justReturn(
			array(
				1700000000 => array(
					'my_custom_hook' => array(
						'abc' => array(
							'schedule' => 'hourly',
							'args'     => array(),
							'interval' => 3600,
						),
					),
				),
			)
		);
		MonkeyFunctions\when( 'get_date_from_gmt' )->returnArg( 1 );
		MonkeyFunctions\when( 'has_action' )->justReturn( true );
		MonkeyFunctions\when( 'has_filter' )->justReturn( false );

		$events = Cron_Events::list_events();

		$this->assertCount( 1, $events );
		$this->assertSame( 'my_custom_hook', $events[0]['hook'] );
		$this->assertSame( 'abc', $events[0]['sig'] );
	}
}
