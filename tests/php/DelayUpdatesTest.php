<?php
/**
 * Tests for delayed automatic updates.
 *
 * @package updatebee
 */

namespace UpdateBee\Tests;

use Brain\Monkey\Functions as MonkeyFunctions;
use ReflectionClass;
use UpdateBee\Options;
use UpdateBee\Updates\Delay_Updates;

/**
 * Delay_Updates test case.
 */
class DelayUpdatesTest extends TestCase {

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
		$this->reset_delay_singleton();
		parent::tearDown();
	}

	/**
	 * Clear the Delay_Updates singleton.
	 */
	private function reset_delay_singleton(): void {
		$reflection = new ReflectionClass( Delay_Updates::class );
		$property   = $reflection->getProperty( 'instance' );
		$property->setAccessible( true );
		$property->setValue( null, null );
	}

	/**
	 * Stub WordPress helpers used by Options and Delay_Updates.
	 */
	private function stub_helpers(): void {
		MonkeyFunctions\when( 'add_filter' )->justReturn( true );
		MonkeyFunctions\when( 'add_action' )->justReturn( true );
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
		MonkeyFunctions\when( 'absint' )->alias(
			static function ( $value ) {
				return abs( (int) $value );
			}
		);
		MonkeyFunctions\when( 'get_site_option' )->alias(
			function ( $key, $default = false ) {
				return array_key_exists( $key, $this->site_options ) ? $this->site_options[ $key ] : $default;
			}
		);
		MonkeyFunctions\when( 'update_site_option' )->alias(
			function ( $key, $value ) {
				$this->site_options[ $key ] = $value;
				return true;
			}
		);
	}

	/**
	 * Return a Delay_Updates instance with default stubs.
	 *
	 * @param array<string, mixed> $options Stored updatebee_options values.
	 * @return Delay_Updates
	 */
	private function get_delay_updates( array $options = array() ): Delay_Updates {
		$this->stub_helpers();
		$this->site_options['updatebee_options'] = $options;

		return Delay_Updates::get_instance();
	}

	/**
	 * Delay of zero never vetoes an auto-update.
	 */
	public function test_delay_zero_never_vetoes(): void {
		$delay = $this->get_delay_updates( array( 'delay_updates' => 0 ) );
		$item  = (object) array(
			'plugin' => 'hello.php',
		);

		$this->site_options[ Delay_Updates::PLUGIN_OPTION ] = array(
			'hello.php' => array(
				'version' => '1.0.1',
				'time'    => time(),
			),
		);

		$this->assertTrue( $delay->auto_update_plugin( true, $item ) );
		$this->assertTrue( $delay->auto_update_theme( true, (object) array( 'theme' => 'twentytwentyfour' ) ) );
	}

	/**
	 * A plugin still inside the delay window is not auto-updated.
	 */
	public function test_plugin_within_window_is_vetoed(): void {
		$delay = $this->get_delay_updates( array( 'delay_updates' => 2 ) );
		$item  = (object) array(
			'plugin' => 'hello.php',
		);

		$this->site_options[ Delay_Updates::PLUGIN_OPTION ] = array(
			'hello.php' => array(
				'version' => '1.0.1',
				'time'    => time(),
			),
		);

		$this->assertFalse( $delay->auto_update_plugin( true, $item ) );
	}

	/**
	 * A theme still inside the delay window is not auto-updated.
	 */
	public function test_theme_within_window_is_vetoed(): void {
		$delay = $this->get_delay_updates( array( 'delay_updates' => 2 ) );
		$item  = (object) array(
			'theme' => 'twentytwentyfour',
		);

		$this->site_options[ Delay_Updates::THEME_OPTION ] = array(
			'twentytwentyfour' => array(
				'version' => '1.0.1',
				'time'    => time(),
			),
		);

		$this->assertFalse( $delay->auto_update_theme( true, $item ) );
	}

	/**
	 * WordPress Site Health fake plugin/theme items are not vetoed.
	 */
	public function test_site_health_fakes_are_not_vetoed(): void {
		$delay = $this->get_delay_updates( array( 'delay_updates' => 2 ) );

		$this->site_options[ Delay_Updates::PLUGIN_OPTION ] = array(
			'a-fake-plugin/a-fake-plugin.php' => array(
				'version' => '1.0.1',
				'time'    => time(),
			),
		);
		$this->site_options[ Delay_Updates::THEME_OPTION ]  = array(
			'a-fake-theme' => array(
				'version' => '1.0.1',
				'time'    => time(),
			),
		);

		$plugin = (object) array(
			'plugin' => 'a-fake-plugin/a-fake-plugin.php',
		);
		$theme  = (object) array(
			'theme' => 'a-fake-theme',
		);

		$this->assertTrue( $delay->auto_update_plugin( true, $plugin ) );
		$this->assertTrue( $delay->auto_update_theme( true, $theme ) );
	}

	/**
	 * A newer version resets the first-seen stamp.
	 */
	public function test_newer_version_resets_stamp(): void {
		$delay = $this->get_delay_updates();

		$this->site_options[ Delay_Updates::PLUGIN_OPTION ] = array(
			'hello.php' => array(
				'version' => '1.0.0',
				'time'    => 100,
			),
		);

		$transient           = new \stdClass();
		$transient->response = array(
			'hello.php' => (object) array(
				'new_version' => '1.0.1',
			),
		);

		$result = $delay->maybe_set_delay_for_plugin_updates( $transient );

		$this->assertSame( $transient, $result );
		$this->assertSame( '1.0.1', $this->site_options[ Delay_Updates::PLUGIN_OPTION ]['hello.php']['version'] );
		$this->assertGreaterThan( 100, $this->site_options[ Delay_Updates::PLUGIN_OPTION ]['hello.php']['time'] );
	}

	/**
	 * An unchanged version does not reset the stamp.
	 */
	public function test_same_version_does_not_reset_stamp(): void {
		$delay = $this->get_delay_updates();

		$this->site_options[ Delay_Updates::PLUGIN_OPTION ] = array(
			'hello.php' => array(
				'version' => '1.0.1',
				'time'    => 100,
			),
		);

		$transient           = new \stdClass();
		$transient->response = array(
			'hello.php' => (object) array(
				'new_version' => '1.0.1',
			),
		);

		$delay->maybe_set_delay_for_plugin_updates( $transient );

		$this->assertSame( 100, $this->site_options[ Delay_Updates::PLUGIN_OPTION ]['hello.php']['time'] );
	}

	/**
	 * After the delay elapses, the stamp is cleared and the original decision stands.
	 */
	public function test_expired_delay_unsets_stamp_and_returns_original(): void {
		$delay = $this->get_delay_updates( array( 'delay_updates' => 2 ) );
		$item  = (object) array(
			'plugin' => 'hello.php',
		);

		$this->site_options[ Delay_Updates::PLUGIN_OPTION ] = array(
			'hello.php' => array(
				'version' => '1.0.1',
				'time'    => time() - ( 3 * DAY_IN_SECONDS ),
			),
			'other.php' => array(
				'version' => '2.0.0',
				'time'    => time(),
			),
		);

		$this->assertTrue( $delay->auto_update_plugin( true, $item ) );
		$this->assertArrayNotHasKey( 'hello.php', $this->site_options[ Delay_Updates::PLUGIN_OPTION ] );
		$this->assertArrayHasKey( 'other.php', $this->site_options[ Delay_Updates::PLUGIN_OPTION ] );
	}

	/**
	 * Empty or invalid transients are a no-op.
	 */
	public function test_empty_or_invalid_transient_is_noop(): void {
		$delay = $this->get_delay_updates();

		$this->assertNull( $delay->maybe_set_delay_for_plugin_updates( null ) );
		$this->assertFalse( $delay->maybe_set_delay_for_theme_updates( false ) );

		$empty           = new \stdClass();
		$empty->response = 'not-an-array';
		$this->assertSame( $empty, $delay->maybe_set_delay_for_plugin_updates( $empty ) );
		$this->assertArrayNotHasKey( Delay_Updates::PLUGIN_OPTION, $this->site_options );
		$this->assertArrayNotHasKey( Delay_Updates::THEME_OPTION, $this->site_options );
	}

	/**
	 * Deleting the update cache clears stamps.
	 */
	public function test_removing_delayed_updates_clears_stamps(): void {
		$delay = $this->get_delay_updates();

		$this->site_options[ Delay_Updates::PLUGIN_OPTION ] = array(
			'hello.php' => array(
				'version' => '1.0.1',
				'time'    => time(),
			),
		);

		$delay->remove_delayed_plugin_updates();

		$this->assertSame( array(), $this->site_options[ Delay_Updates::PLUGIN_OPTION ] );
	}
}
