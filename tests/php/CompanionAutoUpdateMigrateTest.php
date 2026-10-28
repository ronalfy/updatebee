<?php
/**
 * Tests for Companion Auto Update option migration.
 *
 * @package updatebee
 */

namespace UpdateBee\Tests;

use Brain\Monkey\Functions as MonkeyFunctions;
use UpdateBee\Admin\Migrate\CompanionAutoUpdate;
use UpdateBee\Admin\Templates\Tools;
use UpdateBee\Cron\Updates_Cron;
use UpdateBee\Emails\Email_Successes;
use UpdateBee\Emails\Email_Updates_Available;
use UpdateBee\Options;

/**
 * CompanionAutoUpdate migration test case.
 */
class CompanionAutoUpdateMigrateTest extends TestCase {

	/**
	 * In-memory site options.
	 *
	 * @var array<string, mixed>
	 */
	private array $site_options = array();

	/**
	 * Reset caches after each test.
	 */
	protected function tearDown(): void {
		Options::flush_cache();
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	/**
	 * Stub helpers used by mapping methods.
	 */
	private function stub_map_helpers(): void {
		MonkeyFunctions\when( 'apply_filters' )->returnArg( 2 );
		MonkeyFunctions\when( 'sanitize_text_field' )->returnArg( 1 );
		MonkeyFunctions\when( 'sanitize_title' )->alias(
			static function ( $value ) {
				$value = strtolower( (string) $value );
				$value = preg_replace( '/[^a-z0-9]+/', '-', $value );
				return trim( (string) $value, '-' );
			}
		);
		MonkeyFunctions\when( 'absint' )->alias(
			static function ( $value ) {
				return abs( (int) $value );
			}
		);
		MonkeyFunctions\when( 'wp_parse_args' )->alias(
			static function ( $args, $defaults ) {
				return array_merge( (array) $defaults, (array) $args );
			}
		);
		MonkeyFunctions\when( 'get_plugins' )->justReturn(
			array(
				'akismet/akismet.php' => array(
					'Name' => 'Akismet',
				),
				'hello.php'           => array(
					'Name' => 'Hello Dolly',
				),
			)
		);
		MonkeyFunctions\when( 'wp_date' )->alias(
			static function ( $format, $timestamp = null ) {
				$timestamp = null === $timestamp ? time() : (int) $timestamp;
				return gmdate( (string) $format, $timestamp );
			}
		);
	}

	/**
	 * Stub helpers used by run().
	 *
	 * @param bool $plugin_active Whether Companion Auto Update is active.
	 */
	private function stub_run_helpers( bool $plugin_active = false ): void {
		$this->stub_map_helpers();

		MonkeyFunctions\when( 'get_site_option' )->alias(
			function ( $key, $default_value = false ) {
				return array_key_exists( $key, $this->site_options ) ? $this->site_options[ $key ] : $default_value;
			}
		);
		MonkeyFunctions\when( 'update_site_option' )->alias(
			function ( $key, $value ) {
				$this->site_options[ $key ] = $value;
				return true;
			}
		);
		MonkeyFunctions\when( 'get_option' )->alias(
			static function ( $key, $default_value = false ) {
				if ( 'gmt_offset' === $key ) {
					return 0;
				}
				if ( 'admin_email' === $key ) {
					return 'owner@example.com';
				}
				return $default_value;
			}
		);
		MonkeyFunctions\when( 'wp_clear_scheduled_hook' )->justReturn( true );
		MonkeyFunctions\when( 'wp_schedule_event' )->justReturn( true );
		MonkeyFunctions\when( 'get_date_from_gmt' )->alias(
			static function ( $date_string, $format = 'Y-m-d H:i:s' ) {
				$timestamp = strtotime( (string) $date_string . ' +0000' );
				if ( false === $timestamp ) {
					$timestamp = time();
				}
				return gmdate( (string) $format, $timestamp );
			}
		);
		MonkeyFunctions\when( 'delete_site_option' )->alias(
			function ( $key ) {
				unset( $this->site_options[ $key ] );
				return true;
			}
		);
		MonkeyFunctions\when( 'is_plugin_active' )->justReturn( $plugin_active );
		MonkeyFunctions\when( 'wp_create_nonce' )->alias(
			static function ( $action ) {
				return $action . '-nonce';
			}
		);
	}

	/**
	 * Install a fake Companion Auto Update settings table.
	 *
	 * @param array<int, array<string, string>> $rows         Table rows.
	 * @param bool                              $table_exists Whether the table exists.
	 */
	private function mock_settings_table( array $rows, bool $table_exists = true ): void {
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Mocking WPDB for unit tests.
		$GLOBALS['wpdb'] = new class( $rows, $table_exists ) {
			/**
			 * Current site prefix.
			 *
			 * @var string
			 */
			public $prefix = 'wp_';

			/**
			 * Stored rows.
			 *
			 * @var array<int, array<string, string>>
			 */
			private $rows;

			/**
			 * Whether SHOW TABLES should succeed.
			 *
			 * @var bool
			 */
			private $table_exists;

			/**
			 * Constructor.
			 *
			 * @param array<int, array<string, string>> $rows         Table rows.
			 * @param bool                              $table_exists Whether the table exists.
			 */
			public function __construct( array $rows, bool $table_exists ) {
				$this->rows         = $rows;
				$this->table_exists = $table_exists;
			}

			/**
			 * Pass the table name through unchanged.
			 *
			 * @param string $text Text to escape.
			 * @return string
			 */
			public function esc_like( $text ) {
				return $text;
			}

			/**
			 * Return the query unchanged.
			 *
			 * @param string $query SQL query.
			 * @param mixed  ...$args Query arguments.
			 * @return string
			 */
			public function prepare( $query, ...$args ) {
				unset( $args );
				return $query;
			}

			/**
			 * Report whether the settings table exists.
			 *
			 * @param string $query SQL query.
			 * @return string|null
			 */
			public function get_var( $query ) {
				unset( $query );
				return $this->table_exists ? $this->prefix . 'auto_updates' : null;
			}

			/**
			 * Return settings rows.
			 *
			 * @param string $query  SQL query.
			 * @param string $output Return format.
			 * @return array<int, array<string, string>>
			 */
			public function get_results( $query, $output = ARRAY_A ) {
				unset( $query, $output );
				return $this->rows;
			}
		};
	}

	/**
	 * Checked auto-update rows used as the Companion Auto Update install defaults.
	 *
	 * @return array<string, string>
	 */
	private function default_source(): array {
		return array(
			'plugins'      => 'on',
			'themes'       => 'on',
			'minor'        => 'on',
			'major'        => '',
			'translations' => 'on',
			'wpemails'     => 'on',
			'update_delay' => '',
		);
	}

	/**
	 * Default Companion Auto Update toggles map to automatic updates with manual core majors off.
	 */
	public function test_default_toggles_map_to_automatic_updates(): void {
		$this->stub_map_helpers();

		$mapped = CompanionAutoUpdate::map_options( $this->default_source() );

		$this->assertSame( 'on', $mapped['plugin_auto_updates'] );
		$this->assertSame( 'off', $mapped['plugin_auto_updates_custom'] );
		$this->assertSame( 'off', $mapped['plugin_manual_updates_only'] );
		$this->assertSame( 'off', $mapped['plugin_auto_updates_patches_only'] );
		$this->assertSame( 'off', $mapped['plugin_updates_disabled'] );
		$this->assertSame( 'on', $mapped['theme_auto_updates'] );
		$this->assertSame( 'off', $mapped['theme_manual_updates_only'] );
		$this->assertSame( 'on', $mapped['minor_auto_updates'] );
		$this->assertSame( 'off', $mapped['major_auto_updates'] );
		$this->assertSame( 'on', $mapped['translation_auto_updates'] );
		$this->assertSame( 'off', $mapped['translation_manual_updates_only'] );
		$this->assertTrue( $mapped['enable_core_emails'] );
		$this->assertSame( 0, $mapped['delay_updates'] );
		$this->assertSame( array(), $mapped['blocked_plugins'] );
		$this->assertSame( array(), $mapped['auto_update_plugins'] );
	}

	/**
	 * Plugins left off stay manually updatable, and the denylist is still imported.
	 */
	public function test_plugins_off_maps_to_manual_and_keeps_denylist(): void {
		$this->stub_map_helpers();

		$source                  = $this->default_source();
		$source['plugins']       = '';
		$source['notUpdateList'] = 'akismet, hello.php';

		$mapped = CompanionAutoUpdate::map_options( $source );

		$this->assertSame( 'off', $mapped['plugin_auto_updates'] );
		$this->assertSame( 'on', $mapped['plugin_manual_updates_only'] );
		$this->assertSame( 'off', $mapped['plugin_updates_disabled'] );
		$this->assertSame( array( 'akismet' ), $mapped['blocked_plugins'] );
	}

	/**
	 * Denylist tokens resolve to installed plugin ids and skip single-file plugins.
	 */
	public function test_denylist_resolves_installed_plugins_and_skips_unknown_tokens(): void {
		$this->stub_map_helpers();

		$mapped = CompanionAutoUpdate::map_options(
			array(
				'plugins'       => 'on',
				'notUpdateList' => 'akismet, hello.php, missing-plugin',
			)
		);

		$this->assertSame( array( 'akismet' ), $mapped['blocked_plugins'] );
	}

	/**
	 * Theme exclusions become blocked stylesheet slugs.
	 */
	public function test_theme_denylist_maps_to_blocked_themes(): void {
		$this->stub_map_helpers();

		$mapped = CompanionAutoUpdate::map_options(
			array(
				'themes'          => 'on',
				'notUpdateListTh' => 'twentytwentyfour, Twenty Twenty-Three',
			)
		);

		$this->assertSame( 'on', $mapped['theme_auto_updates'] );
		$this->assertSame(
			array( 'twentytwentyfour', 'twenty-twenty-three' ),
			$mapped['blocked_themes']
		);
	}

	/**
	 * An enabled delay with an empty day count uses the runtime default of 2.
	 */
	public function test_delay_defaults_to_two_days_when_count_is_empty(): void {
		$this->stub_map_helpers();

		$mapped = CompanionAutoUpdate::map_options(
			array(
				'update_delay'      => 'on',
				'update_delay_days' => '',
			)
		);

		$this->assertSame( 2, $mapped['delay_updates'] );
	}

	/**
	 * Delay days are capped, and a disabled delay clears the count.
	 */
	public function test_delay_is_capped_and_cleared_when_off(): void {
		$this->stub_map_helpers();

		$capped = CompanionAutoUpdate::map_options(
			array(
				'update_delay'      => 'on',
				'update_delay_days' => '40',
			)
		);
		$off    = CompanionAutoUpdate::map_options(
			array(
				'update_delay'      => '',
				'update_delay_days' => '5',
			)
		);

		$this->assertSame( 31, $capped['delay_updates'] );
		$this->assertSame( 0, $off['delay_updates'] );
	}

	/**
	 * WordPress core emails follow the wpemails toggle only.
	 */
	public function test_core_emails_follow_wpemails_toggle(): void {
		$this->stub_map_helpers();

		$enabled  = CompanionAutoUpdate::map_options(
			array(
				'wpemails' => 'on',
			)
		);
		$disabled = CompanionAutoUpdate::map_options(
			array(
				'wpemails' => '',
			)
		);

		$this->assertTrue( $enabled['enable_core_emails'] );
		$this->assertFalse( $disabled['enable_core_emails'] );
	}

	/**
	 * Both report emails use the Companion Auto Update mail interval.
	 */
	public function test_report_emails_use_the_mail_schedule(): void {
		$this->stub_map_helpers();

		$source = array(
			'sendupdate' => 'on',
			'send'       => 'on',
			'email'      => 'admin@example.com, other@example.com',
		);
		$events = array(
			'cau_set_schedule_mail' => array(
				'recurrence' => 'weekly',
				'timestamp'  => 0,
			),
		);

		$success   = CompanionAutoUpdate::map_success_email_options( $source, $events );
		$available = CompanionAutoUpdate::map_available_email_options( $source, $events );

		$this->assertIsArray( $success );
		$this->assertSame( 'custom', $success['send_schedule'] );
		$this->assertSame( 'weekly', $success['schedule'] );
		$this->assertTrue( $success['include_plugins'] );
		$this->assertTrue( $success['include_themes'] );
		$this->assertFalse( $success['include_core'] );
		$this->assertSame(
			array( 'admin@example.com', 'other@example.com' ),
			$success['to_field']
		);

		$this->assertIsArray( $available );
		$this->assertSame( 'custom', $available['send_schedule'] );
		$this->assertSame( 'weekly', $available['schedule'] );
		$this->assertTrue( $available['include_plugins'] );
		$this->assertTrue( $available['include_themes'] );
	}

	/**
	 * An empty recipient field falls back to the site admin email.
	 */
	public function test_empty_email_falls_back_to_admin_email(): void {
		$this->stub_map_helpers();
		MonkeyFunctions\when( 'get_option' )->alias(
			static function ( $key, $default_value = false ) {
				if ( 'admin_email' === $key ) {
					return 'owner@example.com';
				}
				return $default_value;
			}
		);

		$success = CompanionAutoUpdate::map_success_email_options(
			array(
				'sendupdate' => 'on',
				'email'      => '',
			)
		);

		$this->assertIsArray( $success );
		$this->assertSame( array( 'owner@example.com' ), $success['to_field'] );
		$this->assertSame( 'twelve-hours', $success['schedule'] );
	}

	/**
	 * Plugin, theme, and core intervals map independently.
	 */
	public function test_cron_maps_each_context_separately(): void {
		$this->stub_map_helpers();

		$timestamp = gmmktime( 14, 30, 0, 1, 15, 2026 );
		$mapped    = CompanionAutoUpdate::map_cron_options(
			array(
				'wp_update_plugins' => array(
					'recurrence' => 'daily',
					'timestamp'  => $timestamp,
				),
				'wp_update_themes'  => array(
					'recurrence' => 'hourly',
					'timestamp'  => 0,
				),
				'wp_version_check'  => array(
					'recurrence' => 'twice_monthly',
					'timestamp'  => 0,
				),
			)
		);

		$this->assertSame( 'daily', $mapped[ Updates_Cron::CONTEXT_PLUGINS ]['schedule'] );
		$this->assertSame( '14:30', $mapped[ Updates_Cron::CONTEXT_PLUGINS ]['start_time'] );
		$this->assertSame( 'hourly', $mapped[ Updates_Cron::CONTEXT_THEMES ]['schedule'] );
		$this->assertSame( 'two-weeks', $mapped[ Updates_Cron::CONTEXT_CORE ]['schedule'] );
	}

	/**
	 * Unknown or missing recurrences are left unchanged.
	 */
	public function test_unknown_cron_is_omitted(): void {
		$this->stub_map_helpers();

		$mapped = CompanionAutoUpdate::map_cron_options(
			array(
				'wp_update_plugins' => array(
					'recurrence' => 'custom-every-minute',
					'timestamp'  => 0,
				),
			)
		);

		$this->assertSame( array(), $mapped );
	}

	/**
	 * Persist mapped settings from the settings table and live cron events.
	 */
	public function test_run_persists_mapped_settings(): void {
		$this->stub_run_helpers();
		$this->mock_settings_table(
			array(
				array(
					'name'    => 'plugins',
					'onoroff' => 'on',
				),
				array(
					'name'    => 'themes',
					'onoroff' => '',
				),
				array(
					'name'    => 'minor',
					'onoroff' => 'on',
				),
				array(
					'name'    => 'major',
					'onoroff' => 'on',
				),
				array(
					'name'    => 'translations',
					'onoroff' => '',
				),
				array(
					'name'    => 'notUpdateList',
					'onoroff' => 'akismet',
				),
				array(
					'name'    => 'wpemails',
					'onoroff' => '',
				),
				array(
					'name'    => 'update_delay',
					'onoroff' => 'on',
				),
				array(
					'name'    => 'update_delay_days',
					'onoroff' => '4',
				),
				array(
					'name'    => 'sendupdate',
					'onoroff' => 'on',
				),
				array(
					'name'    => 'send',
					'onoroff' => 'on',
				),
				array(
					'name'    => 'email',
					'onoroff' => 'admin@example.com',
				),
			)
		);

		$timestamp = gmmktime( 14, 30, 0, 1, 15, 2026 );
		$schedules = array(
			'wp_update_plugins'     => 'daily',
			'wp_update_themes'      => 'hourly',
			'wp_version_check'      => 'once_monthly',
			'cau_set_schedule_mail' => 'twicedaily',
		);
		MonkeyFunctions\when( 'wp_get_schedule' )->alias(
			static function ( $hook ) use ( $schedules ) {
				return $schedules[ $hook ] ?? false;
			}
		);
		MonkeyFunctions\when( 'wp_next_scheduled' )->alias(
			static function ( $hook ) use ( $timestamp ) {
				return 'wp_update_plugins' === $hook ? $timestamp : false;
			}
		);

		$this->assertTrue( CompanionAutoUpdate::run() );

		$saved = $this->site_options['updatebee_options'];
		$this->assertSame( 'on', $saved['plugin_auto_updates'] );
		$this->assertSame( 'on', $saved['theme_manual_updates_only'] );
		$this->assertSame( 'on', $saved['major_auto_updates'] );
		$this->assertSame( 'off', $saved['translation_auto_updates'] );
		$this->assertSame( array( 'akismet' ), $saved['blocked_plugins'] );
		$this->assertSame( 4, $saved['delay_updates'] );
		$this->assertFalse( $saved['enable_core_emails'] );

		$this->assertSame( 'daily', $this->site_options[ Updates_Cron::get_option_key( Updates_Cron::CONTEXT_PLUGINS ) ]['schedule'] );
		$this->assertSame( '14:30', $this->site_options[ Updates_Cron::get_option_key( Updates_Cron::CONTEXT_PLUGINS ) ]['start_time'] );
		$this->assertSame( 'hourly', $this->site_options[ Updates_Cron::get_option_key( Updates_Cron::CONTEXT_THEMES ) ]['schedule'] );
		$this->assertSame( 'monthly', $this->site_options[ Updates_Cron::get_option_key( Updates_Cron::CONTEXT_CORE ) ]['schedule'] );

		$this->assertSame( 'custom', $this->site_options[ Email_Successes::OPTIONS_KEY ]['send_schedule'] );
		$this->assertSame( 'twelve-hours', $this->site_options[ Email_Successes::OPTIONS_KEY ]['schedule'] );
		$this->assertSame( 'custom', $this->site_options[ Email_Updates_Available::OPTIONS_KEY ]['send_schedule'] );
		$this->assertSame( array( 'admin@example.com' ), $this->site_options[ Email_Successes::OPTIONS_KEY ]['to_field'] );
	}

	/**
	 * Return false when the settings table is missing.
	 */
	public function test_run_returns_false_without_source(): void {
		$this->stub_run_helpers();
		$this->mock_settings_table( array(), false );

		$this->assertFalse( CompanionAutoUpdate::run() );
	}

	/**
	 * An active plugin with no table is detected, but there is nothing to import.
	 */
	public function test_active_plugin_is_detected_without_settings(): void {
		$this->stub_run_helpers( true );
		$this->mock_settings_table( array(), false );

		$this->assertTrue( CompanionAutoUpdate::is_detected() );
		$this->assertTrue( CompanionAutoUpdate::should_show_importer() );
		$this->assertFalse( CompanionAutoUpdate::run() );
	}

	/**
	 * Hide flag suppresses the Tools card without forgetting the source table.
	 */
	public function test_hide_flag_suppresses_importer(): void {
		$this->stub_run_helpers();
		$this->mock_settings_table(
			array(
				array(
					'name'    => 'plugins',
					'onoroff' => 'on',
				),
			)
		);

		$this->assertTrue( CompanionAutoUpdate::is_detected() );
		$this->assertTrue( CompanionAutoUpdate::should_show_importer() );
		$this->assertTrue( Tools::get_script_data()['cauDetected'] );

		$this->assertTrue( CompanionAutoUpdate::hide_importer() );
		$this->assertSame( 1, $this->site_options[ CompanionAutoUpdate::HIDE_OPTION ] );
		$this->assertTrue( CompanionAutoUpdate::is_importer_hidden() );
		$this->assertFalse( CompanionAutoUpdate::should_show_importer() );
		$this->assertFalse( Tools::get_script_data()['cauDetected'] );
	}

	/**
	 * Resetting importers restores the card and leaves UpdateBee options in place.
	 */
	public function test_reset_importer_options_clears_hide_flag_without_wiping_options(): void {
		$this->stub_run_helpers();
		$this->mock_settings_table(
			array(
				array(
					'name'    => 'plugins',
					'onoroff' => 'on',
				),
			)
		);

		$stored_options                          = array(
			'plugin_auto_updates' => 'off',
		);
		$this->site_options['updatebee_options'] = $stored_options;
		$this->site_options[ CompanionAutoUpdate::HIDE_OPTION ] = 1;

		$this->assertFalse( CompanionAutoUpdate::should_show_importer() );

		Options::reset_importer_options();

		$this->assertArrayNotHasKey( CompanionAutoUpdate::HIDE_OPTION, $this->site_options );
		$this->assertFalse( CompanionAutoUpdate::is_importer_hidden() );
		$this->assertSame( $stored_options, $this->site_options['updatebee_options'] );
		$this->assertTrue( CompanionAutoUpdate::should_show_importer() );
		$this->assertTrue( Tools::get_script_data()['cauDetected'] );
	}
}
