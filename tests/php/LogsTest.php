<?php
/**
 * Tests for update logging.
 *
 * @package updatebee
 */

namespace UpdateBee\Tests;

use Brain\Monkey\Functions as MonkeyFunctions;
use ReflectionClass;
use UpdateBee\Logs;
use UpdateBee\Tables\Table_Logs;
use UpdateBee\Updates\Fatal_Detection;

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
				unset( $callback, $accepted_args );
				$hooks[] = array(
					'hook'     => $hook,
					'priority' => $priority,
				);
				return true;
			}
		);
		MonkeyFunctions\when( 'add_filter' )->justReturn( true );

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
	 * Test run registers manual upgrader logging hooks.
	 */
	public function test_run_registers_manual_upgrader_hooks(): void {
		$filters = array();
		$actions = array();

		MonkeyFunctions\when( 'add_filter' )->alias(
			static function ( $hook ) use ( &$filters ) {
				$filters[] = $hook;
				return true;
			}
		);
		MonkeyFunctions\when( 'add_action' )->alias(
			static function ( $hook, $callback, $priority = 10 ) use ( &$actions ) {
				unset( $callback );
				$actions[] = array(
					'hook'     => $hook,
					'priority' => $priority,
				);
				return true;
			}
		);

		$logs = new Logs();
		$logs->run();

		$this->assertContains( 'upgrader_package_options', $filters );
		$this->assertContains( 'upgrader_pre_download', $filters );
		$this->assertContains(
			array(
				'hook'     => 'upgrader_process_complete',
				'priority' => 1,
			),
			$actions
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

		if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
			define( 'WP_PLUGIN_DIR', ABSPATH . 'wp-content/plugins' );
		}

		MonkeyFunctions\when( 'get_plugin_data' )->justReturn(
			array(
				'Version' => '1.7.2',
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

	/**
	 * Test manual plugin updates are written with manual offer and user id.
	 */
	public function test_log_upgrader_updates_writes_manual_plugin_log(): void {
		$this->stub_log_helpers();
		MonkeyFunctions\when( 'get_current_user_id' )->justReturn( 42 );
		MonkeyFunctions\when( 'maybe_serialize' )->alias(
			static function ( $data ) {
				return serialize( $data );
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
			 * Last inserted row.
			 *
			 * @var array<string, mixed>|null
			 */
			public $inserted_row = null;

			/**
			 * Mock wpdb insert.
			 *
			 * @param string               $table Table name.
			 * @param array<string, mixed> $data  Row data.
			 * @return bool
			 */
			public function insert( $table, $data ) {
				unset( $table );
				$this->inserted_row = $data;
				return true;
			}
		};
		$this->set_mock_wpdb( $wpdb );

		$logs       = new Logs();
		$reflection = new \ReflectionClass( $logs );
		$messages   = $reflection->getProperty( 'log_messages' );
		$messages->setAccessible( true );
		$messages->setValue(
			$logs,
			array(
				'plugin' => array(
					'hello-dolly/hello.php' => array(
						'name'   => 'Hello Dolly',
						'from'   => '1.0.0',
						'to'     => '1.1.0',
						'status' => 1,
					),
				),
			)
		);

		$logs->log_upgrader_updates(
			(object) array(),
			array(
				'action'  => 'update',
				'type'    => 'plugin',
				'plugins' => array( 'hello-dolly/hello.php' ),
			)
		);

		$this->assertIsArray( $wpdb->inserted_row );
		$this->assertSame( 'manual', $wpdb->inserted_row['update_offer'] );
		$this->assertSame( 42, $wpdb->inserted_row['user_id'] );
		$this->assertSame( 'success', $wpdb->inserted_row['update_status'] );
		$this->assertSame( 'hello-dolly', $wpdb->inserted_row['slug'] );
	}

	/**
	 * Test upgrader logging is skipped during automatic updates.
	 */
	public function test_log_upgrader_updates_skips_when_auto_update_flag_set(): void {
		$this->stub_log_helpers();
		MonkeyFunctions\when( 'get_current_user_id' )->justReturn( 1 );

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

		$logs       = new Logs();
		$reflection = new \ReflectionClass( $logs );
		$auto_flag  = $reflection->getProperty( 'is_auto_update' );
		$auto_flag->setAccessible( true );
		$auto_flag->setValue( $logs, true );

		$messages = $reflection->getProperty( 'log_messages' );
		$messages->setAccessible( true );
		$messages->setValue(
			$logs,
			array(
				'plugin' => array(
					'hello-dolly/hello.php' => array(
						'name'   => 'Hello Dolly',
						'from'   => '1.0.0',
						'to'     => '1.1.0',
						'status' => 1,
					),
				),
			)
		);

		$logs->log_upgrader_updates(
			(object) array(),
			array(
				'action'  => 'update',
				'type'    => 'plugin',
				'plugins' => array( 'hello-dolly/hello.php' ),
			)
		);

		$this->assertFalse( $wpdb->insert_ran );
	}

	/**
	 * Test insert_log persists stacktrace when provided.
	 */
	public function test_insert_log_persists_stacktrace(): void {
		$this->stub_log_helpers();

		$wpdb = new class() {
			/**
			 * Table prefix.
			 *
			 * @var string
			 */
			public $base_prefix = 'wp_';

			/**
			 * Last inserted row.
			 *
			 * @var array<string, mixed>|null
			 */
			public $inserted_row = null;

			/**
			 * Mock wpdb insert.
			 *
			 * @param string               $table Table name.
			 * @param array<string, mixed> $data  Row data.
			 * @return bool
			 */
			public function insert( $table, $data ) {
				unset( $table );
				$this->inserted_row = $data;
				return true;
			}
		};
		$this->set_mock_wpdb( $wpdb );

		$logs = new Logs();
		$logs->insert_log(
			'Plugin',
			'plugin-slug',
			5,
			'1.0.0',
			'1.0.1',
			'manual',
			'plugin',
			'success',
			'notes',
			'serialized-trace'
		);

		$this->assertIsArray( $wpdb->inserted_row );
		$this->assertSame( 'serialized-trace', $wpdb->inserted_row['stacktrace'] ?? null );
	}

	/**
	 * Stub helpers used when formatting stack traces.
	 */
	private function stub_stacktrace_format_helpers(): void {
		if ( ! defined( 'WP_CONTENT_DIR' ) ) {
			define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
		}

		MonkeyFunctions\when( 'maybe_unserialize' )->alias(
			static function ( $data ) {
				if ( ! is_string( $data ) ) {
					return $data;
				}

				$unserialized = @unserialize( $data );
				if ( false !== $unserialized || 'b:0;' === $data ) {
					return $unserialized;
				}

				return $data;
			}
		);
		MonkeyFunctions\when( 'wp_normalize_path' )->alias(
			static function ( $path ) {
				return str_replace( '\\', '/', (string) $path );
			}
		);
	}

	/**
	 * Test format_stacktrace_for_display with empty input.
	 */
	public function test_format_stacktrace_for_display_empty(): void {
		$this->stub_stacktrace_format_helpers();

		$this->assertSame( '', Logs::format_stacktrace_for_display( '' ) );
		$this->assertSame( '', Logs::format_stacktrace_for_display( 'not-serialized' ) );
	}

	/**
	 * Test format_stacktrace_for_display with a class method frame.
	 */
	public function test_format_stacktrace_for_display_class_frame(): void {
		$this->stub_stacktrace_format_helpers();

		$trace = array(
			array(
				'file'     => WP_CONTENT_DIR . '/plugins/updatebee/includes/Logs.php',
				'line'     => 42,
				'class'    => 'UpdateBee\\Logs',
				'type'     => '->',
				'function' => 'insert_log',
				'args'     => array( 'arg-one', '' ),
			),
		);

		$formatted = Logs::format_stacktrace_for_display( serialize( $trace ) );

		$this->assertStringContainsString( '#1 /plugins/updatebee/includes/Logs.php (42):', $formatted );
		$this->assertStringContainsString( 'UpdateBee\\Logs->insert_log(arg-one, \'\')', $formatted );
	}

	/**
	 * Test format_stacktrace_for_display with a do_action frame.
	 */
	public function test_format_stacktrace_for_display_do_action_frame(): void {
		$this->stub_stacktrace_format_helpers();

		$trace = array(
			array(
				'function' => 'do_action',
				'args'     => array( 'upgrader_process_complete' ),
			),
		);

		$formatted = Logs::format_stacktrace_for_display( serialize( $trace ) );

		$this->assertStringContainsString( "do_action('upgrader_process_complete')", $formatted );
	}

	/**
	 * Stub helpers used when writing a fatal automatic-update log.
	 */
	private function stub_fatal_insert_helpers(): void {
		MonkeyFunctions\when( 'is_multisite' )->justReturn( false );
		MonkeyFunctions\when( 'is_network_admin' )->justReturn( false );
		MonkeyFunctions\when( 'is_plugin_active_for_network' )->justReturn( false );
		MonkeyFunctions\when( 'admin_url' )->alias(
			static function ( $path = '' ) {
				return 'https://example.com/wp-admin/' . $path;
			}
		);
		MonkeyFunctions\when( 'add_query_arg' )->alias(
			static function ( $args, $url ) {
				$tab = is_array( $args ) && isset( $args['tab'] ) ? (string) $args['tab'] : '';
				return $url . '&tab=' . $tab;
			}
		);
		MonkeyFunctions\when( 'sanitize_title' )->returnArg();
		MonkeyFunctions\when( 'wp_json_encode' )->alias(
			static function ( $data ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Test double.
				return (string) json_encode( $data );
			}
		);
	}

	/**
	 * Build a wpdb mock that records inserted status and notes.
	 *
	 * @return object
	 */
	private function create_status_wpdb_mock(): object {
		return new class() {
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
			 * Last inserted notes.
			 *
			 * @var string|null
			 */
			public $inserted_raw = null;

			/**
			 * Last inserted stacktrace.
			 *
			 * @var string|null
			 */
			public $inserted_stacktrace = null;

			/**
			 * Mock wpdb insert.
			 *
			 * @param string               $table Table name.
			 * @param array<string, mixed> $data  Row data.
			 * @return bool
			 */
			public function insert( $table, $data ) {
				unset( $table );
				$this->inserted_status     = $data['update_status'] ?? null;
				$this->inserted_raw        = $data['raw'] ?? null;
				$this->inserted_stacktrace = $data['stacktrace'] ?? null;
				return true;
			}
		};
	}

	/**
	 * Core rollback WP_Error codes are logged as fatal.
	 */
	public function test_automatic_updates_marks_rollback_error_as_fatal(): void {
		$this->stub_log_helpers();
		$this->stub_fatal_insert_helpers();
		Fatal_Detection::reset();

		if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
			define( 'WP_PLUGIN_DIR', ABSPATH . 'wp-content/plugins' );
		}

		MonkeyFunctions\when( 'get_plugin_data' )->justReturn(
			array(
				'Version' => '1.7.3',
			)
		);

		$wpdb = $this->create_status_wpdb_mock();
		$this->set_mock_wpdb( $wpdb );

		$plugin_result = (object) array(
			'name'     => 'Hello Dolly',
			'messages' => array(),
			'result'   => new \WP_Error( 'plugin_update_fatal_error_rollback_successful', 'The previously installed version has been restored.' ),
			'item'     => (object) array(
				'plugin'          => 'hello-dolly/hello.php',
				'slug'            => 'hello-dolly',
				'current_version' => '1.7.2',
				'new_version'     => '1.7.3',
			),
		);

		$logs = new Logs();
		$logs->automatic_updates(
			array(
				'plugin' => array( $plugin_result ),
			)
		);

		$this->assertSame( 'fatal', $wpdb->inserted_status );
		$this->assertStringContainsString( 'restored', strtolower( (string) $wpdb->inserted_raw ) );
	}

	/**
	 * Non-fatal WP_Error results stay failures.
	 */
	public function test_automatic_updates_marks_generic_wp_error_as_failure(): void {
		$this->stub_log_helpers();
		Fatal_Detection::reset();

		if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
			define( 'WP_PLUGIN_DIR', ABSPATH . 'wp-content/plugins' );
		}

		MonkeyFunctions\when( 'get_plugin_data' )->justReturn(
			array(
				'Version' => '1.7.2',
			)
		);

		$wpdb = $this->create_status_wpdb_mock();
		$this->set_mock_wpdb( $wpdb );

		$plugin_result = (object) array(
			'name'     => 'Hello Dolly',
			'messages' => array(),
			'result'   => new \WP_Error( 'download_failed', 'Could not download.' ),
			'item'     => (object) array(
				'plugin'          => 'hello-dolly/hello.php',
				'slug'            => 'hello-dolly',
				'current_version' => '1.7.2',
				'new_version'     => '1.7.3',
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
	 * A scrape finding upgrades a successful plugin log to fatal.
	 */
	public function test_automatic_updates_applies_scrape_finding(): void {
		$this->stub_log_helpers();
		$this->stub_fatal_insert_helpers();
		Fatal_Detection::reset();

		if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
			define( 'WP_PLUGIN_DIR', ABSPATH . 'wp-content/plugins' );
		}

		$property = ( new ReflectionClass( Fatal_Detection::class ) )->getProperty( 'findings' );
		$property->setAccessible( true );
		$property->setValue(
			null,
			array(
				'plugin:hello-dolly' => array(
					'type'   => 'plugin',
					'slug'   => 'hello-dolly',
					'source' => 'scrape',
					'error'  => array(
						'type'    => E_ERROR,
						'message' => 'Call to undefined function foo()',
						'file'    => 'wp-content/plugins/hello-dolly/hello.php',
						'line'    => 4,
					),
				),
			)
		);

		MonkeyFunctions\when( 'get_plugin_data' )->justReturn(
			array(
				'Version' => '1.7.3',
			)
		);

		$wpdb = $this->create_status_wpdb_mock();
		$this->set_mock_wpdb( $wpdb );

		$plugin_result = (object) array(
			'name'     => 'Hello Dolly',
			'messages' => array(),
			'result'   => true,
			'item'     => (object) array(
				'plugin'          => 'hello-dolly/hello.php',
				'slug'            => 'hello-dolly',
				'current_version' => '1.7.2',
				'new_version'     => '1.7.3',
			),
		);

		$logs = new Logs();
		$logs->automatic_updates(
			array(
				'plugin' => array( $plugin_result ),
			)
		);

		$this->assertSame( 'fatal', $wpdb->inserted_status );
		$this->assertStringContainsString( 'Call to undefined function foo()', (string) $wpdb->inserted_raw );
		$this->assertNotEmpty( $wpdb->inserted_stacktrace );
		Fatal_Detection::reset();
	}

	/**
	 * Failure digest queries include fatal rows.
	 */
	public function test_get_failure_logs_includes_fatal_status(): void {
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
				return array();
			}
		};
		$this->set_mock_wpdb( $wpdb );

		Logs::get_failure_logs();

		$this->assertStringContainsString( "'failure'", $wpdb->query_ran );
		$this->assertStringContainsString( "'fatal'", $wpdb->query_ran );
	}
}
