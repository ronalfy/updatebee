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
		$this->assertSame( 0, $options['clear_logs_days'] );
		$this->assertSame( '', $options['slack_webhook_url'] );
		$this->assertSame( 0, $options['delay_updates'] );
		$this->assertSame( "security\nCSRF\nXSS\nCVE\nSSRF", $options['upgrade_notice_keywords'] );
		$this->assertSame( 'off', $options['block_untested_updates'] );
		$this->assertFalse( $options['disable_wp_cron'] );
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

	/**
	 * Stored option lists used by granular reset tests.
	 *
	 * @return array<string, mixed>
	 */
	private function get_populated_list_options(): array {
		return array(
			'auto_update_plugins'         => array( 'akismet/akismet.php' ),
			'auto_update_plugins_patches' => array( 'hello.php' ),
			'blocked_plugins'             => array( 'classic-editor/classic-editor.php' ),
			'auto_update_themes'          => array( 'twentytwentyfour' ),
			'auto_update_themes_patches'  => array( 'twentytwentythree' ),
			'blocked_themes'              => array( 'twentytwentytwo' ),
			'auto_update_translations'    => array( 'akismet' ),
			'blocked_translations'        => array( 'hello-dolly' ),
			'major_auto_updates'          => 'on',
		);
	}

	/**
	 * Stub option reads and writes for a granular reset.
	 *
	 * @param int $update_count Expected update_site_option calls.
	 */
	private function stub_populated_options( int $update_count ): void {
		$this->stub_option_helpers();
		MonkeyFunctions\expect( 'get_site_option' )
			->once()
			->with( 'updatebee_options', array() )
			->andReturn( $this->get_populated_list_options() );
		MonkeyFunctions\expect( 'update_site_option' )
			->times( $update_count )
			->andReturn( true );
	}

	/**
	 * Test plugin auto-update reset also clears patch lists.
	 */
	public function test_reset_plugin_auto_updates_clears_lists_and_patches() {
		$this->stub_populated_options( 2 );

		Options::reset_plugin_auto_updates();

		$this->assertSame( array(), Options::get_options( 'auto_update_plugins' ) );
		$this->assertSame( array(), Options::get_options( 'auto_update_plugins_patches' ) );
		$this->assertSame( array( 'classic-editor/classic-editor.php' ), Options::get_options( 'blocked_plugins' ) );
		$this->assertSame( 'on', Options::get_options( 'major_auto_updates' ) );
	}

	/**
	 * Test plugin blocked reset leaves auto-update lists alone.
	 */
	public function test_reset_plugin_blocked_updates_clears_blocked_only() {
		$this->stub_populated_options( 1 );

		Options::reset_plugin_blocked_updates();

		$this->assertSame( array(), Options::get_options( 'blocked_plugins' ) );
		$this->assertSame( array( 'akismet/akismet.php' ), Options::get_options( 'auto_update_plugins' ) );
	}

	/**
	 * Test theme auto-update reset also clears patch lists.
	 */
	public function test_reset_theme_auto_updates_clears_lists_and_patches() {
		$this->stub_populated_options( 2 );

		Options::reset_theme_auto_updates();

		$this->assertSame( array(), Options::get_options( 'auto_update_themes' ) );
		$this->assertSame( array(), Options::get_options( 'auto_update_themes_patches' ) );
		$this->assertSame( array( 'twentytwentytwo' ), Options::get_options( 'blocked_themes' ) );
	}

	/**
	 * Test theme blocked reset leaves auto-update lists alone.
	 */
	public function test_reset_theme_blocked_updates_clears_blocked_only() {
		$this->stub_populated_options( 1 );

		Options::reset_theme_blocked_updates();

		$this->assertSame( array(), Options::get_options( 'blocked_themes' ) );
		$this->assertSame( array( 'twentytwentyfour' ), Options::get_options( 'auto_update_themes' ) );
	}

	/**
	 * Test translation auto-update reset leaves blocked translations alone.
	 */
	public function test_reset_translation_auto_updates_clears_auto_only() {
		$this->stub_populated_options( 1 );

		Options::reset_translation_auto_updates();

		$this->assertSame( array(), Options::get_options( 'auto_update_translations' ) );
		$this->assertSame( array( 'hello-dolly' ), Options::get_options( 'blocked_translations' ) );
	}

	/**
	 * Test translation blocked reset leaves auto-update lists alone.
	 */
	public function test_reset_translation_blocked_updates_clears_blocked_only() {
		$this->stub_populated_options( 1 );

		Options::reset_translation_blocked_updates();

		$this->assertSame( array(), Options::get_options( 'blocked_translations' ) );
		$this->assertSame( array( 'akismet' ), Options::get_options( 'auto_update_translations' ) );
	}
}
