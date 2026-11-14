<?php
/**
 * Tests for plugin data cleanup.
 *
 * @package updatebee
 */

namespace UpdateBee\Tests;

use Brain\Monkey\Functions as MonkeyFunctions;
use UpdateBee\Options;
use UpdateBee\Uninstall;

/**
 * Uninstall cleanup test case.
 */
class UninstallTest extends TestCase {

	/**
	 * Clear the options cache and database mock.
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		Options::flush_cache();
		parent::tearDown();
	}

	/**
	 * Cleanup deletes options, tables, cron events, and transients on a single site.
	 */
	public function test_clear_data_removes_plugin_storage(): void {
		$deleted_options    = array();
		$deleted_site_meta  = array();
		$cleared_hooks      = array();
		$deleted_transients = array();
		$queries            = array();

		$this->stub_cleanup_functions( $deleted_options, $deleted_site_meta, $cleared_hooks, $deleted_transients );
		MonkeyFunctions\when( 'is_multisite' )->justReturn( false );
		$this->set_mock_wpdb( $queries );

		Uninstall::clear_data();

		$this->assertEqualsCanonicalizing(
			array(
				'updatebee_options',
				'updatebee_core_version',
				'updatebee_delayed_plugin_updates',
				'updatebee_delayed_theme_updates',
				'updatebee_plugin_cron_schedule',
				'updatebee_theme_cron_schedule',
				'updatebee_core_cron_schedule',
				'updatebee_email_successes',
				'updatebee_email_success_last_send',
				'updatebee_email_failures',
				'updatebee_email_failure_last_send',
				'updatebee_email_updates_available',
				'updatebee_email_updates_available_last_send',
				'updatebee_hide_eum_importer',
				'updatebee_hide_cau_importer',
			),
			$deleted_site_meta
		);
		$this->assertEqualsCanonicalizing(
			array(
				'updatebee_plugins_version',
				'updatebee_plugins_schema',
				'updatebee_themes_version',
				'updatebee_themes_schema',
				'updatebee_logs_version',
				'updatebee_logs_schema',
			),
			$deleted_options
		);
		$this->assertEqualsCanonicalizing(
			array(
				'updatebee_clear_expired_logs',
				'updatebee_email_success_report',
				'updatebee_email_failure_report',
				'updatebee_email_updates_available_report',
				'wp_update_plugins',
				'wp_update_themes',
				'wp_version_check',
			),
			$cleared_hooks
		);
		$this->assertEqualsCanonicalizing(
			array(
				'updatebee_plugin_updates',
				'updatebee_theme_updates',
				'updatebee_core_updates',
			),
			$deleted_transients
		);
		$this->assertStringContainsString( 'wp_updatebee_plugins', implode( ' ', $queries ) );
		$this->assertStringContainsString( 'wp_updatebee_themes', implode( ' ', $queries ) );
		$this->assertStringContainsString( 'wp_updatebee_logs', implode( ' ', $queries ) );
	}

	/**
	 * A network-active plugin redirects to the network Plugins screen.
	 */
	public function test_plugins_screen_url_uses_network_admin_while_network_active(): void {
		MonkeyFunctions\when( 'is_multisite' )->justReturn( true );
		MonkeyFunctions\when( 'is_network_admin' )->justReturn( false );
		MonkeyFunctions\when( 'plugin_basename' )->justReturn( 'updatebee/updatebee.php' );
		MonkeyFunctions\when( 'is_plugin_active_for_network' )->justReturn( true );
		MonkeyFunctions\expect( 'network_admin_url' )
			->once()
			->with( 'plugins.php' )
			->andReturn( 'https://example.test/wp-admin/network/plugins.php' );

		$this->assertSame(
			'https://example.test/wp-admin/network/plugins.php',
			Uninstall::plugins_screen_url()
		);
	}

	/**
	 * After network deactivation, a network-admin request still uses the network Plugins screen.
	 */
	public function test_plugins_screen_url_uses_network_admin_from_network_request(): void {
		MonkeyFunctions\when( 'is_multisite' )->justReturn( true );
		MonkeyFunctions\when( 'is_network_admin' )->justReturn( true );
		MonkeyFunctions\when( 'is_plugin_active_for_network' )->justReturn( false );
		MonkeyFunctions\expect( 'network_admin_url' )
			->once()
			->with( 'plugins.php' )
			->andReturn( 'https://example.test/wp-admin/network/plugins.php' );

		$this->assertSame(
			'https://example.test/wp-admin/network/plugins.php',
			Uninstall::plugins_screen_url()
		);
	}

