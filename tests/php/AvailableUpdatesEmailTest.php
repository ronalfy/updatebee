<?php
/**
 * Tests for available update reports.
 *
 * @package updatebee
 */

namespace UpdateBee\Tests;

use Brain\Monkey\Functions as MonkeyFunctions;
use UpdateBee\Emails\Available_Updates;
use UpdateBee\Emails\Email_Updates_Available;
use UpdateBee\Emails\Emails;
use UpdateBee\Options;
use UpdateBee\Slack\Slack_Available_Updates;

/**
 * Available updates email test case.
 */
class AvailableUpdatesEmailTest extends TestCase {

	/**
	 * Reset options between tests.
	 */
	protected function tearDown(): void {
		Options::flush_cache();
		parent::tearDown();
	}

	/**
	 * Build rows skips core when versions match.
	 */
	public function test_build_rows_skips_core_with_no_version_change(): void {
		MonkeyFunctions\when( 'current_time' )->justReturn( '2026-01-01 12:00:00' );
		MonkeyFunctions\when( 'esc_url' )->returnArg( 1 );

		$core = (object) array(
			'version_checked' => '6.8.1',
			'updates'         => array(
				(object) array(
					'version'  => '6.8.1',
					'response' => 'latest',
				),
			),
		);

		$rows = Available_Updates::build_rows(
			$core,
			null,
			null,
			array(
				'core'         => true,
				'plugins'      => false,
				'themes'       => false,
				'translations' => false,
			)
		);

		$this->assertSame( array(), $rows );
	}

	/**
	 * Build rows includes a pending core update.
	 */
	public function test_build_rows_includes_core_update(): void {
		MonkeyFunctions\when( 'current_time' )->justReturn( '2026-01-01 12:00:00' );
		MonkeyFunctions\when( 'esc_url' )->returnArg( 1 );

		$core = (object) array(
			'version_checked' => '6.8',
			'updates'         => array(
				(object) array(
					'version'  => '6.8.1',
					'response' => 'upgrade',
				),
			),
		);

		$rows = Available_Updates::build_rows(
			$core,
			null,
			null,
			array(
				'core'         => true,
				'plugins'      => false,
				'themes'       => false,
				'translations' => false,
			)
		);

		$this->assertCount( 1, $rows );
		$this->assertSame( 'core', $rows[0]['update_type'] );
		$this->assertSame( '6.8', $rows[0]['from_version'] );
		$this->assertSame( '6.8.1', $rows[0]['to_version'] );
		$this->assertStringContainsString( 'Version_6.8.1', $rows[0]['detail_url'] );
	}

	/**
	 * Email template adds a Details column when detail URLs exist.
	 */
	public function test_email_template_renders_details_column(): void {
		MonkeyFunctions\when( 'home_url' )->justReturn( 'https://example.com/' );
		MonkeyFunctions\when( 'get_bloginfo' )->alias(
			static function ( $show = '' ) {
				return 'name' === $show ? 'Example' : '';
			}
		);
		MonkeyFunctions\when( 'esc_url' )->returnArg( 1 );
		MonkeyFunctions\when( 'esc_html' )->returnArg( 1 );
		MonkeyFunctions\when( 'wp_next_scheduled' )->justReturn( false );
		MonkeyFunctions\when( 'is_multisite' )->justReturn( false );
		MonkeyFunctions\when( 'admin_url' )->justReturn( 'https://example.com/wp-admin/tools.php?page=updatebee' );
		MonkeyFunctions\when( 'add_query_arg' )->returnArg( 2 );
		MonkeyFunctions\when( 'sanitize_title' )->returnArg( 1 );
		MonkeyFunctions\when( 'is_network_admin' )->justReturn( false );
		MonkeyFunctions\when( 'is_plugin_active_for_network' )->justReturn( false );

		$html = Emails::get_update_report_email(
			array(
				array(
					'name'         => 'Akismet',
					'update_type'  => 'plugin',
					'from_version' => '5.0',
					'to_version'   => '5.1',
					'date'         => '2026-01-01',
					'detail_url'   => 'https://wordpress.org/plugins/akismet/#developers',
					'detail_label' => 'Changelog',
				),
			),
			'Available updates',
			'Intro',
			'updatebee_email_updates_available_report'
		);

		$this->assertStringContainsString( 'Details', $html );
		$this->assertStringContainsString( 'Changelog', $html );
	}

	/**
	 * Slack available digest posts to the webhook.
	 */
	public function test_slack_available_send_posts_webhook(): void {
		$this->expectNotToPerformAssertions();
		MonkeyFunctions\when( 'get_site_option' )->alias(
			static function ( $key, $fallback = false ) {
				if ( 'updatebee_options' === $key ) {
					return array( 'slack_webhook_url' => 'https://hooks.slack.com/services/test' );
				}
				return $fallback;
			}
		);
		MonkeyFunctions\when( 'wp_parse_args' )->alias(
			static function ( $args, $defaults ) {
				return array_merge( (array) $defaults, (array) $args );
			}
		);
		MonkeyFunctions\when( 'apply_filters' )->returnArg( 2 );
		MonkeyFunctions\when( 'get_bloginfo' )->justReturn( 'Example' );
		MonkeyFunctions\when( 'home_url' )->justReturn( 'https://example.com/' );
		MonkeyFunctions\when( 'esc_url' )->returnArg( 1 );
		MonkeyFunctions\when( 'esc_html' )->returnArg( 1 );
		MonkeyFunctions\when( 'esc_url_raw' )->returnArg( 1 );
		MonkeyFunctions\when( 'admin_url' )->justReturn( 'https://example.com/wp-admin/update-core.php' );
		MonkeyFunctions\when( 'is_multisite' )->justReturn( false );
		MonkeyFunctions\when( 'is_network_admin' )->justReturn( false );
		MonkeyFunctions\when( 'is_plugin_active_for_network' )->justReturn( false );
		MonkeyFunctions\when( 'add_query_arg' )->returnArg( 2 );
		MonkeyFunctions\when( 'sanitize_title' )->returnArg( 1 );

		MonkeyFunctions\expect( 'wp_remote_post' )->once();

		$slack = new Slack_Available_Updates();
		$slack->send_report(
			array(
				array(
					'name'          => 'Akismet',
					'update_type'   => 'plugin',
					'from_version'  => '5.0',
					'to_version'    => '5.1',
					'update_status' => 'available',
				),
			)
		);
	}

