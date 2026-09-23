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
use UpdateBee\Updates\Plugins;
use UpdateBee\Updates\Themes;
use UpdateBee\Updates\Translations;

/**
 * Updates runtime test case.
 */
class UpdatesRuntimeTest extends TestCase {

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
}
