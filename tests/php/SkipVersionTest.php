<?php
/**
 * Tests for skipped plugin and theme versions.
 *
 * @package updatebee
 */

namespace UpdateBee\Tests;

use Brain\Monkey\Functions as MonkeyFunctions;
use ReflectionClass;
use UpdateBee\Options;
use UpdateBee\Updates\Skip_Version;
use UpdateBee\Updates\Upcoming;

/**
 * Skip_Version test case.
 */
class SkipVersionTest extends TestCase {

	/**
	 * In-memory site options.
	 *
	 * @var array<string, mixed>
	 */
	private array $site_options = array();

	/**
	 * Reset singleton and options cache after each test.
	 */
	protected function tearDown(): void {
		Options::flush_cache();
		$this->reset_skip_singleton();
		parent::tearDown();
	}

	/**
	 * A stored skip vetoes that exact plugin version.
	 */
	public function test_skip_vetoes_matching_plugin_version(): void {
		$skip = $this->get_skip(
			array(
				'skipped_plugin_versions' => array(
					'akismet/akismet.php' => '5.3.2',
				),
			)
		);

		$item = (object) array(
			'plugin'      => 'akismet/akismet.php',
			'new_version' => '5.3.2',
		);

		$this->assertFalse( $skip->auto_update_plugin( true, $item ) );
	}

	/**
	 * A different offered version is not skipped.
	 */
	public function test_different_version_is_not_skipped(): void {
		$skip = $this->get_skip(
			array(
				'skipped_plugin_versions' => array(
					'akismet/akismet.php' => '5.3.2',
				),
			)
		);

		$item = (object) array(
			'plugin'      => 'akismet/akismet.php',
			'new_version' => '5.3.3',
		);

		$this->assertTrue( $skip->auto_update_plugin( true, $item ) );
	}

	/**
	 * Clearing a skip allows that version to update again.
	 */
	public function test_clearing_skip_allows_the_update(): void {
		$this->stub_helpers(
			array(
				'skipped_theme_versions' => array(
					'twentytwentyfour' => '1.2',
				),
			)
		);
		MonkeyFunctions\when( 'update_site_option' )->alias(
			function ( $key, $value ) {
				$this->site_options[ $key ] = $value;
				return true;
			}
		);

		$this->assertTrue( Skip_Version::set_skipped( 'theme', 'twentytwentyfour', '1.2', false ) );

		$skip = Skip_Version::get_instance();
		$item = (object) array(
			'theme'       => 'twentytwentyfour',
			'new_version' => '1.2',
		);

		$this->assertTrue( $skip->auto_update_theme( true, $item ) );
	}

	/**
	 * A blocked item cannot be updated now.
	 */
	public function test_block_prevents_update_now(): void {
		$this->assertFalse( Upcoming::allows_manual_update( true, true, false, true ) );
		$this->assertFalse( Upcoming::allows_manual_update( true, false, true, true ) );
		$this->assertTrue( Upcoming::allows_manual_update( true, false, false, true ) );
	}

	/**
	 * Clear the Skip_Version singleton.
	 */
	private function reset_skip_singleton(): void {
		$reflection = new ReflectionClass( Skip_Version::class );
		$instance   = $reflection->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );
	}

	/**
	 * Return a skip filter with stubs applied.
	 *
	 * @param array<string, mixed> $options Stored updatebee_options values.
	 * @return Skip_Version
	 */
	private function get_skip( array $options = array() ): Skip_Version {
		$this->stub_helpers( $options );

		return Skip_Version::get_instance();
	}

	/**
	 * Stub WordPress helpers used by Options.
	 *
	 * @param array<string, mixed> $options Stored updatebee_options values.
	 */
	private function stub_helpers( array $options = array() ): void {
		MonkeyFunctions\when( 'add_filter' )->justReturn( true );
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
			function ( $key, $default_value = false ) {
				return array_key_exists( $key, $this->site_options ) ? $this->site_options[ $key ] : $default_value;
			}
		);

		$this->site_options['updatebee_options'] = $options;
	}
}
