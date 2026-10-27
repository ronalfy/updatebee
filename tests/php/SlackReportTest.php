<?php
/**
 * Tests for Slack update reports.
 *
 * @package updatebee
 */

namespace UpdateBee\Tests;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions as MonkeyFunctions;
use ReflectionClass;
use UpdateBee\Logs;
use UpdateBee\Options;
use UpdateBee\Slack\Slack_Report;

/**
 * Slack report test case.
 */
class SlackReportTest extends TestCase {

	/**
	 * Posted request bodies.
	 *
	 * @var array<int, string>
	 */
	private array $posted_bodies = array();

	/**
	 * In-memory site options.
	 *
	 * @var array<string, mixed>
	 */
	private array $site_options = array();

	/**
	 * Reset option and log state.
	 */
	protected function tearDown(): void {
		Options::flush_cache();
		$this->set_current_updates( array() );
		parent::tearDown();
	}

	/**
	 * Stub helpers used while building and sending a report.
	 *
	 * @param string $webhook Saved webhook URL.
	 */
	private function stub_helpers( string $webhook = 'https://hooks.slack.com/services/T/B/X' ): void {
		$this->posted_bodies                     = array();
		$this->site_options['updatebee_options'] = array(
			'slack_webhook_url' => $webhook,
			'slack_enabled'     => true,
		);

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
		MonkeyFunctions\when( 'get_site_option' )->alias(
			function ( $key, $fallback = false ) {
				return array_key_exists( $key, $this->site_options ) ? $this->site_options[ $key ] : $fallback;
			}
		);
		MonkeyFunctions\when( 'get_bloginfo' )->justReturn( 'Example Site' );
		MonkeyFunctions\when( 'home_url' )->justReturn( 'https://example.com/' );
		MonkeyFunctions\when( 'esc_url' )->returnArg();
		MonkeyFunctions\when( 'esc_html' )->returnArg();
		MonkeyFunctions\when( 'esc_url_raw' )->returnArg();
		MonkeyFunctions\when( 'wp_json_encode' )->alias(
			static function ( $data ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Test double for wp_json_encode().
				return (string) json_encode( $data );
			}
		);
		MonkeyFunctions\when( 'admin_url' )->alias(
			static function ( $path = '' ) {
				return 'https://example.com/wp-admin/' . $path;
			}
		);
		MonkeyFunctions\when( 'add_query_arg' )->alias(
			static function ( $args, $url ) {
				$tab = is_array( $args ) && isset( $args['tab'] ) ? (string) $args['tab'] : '';
				return $url . '&tab=' . $tab;
			}
		);
		MonkeyFunctions\when( 'sanitize_title' )->returnArg();
		MonkeyFunctions\when( 'is_multisite' )->justReturn( false );
		MonkeyFunctions\when( 'is_network_admin' )->justReturn( false );
		MonkeyFunctions\when( 'is_plugin_active_for_network' )->justReturn( false );
		MonkeyFunctions\when( '_n' )->alias(
			static function ( $single, $plural, $number ) {
				unset( $plural );
				return sprintf( $single, $number );
			}
		);
		MonkeyFunctions\when( 'get_userdata' )->alias(
			static function () {
				$user               = new \stdClass();
				$user->display_name = 'Ada Lovelace';
				return $user;
			}
		);
		MonkeyFunctions\when( 'wp_remote_post' )->alias(
			function ( $url, $args = array() ) {
				unset( $url );
				$this->posted_bodies[] = isset( $args['body'] ) ? (string) $args['body'] : '';
				return array();
			}
		);
	}

	/**
	 * Sample plugin and core rows.
	 *
	 * @param string $offer automatic|manual.
	 * @return array<int, array<string, mixed>>
	 */
	private function sample_rows( string $offer = 'automatic' ): array {
		return array(
			array(
				'name'          => 'Akismet',
				'update_type'   => 'plugin',
				'from_version'  => '5.0',
				'to_version'    => '5.1',
				'update_status' => 'success',
				'update_offer'  => $offer,
				'user_id'       => 'manual' === $offer ? 4 : 0,
			),
			array(
				'name'          => 'WordPress',
				'update_type'   => 'core',
				'from_version'  => '6.8',
				'to_version'    => '6.8.1',
				'update_status' => 'failure',
				'update_offer'  => $offer,
				'user_id'       => 'manual' === $offer ? 4 : 0,
			),
		);
	}

	/**
	 * Replace the private in-memory log batch.
	 *
	 * @param array<int, array<string, mixed>> $updates Log rows.
	 */
	private function set_current_updates( array $updates ): void {
		$property = ( new ReflectionClass( Logs::class ) )->getProperty( 'current_updates' );
		$property->setAccessible( true );
		$property->setValue( null, $updates );
	}

	/**
	 * Set whether the logs instance is inside an automatic update.
	 *
	 * @param Logs $logs  Logs instance.
	 * @param bool $auto  True for an automatic update request.
	 */
	private function set_auto_update( Logs $logs, bool $auto ): void {
		$property = ( new ReflectionClass( Logs::class ) )->getProperty( 'is_auto_update' );
		$property->setAccessible( true );
		$property->setValue( $logs, $auto );
	}