	/**
	 * Send now rejects requests with no delivery channel.
	 */
	public function test_send_now_requires_recipients_or_slack(): void {
		$this->expectNotToPerformAssertions();
		MonkeyFunctions\when( 'check_ajax_referer' )->justReturn( true );
		MonkeyFunctions\when( 'is_multisite' )->justReturn( false );
		MonkeyFunctions\when( 'is_network_admin' )->justReturn( false );
		MonkeyFunctions\when( 'is_plugin_active_for_network' )->justReturn( false );
		MonkeyFunctions\when( 'current_user_can' )->justReturn( true );
		MonkeyFunctions\when( 'wp_parse_args' )->alias(
			static function ( $args, $defaults ) {
				return array_merge( (array) $defaults, (array) $args );
			}
		);
		MonkeyFunctions\when( 'get_site_option' )->justReturn( array() );
		MonkeyFunctions\expect( 'wp_send_json_error' )->once();

		$_POST = array(
			'toField'      => array(),
			'bccField'     => array(),
			'notifySlack'  => 'false',
		);

		$report = new Email_Updates_Available();
		$report->ajax_send_now();
	}

	/**
	 * Immediate report hook is disabled for available updates.
	 */
	public function test_maybe_send_immediate_report_is_noop(): void {
		$report = new Email_Updates_Available();
		$report->maybe_send_immediate_report(
			array(
				array( 'name' => 'Test' ),
			)
		);
		$this->assertTrue( true );
	}

	/**
	 * Slack available message includes headline text.
	 */
	public function test_slack_available_message_headline(): void {
		MonkeyFunctions\when( 'get_bloginfo' )->justReturn( 'Example' );
		MonkeyFunctions\when( 'home_url' )->justReturn( 'https://example.com/' );
		MonkeyFunctions\when( 'esc_url' )->returnArg( 1 );
		MonkeyFunctions\when( 'esc_html' )->returnArg( 1 );
		MonkeyFunctions\when( 'esc_url_raw' )->returnArg( 1 );
		MonkeyFunctions\when( 'admin_url' )->justReturn( 'https://example.com/wp-admin/update-core.php' );
		MonkeyFunctions\when( 'is_multisite' )->justReturn( false );
		MonkeyFunctions\when( 'is_network_admin' )->justReturn( false );
		MonkeyFunctions\when( 'is_plugin_active_for_network' )->justReturn( false );
		MonkeyFunctions\when( 'add_query_arg' )->returnArg( 2 );
		MonkeyFunctions\when( 'sanitize_title' )->returnArg( 1 );

		$slack   = new Slack_Available_Updates();
		$message = $slack->build_message(
			array(
				array(
					'name'         => 'Akismet',
					'update_type'  => 'plugin',
					'from_version' => '5.0',
					'to_version'   => '5.1',
					'detail_url'   => '',
					'detail_label' => '',
				),
			)
		);

		$this->assertStringContainsString( 'Updates available', $message['blocks'][0]['text']['text'] );
	}

	/**
	 * Refresh uses live plugin transients when capture copies were not stored.
	 */
	public function test_refresh_and_capture_falls_back_to_live_transients(): void {
		$plugins = (object) array(
			'response' => array(
				'hello.php' => (object) array(
					'new_version' => '2.0',
				),
			),
		);

		MonkeyFunctions\when( 'add_filter' )->justReturn( true );
		MonkeyFunctions\when( 'remove_filter' )->justReturn( true );
		MonkeyFunctions\when( 'delete_site_transient' )->justReturn( true );
		MonkeyFunctions\when( 'wp_version_check' )->justReturn( null );
		MonkeyFunctions\when( 'wp_update_plugins' )->justReturn( null );
		MonkeyFunctions\when( 'wp_update_themes' )->justReturn( null );
		MonkeyFunctions\when( 'get_site_transient' )->alias(
			function ( $key ) use ( $plugins ) {
				if ( in_array( $key, array( Available_Updates::PLUGIN_CAPTURE_TRANSIENT, Available_Updates::THEME_CAPTURE_TRANSIENT, Available_Updates::CORE_CAPTURE_TRANSIENT ), true ) ) {
					return false;
				}
				if ( 'update_plugins' === $key ) {
					return $plugins;
				}

				return false;
			}
		);

		$data = Available_Updates::refresh_and_capture();

		$this->assertSame( $plugins, $data['plugins'] );
	}
}
