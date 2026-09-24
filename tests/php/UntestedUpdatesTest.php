<?php
/**
 * Tests for blocking untested plugin and theme updates.
 *
 * @package updatebee
 */

namespace UpdateBee\Tests;

use Brain\Monkey\Functions as MonkeyFunctions;
use UpdateBee\Options;

/**
 * Untested_Updates test case.
 */
class UntestedUpdatesTest extends TestCase {

	/**
	 * In-memory site options.
	 *
	 * @var array<string, mixed>
	 */
	private array $site_options = array();

	/**
	 * Reset the options cache after each test.
	 */
	protected function tearDown(): void {
		Options::flush_cache();
		parent::tearDown();
	}

	/**
	 * Stub WordPress helpers used by Options.
	 */
	private function stub_helpers(): void {
		MonkeyFunctions\when( 'add_filter' )->justReturn( true );
		MonkeyFunctions\when( 'apply_filters' )->returnArg( 2 );
		MonkeyFunctions\when( 'get_bloginfo' )->alias(
			static function ( $show ) {
				return 'version' === $show ? '6.8.1' : '';
			}
		);
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
			function ( $key, $fallback = false ) {
				return array_key_exists( $key, $this->site_options ) ? $this->site_options[ $key ] : $fallback;
			}
		);
	}

	/**
	 * Return a harness with the global switch set.
	 *
	 * @param string $enabled on|off.
	 * @return UntestedUpdatesHarness
	 */
	private function checker( string $enabled = 'on' ): UntestedUpdatesHarness {
		$this->stub_helpers();
		$this->site_options['updatebee_options'] = array(
			'block_untested_updates' => $enabled,
		);

		return new UntestedUpdatesHarness();
	}

	/**
	 * The global switch off never vetoes.
	 */
	public function test_global_off_allows_untested_plugin(): void {
		$checker = $this->checker( 'off' );
		$item    = (object) array(
			'plugin' => 'akismet/akismet.php',
			'tested' => '6.7',
		);

		$this->assertTrue( $checker->auto_update_plugin( true, $item ) );
		$this->assertSame( 0, $checker->logs );
	}

	/**
	 * A newer site major.minor than tested blocks the update.
	 */
	public function test_untested_major_minor_is_blocked(): void {
		$checker = $this->checker();
		$item    = (object) array(
			'plugin' => 'hello.php',
			'tested' => '6.7.2',
		);

		$this->assertFalse( $checker->auto_update_plugin( true, $item ) );
		$this->assertSame( 1, $checker->logs );
	}

	/**
	 * The same major.minor is allowed, including a newer patch on the site.
	 */
	public function test_same_major_minor_is_allowed(): void {
		$checker = $this->checker();
		$item    = (object) array(
			'theme'  => 'twentytwentyfour',
			'tested' => '6.8',
		);

		$this->assertTrue( $checker->auto_update_theme( true, $item ) );
		$this->assertSame( 0, $checker->logs );
	}

	/**
	 * Offers without a tested field are left alone.
	 */
	public function test_missing_tested_is_allowed(): void {
		$checker = $this->checker();
		$item    = (object) array(
			'plugin' => 'premium/premium.php',
		);

		$this->assertTrue( $checker->auto_update_plugin( true, $item ) );
		$this->assertSame( 0, $checker->logs );
	}

	/**
	 * A per-item exemption allows an otherwise untested update.
	 */
	public function test_per_item_exemption_allows_update(): void {
		$checker           = $this->checker();
		$checker->exempt[] = 'plugin:akismet/akismet.php';
		$item              = (object) array(
			'plugin' => 'akismet/akismet.php',
			'tested' => '6.0',
		);

		$this->assertTrue( $checker->auto_update_plugin( true, $item ) );
		$this->assertSame( 0, $checker->logs );
	}

	/**
	 * WordPress Site Health fake items are not vetoed.
	 */
	public function test_site_health_fakes_are_not_vetoed(): void {
		$checker = $this->checker();
		$plugin  = (object) array(
			'plugin' => 'a-fake-plugin/a-fake-plugin.php',
			'tested' => '6.0',
		);
		$theme   = (object) array(
			'theme'  => 'a-fake-theme',
			'tested' => '6.0',
		);

		$this->assertTrue( $checker->auto_update_plugin( true, $plugin ) );
		$this->assertTrue( $checker->auto_update_theme( true, $theme ) );
		$this->assertSame( 0, $checker->logs );
	}

	/**
	 * An update that is already blocked stays blocked.
	 */
	public function test_already_blocked_update_is_unchanged(): void {
		$checker = $this->checker();
		$item    = (object) array(
			'plugin' => 'hello.php',
			'tested' => '6.7',
		);

		$this->assertFalse( $checker->auto_update_plugin( false, $item ) );
		$this->assertSame( 0, $checker->logs );
	}
}
