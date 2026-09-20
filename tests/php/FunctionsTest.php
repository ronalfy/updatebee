<?php
/**
 * Tests for Functions helpers.
 *
 * @package updatebee
 */

namespace UpdateBee\Tests;

use Brain\Monkey\Functions as MonkeyFunctions;
use UpdateBee\Functions;

/**
 * Functions test case.
 */
class FunctionsTest extends TestCase {

	/**
	 * Test camelCase conversion.
	 */
	public function test_to_camelcase_converts_underscore_fields() {
		$this->assertSame( 'majorUpdates', Functions::to_camelcase( 'major_updates' ) );
		$this->assertSame( 'dashboard', Functions::to_camelcase( 'dashboard' ) );
		$this->assertSame( 'autoUpdatePlugins', Functions::to_camelcase( 'auto_update_plugins' ) );
	}

	/**
	 * Test settings URL query args on a single site.
	 */
	public function test_get_settings_url_adds_tab_query_args() {
		MonkeyFunctions\when( 'is_plugin_active_for_network' )->justReturn( false );
		MonkeyFunctions\when( 'is_multisite' )->justReturn( false );
		MonkeyFunctions\when( 'is_network_admin' )->justReturn( false );
		MonkeyFunctions\when( 'admin_url' )->alias(
			static function ( $path ) {
				return 'https://example.com/wp-admin/' . ltrim( $path, '/' );
			}
		);
		MonkeyFunctions\when( 'sanitize_title' )->returnArg( 1 );
		MonkeyFunctions\when( 'add_query_arg' )->alias(
			static function ( $args, $url ) {
				return $url . '&' . http_build_query( $args );
			}
		);
		MonkeyFunctions\when( 'plugin_basename' )->alias(
			static function ( $file ) {
				return basename( dirname( $file ) ) . '/' . basename( $file );
			}
		);

		$url = Functions::get_settings_url( 'dashboard', 'status' );

		$this->assertStringContainsString( 'page=updatebee', $url );
		$this->assertStringContainsString( 'tab=dashboard', $url );
		$this->assertStringContainsString( 'subtab=status', $url );
	}

	/**
	 * Test settings URL uses network admin when the plugin is network-active.
	 */
	public function test_get_settings_url_uses_network_admin_when_network_active() {
		MonkeyFunctions\when( 'is_plugin_active_for_network' )->justReturn( true );
		MonkeyFunctions\when( 'is_multisite' )->justReturn( true );
		MonkeyFunctions\when( 'is_network_admin' )->justReturn( false );
		MonkeyFunctions\when( 'plugin_basename' )->alias(
			static function ( $file ) {
				return basename( dirname( $file ) ) . '/' . basename( $file );
			}
		);
		MonkeyFunctions\when( 'admin_url' )->alias(
			static function ( $path ) {
				return 'https://example.com/wp-admin/' . ltrim( $path, '/' );
			}
		);
		MonkeyFunctions\when( 'network_admin_url' )->alias(
			static function ( $path ) {
				return 'https://example.com/wp-admin/network/' . ltrim( $path, '/' );
			}
		);
		MonkeyFunctions\when( 'sanitize_title' )->returnArg( 1 );
		MonkeyFunctions\when( 'add_query_arg' )->alias(
			static function ( $args, $url ) {
				return $url . '&' . http_build_query( $args );
			}
		);

		$url = Functions::get_settings_url( 'dashboard' );

		$this->assertStringContainsString( 'wp-admin/network/settings.php?page=updatebee', $url );
		$this->assertStringContainsString( 'tab=dashboard', $url );
	}
}
