<?php
/**
 * Tests for expired-log cron scheduling.
 *
 * @package updatebee
 */

namespace UpdateBee\Tests;

use Brain\Monkey\Functions as MonkeyFunctions;
use Mockery;
use UpdateBee\Cron\Clear_Logs;
use UpdateBee\Options;
use UpdateBee\Tables\Table_Logs;

/**
 * Clear_Logs cron test case.
 */
class ClearLogsCronTest extends TestCase {

	/**
	 * Reset the options cache after each test.
	 */
	protected function tearDown(): void {
		Options::flush_cache();
		parent::tearDown();
	}

	/**
	 * Stub helpers used when reading options.
	 */
	private function stub_option_helpers(): void {
		MonkeyFunctions\when( 'apply_filters' )->returnArg( 2 );
		MonkeyFunctions\when( 'absint' )->alias(
			static function ( $value ) {
				return (int) $value;
			}
		);
		MonkeyFunctions\when( 'wp_parse_args' )->alias(
			static function ( $args, $defaults ) {
				return array_merge( (array) $defaults, (array) $args );
			}
		);
	}

	/**
	 * Assign a mock wpdb instance for table queries.
	 *
	 * @param object $wpdb Mock database object.
	 */
	private function set_mock_wpdb( object $wpdb ): void {
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test mock.
		$GLOBALS['wpdb'] = $wpdb;
	}

	/**
	 * Test a positive retention value schedules a daily event.
	 */
	public function test_sync_schedule_schedules_daily_event_when_days_positive(): void {
		$this->stub_option_helpers();
		MonkeyFunctions\expect( 'get_site_option' )
			->once()
			->with( 'updatebee_options', array() )
			->andReturn(
				array(
					'clear_logs_days' => 14,
				)
			);
		MonkeyFunctions\expect( 'wp_next_scheduled' )
			->once()
			->with( Clear_Logs::HOOK )
			->andReturn( false );
		MonkeyFunctions\expect( 'wp_schedule_event' )
			->once()
			->with( Mockery::type( 'int' ), 'daily', Clear_Logs::HOOK )
			->andReturn( true );
		MonkeyFunctions\expect( 'wp_clear_scheduled_hook' )->never();

		Clear_Logs::sync_schedule();

		$this->assertSame( 14, Clear_Logs::get_retention_days() );
	}

	/**
	 * Test an existing daily event is left in place.
	 */
	public function test_sync_schedule_skips_when_event_already_scheduled(): void {
		$this->stub_option_helpers();
		MonkeyFunctions\expect( 'get_site_option' )
			->once()
			->with( 'updatebee_options', array() )
			->andReturn(
				array(
					'clear_logs_days' => 7,
				)
			);
		MonkeyFunctions\expect( 'wp_next_scheduled' )
			->once()
			->with( Clear_Logs::HOOK )
			->andReturn( 1234567890 );
		MonkeyFunctions\expect( 'wp_schedule_event' )->never();
		MonkeyFunctions\expect( 'wp_clear_scheduled_hook' )->never();

		Clear_Logs::sync_schedule();

		$this->assertSame( 7, Clear_Logs::get_retention_days() );
	}

	/**
	 * Test a zero retention value unschedules the daily event.
	 */
	public function test_sync_schedule_unschedules_when_days_zero(): void {
		$this->stub_option_helpers();
		MonkeyFunctions\expect( 'get_site_option' )
			->once()
			->with( 'updatebee_options', array() )
			->andReturn( array() );
		MonkeyFunctions\expect( 'wp_clear_scheduled_hook' )
			->once()
			->with( Clear_Logs::HOOK );
		MonkeyFunctions\expect( 'wp_schedule_event' )->never();

		Clear_Logs::sync_schedule();

		$this->assertSame( 0, Clear_Logs::get_retention_days() );
	}

	/**
	 * Test the cron callback is a no-op when retention is disabled.
	 */
	public function test_clear_expired_logs_skips_when_days_zero(): void {
		$this->stub_option_helpers();
		MonkeyFunctions\expect( 'get_site_option' )
			->once()
			->with( 'updatebee_options', array() )
			->andReturn( array() );
		MonkeyFunctions\expect( 'wp_date' )->never();

		$cron = new Clear_Logs();
		$cron->clear_expired_logs();

		$this->assertSame( 0, Clear_Logs::get_retention_days() );
	}

	/**
	 * Test the cron callback deletes aged rows when retention is enabled.
	 */
	public function test_clear_expired_logs_deletes_when_days_positive(): void {
		$this->stub_option_helpers();
		$cutoff = '2023-10-14 12:00:00';
		MonkeyFunctions\expect( 'wp_date' )
			->once()
			->with( 'Y-m-d H:i:s', Mockery::type( 'int' ) )
			->andReturn( $cutoff );
		MonkeyFunctions\expect( 'get_site_option' )
			->once()
			->with( 'updatebee_options', array() )
			->andReturn(
				array(
					'clear_logs_days' => 7,
				)
			);

		$wpdb = new class() {
			/**
			 * Table prefix.
			 *
			 * @var string
			 */
			public $base_prefix = 'wp_';

			/**
			 * Last SQL query.
			 *
			 * @var string
			 */
			public $query_ran = '';

			/**
			 * Mock wpdb prepare.
			 *
			 * @param string $query Query.
			 * @param mixed  ...$args Args.
			 * @return string
			 */
			public function prepare( $query, ...$args ) {
				foreach ( $args as $arg ) {
					$query = preg_replace( '/%s/', "'" . $arg . "'", $query, 1 );
				}

				return $query;
			}

			/**
			 * Mock wpdb query.
			 *
			 * @param string $query Query.
			 * @return int
			 */
			public function query( $query ) {
				$this->query_ran = $query;
				return 2;
			}
		};
		$this->set_mock_wpdb( $wpdb );

		$cron = new Clear_Logs();
		$cron->clear_expired_logs();

		$this->assertStringContainsString( 'DELETE FROM', $wpdb->query_ran );
		$this->assertStringContainsString( Table_Logs::get_table_name(), $wpdb->query_ran );
		$this->assertStringContainsString( "date < '" . $cutoff . "'", $wpdb->query_ran );
	}
}
