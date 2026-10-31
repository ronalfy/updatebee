<?php
/**
 * Tests for hiding blocked updates from the WordPress updater.
 *
 * @package updatebee
 */

namespace UpdateBee\Tests;

use Brain\Monkey\Functions as MonkeyFunctions;
use ReflectionClass;
use UpdateBee\Options;
use UpdateBee\Updates\Hide_Blocked_Updates;

/**
 * Hide_Blocked_Updates test case.
 */
class HideBlockedUpdatesTest extends TestCase {

	/**
	 * In-memory site options.
	 *
	 * @var array<string, mixed>
	 */
	private array $site_options = array();

	/**
	 * Reset the singleton and option cache.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->reset_singleton();
		Options::flush_cache();
		$this->stub_helpers();
	}

	/**
	 * Clear cached options and the singleton.
	 */
	protected function tearDown(): void {
		Options::flush_cache();
		$this->reset_singleton();
		parent::tearDown();
	}

	/**
	 * A blocked plugin is removed and an unblocked plugin stays.
	 */
	public function test_blocked_plugin_is_removed_and_other_plugin_stays(): void {
		$this->set_options(
			array(
				'blocked_plugins' => array( 'akismet' ),
			)
		);

		$filtered = $this->hide()->filter_plugins( $this->plugin_transient() );

		$this->assertIsObject( $filtered );
		$this->assertArrayNotHasKey( 'akismet/akismet.php', $filtered->response );
		$this->assertArrayHasKey( 'hello.php', $filtered->response );
		$this->assertSame( 42, $filtered->last_checked );
	}

	/**
	 * Disabling all plugin updates clears every plugin offer.
	 */
	public function test_plugin_updates_disabled_clears_every_plugin_response(): void {
		$this->set_options(
			array(
				'plugin_updates_disabled' => 'on',
			)
		);

		$filtered = $this->hide()->filter_plugins( $this->plugin_transient() );

		$this->assertIsObject( $filtered );
		$this->assertSame( array(), $filtered->response );
		$this->assertSame( 42, $filtered->last_checked );
		$this->assertSame( array( 'akismet/akismet.php' => '5.0' ), $filtered->checked );
	}

	/**
	 * A blocked theme stylesheet is removed.
	 */
	public function test_blocked_theme_is_removed(): void {
		$this->set_options(
			array(
				'blocked_themes' => array( 'twentytwentyfour' ),
			)
		);

		$updates           = new \stdClass();
		$updates->response = array(
			'twentytwentyfour' => array( 'new_version' => '1.2' ),
			'twentytwentyfive' => array( 'new_version' => '1.0' ),
		);

		$filtered = $this->hide()->filter_themes( $updates );

		$this->assertIsObject( $filtered );
		$this->assertArrayNotHasKey( 'twentytwentyfour', $filtered->response );
		$this->assertArrayHasKey( 'twentytwentyfive', $filtered->response );
	}

	/**
	 * A blocked locale is removed and other locales stay.
	 */
	public function test_blocked_locale_is_removed_from_translations(): void {
		$this->set_options(
			array(
				'blocked_translations' => array( 'fr_FR' ),
			)
		);

		$filtered = $this->hide()->filter_core( $this->core_transient() );

		$this->assertIsObject( $filtered );
		$this->assertCount( 1, $filtered->translations );
		$this->assertSame( 'de_DE', $filtered->translations[0]->language );
		$this->assertCount( 1, $filtered->updates );
		$this->assertSame( 'upgrade', $filtered->updates[0]->response );
	}

	/**
	 * Disabling translation updates clears translations and leaves core offers.
	 */
	public function test_translation_updates_disabled_clears_translations_and_keeps_core_offers(): void {
		$this->set_options(
			array(
				'translation_updates_disabled' => 'on',
			)
		);

		$filtered = $this->hide()->filter_core( $this->core_transient() );

		$this->assertIsObject( $filtered );
		$this->assertSame( array(), $filtered->translations );
		$this->assertCount( 1, $filtered->updates );
		$this->assertSame( '6.9', $filtered->updates[0]->current );
	}

