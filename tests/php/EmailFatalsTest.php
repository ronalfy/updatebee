<?php
/**
 * Tests for immediate fatal emails.
 *
 * @package updatebee
 */

namespace UpdateBee\Tests;

use Brain\Monkey\Functions as MonkeyFunctions;
use UpdateBee\Emails\Email_Fatals;
use UpdateBee\Options;
use UpdateBee\Updates\Fatal_Detection;

/**
 * Fatal email test case.
 */
class EmailFatalsTest extends TestCase {

	/**
	 * Posted Slack request bodies.
	 *
	 * @var array<int, string>
	 */
	private array $posted_bodies = array();

	/**
	 * Reset caches.
	 */
	protected function tearDown(): void {
		Fatal_Detection::reset();
		Options::flush_cache();
		parent::tearDown();
	}

	/**
	 * Stub options helpers.
	 *
	 * @param array<string, mixed> $stored Stored option overrides.
	 */
	private function stub_options( array $stored = array() ): void {
		MonkeyFunctions\when( 'apply_filters' )->returnArg( 2 );
		MonkeyFunctions\when( 'wp_parse_args' )->alias(
			static function ( $args, $defaults ) {
				return array_merge( (array) $defaults, (array) $args );
			}
		);
		MonkeyFunctions\when( 'get_site_option' )->alias(
			static function ( $key, $fallback = false ) use ( $stored ) {
				if ( 'updatebee_options' === $key ) {
					return $stored;
				}
				if ( 'admin_email' === $key ) {
					return $stored['admin_email'] ?? 'admin@example.com';
				}

				return $fallback;
			}
		);
	}

	/**
	 * Stub helpers used to build a Slack fatal payload.
	 */
	private function stub_slack_message_helpers(): void {
		$this->posted_bodies = array();

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
	 * Fatal row used by Slack send tests.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function sample_fatal_updates(): array {
		return array(
			array(
				'name'          => 'Hello Dolly',
				'update_type'   => 'plugin',
				'from_version'  => '1.7.2',
				'to_version'    => '1.7.3',
				'update_status' => 'fatal',
				'fatal_message' => 'Error: Call to undefined function foo()',
			),
		);
	}

	/**
	 * Filter keeps only fatal rows.
	 */
	public function test_filter_fatal_updates(): void {
		$filtered = Email_Fatals::filter_fatal_updates(
			array(
				array(
					'name'          => 'Akismet',
					'update_status' => 'success',
				),
				array(
					'name'          => 'Hello Dolly',
					'update_status' => 'fatal',
				),
				array(
					'name'          => 'Broken',
					'update_status' => 'failure',
				),
			)
		);

		$this->assertCount( 1, $filtered );
		$this->assertSame( 'Hello Dolly', $filtered[0]['name'] );
	}

	/**
	 * Recipients fall back to the admin email.
	 */
	public function test_get_recipients_falls_back_to_admin_email(): void {
		$this->stub_options(
			array(
				'fatal_email_recipients' => array(),
			)
		);

		$this->assertSame( array( 'admin@example.com' ), Email_Fatals::get_recipients() );
	}

	/**
	 * Stored recipients are preferred.
	 */
	public function test_get_recipients_uses_stored_list(): void {
		$this->stub_options(
			array(
				'fatal_email_recipients' => array( 'ops@example.com' ),
			)
		);

		$this->assertSame( array( 'ops@example.com' ), Email_Fatals::get_recipients() );
	}

	/**
	 * Disabled detection never sends mail.
	 */
	public function test_maybe_send_skips_when_disabled(): void {
		$this->expectNotToPerformAssertions();
		$this->stub_options(
			array(
				'fatal_error_detection' => false,
			)
		);
		MonkeyFunctions\expect( 'wp_mail' )->never();

		$emails = new Email_Fatals();
		$emails->maybe_send_fatal_report(
			array(
				array(
					'name'          => 'Hello Dolly',
					'update_status' => 'fatal',
				),
			)
		);
	}

	/**
	 * Slack stays quiet when notify_slack is off even with a webhook.
	 */
	public function test_maybe_send_skips_slack_when_notify_off(): void {
		$this->stub_options(
			array(
				'fatal_error_detection'  => true,
				'fatal_notify_slack'     => false,
				'fatal_email_recipients' => array(),
				'admin_email'            => '',
				'slack_webhook_url'      => 'https://hooks.slack.com/services/T/B/X',
			)
		);
		MonkeyFunctions\expect( 'wp_mail' )->never();
		MonkeyFunctions\expect( 'wp_remote_post' )->never();

		$emails = new Email_Fatals();
		$emails->maybe_send_fatal_report( $this->sample_fatal_updates() );

		$this->assertSame( array(), $this->posted_bodies );
	}

	/**
	 * Slack posts a dedicated Fatals payload when notify is on and a webhook is saved.
	 */
	public function test_maybe_send_posts_slack_when_notify_on(): void {
		$this->stub_options(
			array(
				'fatal_error_detection'  => true,
				'fatal_notify_slack'     => true,
				'fatal_email_recipients' => array(),
				'admin_email'            => '',
				'slack_webhook_url'      => 'https://hooks.slack.com/services/T/B/X',
			)
		);
		$this->stub_slack_message_helpers();
		MonkeyFunctions\expect( 'wp_mail' )->never();

		$emails = new Email_Fatals();
		$emails->maybe_send_fatal_report( $this->sample_fatal_updates() );

		$this->assertCount( 1, $this->posted_bodies );
		$this->assertStringContainsString( 'Fatals', $this->posted_bodies[0] );
		$this->assertStringContainsString( 'Hello Dolly', $this->posted_bodies[0] );
		$this->assertStringContainsString( 'Fatal detection alert', $this->posted_bodies[0] );
	}

	/**
	 * A missing webhook skips Slack even when notify is on.
	 */
	public function test_maybe_send_skips_slack_when_webhook_missing(): void {
		$this->stub_options(
			array(
				'fatal_error_detection'  => true,
				'fatal_notify_slack'     => true,
				'fatal_email_recipients' => array(),
				'admin_email'            => '',
				'slack_webhook_url'      => '',
			)
		);
		MonkeyFunctions\expect( 'wp_mail' )->never();
		MonkeyFunctions\expect( 'wp_remote_post' )->never();

		$emails = new Email_Fatals();
		$emails->maybe_send_fatal_report( $this->sample_fatal_updates() );

		$this->assertSame( array(), $this->posted_bodies );
	}
}
