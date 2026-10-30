<?php
/**
 * Tests for post-update fatal detection.
 *
 * @package updatebee
 */

namespace UpdateBee\Tests;

use Brain\Monkey\Functions as MonkeyFunctions;
use UpdateBee\Options;
use UpdateBee\Updates\Fatal_Detection;

/**
 * Fatal detection test case.
 */
class FatalDetectionTest extends TestCase {

	/**
	 * Reset findings and options cache.
	 */
	protected function tearDown(): void {
		Fatal_Detection::reset();
		Options::flush_cache();
		parent::tearDown();
	}

	/**
	 * Define plugin and theme directories used for path mapping.
	 */
	private function define_content_dirs(): void {
		if ( ! defined( 'WP_CONTENT_DIR' ) ) {
			define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
		}
		if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
			define( 'WP_PLUGIN_DIR', ABSPATH . 'wp-content/plugins' );
		}
	}

	/**
	 * Stub option helpers used by is_enabled().
	 *
	 * @param bool $enabled Whether detection is on.
	 */
	private function stub_options( bool $enabled = true ): void {
		MonkeyFunctions\when( 'apply_filters' )->returnArg( 2 );
		MonkeyFunctions\when( 'wp_parse_args' )->alias(
			static function ( $args, $defaults ) {
				return array_merge( (array) $defaults, (array) $args );
			}
		);
		MonkeyFunctions\when( 'get_site_option' )->justReturn(
			array(
				'fatal_error_detection' => $enabled,
			)
		);
	}

	/**
	 * Sample plugin update result.
	 *
	 * @param string $slug   Plugin slug.
	 * @param mixed  $result Upgrader result.
	 * @return object
	 */
	private function plugin_result( string $slug = 'hello-dolly', $result = true ): object {
		return (object) array(
			'name'   => 'Hello Dolly',
			'result' => $result,
			'item'   => (object) array(
				'plugin' => $slug . '/' . $slug . '.php',
				'slug'   => $slug,
			),
		);
	}

	/**
	 * Sample theme update result.
	 *
	 * @param string $slug Theme stylesheet.
	 * @return object
	 */
	private function theme_result( string $slug = 'twentytwentyfour' ): object {
		return (object) array(
			'name'   => 'Twenty Twenty-Four',
			'result' => true,
			'item'   => (object) array(
				'theme' => $slug,
			),
		);
	}

	/**
	 * Build a native scrape body.
	 *
	 * @param string $key  Scrape key.
	 * @param mixed  $json JSON-serializable payload.
	 * @return string
	 */
	private function scrape_body( string $key, $json ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Test helper.
		$encoded = json_encode( $json );

		return "\n###### wp_scraping_result_start:{$key} ######\n{$encoded}\n###### wp_scraping_result_end:{$key} ######\n";
	}

	/**
	 * Parse a fatal scrape payload.
	 */
	public function test_parse_scrape_body_detects_fatal(): void {
		$key    = 'abc123';
		$parsed = Fatal_Detection::parse_scrape_body(
			$this->scrape_body(
				$key,
				array(
					'type'    => E_ERROR,
					'message' => 'Call to undefined function foo()',
					'file'    => 'wp-content/plugins/hello-dolly/hello.php',
					'line'    => 12,
				)
			),
			$key
		);

		$this->assertSame( 'fatal', $parsed['outcome'] );
		$this->assertSame( E_ERROR, $parsed['error']['type'] );
		$this->assertSame( 'Call to undefined function foo()', $parsed['error']['message'] );
		$this->assertSame( 12, $parsed['error']['line'] );
	}

	/**
	 * Parse a successful scrape payload.
	 */
	public function test_parse_scrape_body_true_is_ok(): void {
		$key    = 'okkey';
		$parsed = Fatal_Detection::parse_scrape_body( $this->scrape_body( $key, true ), $key );

		$this->assertSame( 'ok', $parsed['outcome'] );
	}

	/**
	 * Missing scrape markers are inconclusive, not fatal.
	 */
	public function test_parse_scrape_body_missing_markers_is_inconclusive(): void {
		$parsed = Fatal_Detection::parse_scrape_body( '<html>ok</html>', 'missing' );

		$this->assertSame( 'inconclusive', $parsed['outcome'] );
		$this->assertSame( 'loopback_request_failed', $parsed['code'] );
	}

	/**
	 * A scrape nonce failure has no fatal type key.
	 */
	public function test_parse_scrape_body_nonce_failure_is_inconclusive(): void {
		$key    = 'noncekey';
		$parsed = Fatal_Detection::parse_scrape_body(
			$this->scrape_body(
				$key,
				array(
					'code'    => 'scrape_nonce_failure',
					'message' => 'Scrape key check failed.',
				)
			),
			$key
		);

		$this->assertSame( 'inconclusive', $parsed['outcome'] );
		$this->assertSame( 'scrape_nonce_failure', $parsed['code'] );
	}

	/**
	 * Map a plugin file to the matching batch item.
	 */
	public function test_map_error_file_to_plugin(): void {
		$this->define_content_dirs();
		$mapped = Fatal_Detection::map_error_file_to_extension(
			'wp-content/plugins/hello-dolly/includes/foo.php',
			array(
				'plugin' => array( $this->plugin_result() ),
			)
		);

		$this->assertSame(
			array(
				'type' => 'plugin',
				'slug' => 'hello-dolly',
			),
			$mapped
		);
	}

	/**
	 * Map a theme file to the matching batch item.
	 */
	public function test_map_error_file_to_theme(): void {
		$this->define_content_dirs();
		$mapped = Fatal_Detection::map_error_file_to_extension(
			'wp-content/themes/twentytwentyfour/functions.php',
			array(
				'theme' => array( $this->theme_result() ),
			)
		);

		$this->assertSame(
			array(
				'type' => 'theme',
				'slug' => 'twentytwentyfour',
			),
			$mapped
		);
	}

	/**
	 * Unknown paths are not attributed to a random extension.
	 */
	public function test_map_error_file_unknown_returns_null(): void {
		$this->define_content_dirs();
		$mapped = Fatal_Detection::map_error_file_to_extension(
			'wp-includes/class-wp.php',
			array(
				'plugin' => array( $this->plugin_result() ),
			)
		);

		$this->assertNull( $mapped );
	}

	/**
	 * Core rollback error codes are treated as fatal.
	 */
	public function test_is_fatal_result_for_rollback_codes(): void {
		MonkeyFunctions\when( 'apply_filters' )->returnArg( 2 );

		$fatal = new \WP_Error( 'plugin_update_fatal_error_rollback_successful', 'Restored.' );
		$other = new \WP_Error( 'download_failed', 'Nope.' );

		$this->assertTrue( Fatal_Detection::is_fatal_result( $fatal ) );
		$this->assertSame( 'fatal', Fatal_Detection::status_from_result( $fatal ) );
		$this->assertFalse( Fatal_Detection::is_fatal_result( $other ) );
		$this->assertSame( 'failure', Fatal_Detection::status_from_result( $other ) );
		$this->assertNull( Fatal_Detection::status_from_result( true ) );
	}

	/**
	 * Disabled detection does not loopback.
	 */
	public function test_inspect_skips_http_when_disabled(): void {
		$this->stub_options( false );
		MonkeyFunctions\when( 'is_multisite' )->justReturn( false );
		MonkeyFunctions\expect( 'wp_remote_get' )->never();

		$detection = new Fatal_Detection();
		$detection->inspect_update_results(
			array(
				'plugin' => array( $this->plugin_result() ),
			)
		);

		$this->assertSame( array(), Fatal_Detection::get_findings() );
		$this->assertNull( Fatal_Detection::get_unattributed() );
	}

	/**
	 * A failed loopback is inconclusive and does not mark a fatal.
	 */
	public function test_inspect_loopback_error_is_not_fatal(): void {
		$this->stub_options( true );
		MonkeyFunctions\when( 'is_multisite' )->justReturn( false );
		MonkeyFunctions\when( 'wp_rand' )->justReturn( 'abc' );
		MonkeyFunctions\when( 'set_transient' )->justReturn( true );
		MonkeyFunctions\when( 'delete_transient' )->justReturn( true );
		MonkeyFunctions\when( 'home_url' )->justReturn( 'https://example.com/' );
		MonkeyFunctions\when( 'admin_url' )->justReturn( 'https://example.com/wp-admin/' );
		MonkeyFunctions\when( 'add_query_arg' )->alias(
			static function ( $args, $url ) {
				unset( $args );
				return $url;
			}
		);
		MonkeyFunctions\when( 'wp_remote_get' )->justReturn( new \WP_Error( 'http_request_failed', 'timeout' ) );
		MonkeyFunctions\when( 'wp_remote_retrieve_body' )->justReturn( '' );

		$detection = new Fatal_Detection();
		$detection->inspect_update_results(
			array(
				'plugin' => array( $this->plugin_result() ),
			)
		);

		$this->assertSame( array(), Fatal_Detection::get_findings() );
		$this->assertNull( Fatal_Detection::get_unattributed() );
	}

	/**
	 * A scrape fatal is attributed to the matching plugin.
	 */
	public function test_inspect_scrape_fatal_is_attributed(): void {
		$this->define_content_dirs();
		$this->stub_options( true );
		$key = md5( 'abc' );

		MonkeyFunctions\when( 'is_multisite' )->justReturn( false );
		MonkeyFunctions\when( 'wp_rand' )->justReturn( 'abc' );
		MonkeyFunctions\when( 'set_transient' )->justReturn( true );
		MonkeyFunctions\when( 'delete_transient' )->justReturn( true );
		MonkeyFunctions\when( 'home_url' )->justReturn( 'https://example.com/' );
		MonkeyFunctions\when( 'admin_url' )->justReturn( 'https://example.com/wp-admin/' );
		MonkeyFunctions\when( 'add_query_arg' )->alias(
			static function ( $args, $url ) {
				unset( $args );
				return $url;
			}
		);
		MonkeyFunctions\when( 'wp_remote_retrieve_body' )->alias(
			static function ( $response ) {
				return is_array( $response ) && isset( $response['body'] ) ? (string) $response['body'] : '';
			}
		);
		MonkeyFunctions\when( 'wp_remote_get' )->justReturn(
			array(
				'body' => $this->scrape_body(
					$key,
					array(
						'type'    => E_ERROR,
						'message' => 'Call to undefined function foo()',
						'file'    => 'wp-content/plugins/hello-dolly/hello.php',
						'line'    => 4,
					)
				),
			)
		);

		$detection = new Fatal_Detection();
		$detection->inspect_update_results(
			array(
				'plugin' => array( $this->plugin_result() ),
			)
		);

		$finding = Fatal_Detection::get_finding( 'plugin', 'hello-dolly' );
		$this->assertIsArray( $finding );
		$this->assertSame( 'scrape', $finding['source'] );
		$this->assertSame( 'Call to undefined function foo()', $finding['error']['message'] );
		$this->assertNull( Fatal_Detection::get_unattributed() );
	}

	/**
	 * Core rollback codes are harvested without a scrape.
	 */
	public function test_inspect_harvests_core_rollback_error(): void {
		$this->stub_options( true );
		MonkeyFunctions\when( 'is_multisite' )->justReturn( false );
		MonkeyFunctions\expect( 'wp_remote_get' )->never();

		$detection = new Fatal_Detection();
		$detection->inspect_update_results(
			array(
				'plugin' => array(
					$this->plugin_result(
						'hello-dolly',
						new \WP_Error( 'plugin_update_fatal_error_rollback_successful', 'Restored previous version.' )
					),
				),
			)
		);

		$finding = Fatal_Detection::get_finding( 'plugin', 'hello-dolly' );
		$this->assertIsArray( $finding );
		$this->assertSame( 'core', $finding['source'] );
		$this->assertSame( 'Restored previous version.', $finding['error']['message'] );
	}

	/**
	 * Run registers the automatic_updates_complete hook at priority 8.
	 */
	public function test_run_registers_hook(): void {
		$priority = null;
		MonkeyFunctions\when( 'add_action' )->alias(
			static function ( $hook, $callback, $registered_priority = 10 ) use ( &$priority ) {
				unset( $callback );
				if ( 'automatic_updates_complete' === $hook ) {
					$priority = $registered_priority;
				}
				return true;
			}
		);

		$detection = new Fatal_Detection();
		$detection->run();

		$this->assertSame( 8, $priority );
	}
}
