<?php
/**
 * Tests for settings import and export.
 *
 * @package updatebee
 */

namespace UpdateBee\Tests;

use Brain\Monkey\Functions as MonkeyFunctions;
use UpdateBee\Admin\Settings_Transfer;
use UpdateBee\Options;

/**
 * Settings transfer test case.
 */
class SettingsTransferTest extends TestCase {

	/**
	 * Slack webhook stored on the site and omitted from transfers.
	 */
	private const WEBHOOK = 'https://hooks.slack.com/services/T00/B00/secret';

	/**
	 * Clear the options cache before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		Options::flush_cache();
	}

	/**
	 * Clear the options cache after each test.
	 */
	protected function tearDown(): void {
		Options::flush_cache();
		parent::tearDown();
	}

	/**
	 * Export omits the Slack webhook even when one is stored.
	 */
	public function test_export_omits_slack_webhook(): void {
		$this->stub_export_environment(
			array(
				'slack_webhook_url' => self::WEBHOOK,
				'delay_updates'     => 4,
			)
		);

		$document = Settings_Transfer::build_document();
		$encoded  = json_encode( $document );

		$this->assertIsArray( $document['options'] );
		$this->assertArrayNotHasKey( 'slack_webhook_url', $document['options'] );
		$this->assertSame( 4, $document['options']['delay_updates'] );
		$this->assertIsString( $encoded );
		$this->assertStringNotContainsString( 'hooks.slack.com', $encoded );
		$this->assertStringNotContainsString( 'slack_webhook_url', $encoded );
	}

	/**
	 * A valid file updates an allowlisted option and leaves the webhook unchanged.
	 */
	public function test_import_updates_allowlisted_option_and_preserves_webhook(): void {
		$saved = $this->stub_option_store(
			array(
				'slack_webhook_url' => self::WEBHOOK,
				'delay_updates'     => 1,
			)
		);

		$result = Settings_Transfer::import_json(
			(string) json_encode(
				array(
					'format'  => 'updatebee-settings',
					'version' => 1,
					'options' => array(
						'delay_updates'     => 9,
						'slack_webhook_url' => 'https://hooks.slack.com/services/T00/B00/evil',
						'not_a_real_key'    => true,
					),
				)
			)
		);

		$this->assertTrue( $result );
		$this->assertIsArray( $saved->value );
		$this->assertSame( self::WEBHOOK, $saved->value['slack_webhook_url'] );
		$this->assertSame( 9, $saved->value['delay_updates'] );
		$this->assertArrayNotHasKey( 'not_a_real_key', $saved->value );
		$this->assertStringNotContainsString( 'evil', (string) json_encode( $saved->value ) );
	}

	/**
	 * Malformed documents are rejected and do not write settings.
	 *
	 * @dataProvider rejected_document_provider
	 *
	 * @param string $raw JSON or other file body.
	 */
	public function test_import_rejects_malformed_documents_without_writing( string $raw ): void {
		$wrote = false;
		MonkeyFunctions\when( 'update_site_option' )->alias(
			static function () use ( &$wrote ) {
				$wrote = true;
				return true;
			}
		);

		$result = Settings_Transfer::import_json( $raw );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertFalse( $wrote );
	}

	/**
	 * Documents that must be rejected before any write.
	 *
	 * @return array<string, array<int, string>> Case name => file body.
	 */
	public static function rejected_document_provider(): array {
		return array(
			'php serialized'   => array( 'a:1:{s:3:"foo";s:3:"bar";}' ),
			'invalid utf-8'    => array( "{\"format\":\"updatebee-settings\",\"version\":1,\"options\":{\"branding_name\":\"\xFF\"}}" ),
			'excessive depth'  => array( self::deeply_nested_document() ),
			'wrong format'     => array( '{"format":"not-updatebee","version":1}' ),
			'wrong version'    => array( '{"format":"updatebee-settings","version":2}' ),
			'wrong json type'  => array( '{"format":"updatebee-settings","version":1,"options":{"delay_updates":"3"}}' ),
			'plugin traversal' => array( '{"format":"updatebee-settings","version":1,"plugins":{"../wp-config.php":{"notes":"nope"}}}' ),
		);
	}

	/**
	 * Build a JSON document nested deeper than the parser allows.
	 *
	 * @return string JSON document.
	 */
	private static function deeply_nested_document(): string {
		$nested = '0';
		for ( $level = 0; $level < 12; $level++ ) {
			$nested = '{"level":' . $nested . '}';
		}

		return '{"format":"updatebee-settings","version":1,"options":' . $nested . '}';
	}

	/**
	 * Stub option reads and capture the array written for the main options.
	 *
	 * @param array<string, mixed> $stored Stored option overrides.
	 * @return \stdClass Saved options container. The value property is the written array.
	 */
	private function stub_option_store( array $stored ): \stdClass {
		$saved          = new \stdClass();
		$saved->value   = null;
		$stored_options = $stored;

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
			static function ( $key, $default = array() ) use ( &$stored_options ) {
				if ( 'updatebee_options' === $key ) {
					return $stored_options;
				}
				return $default;
			}
		);
		MonkeyFunctions\when( 'update_site_option' )->alias(
			static function ( $key, $value ) use ( &$stored_options, &$saved ) {
				if ( 'updatebee_options' === $key && is_array( $value ) ) {
					$stored_options = $value;
					$saved->value   = $value;
				}
				return true;
			}
		);

		return $saved;
	}

	/**
	 * Stub the reads used while building an export document.
	 *
	 * @param array<string, mixed> $stored Stored main options.
	 */
	private function stub_export_environment( array $stored ): void {
		$this->stub_option_store( $stored );
		MonkeyFunctions\when( 'absint' )->alias(
			static function ( $value ) {
				return abs( (int) $value );
			}
		);
		MonkeyFunctions\when( 'sanitize_text_field' )->returnArg( 1 );
		MonkeyFunctions\when( 'sanitize_textarea_field' )->returnArg( 1 );
		MonkeyFunctions\when( 'get_site_option' )->alias(
			static function ( $key, $default = array() ) use ( $stored ) {
				if ( 'updatebee_options' === $key ) {
					return $stored;
				}
				if ( in_array( $key, array( 'updatebee_plugin_cron_schedule', 'updatebee_theme_cron_schedule', 'updatebee_core_cron_schedule' ), true ) ) {
					return array(
						'schedule'   => 'twelve-hours',
						'day'        => 'sunday',
						'week'       => '1',
						'start_time' => '00:00',
						'day_num'    => '1',
						'days_list'  => array( 1, 2, 3, 4, 5, 6, 7 ),
					);
				}
				return $default;
			}
		);

		$GLOBALS['wpdb'] = new class() {
			/**
			 * Table prefix.
			 *
			 * @var string
			 */
			public $base_prefix = 'wp_';

			/**
			 * Return no rows.
			 *
			 * @param string $query  SQL query.
			 * @param string $output Return format.
			 * @return array<int, mixed> Empty result set.
			 */
			public function get_results( $query, $output = '' ) {
				unset( $query, $output );
				return array();
			}
		};
	}
}
