<?php
/**
 * Tests for update logging.
 *
 * @package updatebee
 */

namespace UpdateBee\Tests;

use Brain\Monkey\Functions as MonkeyFunctions;
use UpdateBee\Logs;
use UpdateBee\Tables\Table_Logs;

/**
 * Logs test case.
 */
class LogsTest extends TestCase {

	/**
	 * Assign a mock wpdb instance for table name resolution.
	 *
	 * @param object $wpdb Mock database object.
	 */
	private function set_mock_wpdb( object $wpdb ): void {
		$GLOBALS['wpdb'] = $wpdb;
	}

	/**
	 * Stub common WordPress helpers used by Logs.
	 */
	private function stub_log_helpers(): void {
		MonkeyFunctions\when( 'apply_filters' )->returnArg( 2 );
		MonkeyFunctions\when( 'do_action' )->justReturn( null );
		MonkeyFunctions\when( 'absint' )->alias(
			static function ( $value ) {
				return (int) $value;
			}
		);
		MonkeyFunctions\when( 'sanitize_text_field' )->returnArg( 1 );
		MonkeyFunctions\when( 'current_time' )->justReturn( '2026-01-01 12:00:00' );
		MonkeyFunctions\when( 'wp_parse_args' )->alias(
			static function ( $args, $defaults ) {
				return array_merge( (array) $defaults, (array) $args );
			}
		);
		MonkeyFunctions\when( 'sanitize_sql_orderby' )->returnArg( 1 );
	}

	/**
	 * Test run registers automatic update hooks.
	 */
	public function test_run_registers_hooks(): void {
		$hooks = array();
		MonkeyFunctions\when( 'add_action' )->alias(
			static function ( $hook, $callback, $priority = 10, $accepted_args = 1 ) use ( &$hooks ) {
				$hooks[] = array(
					'hook'     => $hook,
					'priority' => $priority,
				);
				return true;
			}
		);

		$logs = new Logs();
		$logs->run();

		$this->assertContains(
			array(
				'hook'     => 'pre_auto_update',
				'priority' => 10,
			),
			$hooks
		);
		$this->assertContains(
			array(
				'hook'     => 'automatic_updates_complete',
				'priority' => 10,
			),
			$hooks
		);
		$this->assertContains(
			array(
				'hook'     => 'automatic_updates_complete',
				'priority' => 9,
			),
			$hooks
		);
	}

	/**
	 * Test insert_log skips database write when filter disallows inserts.
	 */
	public function test_insert_log_respects_allow_filter(): void {
		$this->stub_log_helpers();
		MonkeyFunctions\when( 'apply_filters' )->alias(
			static function ( $hook, $value ) {
				if ( 'updatebee_allow_log_insert' === $hook ) {
					return false;
				}
				return $value;
			}
		);

		$wpdb = new class() {
			/**
			 * Table prefix.
			 *
			 * @var string
			 */
			public $base_prefix = 'wp_';

			/**
			 * Whether insert was called.
			 *
			 * @var bool
			 */
			public $insert_ran = false;

			/**
			 * Mock wpdb insert.
			 *
			 * @return bool
			 */
			public function insert() {
				$this->insert_ran = true;
				return true;
			}
		};
		$this->set_mock_wpdb( $wpdb );

		$logs = new Logs();
		$logs->insert_log( 'Plugin', 'plugin-slug', 0, '1.0.0', '1.0.1', 'automatic', 'plugin', 'success', '' );

		$this->assertFalse( $wpdb->insert_ran );
	}

	/**
	 * Test plugin failures are logged when versions do not increase.
	 */
	public function test_automatic_updates_marks_stale_plugin_version_as_failure(): void {
		$this->stub_log_helpers();

		$wpdb = new class() {
			/**
			 * Table prefix.
			 *
			 * @var string
			 */
			public $base_prefix = 'wp_';

			/**
			 * Last inserted update status.
			 *
			 * @var string|null
			 */
			public $inserted_status = null;

			/**
			 * Mock wpdb insert.
			 *
			 * @param string               $table Table name.
			 * @param array<string, mixed> $data  Row data.
			 * @return bool
			 */
			public function insert( $table, $data ) {
				unset( $table );
				$this->inserted_status = $data['update_status'] ?? null;
				return true;
			}

			/**
			 * Mock wpdb prepare.
			 *
			 * @param string $query Query.
			 * @param mixed  ...$args Args.
			 * @return string
			 */
			public function prepare( $query, ...$args ) {
				return vsprintf( str_replace( '%s', "'%s'", $query ), $args );
			}
		};
		$this->set_mock_wpdb( $wpdb );

		MonkeyFunctions\when( 'get_site_option' )->justReturn( '' );
		MonkeyFunctions\when( 'delete_site_option' )->justReturn( true );

		$plugin_result = (object) array(
			'name'     => 'Hello Dolly',
			'messages' => array(),
			'item'     => (object) array(
				'plugin'          => 'hello-dolly/hello.php',
				'slug'            => 'hello-dolly',
				'current_version' => '1.7.2',
				'new_version'     => '1.7.2',
			),
		);

		$logs = new Logs();
		$logs->automatic_updates(
			array(
				'plugin' => array( $plugin_result ),
			)
		);

		$this->assertSame( 'failure', $wpdb->inserted_status );
	}

	/**
	 * Test get_success_logs returns empty when no types are supported.
	 */
	public function test_get_success_logs_empty_when_no_supported_types(): void {
		$this->stub_log_helpers();
		$wpdb = new class() {
			/**
			 * Table prefix.
			 *
			 * @var string
			 */
			public $base_prefix = 'wp_';

			/**
			 * Whether get_results ran.
			 *
			 * @var bool
			 */
			public $query_ran = false;

			/**
			 * Mock wpdb get_results.
			 *
			 * @return array<int, mixed>
			 */
			public function get_results() {
				$this->query_ran = true;
				return array();
			}
		};
		$this->set_mock_wpdb( $wpdb );

		$results = Logs::get_success_logs(
			array(
				'plugins'      => false,
				'themes'       => false,
				'core'         => false,
				'translations' => false,
			)
		);

		$this->assertSame( array(), $results );
		$this->assertFalse( $wpdb->query_ran );
	}

	/**
	 * Test get_failure_logs queries the logs table.
	 */
	public function test_get_failure_logs_queries_table(): void {
		$this->stub_log_helpers();

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
				return vsprintf( str_replace( '%s', "'%s'", $query ), $args );
			}

			/**
			 * Mock wpdb get_results.
			 *
			 * @param string $query Query.
			 * @return array<int, array<string, string>>
			 */
			public function get_results( $query ) {
				$this->query_ran = $query;
				return array(
					array(
						'name'          => 'Core',
						'update_status' => 'failure',
						'update_type'   => 'core',
					),
				);
			}
		};
		$this->set_mock_wpdb( $wpdb );

		$results = Logs::get_failure_logs();

		$this->assertNotSame( '', $wpdb->query_ran );
		$this->assertStringContainsString( Table_Logs::get_table_name(), $wpdb->query_ran );
		$this->assertStringContainsString( "'failure'", $wpdb->query_ran );
		$this->assertCount( 1, $results );
	}
}
