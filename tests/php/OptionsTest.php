<?php
/**
 * Tests for Options.
 *
 * @package updatebee
 */

namespace UpdateBee\Tests;

use Brain\Monkey\Functions as MonkeyFunctions;
use UpdateBee\Admin\Migrate\CompanionAutoUpdate;
use UpdateBee\Admin\Migrate\EasyUpdatesManager;
use UpdateBee\Branding;
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
		Branding::flush_cache();
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
		$this->assertFalse( $options['slack_enabled'] );
		$this->assertFalse( $options['slack_skip_delayed'] );
		$this->assertSame( '', $options['discord_webhook_url'] );
		$this->assertFalse( $options['discord_enabled'] );
		$this->assertFalse( $options['discord_skip_delayed'] );
		$this->assertSame( 0, $options['delay_updates'] );
		$this->assertSame( "security\nCSRF\nXSS\nCVE\nSSRF", $options['upgrade_notice_keywords'] );
		$this->assertFalse( $options['upgrade_notice_keywords_enabled'] );
		$this->assertSame( 'off', $options['block_untested_updates'] );
		$this->assertTrue( $options['fatal_error_detection'] );
		$this->assertSame( array(), $options['fatal_email_recipients'] );
		$this->assertFalse( $options['fatal_notify_slack'] );
		$this->assertFalse( $options['fatal_notify_discord'] );
		$this->assertArrayNotHasKey( 'branding_name', $options );
		$this->assertFalse( $options['admin_bar_enabled'] );
		$this->assertTrue( $options['admin_bar_show_admin'] );
		$this->assertTrue( $options['admin_bar_show_frontend'] );
		$this->assertFalse( $options['automatic_updater_disabled_override'] );
		$this->assertSame( array(), $options['hidden_features'] );
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
	 * Test clearing an array option with an empty list.
	 */
	public function test_update_options_clears_hidden_features(): void {
		$this->stub_option_helpers();
		$stored = array(
			'hidden_features' => array( 'upcoming', 'tools' ),
		);
		MonkeyFunctions\when( 'get_site_option' )->alias(
			static function () use ( &$stored ) {
				return $stored;
			}
		);
		MonkeyFunctions\when( 'update_site_option' )->alias(
			static function ( $key, $value ) use ( &$stored ) {
				unset( $key );
				$stored = $value;

				return true;
			}
		);

		$updated = Options::update_options( 'hidden_features', array() );

		$this->assertIsArray( $updated );
		$this->assertSame( array(), $updated['hidden_features'] );
		$this->assertSame( array(), $stored['hidden_features'] );
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
					'hidden_features'    => array( 'tools', 'translations' ),
				)
			);
		MonkeyFunctions\expect( 'update_site_option' )
			->times( 3 )
			->andReturn( true );
		MonkeyFunctions\expect( 'delete_site_option' )
			->once()
			->with( EasyUpdatesManager::HIDE_OPTION )
			->andReturn( true );
		MonkeyFunctions\expect( 'delete_site_option' )
			->once()
			->with( CompanionAutoUpdate::HIDE_OPTION )
			->andReturn( true );

		Options::reset_options();

		$this->assertSame( 'off', Options::get_options( 'major_auto_updates' ) );
		$this->assertSame( array(), Options::get_options( 'hidden_features' ) );
	}

	/**
	 * Unlocked reset clears the branding option.
	 */
	public function test_reset_options_clears_branding_when_unlocked(): void {
		$branding = array(
			'branding_name'              => 'Stored Agency',
			'branding_description'       => '',
			'branding_author'            => '',
			'branding_url'               => '',
			'branding_hide_license_tab'  => true,
			'branding_hide_header_links' => true,
		);
		$this->stub_branding_reset_store( $branding );

		Options::reset_options();

		$this->assertSame( '', $branding['branding_name'] );
		$this->assertFalse( $branding['branding_hide_license_tab'] );
		$this->assertFalse( $branding['branding_hide_header_links'] );
	}

	/**
	 * A lock leaves saved branding in place when all options are reset.
	 */
	public function test_reset_options_keeps_branding_when_locked(): void {
		$branding = array(
			'branding_name'              => 'Stored Agency',
			'branding_description'       => 'Managed updates.',
			'branding_author'            => 'Agency Co',
			'branding_url'               => 'https://agency.example/',
			'branding_hide_license_tab'  => true,
			'branding_hide_header_links' => true,
		);
		$this->stub_branding_reset_store( $branding );
		Branding::set_license_active_override( true );
		MonkeyFunctions\when( 'apply_filters' )->alias(
			static function ( $hook, $value ) {
				if ( 'updatebee_branding_locked' === $hook ) {
					return true;
				}

				return $value;
			}
		);

		Options::reset_options();

		$this->assertSame( 'Stored Agency', $branding['branding_name'] );
		$this->assertTrue( $branding['branding_hide_license_tab'] );
		$this->assertTrue( $branding['branding_hide_header_links'] );
		$this->assertSame( 'off', Options::get_options( 'major_auto_updates' ) );
	}

	/**
	 * Stub option storage for reset tests that also touch branding.
	 *
	 * @param array<string, mixed> $branding Branding option, passed by reference.
	 */
	private function stub_branding_reset_store( array &$branding ): void {
		$this->stub_option_helpers();
		MonkeyFunctions\when( 'get_site_option' )->alias(
			static function ( $key, $fallback = array() ) use ( &$branding ) {
				if ( 'updatebee_options' === $key ) {
					return array(
						'major_auto_updates' => 'on',
					);
				}
				if ( Branding::OPTION_KEY === $key ) {
					return $branding;
				}

				return $fallback;
			}
		);
		MonkeyFunctions\when( 'update_site_option' )->alias(
			static function ( $key, $value ) use ( &$branding ) {
				if ( Branding::OPTION_KEY === $key && is_array( $value ) ) {
					$branding = $value;
				}

				return true;
			}
		);
		MonkeyFunctions\when( 'delete_site_option' )->justReturn( true );
	}

	/**
	 * Test importer-only reset clears the hide flag without writing options.
	 */
	public function test_reset_importer_options_clears_hide_flag_without_wiping_options() {
		$this->stub_option_helpers();
		MonkeyFunctions\expect( 'update_site_option' )->never();
		MonkeyFunctions\expect( 'delete_site_option' )
			->once()
			->with( EasyUpdatesManager::HIDE_OPTION )
			->andReturn( true );
		MonkeyFunctions\expect( 'delete_site_option' )
			->once()
			->with( CompanionAutoUpdate::HIDE_OPTION )
			->andReturn( true );

		Options::reset_importer_options();

		// Brain Monkey expectations above are the assertions for this test.
		$this->addToAssertionCount( 3 );
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
	 * An unchanged site option is a successful save.
	 */
	public function test_update_options_treats_unchanged_value_as_success(): void {
		$this->stub_option_helpers();
		$stored = null;
		MonkeyFunctions\when( 'get_site_option' )->alias(
			static function () use ( &$stored ) {
				return is_array( $stored ) ? $stored : array();
			}
		);
		MonkeyFunctions\when( 'update_site_option' )->alias(
			static function ( $key, $value ) use ( &$stored ) {
				unset( $key );
				$stored = $value;

				return false;
			}
		);

		$updated = Options::update_options( '', array( 'plugin_auto_updates' => 'on' ) );

		$this->assertIsArray( $updated );
		$this->assertSame( 'on', $updated['plugin_auto_updates'] );
	}

	/**
	 * A failed write is not cached and is reported as null.
	 */
	public function test_update_options_returns_null_when_write_fails(): void {
		$this->stub_option_helpers();
		MonkeyFunctions\when( 'get_site_option' )->justReturn( array() );
		MonkeyFunctions\when( 'update_site_option' )->justReturn( false );

		$updated = Options::update_options( '', array( 'plugin_auto_updates' => 'on' ) );

		$this->assertNull( $updated );
		$this->assertSame( 'off', Options::get_options( 'plugin_auto_updates' ) );
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
