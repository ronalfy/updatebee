<?php
/**
 * Tests for Options.
 *
 * @package updatebee
 */

namespace UpdateBee\Tests;

use Brain\Monkey\Functions as MonkeyFunctions;
use UpdateBee\Options;

/**
 * Options test case.
 */
class OptionsTest extends TestCase {

	/**
	 * Reset the options cache after each test.
	 */
	protected function tearDown(): void {
		Options::flush_cache();
		parent::tearDown();
	}

	/**
	 * Stub common WordPress option helpers.
	 */
	private function stub_option_helpers() {
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
	}

	/**
	 * Test defaults merge when no options are stored.
	 */
	public function test_get_options_merges_defaults() {
		$this->stub_option_helpers();
		MonkeyFunctions\expect( 'get_site_option' )
			->once()
			->with( 'updatebee_options', array() )
			->andReturn( array() );

		$options = Options::get_options();

		$this->assertIsArray( $options );
		$this->assertSame( 'off', $options['major_auto_updates'] );
		$this->assertSame( 'on', $options['minor_auto_updates'] );
		$this->assertSame( 'on', $options['translation_auto_updates'] );
		$this->assertSame( array(), $options['auto_update_plugins'] );
	}

	/**
	 * Test retrieving a single option by key.
	 */
	public function test_get_options_returns_value_for_key() {
		$this->stub_option_helpers();
		MonkeyFunctions\expect( 'get_site_option' )
			->once()
			->with( 'updatebee_options', array() )
			->andReturn(
				array(
					'major_auto_updates' => 'on',
				)
			);

		$this->assertSame( 'on', Options::get_options( 'major_auto_updates' ) );
	}

	/**
	 * Test updating a single option key.
	 */
	public function test_update_options_saves_key() {
		$this->stub_option_helpers();
		MonkeyFunctions\expect( 'get_site_option' )
			->once()
			->with( 'updatebee_options', array() )
			->andReturn( array() );
		MonkeyFunctions\expect( 'update_site_option' )
			->once()
			->with(
				'updatebee_options',
				\Mockery::on(
					static function ( $options ) {
						return is_array( $options ) && 'on' === $options['plugin_auto_updates'];
					}
				)
			)
			->andReturn( true );

		$updated = Options::update_options( 'plugin_auto_updates', array( 'plugin_auto_updates' => 'on' ) );

		$this->assertIsArray( $updated );
		$this->assertSame( 'on', $updated['plugin_auto_updates'] );
	}

	/**
	 * Test resetting options to defaults.
	 */
	public function test_reset_options_restores_defaults() {
		$this->stub_option_helpers();
		MonkeyFunctions\expect( 'get_site_option' )
			->once()
			->with( 'updatebee_options', array() )
			->andReturn(
				array(
					'major_auto_updates' => 'on',
				)
			);
		MonkeyFunctions\expect( 'update_site_option' )
			->once()
			->andReturn( true );

		Options::reset_options();

		$this->assertSame( 'off', Options::get_options( 'major_auto_updates' ) );
	}
}
