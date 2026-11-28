<?php
/**
 * Tests for settings import and export.
 *
 * @package updatebee
 */

namespace UpdateBee\Tests;

use Brain\Monkey\Functions as MonkeyFunctions;
use UpdateBee\Admin\Settings_Transfer;
use UpdateBee\Branding;
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
		Branding::flush_cache();
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
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Unit tests run without WordPress loaded.
		$encoded = json_encode( $document );

		$this->assertIsArray( $document['options'] );
		$this->assertArrayNotHasKey( 'slack_webhook_url', $document['options'] );
		$this->assertSame( 4, $document['options']['delay_updates'] );
		$this->assertIsString( $encoded );
		$this->assertStringNotContainsString( 'hooks.slack.com', $encoded );
		$this->assertStringNotContainsString( 'slack_webhook_url', $encoded );
	}

	/**
	 * Export omits the Discord webhook even when one is stored.
	 */
	public function test_export_omits_discord_webhook(): void {
		$webhook = 'https://discord.com/api/webhooks/123456789012345678/abcdefghijklmnopqrstuvwxyz';
		$this->stub_export_environment(
			array(
				'discord_webhook_url' => $webhook,
				'discord_enabled'     => true,
				'delay_updates'       => 4,
			)
		);

		$document = Settings_Transfer::build_document();
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Unit tests run without WordPress loaded.
		$encoded = json_encode( $document );

		$this->assertIsArray( $document['options'] );
		$this->assertArrayNotHasKey( 'discord_webhook_url', $document['options'] );
		$this->assertTrue( $document['options']['discord_enabled'] );
		$this->assertIsString( $encoded );
		$this->assertStringNotContainsString( 'discord.com/api/webhooks', $encoded );
		$this->assertStringNotContainsString( 'discord_webhook_url', $encoded );
	}

	/**
	 * Import leaves a stored Discord webhook unchanged.
	 */
	public function test_import_preserves_discord_webhook(): void {
		$webhook = 'https://discord.com/api/webhooks/123456789012345678/abcdefghijklmnopqrstuvwxyz';
		$saved   = $this->stub_option_store(
			array(
				'discord_webhook_url' => $webhook,
				'delay_updates'       => 1,
			)
		);

		$result = Settings_Transfer::import_json(
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Unit tests run without WordPress loaded.
			(string) json_encode(
				array(
					'format'  => 'updatebee-settings',
					'version' => 1,
					'options' => array(
						'delay_updates'       => 9,
						'discord_webhook_url' => 'https://discord.com/api/webhooks/999/evil-token',
						'discord_enabled'     => true,
					),
				)
			)
		);

		$this->assertTrue( $result );
		$this->assertIsArray( $saved->value );
		$this->assertSame( $webhook, $saved->value['discord_webhook_url'] );
		$this->assertTrue( $saved->value['discord_enabled'] );
		$this->assertSame( 9, $saved->value['delay_updates'] );
	}

	/**
	 * A valid file updates an allowlisted option and leaves the webhook unchanged.
	 */
	public function test_import_updates_allowlisted_option_and_preserves_webhook(): void {
		MonkeyFunctions\when( 'sanitize_key' )->returnArg( 1 );

		$saved = $this->stub_option_store(
			array(
				'slack_webhook_url' => self::WEBHOOK,
				'delay_updates'     => 1,
			)
		);

		$result = Settings_Transfer::import_json(
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Unit tests run without WordPress loaded.
			(string) json_encode(
				array(
					'format'  => 'updatebee-settings',
					'version' => 1,
					'options' => array(
						'delay_updates'     => 9,
						'hidden_features'   => array( 'upcoming', 'webhooks', 'tools' ),
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
		$this->assertSame( array( 'upcoming', 'webhooks', 'tools' ), $saved->value['hidden_features'] );
		$this->assertArrayNotHasKey( 'not_a_real_key', $saved->value );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Unit tests run without WordPress loaded.
		$this->assertStringNotContainsString( 'evil', (string) json_encode( $saved->value ) );
	}

	/**
	 * Unlocked export includes branding stored with the main options.
	 */
	public function test_export_includes_branding_when_unlocked(): void {
		$this->stub_export_environment(
			array(
				'delay_updates'              => 4,
				'branding_name'              => 'Stored Agency',
				'branding_hide_header_links' => true,
			)
		);

		$document = Settings_Transfer::build_document();

		$this->assertIsArray( $document['options'] );
		$this->assertSame( 'Stored Agency', $document['options']['branding_name'] );
		$this->assertTrue( $document['options']['branding_hide_header_links'] );
	}

	/**
	 * A lock omits branding from the export document.
	 */
	public function test_export_omits_branding_when_locked(): void {
		$this->stub_export_environment(
			array(
				'delay_updates' => 4,
				'branding_name' => 'Stored Agency',
			)
		);
		$this->force_branding_lock();

		$document = Settings_Transfer::build_document();

		$this->assertIsArray( $document['options'] );
		$this->assertArrayNotHasKey( 'branding_name', $document['options'] );
		$this->assertArrayNotHasKey( 'branding_hide_license_tab', $document['options'] );
		$this->assertArrayNotHasKey( 'branding_hide_header_links', $document['options'] );
		$this->assertSame( 4, $document['options']['delay_updates'] );
	}

	/**
	 * Unlocked import writes branding to its own option.
	 */
	public function test_import_updates_branding_when_unlocked(): void {
		$state = $this->stub_split_store(
			array(
				'slack_webhook_url' => self::WEBHOOK,
				'delay_updates'     => 1,
			),
			$this->empty_branding()
		);

		$result = Settings_Transfer::import_json( $this->branding_import_json( 'Imported Agency', 6 ) );

		$this->assertTrue( $result );
		$this->assertSame( 6, $state->main['delay_updates'] );
		$this->assertSame( 'Imported Agency', $state->branding['branding_name'] );
	}

	/**
	 * A lock ignores branding keys in an otherwise valid import.
	 */
	public function test_import_skips_branding_when_locked(): void {
		$state = $this->stub_split_store(
			array(
				'slack_webhook_url' => self::WEBHOOK,
				'delay_updates'     => 1,
			),
			array_merge(
				$this->empty_branding(),
				array(
					'branding_name' => 'Stored Agency',
				)
			)
		);
		$this->force_branding_lock();

		$result = Settings_Transfer::import_json( $this->branding_import_json( 'Imported Agency', 6 ) );

		$this->assertTrue( $result );
		$this->assertSame( 6, $state->main['delay_updates'] );
		$this->assertSame( 'Stored Agency', $state->branding['branding_name'] );
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
	 * Force an active license and the branding lock.
	 */
	private function force_branding_lock(): void {
		Branding::set_license_active_override( true );
		MonkeyFunctions\when( 'apply_filters' )->alias(
			static function ( $hook, $value ) {
				if ( 'updatebee_branding_locked' === $hook ) {
					return true;
				}

				return $value;
			}
		);
	}

	/**
	 * Empty saved branding.
	 *
	 * @return array<string, mixed>
	 */
	private function empty_branding(): array {
		return array(
			'branding_name'              => '',
			'branding_description'       => '',
			'branding_author'            => '',
			'branding_url'               => '',
			'branding_hide_license_tab'  => false,
			'branding_hide_header_links' => false,
		);
	}

	/**
	 * Settings JSON that changes the delay and the branded name.
	 *
	 * @param string $name  Branded name.
	 * @param int    $delay Delay in days.
	 * @return string JSON document.
	 */
	private function branding_import_json( string $name, int $delay ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Unit tests run without WordPress loaded.
		return (string) json_encode(
			array(
				'format'  => 'updatebee-settings',
				'version' => 1,
				'options' => array(
					'delay_updates' => $delay,
					'branding_name' => $name,
				),
			)
		);
	}

	/**
	 * Stub the main options and the branding option as separate stores.
	 *
	 * @param array<string, mixed> $main     Stored main options.
	 * @param array<string, mixed> $branding Stored branding.
	 * @return \stdClass State with main and branding properties.
	 */
	private function stub_split_store( array $main, array $branding ): \stdClass {
		$state           = new \stdClass();
		$state->main     = $main;
		$state->branding = $branding;

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
		MonkeyFunctions\when( 'sanitize_text_field' )->alias(
			static function ( $value ) {
				return is_string( $value ) ? trim( $value ) : '';
			}
		);
		MonkeyFunctions\when( 'get_site_option' )->alias(
			static function ( $key, $fallback = array() ) use ( $state ) {
				if ( 'updatebee_options' === $key ) {
					return $state->main;
				}
				if ( Branding::OPTION_KEY === $key ) {
					return $state->branding;
				}

				return $fallback;
			}
		);
		MonkeyFunctions\when( 'update_site_option' )->alias(
			static function ( $key, $value ) use ( $state ) {
				if ( 'updatebee_options' === $key && is_array( $value ) ) {
					$state->main = $value;
				}
				if ( Branding::OPTION_KEY === $key && is_array( $value ) ) {
					$state->branding = $value;
				}

				return true;
			}
		);

		return $state;
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
			static function ( $key, $fallback = array() ) use ( &$stored_options ) {
				if ( 'updatebee_options' === $key ) {
					return $stored_options;
				}
				return $fallback;
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
			static function ( $key, $fallback = array() ) use ( $stored ) {
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
				return $fallback;
			}
		);

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test double for $wpdb.
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