	/**
	 * The payload includes the headline, fields, type sections, and logs button.
	 */
	public function test_build_message_includes_report_sections(): void {
		$this->stub_helpers();
		$report  = new Slack_Report();
		$message = $report->build_message(
			array(
				array(
					'name'          => 'Akismet',
					'update_type'   => 'plugin',
					'from_version'  => '5.0',
					'to_version'    => '5.1',
					'update_status' => 'success',
					'user_id'       => '4',
				),
				array(
					'name'          => 'WordPress',
					'update_type'   => 'core',
					'from_version'  => '6.8',
					'to_version'    => '6.8.1',
					'update_status' => 'failure',
					'user_id'       => '4',
				),
			),
			'manual'
		);

		$this->assertStringContainsString( 'Example Site', $message['text'] );
		$this->assertStringContainsString( 'Manual', $message['text'] );

		$blocks = $message['blocks'];
		$this->assertSame( '*Updates finished on <https://example.com/|Example Site>*', $blocks[0]['text']['text'] );
		$this->assertSame( 'Update details', $blocks[1]['text']['text'] );
		$this->assertSame( '*Site:* Example Site', $blocks[3]['fields'][0]['text'] );
		$this->assertSame( '*Trigger:* Manual', $blocks[3]['fields'][1]['text'] );
		$this->assertSame( '*Succeeded:* 1', $blocks[3]['fields'][2]['text'] );
		$this->assertSame( '*Failed:* 1', $blocks[3]['fields'][3]['text'] );
		$this->assertSame( '*User:* Ada Lovelace', $blocks[3]['fields'][4]['text'] );
		$this->assertSame( 'WordPress core', $blocks[5]['text']['text'] );
		$this->assertSame( 'Plugins', $blocks[8]['text']['text'] );
		$this->assertSame( '*Akismet* — success — 5.0 → 5.1', $blocks[9]['text']['text'] );

		$button = $blocks[11]['elements'][0];
		$this->assertSame( 'view_logs', $button['action_id'] );
		$this->assertStringContainsString( 'tab=logs', $button['url'] );
	}

	/**
	 * An empty webhook does not post.
	 */
	public function test_empty_webhook_skips_post(): void {
		$this->stub_helpers( '' );
		MonkeyFunctions\expect( 'wp_remote_post' )->never();

		$report = new Slack_Report();
		$report->send_report( $this->sample_rows(), 'automatic' );

		$this->assertSame( array(), $this->posted_bodies );
	}

	/**
	 * A disabled Slack toggle skips the post even when a webhook is saved.
	 */
	public function test_slack_disabled_skips_post(): void {
		$this->stub_helpers();
		$this->site_options['updatebee_options']['slack_enabled'] = false;
		MonkeyFunctions\expect( 'wp_remote_post' )->never();

		$report = new Slack_Report();
		$report->send_report( $this->sample_rows(), 'automatic' );

		$this->assertSame( array(), $this->posted_bodies );
	}

	/**
	 * Each completion action posts once.
	 */
	public function test_completion_actions_post_once(): void {
		$this->stub_helpers();

		$report = new Slack_Report();
		$report->send_automatic_report( $this->sample_rows( 'automatic' ) );
		$report->send_manual_report( $this->sample_rows( 'manual' ) );

		$this->assertCount( 2, $this->posted_bodies );
		$this->assertStringContainsString( 'Automatic', $this->posted_bodies[0] );
		$this->assertStringContainsString( 'Manual', $this->posted_bodies[1] );
		$this->assertStringContainsString( 'WordPress core', $this->posted_bodies[1] );
		$this->assertStringContainsString( 'Update details', $this->posted_bodies[0] );
	}

	/**
	 * Run registers both completion hooks.
	 */
	public function test_run_registers_completion_hooks(): void {
		$this->expectNotToPerformAssertions();
		Actions\expectAdded( 'updatebee_automatic_updates_complete' )->once();
		Actions\expectAdded( 'updatebee_manual_updates_complete' )->once();

		$report = new Slack_Report();
		$report->run();
	}

	/**
	 * Manual shutdown fires one completion action with the in-memory rows.
	 */
	public function test_manual_shutdown_fires_completion_action(): void {
		$this->expectNotToPerformAssertions();
		$rows = $this->sample_rows( 'manual' );
		$this->set_current_updates( $rows );

		$logs = new Logs();
		$this->set_auto_update( $logs, false );

		MonkeyFunctions\expect( 'do_action' )
			->once()
			->with( 'updatebee_manual_updates_complete', $rows );

		$logs->perform_shutdown_task();
	}

	/**
	 * Automatic requests do not emit the manual completion action.
	 */
	public function test_automatic_shutdown_skips_manual_action(): void {
		$this->expectNotToPerformAssertions();
		$this->set_current_updates( $this->sample_rows( 'automatic' ) );

		$logs = new Logs();
		$this->set_auto_update( $logs, true );

		MonkeyFunctions\expect( 'do_action' )->never();

		$logs->perform_shutdown_task();
	}
}
