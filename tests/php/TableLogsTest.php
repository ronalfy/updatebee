<?php
/**
 * Tests for the logs table query helpers.
 *
 * @package updatebee
 */

namespace UpdateBee\Tests;

use Brain\Monkey\Functions as MonkeyFunctions;
use UpdateBee\Tables\Table_Logs;

/**
 * Table_Logs test case.
 */
class TableLogsTest extends TestCase {

	/**
	 * Assign a mock wpdb instance for table name resolution.
	 *
	 * @param object $wpdb Mock database object.
	 */
	private function set_mock_wpdb( object $wpdb ): void {
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test mock.
		$GLOBALS['wpdb'] = $wpdb;
	}

	/**
	 * Stub WordPress helpers used by Table_Logs queries.
	 */
	private function stub_table_helpers(): void {
		MonkeyFunctions\when( 'apply_filters' )->returnArg( 2 );
		MonkeyFunctions\when( 'absint' )->alias(
			static function ( $value ) {
				return (int) $value;
			}
		);
		MonkeyFunctions\when( 'sanitize_text_field' )->returnArg( 1 );
		MonkeyFunctions\when( 'wp_parse_args' )->alias(
			static function ( $args, $defaults ) {
				return array_merge( (array) $defaults, (array) $args );
			}
		);
	}

	/**
	 * Create a wpdb mock that records queries.
	 *
	 * @param array<int, array<string, mixed>> $results Query results.
	 * @return object
	 */
	private function create_wpdb_mock( array $results = array() ): object {
		return new class( $results ) {
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
			 * Query results.
			 *
			 * @var array<int, array<string, mixed>>
			 */
			public $results;

			/**
			 * Constructor.
			 *
			 * @param array<int, array<string, mixed>> $results Query results.
			 */
			public function __construct( array $results ) {
				$this->results = $results;
			}

			/**
			 * Mock wpdb prepare.
			 *
			 * @param string $query Query.
			 * @param mixed  ...$args Args.
			 * @return string
			 */
			public function prepare( $query, ...$args ) {
				if ( isset( $args[0] ) && is_array( $args[0] ) && 1 === count( $args ) ) {
					$args = $args[0];
				}

				foreach ( $args as $arg ) {
					if ( is_int( $arg ) ) {
						$query = preg_replace( '/%d/', (string) $arg, $query, 1 );
						continue;
					}

					$query = preg_replace( '/%s/', "'" . $arg . "'", $query, 1 );
				}

				return $query;
			}

			/**
			 * Mock wpdb get_results.
			 *
			 * @param string $query Query.
			 * @return array<int, array<string, mixed>>
			 */
			public function get_results( $query ) {
				$this->query_ran = $query;
				return $this->results;
			}

			/**
			 * Mock wpdb query.
			 *
			 * @param string $query Query.
			 * @return int
			 */
			public function query( $query ) {
				$this->query_ran = $query;
				return 4;
			}
		};
	}

	/**
	 * Test clear_logs issues a delete against the logs table.
	 */
	public function test_clear_logs_deletes_all_rows(): void {
		$this->stub_table_helpers();
		$wpdb = $this->create_wpdb_mock();
		$this->set_mock_wpdb( $wpdb );

		$cleared = Table_Logs::clear_logs();

		$this->assertTrue( $cleared );
		$this->assertStringContainsString( 'DELETE FROM', $wpdb->query_ran );
		$this->assertStringContainsString( Table_Logs::get_table_name(), $wpdb->query_ran );
	}

	/**
	 * Test aged clear deletes rows older than the cutoff.
	 */
	public function test_clear_logs_older_than_deletes_rows_before_cutoff(): void {
		$this->stub_table_helpers();
		$cutoff = '2023-10-14 12:00:00';
		MonkeyFunctions\expect( 'wp_date' )
			->once()
			->with( 'Y-m-d H:i:s', \Mockery::type( 'int' ) )
			->andReturn( $cutoff );

		$wpdb = $this->create_wpdb_mock();
		$this->set_mock_wpdb( $wpdb );

		$cleared = Table_Logs::clear_logs_older_than( 7 );

		$this->assertTrue( $cleared );
		$this->assertStringContainsString( 'DELETE FROM', $wpdb->query_ran );
		$this->assertStringContainsString( Table_Logs::get_table_name(), $wpdb->query_ran );
		$this->assertStringContainsString( "date < '" . $cutoff . "'", $wpdb->query_ran );
	}