	/**
	 * A single site uses its own Plugins screen.
	 */
	public function test_plugins_screen_url_uses_site_admin_on_single_site(): void {
		MonkeyFunctions\when( 'is_multisite' )->justReturn( false );
		MonkeyFunctions\expect( 'admin_url' )
			->once()
			->with( 'plugins.php' )
			->andReturn( 'https://example.test/wp-admin/plugins.php' );

		$this->assertSame(
			'https://example.test/wp-admin/plugins.php',
			Uninstall::plugins_screen_url()
		);
	}

	/**
	 * On multisite, table version options are removed for every site.
	 */
	public function test_clear_data_drops_tables_on_each_site(): void {
		$deleted_options    = array();
		$deleted_site_meta  = array();
		$cleared_hooks      = array();
		$deleted_transients = array();
		$queries            = array();
		$switched           = array();

		$this->stub_cleanup_functions( $deleted_options, $deleted_site_meta, $cleared_hooks, $deleted_transients );
		MonkeyFunctions\when( 'is_multisite' )->justReturn( true );
		MonkeyFunctions\when( 'get_sites' )->justReturn( array( 1, 2 ) );
		MonkeyFunctions\when( 'switch_to_blog' )->alias(
			static function ( $site_id ) use ( &$switched ) {
				$switched[] = (int) $site_id;
			}
		);
		MonkeyFunctions\expect( 'restore_current_blog' )->twice();
		$this->set_mock_wpdb( $queries );

		Uninstall::clear_data();

		$this->assertSame( array( 1, 2 ), $switched );
		$this->assertCount( 12, $deleted_options );
		$this->assertCount( 6, $queries );
	}

	/**
	 * Record WordPress cleanup calls.
	 *
	 * @param array<int, string> $deleted_options    Option keys passed to delete_option.
	 * @param array<int, string> $deleted_site_meta  Option keys passed to delete_site_option.
	 * @param array<int, string> $cleared_hooks      Hooks passed to wp_clear_scheduled_hook.
	 * @param array<int, string> $deleted_transients Transient names passed to delete_site_transient.
	 */
	private function stub_cleanup_functions( array &$deleted_options, array &$deleted_site_meta, array &$cleared_hooks, array &$deleted_transients ): void {
		MonkeyFunctions\when( 'delete_option' )->alias(
			static function ( $key ) use ( &$deleted_options ) {
				$deleted_options[] = (string) $key;
				return true;
			}
		);
		MonkeyFunctions\when( 'delete_site_option' )->alias(
			static function ( $key ) use ( &$deleted_site_meta ) {
				$deleted_site_meta[] = (string) $key;
				return true;
			}
		);
		MonkeyFunctions\when( 'wp_clear_scheduled_hook' )->alias(
			static function ( $hook ) use ( &$cleared_hooks ) {
				$cleared_hooks[] = (string) $hook;
				return 0;
			}
		);
		MonkeyFunctions\when( 'delete_site_transient' )->alias(
			static function ( $transient ) use ( &$deleted_transients ) {
				$deleted_transients[] = (string) $transient;
				return true;
			}
		);
	}

	/**
	 * Assign a wpdb mock that records queries.
	 *
	 * @param array<int, string> $queries Recorded SQL.
	 */
	private function set_mock_wpdb( array &$queries ): void {
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test mock.
		$GLOBALS['wpdb'] = new class( $queries ) {
			/**
			 * Network table prefix.
			 *
			 * @var string
			 */
			public $base_prefix = 'wp_';

			/**
			 * Recorded queries.
			 *
			 * @var array<int, string>
			 */
			public $queries;

			/**
			 * Constructor.
			 *
			 * @param array<int, string> $queries Recorded queries, by reference.
			 */
			public function __construct( array &$queries ) {
				$this->queries = &$queries;
			}

			/**
			 * Record a query.
			 *
			 * @param string $sql SQL statement.
			 * @return bool
			 */
			public function query( $sql ) {
				$this->queries[] = (string) $sql;
				return true;
			}
		};
	}
}
