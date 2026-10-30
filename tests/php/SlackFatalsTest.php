<?php
/**
 * Tests for dedicated Slack fatal alerts.
 *
 * @package updatebee
 */

namespace UpdateBee\Tests;

use Brain\Monkey\Functions as MonkeyFunctions;
use UpdateBee\Options;
use UpdateBee\Slack\Slack_Fatals;

/**
 * Slack fatal report test case.
 */
class SlackFatalsTest extends TestCase {

	/**
	 * Posted request bodies.
	 *
	 * @var array<int, string>
	 */
	private array $posted_bodies = array();

	/**
	 * Reset option cache.
	 */
	protected function tearDown(): void {
		Options::flush_cache();
		parent::tearDown();
	}

	/**
	 * Stub helpers used while building and sending a report.
	 *
	 * @param string $webhook Saved webhook URL.
	 */
	private function stub_helpers( string $webhook = 'https://hooks.slack.com/services/T/B/X' ): void {
		$this->posted_bodies = array();

		MonkeyFunctions\when( 'apply_filters' )->returnArg( 2 );
		MonkeyFunctions\when( 'wp_parse_args' )->alias(
			static function ( $args, $defaults ) {
				return array_merge( (array) $defaults, (array) $args );
			}
		);
		MonkeyFunctions\when( 'get_site_option' )->alias(
			static function ( $key, $fallback = false ) use ( $webhook ) {
				if ( 'updatebee_options' === $key ) {
					return array(
						'slack_webhook_url' => $webhook,
					);
				}

				return $fallback;
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
		MonkeyFunctions\when( 'wp_remote_post' )->alias(
			function ( $url, $args = array() ) {
				unset( $url );
				$this->posted_bodies[] = isset( $args['body'] ) ? (string) $args['body'] : '';
				return array();
			}
		);
	}

	/**
	 * Sample fatal row.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function sample_fatal_rows(): array {
		return array(
			array(
				'name'          => 'Hello Dolly',
				'update_type'   => 'plugin',
				'from_version'  => '1.7.2',
				'to_version'    => '1.7.3',
				'update_status' => 'fatal',
				'fatal_message' => 'Error: Call to undefined function foo()',
			),
			array(
				'name'          => 'Akismet',
				'update_type'   => 'plugin',
				'from_version'  => '5.0',
				'to_version'    => '5.1',
				'update_status' => 'success',
			),
		);
	}

	/**
	 * The payload includes the Fatals headline, site, items, and logs button.
	 */
	public function test_build_message_includes_fatals_headline(): void {
		$this->stub_helpers();
		$slack   = new Slack_Fatals();
		$message = $slack->build_message(
			array(
				array(
					'name'          => 'Hello Dolly',
					'update_type'   => 'plugin',
					'from_version'  => '1.7.2',
					'to_version'    => '1.7.3',
					'fatal_message' => 'Error: Call to undefined function foo()',
					'update_status' => 'fatal',
				),
			)
		);

		$this->assertStringContainsString( 'Fatal detection alert', $message['text'] );
		$this->assertStringContainsString( 'Example Site', $message['text'] );

		$blocks = $message['blocks'];
		$this->assertSame( '*Fatal error after automatic updates on <https://example.com/|Example Site>*', $blocks[0]['text']['text'] );
		$this->assertSame( 'Fatals', $blocks[1]['text']['text'] );
		$this->assertSame( '*Site:* Example Site', $blocks[3]['fields'][0]['text'] );
		$this->assertSame( '*Trigger:* Automatic', $blocks[3]['fields'][1]['text'] );
		$this->assertSame( '*Fatals:* 1', $blocks[3]['fields'][2]['text'] );
		$this->assertStringContainsString( 'This is a fatal detection alert', $blocks[4]['text']['text'] );
		$this->assertStringContainsString( '*Hello Dolly* — Plugins — 1.7.2 → 1.7.3', $blocks[6]['text']['text'] );
		$this->assertStringContainsString( 'Call to undefined function foo()', $blocks[6]['text']['text'] );

		$button = $blocks[8]['elements'][0];
		$this->assertSame( 'view_logs', $button['action_id'] );
		$this->assertSame( 'View logs', $button['text']['text'] );
		$this->assertStringContainsString( 'tab=logs', $button['url'] );
	}

	/**
	 * Sending posts a dedicated payload and skips non-fatal rows.
	 */
	public function test_send_report_posts_fatals_headline(): void {
		$this->stub_helpers();

		$slack = new Slack_Fatals();
		$slack->send_report( $this->sample_fatal_rows() );

		$this->assertCount( 1, $this->posted_bodies );
		$this->assertStringContainsString( 'Fatals', $this->posted_bodies[0] );
		$this->assertStringContainsString( 'Hello Dolly', $this->posted_bodies[0] );
		$this->assertStringContainsString( 'Call to undefined function foo()', $this->posted_bodies[0] );
		$this->assertStringNotContainsString( 'Akismet', $this->posted_bodies[0] );
	}

	/**
	 * Raw notes fall back to the first line when fatal_message is missing.
	 */
	public function test_send_report_uses_raw_first_line(): void {
		$this->stub_helpers();

		$slack = new Slack_Fatals();
		$slack->send_report(
			array(
				array(
					'name'          => 'Broken Plugin',
					'update_type'   => 'plugin',
					'from_version'  => '1.0',
					'to_version'    => '1.1',
					'update_status' => 'fatal',
					'raw'           => "Error: Allowed memory size exhausted\nFile: /tmp/plugin.php\n",
				),
			)
		);

		$this->assertCount( 1, $this->posted_bodies );
		$this->assertStringContainsString( 'Allowed memory size exhausted', $this->posted_bodies[0] );
		$this->assertStringNotContainsString( 'File: /tmp/plugin.php', $this->posted_bodies[0] );
	}

	/**
	 * An empty webhook does not post.
	 */
	public function test_empty_webhook_skips_post(): void {
		$this->stub_helpers( '' );
		MonkeyFunctions\expect( 'wp_remote_post' )->never();

		$slack = new Slack_Fatals();
		$slack->send_report( $this->sample_fatal_rows() );

		$this->assertSame( array(), $this->posted_bodies );
	}
}