	/**
	 * Test aged clear does not query for zero or negative days.
	 */
	public function test_clear_logs_older_than_skips_non_positive_days(): void {
		$this->stub_table_helpers();
		$wpdb = $this->create_wpdb_mock();
		$this->set_mock_wpdb( $wpdb );

		$this->assertFalse( Table_Logs::clear_logs_older_than( 0 ) );
		$this->assertSame( '', $wpdb->query_ran );

		$this->assertFalse( Table_Logs::clear_logs_older_than( -3 ) );
		$this->assertSame( '', $wpdb->query_ran );
	}

	/**
	 * Test export query applies categorical filters and a date range.
	 */
	public function test_get_logs_for_export_applies_filters_and_date_range(): void {
		$this->stub_table_helpers();
		$wpdb = $this->create_wpdb_mock(
			array(
				array(
					'name' => 'Akismet',
				),
			)
		);
		$this->set_mock_wpdb( $wpdb );

		$results = Table_Logs::get_logs_for_export(
			array(
				'update_offer'  => 'automatic',
				'update_type'   => 'plugin',
				'update_status' => 'success',
				'date_from'     => '2026-01-01',
				'date_to'       => '2026-01-31',
			)
		);

		$this->assertCount( 1, $results );
		$this->assertStringContainsString( Table_Logs::get_table_name(), $wpdb->query_ran );
		$this->assertStringContainsString( "update_offer = 'automatic'", $wpdb->query_ran );
		$this->assertStringContainsString( "update_type = 'plugin'", $wpdb->query_ran );
		$this->assertStringContainsString( "update_status = 'success'", $wpdb->query_ran );
		$this->assertStringContainsString( "date >= '2026-01-01 00:00:00'", $wpdb->query_ran );
		$this->assertStringContainsString( "date <= '2026-01-31 23:59:59'", $wpdb->query_ran );
		$this->assertStringContainsString( 'ORDER BY id ASC', $wpdb->query_ran );
		$this->assertStringContainsString( 'LIMIT 500', $wpdb->query_ran );
	}

	/**
	 * Test export query ignores default all filters and invalid dates.
	 */
	public function test_get_logs_for_export_ignores_all_filters_and_invalid_dates(): void {
		$this->stub_table_helpers();
		$wpdb = $this->create_wpdb_mock();
		$this->set_mock_wpdb( $wpdb );

		Table_Logs::get_logs_for_export(
			array(
				'update_offer'  => 'all',
				'update_type'   => 'not-a-type',
				'update_status' => 'all',
				'date_from'     => '01-01-2026',
				'date_to'       => 'invalid',
			)
		);

		$this->assertStringNotContainsString( 'update_offer =', $wpdb->query_ran );
		$this->assertStringNotContainsString( 'update_type =', $wpdb->query_ran );
		$this->assertStringNotContainsString( 'update_status =', $wpdb->query_ran );
		$this->assertStringNotContainsString( 'date >=', $wpdb->query_ran );
		$this->assertStringNotContainsString( 'date <=', $wpdb->query_ran );
	}

	/**
	 * Test paginated logs accept skipped and delayed status filters.
	 */
	public function test_get_logs_paginated_accepts_skipped_and_delayed_status_filters(): void {
		$this->stub_table_helpers();

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
			 * Mock esc_like.
			 *
			 * @param string $text Text.
			 * @return string
			 */
			public function esc_like( $text ) {
				return $text;
			}

			/**
			 * Mock prepare.
			 *
			 * @param string $query Query.
			 * @param mixed  ...$args Args.
			 * @return string
			 */
			public function prepare( $query, ...$args ) {
				if ( isset( $args[0] ) && is_array( $args[0] ) && 1 === count( $args ) ) {
					$args = $args[0];
				}

				foreach ( $args as $arg ) {
					if ( is_int( $arg ) ) {
						$query = preg_replace( '/%d/', (string) $arg, $query, 1 );
						continue;
					}

					$query = preg_replace( '/%s/', "'" . $arg . "'", $query, 1 );
				}

				return $query;
			}

			/**
			 * Mock get_var.
			 *
			 * @param string $query Query.
			 * @return int
			 */
			public function get_var( $query ) {
				$this->query_ran = $query;
				return 0;
			}

			/**
			 * Mock get_results.
			 *
			 * @param string $query Query.
			 * @return array<int, array<string, mixed>>
			 */
			public function get_results( $query ) {
				$this->query_ran = $query;
				return array();
			}
		};

		$this->set_mock_wpdb( $wpdb );

		Table_Logs::get_logs_paginated(
			array(
				'filters' => array(
					'update_status' => 'skipped',
				),
			)
		);

		$this->assertStringContainsString( "update_status = 'skipped'", $wpdb->query_ran );

		Table_Logs::get_logs_paginated(
			array(
				'filters' => array(
					'update_status' => 'delayed',
				),
			)
		);

		$this->assertStringContainsString( "update_status = 'delayed'", $wpdb->query_ran );
	}
}
