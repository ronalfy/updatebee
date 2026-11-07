<?php
/**
 * Tests for the upcoming updates list.
 *
 * @package updatebee
 */

namespace UpdateBee\Tests;

use Brain\Monkey\Functions as MonkeyFunctions;
use ReflectionClass;
use UpdateBee\Options;
use UpdateBee\Updates\Delay_Updates;
use UpdateBee\Updates\Upcoming;

/**
 * Upcoming list filtering.
 */
class UpcomingTest extends TestCase {

	/**
	 * Clear option and delay state after each test.
	 */
	protected function tearDown(): void {
		Options::flush_cache();
		$this->reset_delay_singleton();
		parent::tearDown();
	}

	/**
	 * A blocked plugin with an offer is left off the upcoming list.
	 */
	public function test_blocked_plugin_is_omitted(): void {
		$this->stub_list_helpers(
			array(
				'blocked_plugins' => array( 'akismet' ),
			)
		);

		$items = Upcoming::get_list()['items'];
		$ids   = array_column( $items, 'Id' );

		$this->assertSame( array( 'hello.php' ), $ids );
		$this->assertSame( 'plugin', $items[0]['Type'] );
		$this->assertFalse( $items[0]['Blocked'] );
	}

	/**
	 * Disabling every plugin update removes plugin rows from the list.
	 */
	public function test_fully_blocked_plugins_are_omitted(): void {
		$this->stub_list_helpers(
			array(
				'plugin_updates_disabled' => 'on',
			)
		);

		$ids = array_column( Upcoming::get_list()['items'], 'Id' );

		$this->assertSame( array(), $ids );
	}

	/**
	 * Stub the WordPress calls used while building upcoming rows.
	 *
	 * @param array<string, mixed> $options Stored updatebee_options values.
	 */
	private function stub_list_helpers( array $options ): void {
		if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
			define( 'WP_PLUGIN_DIR', ABSPATH . 'wp-content/plugins' );
		}

		MonkeyFunctions\when( 'absint' )->alias(
			static function ( $value ) {
				return abs( (int) $value );
			}
		);
		MonkeyFunctions\when( 'add_filter' )->justReturn( true );
		MonkeyFunctions\when( 'add_action' )->justReturn( true );
		MonkeyFunctions\when( 'sanitize_text_field' )->returnArg( 1 );
		MonkeyFunctions\when( 'sanitize_title' )->returnArg( 1 );
		MonkeyFunctions\when( 'wp_kses_post' )->returnArg( 1 );
		MonkeyFunctions\when( 'wp_create_nonce' )->justReturn( 'nonce' );
		MonkeyFunctions\when( 'current_user_can' )->justReturn( true );
		MonkeyFunctions\when( 'wp_get_scheduled_event' )->justReturn( false );
		MonkeyFunctions\when( 'wp_get_themes' )->justReturn( array() );
		MonkeyFunctions\when( 'apply_filters' )->returnArg( 2 );
		MonkeyFunctions\when( 'wp_parse_args' )->alias(
			static function ( $args, $defaults ) {
				if ( ! is_array( $args ) ) {
					$args = array();
				}
				if ( ! is_array( $defaults ) ) {
					$defaults = array();
				}

				return array_merge( $defaults, $args );
			}
		);
		MonkeyFunctions\when( 'get_site_option' )->alias(
			static function ( $key, $default_value = false ) use ( $options ) {
				if ( 'updatebee_options' === $key ) {
					return $options;
				}

				return $default_value;
			}
		);
		Options::flush_cache();
		MonkeyFunctions\when( 'get_plugins' )->justReturn(
			array(
				'akismet/akismet.php' => array(
					'Name'    => 'Akismet',
					'Version' => '5.0',
				),
				'hello.php'           => array(
					'Name'    => 'Hello Dolly',
					'Version' => '1.0',
				),
			)
		);
		MonkeyFunctions\when( 'get_plugin_data' )->alias(
			static function ( $plugin_file ) {
				if ( str_contains( (string) $plugin_file, 'akismet' ) ) {
					return array( 'Version' => '5.0' );
				}

				return array( 'Version' => '1.0' );
			}
		);
		MonkeyFunctions\when( 'get_site_transient' )->alias(
			static function ( $key ) {
				if ( 'update_plugins' !== $key ) {
					return false;
				}

				return (object) array(
					'response' => array(
						'akismet/akismet.php' => (object) array(
							'new_version' => '5.1',
							'package'     => 'https://downloads.example.com/akismet.zip',
						),
						'hello.php'           => (object) array(
							'new_version' => '1.1',
							'package'     => 'https://downloads.example.com/hello.zip',
						),
					),
				);
			}
		);
	}

	/**
	 * Clear the Delay_Updates singleton created while labeling a schedule.
	 */
	private function reset_delay_singleton(): void {
		$reflection = new ReflectionClass( Delay_Updates::class );
		$property   = $reflection->getProperty( 'instance' );
		$property->setAccessible( true );
		$property->setValue( null, null );

		$skip = $reflection->getProperty( 'skip_delay' );
		$skip->setAccessible( true );
		$skip->setValue( null, false );
	}
}