	/**
	 * Manual-only mode still shows plugin updates.
	 */
	public function test_manual_updates_only_does_not_remove_plugins(): void {
		$this->set_options(
			array(
				'plugin_manual_updates_only' => 'on',
			)
		);

		$updates  = $this->plugin_transient();
		$filtered = $this->hide()->filter_plugins( $updates );

		$this->assertSame( $updates, $filtered );
		$this->assertArrayHasKey( 'akismet/akismet.php', $filtered->response );
		$this->assertArrayHasKey( 'hello.php', $filtered->response );
	}

	/**
	 * Filtering does not remove the blocked plugin from the stored transient object.
	 */
	public function test_original_transient_still_contains_blocked_plugin(): void {
		$this->set_options(
			array(
				'blocked_plugins' => array( 'akismet' ),
			)
		);

		$updates  = $this->plugin_transient();
		$filtered = $this->hide()->filter_plugins( $updates );

		$this->assertNotSame( $updates, $filtered );
		$this->assertArrayHasKey( 'akismet/akismet.php', $updates->response );
		$this->assertArrayNotHasKey( 'akismet/akismet.php', $filtered->response );
	}

	/**
	 * The unfiltered bypass still returns a blocked plugin offer.
	 */
	public function test_unfiltered_returns_blocked_plugin(): void {
		$this->set_options(
			array(
				'blocked_plugins' => array( 'akismet' ),
			)
		);

		$updates = $this->plugin_transient();
		$hide    = $this->hide();
		$raw     = Hide_Blocked_Updates::unfiltered(
			static function () use ( $hide, $updates ) {
				return $hide->filter_plugins( $updates );
			}
		);

		$this->assertIsObject( $raw );
		$this->assertArrayHasKey( 'akismet/akismet.php', $raw->response );
		$this->assertSame( $updates, $raw );
	}

	/**
	 * Plugin update transient with two offers.
	 *
	 * @return object Transient.
	 */
	private function plugin_transient(): object {
		$updates               = new \stdClass();
		$updates->last_checked = 42;
		$updates->checked      = array(
			'akismet/akismet.php' => '5.0',
		);
		$updates->response     = array(
			'akismet/akismet.php' => (object) array( 'new_version' => '5.1' ),
			'hello.php'           => (object) array( 'new_version' => '1.8' ),
		);

		return $updates;
	}

	/**
	 * Core update transient with one version offer and two translation locales.
	 *
	 * @return object Transient.
	 */
	private function core_transient(): object {
		$updates               = new \stdClass();
		$updates->last_checked = 7;
		$updates->updates      = array(
			(object) array(
				'response' => 'upgrade',
				'current'  => '6.9',
			),
		);
		$updates->translations = array(
			(object) array(
				'language' => 'fr_FR',
				'type'     => 'core',
			),
			(object) array(
				'language' => 'de_DE',
				'type'     => 'core',
			),
		);

		return $updates;
	}

	/**
	 * Store plugin options and clear the in-memory cache.
	 *
	 * @param array<string, mixed> $options Option overrides.
	 */
	private function set_options( array $options ): void {
		$this->site_options['updatebee_options'] = $options;
		Options::flush_cache();
	}

	/**
	 * Hide_Blocked_Updates instance.
	 *
	 * @return Hide_Blocked_Updates
	 */
	private function hide(): Hide_Blocked_Updates {
		return Hide_Blocked_Updates::get_instance();
	}

	/**
	 * Stub WordPress helpers used while reading options.
	 */
	private function stub_helpers(): void {
		MonkeyFunctions\when( 'add_filter' )->justReturn( true );
		MonkeyFunctions\when( 'apply_filters' )->returnArg( 2 );
		MonkeyFunctions\when( 'sanitize_title' )->returnArg( 1 );
		MonkeyFunctions\when( 'sanitize_text_field' )->returnArg( 1 );
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
	}

	/**
	 * Clear the singleton and the suspend flag.
	 */
	private function reset_singleton(): void {
		$reflection = new ReflectionClass( Hide_Blocked_Updates::class );

		$instance = $reflection->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );

		$suspended = $reflection->getProperty( 'suspended' );
		$suspended->setAccessible( true );
		$suspended->setValue( null, false );
	}
}
