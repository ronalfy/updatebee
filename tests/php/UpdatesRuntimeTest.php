<?php
/**
 * Tests for Updates runtime hooks.
 *
 * @package updatebee
 */

namespace UpdateBee\Tests;

use Brain\Monkey\Functions as MonkeyFunctions;
use ReflectionClass;
use UpdateBee\Functions;
use UpdateBee\Options;
use UpdateBee\Updates\Core;
use UpdateBee\Updates\Delay_Updates;
use UpdateBee\Updates\Init;
use UpdateBee\Updates\Upgrade_Notice_Keywords;
use UpdateBee\Updates\Plugins;
use UpdateBee\Updates\Themes;
use UpdateBee\Updates\Translations;

/**
 * Updates runtime test case.
 */
class UpdatesRuntimeTest extends TestCase {

	/**
	 * Define WP_PLUGIN_DIR before each test.
	 */
	protected function setUp(): void {
		parent::setUp();

		if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
			define( 'WP_PLUGIN_DIR', ABSPATH . 'wp-content/plugins' );
		}
	}

	/**
	 * Reset singletons and options cache after each test.
	 */
	protected function tearDown(): void {
		Options::flush_cache();
		$this->reset_updates_singleton( Core::class );
		$this->reset_updates_singleton( Plugins::class );
		$this->reset_updates_singleton( Themes::class );
		$this->reset_updates_singleton( Translations::class );
		$this->reset_updates_singleton( Delay_Updates::class );
		$this->reset_updates_singleton( Upgrade_Notice_Keywords::class );
		parent::tearDown();
	}

	/**
	 * Clear a Updates class singleton.
	 *
	 * @param class-string $class Class name.
	 */
	private function reset_updates_singleton( string $class ): void {
		$reflection = new ReflectionClass( $class );
		if ( ! $reflection->hasProperty( 'instance' ) ) {
			return;
		}

		$property = $reflection->getProperty( 'instance' );
		$property->setAccessible( true );
		$property->setValue( null, null );
	}

	/**
	 * Stub common WordPress option helpers.
	 */
	private function stub_option_helpers(): void {
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
		MonkeyFunctions\when( 'absint' )->returnArg( 1 );
		MonkeyFunctions\when( 'sanitize_text_field' )->returnArg( 1 );
		MonkeyFunctions\when( 'sanitize_title' )->returnArg( 1 );
	}

	/**
	 * Set up a minimal WPDB mock for log insert tests.
	 */
	private function setup_wpdb_mock(): object {
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Mocking WPDB for unit tests.
		$GLOBALS['wpdb'] = new class() {
			/**
			 * WPDB base prefix.
			 *
			 * @var string
			 */
			public $base_prefix = 'wp_';

			/**
			 * Recorded insert calls.
			 *
			 * @var array<int, array<string, mixed>>
			 */
			public $insert_calls = array();

			/**
			 * Mock get_var.
			 *
			 * @param string $query Query.
			 * @return null
			 */
			public function get_var( $query ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
				return null;
			}

			/**
			 * Mock prepare.
			 *
			 * @param string $query Query.
			 * @param mixed  ...$args Arguments.
			 * @return string
			 */
			public function prepare( $query, ...$args ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
				return $query;
			}

			/**
			 * Mock insert.
			 *
			 * @param string               $table   Table.
			 * @param array<string, mixed> $data    Data.
			 * @param array<string, mixed> $formats Formats.
			 */
			public function insert( $table, $data, $formats ) {
				$this->insert_calls[] = array( $table, $data, $formats );
			}
		};

		return $GLOBALS['wpdb'];
	}

	/**
	 * Set up mocks needed when a skipped log row is expected.
	 *
	 * @return object WPDB mock.
	 */
	private function setup_skipped_log_mocks(): object {
		$wpdb = $this->setup_wpdb_mock();
		MonkeyFunctions\when( 'current_time' )->justReturn( '2026-01-01 00:00:00' );

		return $wpdb;
	}

	/**
	 * Test highest priority helper matches UA behavior.
	 */
	public function test_get_highest_priority(): void {
		MonkeyFunctions\when( 'absint' )->alias(
			static function ( $value ) {
				return (int) $value;
			}
		);

		$this->assertSame( PHP_INT_MAX - 1, Functions::get_highest_priority( 0 ) );
		$this->assertSame( PHP_INT_MAX - 10, Functions::get_highest_priority( 10 ) );
	}

	/**
	 * Test core email option is enabled when stored as true.
	 */
	public function test_core_emails_enabled_when_true(): void {
		$this->stub_option_helpers();
		MonkeyFunctions\when( 'get_site_option' )->justReturn( array( 'enable_core_emails' => true ) );

		$this->assertTrue( Init::core_emails_enabled() );
	}

	/**
	 * Test core email option is disabled when stored as false or off.
	 */
	public function test_core_emails_disabled_when_false_or_off(): void {
		$this->stub_option_helpers();
		MonkeyFunctions\when( 'get_site_option' )->justReturn( array( 'enable_core_emails' => false ) );
		$this->assertFalse( Init::core_emails_enabled() );

		Options::flush_cache();
		MonkeyFunctions\when( 'get_site_option' )->justReturn( array( 'enable_core_emails' => 'off' ) );
		$this->assertFalse( Init::core_emails_enabled() );
	}

	/**
	 * Test plugin auto-update callback returns false for invalid item.
	 */
	public function test_maybe_auto_update_plugin_rejects_invalid_item(): void {
		MonkeyFunctions\when( 'add_filter' )->justReturn( true );

		$plugins = Plugins::get_instance();
		$this->assertFalse( $plugins->maybe_auto_update_plugin( false, new \stdClass() ) );
	}

	/**
	 * Test plugin auto-update callback honors global plugin auto-updates.
	 */
	public function test_maybe_auto_update_plugin_returns_true_when_globally_enabled(): void {
		MonkeyFunctions\when( 'add_filter' )->justReturn( true );
		$this->stub_option_helpers();
		MonkeyFunctions\expect( 'get_site_option' )
			->once()
			->with( 'updatebee_options', array() )
			->andReturn(
				array(
					'plugin_auto_updates'        => 'on',
					'plugin_auto_updates_custom' => 'off',
				)
			);

		$plugins = Plugins::get_instance();
		$item    = (object) array(
			'plugin' => 'hello.php',
		);

		$this->assertTrue( $plugins->maybe_auto_update_plugin( false, $item ) );
	}

	/**
	 * Test theme auto-update callback returns false for invalid item.
	 */
	public function test_maybe_auto_update_theme_rejects_invalid_item(): void {
		MonkeyFunctions\when( 'add_filter' )->justReturn( true );

		$themes = Themes::get_instance();
		$this->assertFalse( $themes->maybe_auto_update_theme( false, new \stdClass() ) );
	}

	/**
	 * Test Init registers email suppression when core emails are disabled.
	 */
	public function test_init_suppresses_wp_emails_when_core_emails_disabled(): void {
		$this->stub_option_helpers();
		MonkeyFunctions\when( 'add_action' )->justReturn( true );
		MonkeyFunctions\when( 'get_site_option' )->justReturn(
			array(
				'enable_core_emails' => false,
			)
		);

		$email_filters = array();
		MonkeyFunctions\when( 'add_filter' )->alias(
			static function ( $hook, $callback, $priority = 10, $accepted_args = 1 ) use ( &$email_filters ) {
				if ( in_array(
					$hook,
					array(
						'auto_core_update_send_email',
						'send_core_update_notification_email',
						'automatic_updates_send_debug_email',
						'auto_theme_update_send_email',
						'auto_plugin_update_send_email',
					),
					true
				) ) {
					$email_filters[] = $hook;
				}

				return true;
			}
		);

		$init = new Init();
		$init->run();

		$this->assertCount( 5, $email_filters );
	}

	/**
	 * Test Init registers Flywheel fallback when core version check is invalid.
	 */
	public function test_init_registers_flywheel_fallback(): void {
		MonkeyFunctions\when( 'add_filter' )->justReturn( true );
		MonkeyFunctions\when( 'has_action' )->justReturn( false );
		MonkeyFunctions\when( 'get_site_option' )->justReturn( array( 'enable_core_emails' => true ) );
		MonkeyFunctions\when( 'wp_parse_args' )->alias(
			static function ( $args, $defaults ) {
				return array_merge( (array) $defaults, (array) $args );
			}
		);
		MonkeyFunctions\when( 'apply_filters' )->returnArg( 2 );
		MonkeyFunctions\when( 'absint' )->returnArg( 1 );

		$registered = false;
		MonkeyFunctions\when( 'add_action' )->alias(
			static function ( $hook, $callback, $priority = 10, $accepted_args = 1 ) use ( &$registered ) {
				if ( 'wp_update_plugins' === $hook && 20 === $priority ) {
					$registered = true;
				}

				return true;
			}
		);

		$init = new Init();
		$init->run();
		$init->init();

		$this->assertTrue( $registered );
	}

	/**
	 * Test Init registers recipient filters when core emails are enabled.
	 */
	public function test_init_registers_recipient_filters_when_core_emails_enabled(): void {
		$this->stub_option_helpers();
		MonkeyFunctions\when( 'add_action' )->justReturn( true );
		MonkeyFunctions\when( 'get_site_option' )->justReturn(
			array(
				'enable_core_emails' => true,
			)
		);

		$email_filters = array();
		MonkeyFunctions\when( 'add_filter' )->alias(
			static function ( $hook ) use ( &$email_filters ) {
				if ( in_array(
					$hook,
					array(
						'auto_core_update_email',
						'auto_plugin_theme_update_email',
						'automatic_updates_debug_email',
					),
					true
				) ) {
					$email_filters[] = $hook;
				}

				return true;
			}
		);

		$init = new Init();
		$init->run();

		$this->assertSame(
			array(
				'auto_core_update_email',
				'auto_plugin_theme_update_email',
				'automatic_updates_debug_email',
			),
			$email_filters
		);
	}

	/**
	 * Test recipient filter replaces the WordPress to address.
	 */
	public function test_filter_update_email_recipients_replaces_to(): void {
		$this->stub_option_helpers();
		MonkeyFunctions\when( 'get_site_option' )->justReturn(
			array(
				'core_email_recipients' => array( 'one@example.com', 'two@example.com' ),
			)
		);

		$init  = new Init();
		$email = $init->filter_update_email_recipients(
			array(
				'to'      => 'admin@example.com',
				'subject' => 'Hi',
				'body'    => 'Body',
				'headers' => '',
			)
		);

		$this->assertSame(
			array( 'one@example.com', 'two@example.com' ),
			$email['to']
		);
	}

	/**
	 * Test recipient filter keeps WordPress's address when none are stored.
	 */
	public function test_filter_update_email_recipients_keeps_admin_when_empty(): void {
		$this->stub_option_helpers();
		MonkeyFunctions\when( 'get_site_option' )->justReturn(
			array(
				'core_email_recipients' => array(),
			)
		);

		$init  = new Init();
		$email = $init->filter_update_email_recipients(
			array(
				'to'      => 'admin@example.com',
				'subject' => 'Hi',
				'body'    => 'Body',
				'headers' => '',
			)
		);

		$this->assertSame( 'admin@example.com', $email['to'] );
	}

	/**
	 * Test plugin patch update is allowed when patches-only is active.
	 */
	public function test_maybe_auto_update_plugin_allows_patch_update(): void {
		MonkeyFunctions\when( 'add_filter' )->justReturn( true );
		$this->stub_option_helpers();
		MonkeyFunctions\when( 'get_plugin_data' )->justReturn(
			array(
				'Name'    => 'Hello Dolly',
				'Version' => '1.2.3',
			)
		);
		MonkeyFunctions\expect( 'get_site_option' )
			->once()
			->with( 'updatebee_options', array() )
			->andReturn(
				array(
					'plugin_auto_updates'              => 'on',
					'plugin_auto_updates_custom'       => 'off',
					'plugin_auto_updates_patches_only' => 'on',
				)
			);

		$plugins = Plugins::get_instance();
		$item    = (object) array(
			'plugin'      => 'hello.php',
			'new_version' => '1.2.4',
		);

		$this->assertTrue( $plugins->maybe_auto_update_plugin( false, $item ) );
	}

	/**
	 * Test plugin minor update is blocked when patches-only is active.
	 */
	public function test_maybe_auto_update_plugin_blocks_minor_update(): void {
		MonkeyFunctions\when( 'add_filter' )->justReturn( true );
		$this->stub_option_helpers();
		$wpdb = $this->setup_skipped_log_mocks();
		MonkeyFunctions\when( 'get_plugin_data' )->justReturn(
			array(
				'Version' => '1.2.3',
			)
		);
		MonkeyFunctions\expect( 'get_site_option' )
			->once()
			->andReturn(
				array(
					'plugin_auto_updates'              => 'on',
					'plugin_auto_updates_custom'       => 'off',
					'plugin_auto_updates_patches_only' => 'on',
				)
			);

		$plugins = Plugins::get_instance();
		$item    = (object) array(
			'plugin'      => 'hello.php',
			'new_version' => '1.3.0',
		);

		$this->assertFalse( $plugins->maybe_auto_update_plugin( false, $item ) );
		$this->assertCount( 1, $wpdb->insert_calls );
		$this->assertSame( 'skipped', $wpdb->insert_calls[0][1]['update_status'] );
	}

	/**
	 * Test plugin major update is blocked when patches-only is active.
	 */
	public function test_maybe_auto_update_plugin_blocks_major_update(): void {
		MonkeyFunctions\when( 'add_filter' )->justReturn( true );
		$this->stub_option_helpers();
		$wpdb = $this->setup_skipped_log_mocks();
		MonkeyFunctions\when( 'get_plugin_data' )->justReturn(
			array(
				'Version' => '1.2.3',
			)
		);
		MonkeyFunctions\expect( 'get_site_option' )
			->once()
			->andReturn(
				array(
					'plugin_auto_updates'              => 'on',
					'plugin_auto_updates_custom'       => 'off',
					'plugin_auto_updates_patches_only' => 'on',
				)
			);

		$plugins = Plugins::get_instance();
		$item    = (object) array(
			'plugin'      => 'hello.php',
			'new_version' => '2.0.0',
		);

		$this->assertFalse( $plugins->maybe_auto_update_plugin( false, $item ) );
		$this->assertCount( 1, $wpdb->insert_calls );
		$this->assertSame( 'skipped', $wpdb->insert_calls[0][1]['update_status'] );
	}

	/**
	 * Test plugin build segment update is allowed when patches-only is active.
	 */
	public function test_maybe_auto_update_plugin_allows_build_update(): void {
		MonkeyFunctions\when( 'add_filter' )->justReturn( true );
		$this->stub_option_helpers();
		MonkeyFunctions\when( 'get_plugin_data' )->justReturn(
			array(
				'Version' => '1.2.3',
			)
		);
		MonkeyFunctions\expect( 'get_site_option' )
			->once()
			->andReturn(
				array(
					'plugin_auto_updates'              => 'on',
					'plugin_auto_updates_custom'       => 'off',
					'plugin_auto_updates_patches_only' => 'on',
				)
			);

		$plugins = Plugins::get_instance();
		$item    = (object) array(
			'plugin'      => 'hello.php',
			'new_version' => '1.2.3.1',
		);

		$this->assertTrue( $plugins->maybe_auto_update_plugin( false, $item ) );
	}

	/**
	 * Test plugin unparseable version is blocked and logged as skipped.
	 */
	public function test_maybe_auto_update_plugin_blocks_unparseable_version(): void {
		MonkeyFunctions\when( 'add_filter' )->justReturn( true );
		$this->stub_option_helpers();
		$wpdb = $this->setup_skipped_log_mocks();
		MonkeyFunctions\when( 'get_plugin_data' )->justReturn(
			array(
				'Name'    => 'Hello Dolly',
				'Version' => 'stable',
			)
		);
		MonkeyFunctions\expect( 'get_site_option' )
			->once()
			->andReturn(
				array(
					'plugin_auto_updates'              => 'on',
					'plugin_auto_updates_custom'       => 'off',
					'plugin_auto_updates_patches_only' => 'on',
				)
			);

		$plugins = Plugins::get_instance();
		$item    = (object) array(
			'plugin'      => 'hello.php',
			'new_version' => '1.2.4',
		);

		$this->assertFalse( $plugins->maybe_auto_update_plugin( false, $item ) );
	}

	/**
	 * Test skipped log is inserted for unparseable patch-only update.
	 */
	public function test_log_patch_update_skipped_inserts_skipped_row(): void {
		$this->stub_option_helpers();
		$wpdb = $this->setup_skipped_log_mocks();

		MonkeyFunctions\when( 'get_plugin_data' )->justReturn(
			array(
				'Name'    => 'Hello Dolly',
				'Version' => 'stable',
			)
		);

		Functions::log_patch_update_skipped(
			'hello.php',
			'plugin',
			'stable',
			'1.2.4',
			'Installed version cannot be parsed'
		);

		$this->assertCount( 1, $wpdb->insert_calls );
		$this->assertSame( 'skipped', $wpdb->insert_calls[0][1]['update_status'] );
	}

	/**
	 * Test theme patch update is allowed when patches-only is active.
	 */
	public function test_maybe_auto_update_theme_allows_patch_update(): void {
		MonkeyFunctions\when( 'add_filter' )->justReturn( true );
		$this->stub_option_helpers();
		MonkeyFunctions\when( 'wp_get_theme' )->justReturn(
			new class() {
				/**
				 * Get a theme attribute.
				 *
				 * @param string $key Attribute key.
				 * @return string
				 */
				public function get( $key ) {
					return 'Version' === $key ? '1.2.3' : '';
				}
			}
		);
		MonkeyFunctions\expect( 'get_site_option' )
			->once()
			->andReturn(
				array(
					'theme_auto_updates'              => 'on',
					'theme_auto_updates_custom'       => 'off',
					'theme_auto_updates_patches_only' => 'on',
				)
			);

		$themes = Themes::get_instance();
		$item   = (object) array(
			'theme'       => 'twentytwentyfour',
			'new_version' => '1.2.4',
		);

		$this->assertTrue( $themes->maybe_auto_update_theme( false, $item ) );
	}

	/**
	 * Test theme minor update is blocked when patches-only is active.
	 */
	public function test_maybe_auto_update_theme_blocks_minor_update(): void {
		MonkeyFunctions\when( 'add_filter' )->justReturn( true );
		$this->stub_option_helpers();
		$wpdb = $this->setup_skipped_log_mocks();
		MonkeyFunctions\when( 'wp_get_theme' )->justReturn(
			new class() {
				/**
				 * Get a theme attribute.
				 *
				 * @param string $key Attribute key.
				 * @return string
				 */
				public function get( $key ) {
					return 'Version' === $key ? '1.2.3' : '';
				}
			}
		);
		MonkeyFunctions\expect( 'get_site_option' )
			->once()
			->andReturn(
				array(
					'theme_auto_updates'              => 'on',
					'theme_auto_updates_custom'       => 'off',
					'theme_auto_updates_patches_only' => 'on',
				)
			);

		$themes = Themes::get_instance();
		$item   = (object) array(
			'theme'       => 'twentytwentyfour',
			'new_version' => '1.3.0',
		);

		$this->assertFalse( $themes->maybe_auto_update_theme( false, $item ) );
		$this->assertCount( 1, $wpdb->insert_calls );
		$this->assertSame( 'skipped', $wpdb->insert_calls[0][1]['update_status'] );
	}
}
