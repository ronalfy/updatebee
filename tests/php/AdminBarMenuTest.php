<?php
/**
 * Tests for the toolbar shortcut menu visibility.
 *
 * @package updatebee
 */

namespace UpdateBee\Tests;

use Brain\Monkey\Functions as MonkeyFunctions;
use Closure;
use UpdateBee\Admin\Admin_Bar_Menu;
use UpdateBee\Options;

/**
 * Admin_Bar_Menu visibility test case.
 */
class AdminBarMenuTest extends TestCase {

	/**
	 * Reset the options cache after each test.
	 */
	protected function tearDown(): void {
		Options::flush_cache();
		parent::tearDown();
	}

	/**
	 * Stub option helpers used by Options.
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
	}

	/**
	 * Stub access checks and stored options.
	 *
	 * @param array<string, mixed> $stored     Stored option overrides.
	 * @param bool                 $can_access Whether the user can open settings.
	 * @return Closure(bool): void Setter for the current is_admin() value.
	 */
	private function stub_visibility( array $stored, bool $can_access ): Closure {
		$this->stub_option_helpers();
		MonkeyFunctions\when( 'get_site_option' )->justReturn( $stored );
		MonkeyFunctions\when( 'is_plugin_active_for_network' )->justReturn( false );
		MonkeyFunctions\when( 'is_multisite' )->justReturn( false );
		MonkeyFunctions\when( 'current_user_can' )->justReturn( $can_access );

		$is_admin = true;
		MonkeyFunctions\when( 'is_admin' )->alias(
			static function () use ( &$is_admin ): bool {
				return $is_admin;
			}
		);

		return static function ( bool $next ) use ( &$is_admin ): void {
			$is_admin = $next;
		};
	}

	/**
	 * The menu stays hidden until the master toggle is saved on.
	 */
	public function test_hidden_when_disabled_by_default(): void {
		$set_is_admin = $this->stub_visibility( array(), true );
		$set_is_admin( true );

		$this->assertFalse( Admin_Bar_Menu::should_display() );
	}

	/**
	 * Users who cannot open settings never see the menu.
	 */
	public function test_hidden_without_capability(): void {
		$set_is_admin = $this->stub_visibility(
			array(
				'admin_bar_enabled' => true,
			),
			false
		);
		$set_is_admin( true );

		$this->assertFalse( Admin_Bar_Menu::should_display() );
	}

	/**
	 * Admin location shows the menu only in wp-admin.
	 */
	public function test_shown_in_wp_admin_only(): void {
		$set_is_admin = $this->stub_visibility(
			array(
				'admin_bar_enabled'       => true,
				'admin_bar_show_admin'    => true,
				'admin_bar_show_frontend' => false,
			),
			true
		);

		$set_is_admin( true );
		$this->assertTrue( Admin_Bar_Menu::should_display() );

		Options::flush_cache();
		$set_is_admin( false );
		$this->assertFalse( Admin_Bar_Menu::should_display() );
	}

	/**
	 * Frontend location shows the menu only outside wp-admin.
	 */
	public function test_shown_on_frontend_only(): void {
		$set_is_admin = $this->stub_visibility(
			array(
				'admin_bar_enabled'       => true,
				'admin_bar_show_admin'    => false,
				'admin_bar_show_frontend' => true,
			),
			true
		);

		$set_is_admin( false );
		$this->assertTrue( Admin_Bar_Menu::should_display() );

		Options::flush_cache();
		$set_is_admin( true );
		$this->assertFalse( Admin_Bar_Menu::should_display() );
	}

	/**
	 * Both location toggles off hides the menu in each context.
	 */
	public function test_hidden_when_location_toggles_are_off(): void {
		$set_is_admin = $this->stub_visibility(
			array(
				'admin_bar_enabled'       => true,
				'admin_bar_show_admin'    => false,
				'admin_bar_show_frontend' => false,
			),
			true
		);

		$set_is_admin( true );
		$this->assertFalse( Admin_Bar_Menu::should_display() );

		Options::flush_cache();
		$set_is_admin( false );
		$this->assertFalse( Admin_Bar_Menu::should_display() );
	}

	/**
	 * Parent title includes the bee icon and an escaped label.
	 *
	 * A label containing "%" must survive. The SVG used to live inside sprintf(),
	 * where width="100%" was an invalid format specifier.
	 */
	public function test_parent_title_includes_icon_and_escaped_label(): void {
		$set_is_admin = $this->stub_visibility(
			array(
				'admin_bar_enabled'    => true,
				'admin_bar_show_admin' => true,
			),
			true
		);
		$set_is_admin( true );

		MonkeyFunctions\when( 'apply_filters' )->alias(
			static function ( $hook, $value ) {
				if ( 'updatebee_plugin_menu_name' === $hook ) {
					return '100% "Bee" <script>';
				}

				return $value;
			}
		);
		MonkeyFunctions\when( 'esc_html' )->alias(
			static function ( $text ) {
				return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
			}
		);
		MonkeyFunctions\when( '_x' )->returnArg( 1 );
		MonkeyFunctions\when( 'admin_url' )->alias(
			static function ( $path ) {
				return 'https://example.com/wp-admin/' . ltrim( (string) $path, '/' );
			}
		);
		MonkeyFunctions\when( 'sanitize_title' )->returnArg( 1 );
		MonkeyFunctions\when( 'add_query_arg' )->alias(
			static function ( $args, $url ) {
				return $url . '&' . http_build_query( (array) $args );
			}
		);

		$bar = new \WP_Admin_Bar();
		( new Admin_Bar_Menu() )->add_menu_items( $bar );

		$this->assertArrayHasKey( 'updatebee', $bar->nodes );
		$title = $bar->nodes['updatebee']['title'];

		$this->assertIsString( $title );
		$this->assertStringContainsString( 'class="ab-icon"', $title );
		$this->assertStringContainsString( 'aria-hidden="true"', $title );
		$this->assertStringContainsString( '<svg ', $title );
		$this->assertStringContainsString( 'viewBox="0 0 475 451"', $title );
		$this->assertStringContainsString( 'fill="rgb(3,4,4)"', $title );
		$this->assertStringContainsString( 'fill="rgb(255,198,36)"', $title );
		$this->assertStringContainsString( 'M2864.67,2149.02', $title );
		$this->assertStringContainsString( 'M1355.87,2335.71', $title );
		$this->assertStringContainsString( '<span class="ab-label">100% &quot;Bee&quot; &lt;script&gt;</span>', $title );
		$this->assertLessThan( strpos( $title, 'ab-label' ), strpos( $title, 'ab-icon' ) );
		$this->assertStringNotContainsString( 'dashicons', $title );
		$this->assertStringNotContainsString( 'width="100%"', $title );
		$this->assertStringNotContainsString( 'affinity', $title );
		$this->assertSame( 1, substr_count( $title, '%' ) );
	}
}
