<?php
/**
 * Tests for Easy Updates Manager option migration.
 *
 * @package updatebee
 */

namespace UpdateBee\Tests;

use Brain\Monkey\Functions as MonkeyFunctions;
use UpdateBee\Admin\Migrate\EasyUpdatesManager;
use UpdateBee\Admin\Templates\Tools;
use UpdateBee\Cron\Updates_Cron;
use UpdateBee\Emails\Email_Successes;
use UpdateBee\Emails\Email_Updates_Available;
use UpdateBee\Options;
use UpdateBee\Updates\Delay_Updates;

/**
 * EasyUpdatesManager migration test case.
 */
class EasyUpdatesManagerMigrateTest extends TestCase {

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
		MonkeyFunctions\when( 'esc_url_raw' )->returnArg( 1 );
		MonkeyFunctions\when( 'wp_parse_args' )->alias(
			static function ( $args, $defaults ) {
				return array_merge( (array) $defaults, (array) $args );
			}
		);
	}

	/**
	 * Stub helpers used by run().
	 */
	private function stub_run_helpers(): void {
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
				return $default_value;
			}
		);
		MonkeyFunctions\when( 'wp_clear_scheduled_hook' )->justReturn( true );
		MonkeyFunctions\when( 'wp_schedule_event' )->justReturn( true );
		MonkeyFunctions\when( 'wp_next_scheduled' )->justReturn( false );
		MonkeyFunctions\when( 'delete_site_option' )->alias(
			function ( $key ) {
				unset( $this->site_options[ $key ] );
				return true;
			}
		);
		MonkeyFunctions\when( 'is_plugin_active' )->justReturn( false );
		MonkeyFunctions\when( 'wp_create_nonce' )->alias(
			static function ( $action ) {
				return $action . '-nonce';
			}
		);
	}

	/**
	 * Individual plugin mode turns UpdateBee auto-updates on with a custom list.
	 */
	public function test_individual_plugin_mode_enables_custom_auto_updates(): void {
		$this->stub_map_helpers();

		$mapped = EasyUpdatesManager::map_options(
			array(
				'core'              => array(
					'plugin_updates' => 'individual',
				),
				'plugins_automatic' => array(
					'akismet/akismet.php',
					'hello-dolly/hello.php',
				),
			)
		);

		$this->assertSame( 'on', $mapped['plugin_auto_updates'] );
		$this->assertSame( 'on', $mapped['plugin_auto_updates_custom'] );
		$this->assertSame( 'off', $mapped['plugin_updates_disabled'] );
		$this->assertSame( 'off', $mapped['plugin_manual_updates_only'] );
		$this->assertSame( 'off', $mapped['plugin_auto_updates_patches_only'] );
		$this->assertSame(
			array( 'akismet/akismet.php', 'hello-dolly/hello.php' ),
			$mapped['auto_update_plugins']
		);
	}

	/**
	 * Automatic, manual, disabled, and patch-only plugin modes map to the right flags.
	 */
	public function test_plugin_modes_map_to_updatebee_flags(): void {
		$this->stub_map_helpers();

		$automatic = EasyUpdatesManager::map_options(
			array(
				'core' => array(
					'plugin_updates' => 'automatic',
				),
			)
		);
		$this->assertSame( 'on', $automatic['plugin_auto_updates'] );
		$this->assertSame( 'off', $automatic['plugin_auto_updates_custom'] );
		$this->assertSame( 'off', $automatic['plugin_manual_updates_only'] );
		$this->assertSame( 'off', $automatic['plugin_updates_disabled'] );
		$this->assertSame( 'off', $automatic['plugin_auto_updates_patches_only'] );

		$manual = EasyUpdatesManager::map_options(
			array(
				'core' => array(
					'plugin_updates' => 'on',
				),
			)
		);
		$this->assertSame( 'off', $manual['plugin_auto_updates'] );
		$this->assertSame( 'on', $manual['plugin_manual_updates_only'] );

		$disabled = EasyUpdatesManager::map_options(
			array(
				'core' => array(
					'plugin_updates' => 'off',
				),
			)
		);
		$this->assertSame( 'off', $disabled['plugin_auto_updates'] );
		$this->assertSame( 'on', $disabled['plugin_updates_disabled'] );

		$patches = EasyUpdatesManager::map_options(
			array(
				'core' => array(
					'plugin_updates' => 'automatic_patch_releases',
				),
			)
		);
		$this->assertSame( 'on', $patches['plugin_auto_updates'] );
		$this->assertSame( 'on', $patches['plugin_auto_updates_patches_only'] );
		$this->assertSame( 'off', $patches['plugin_auto_updates_custom'] );
	}

	/**
	 * Theme modes follow the same mapping as plugins.
	 */
	public function test_theme_modes_and_lists(): void {
		$this->stub_map_helpers();

		$mapped = EasyUpdatesManager::map_options(
			array(
				'core'             => array(
					'theme_updates' => 'individual',
				),
				'themes_automatic' => array( 'twentytwentyfour', 'twentytwentyfive' ),
				'themes'           => array( 'twentytwentythree' ),
				'themes_semantic'  => array( 'twentytwentyfour' ),
			)
		);

		$this->assertSame( 'on', $mapped['theme_auto_updates'] );
		$this->assertSame( 'on', $mapped['theme_auto_updates_custom'] );
		$this->assertSame( array( 'twentytwentyfour', 'twentytwentyfive' ), $mapped['auto_update_themes'] );
		$this->assertSame( array( 'twentytwentythree' ), $mapped['blocked_themes'] );
		$this->assertSame( array( 'twentytwentyfour' ), $mapped['auto_update_themes_patches'] );
	}

	/**
	 * Core automatic vs minor-only modes.
	 */
	public function test_core_update_modes(): void {
		$this->stub_map_helpers();

		$automatic = EasyUpdatesManager::map_options(
			array(
				'core' => array(
					'core_updates' => 'automatic',
				),
			)
		);
		$this->assertSame( 'on', $automatic['major_auto_updates'] );
		$this->assertSame( 'on', $automatic['minor_auto_updates'] );

		$minor = EasyUpdatesManager::map_options(
			array(
				'core' => array(
					'core_updates' => 'automatic_minor',
				),
			)
		);
		$this->assertSame( 'off', $minor['major_auto_updates'] );
		$this->assertSame( 'on', $minor['minor_auto_updates'] );

		$dev = EasyUpdatesManager::map_options(
			array(
				'core' => array(
					'core_updates'                  => 'on',
					'automatic_development_updates' => 'on',
				),
			)
		);
		$this->assertSame( 'on', $dev['major_auto_updates'] );
		$this->assertSame( 'off', $dev['minor_auto_updates'] );
	}

	/**
	 * Translation modes and the all-updates master switch.
	 */
	public function test_translation_modes_and_all_updates_off(): void {
		$this->stub_map_helpers();

		$automatic = EasyUpdatesManager::map_options(
			array(
				'core' => array(
					'translation_updates' => 'automatic',
				),
			)
		);
		$this->assertSame( 'on', $automatic['translation_auto_updates'] );
		$this->assertSame( 'off', $automatic['translation_manual_updates_only'] );

		$all_off = EasyUpdatesManager::map_options(
			array(
				'core' => array(
					'all_updates'         => 'off',
					'plugin_updates'      => 'automatic',
					'theme_updates'       => 'automatic',
					'translation_updates' => 'automatic',
					'core_updates'        => 'automatic',
				),
			)
		);
		$this->assertSame( 'on', $all_off['plugin_updates_disabled'] );
		$this->assertSame( 'on', $all_off['theme_updates_disabled'] );
		$this->assertSame( 'on', $all_off['translation_updates_disabled'] );
		$this->assertSame( 'off', $all_off['plugin_auto_updates'] );
		$this->assertSame( 'off', $all_off['major_auto_updates'] );
		$this->assertSame( 'off', $all_off['minor_auto_updates'] );
	}

	/**
	 * Numeric EUM lists are read from values, not keys treated as slugs.
	 */
	public function test_numeric_lists_use_values_not_keys(): void {
		$this->stub_map_helpers();

		$mapped = EasyUpdatesManager::map_options(
			array(
				'core'              => array(
					'plugin_updates' => 'individual',
				),
				'plugins_automatic' => array(
					0 => 'akismet/akismet.php',
					1 => 'jetpack/jetpack.php',
				),
				'plugins'           => array(
					0 => 'hello-dolly/hello.php',
				),
				'plugins_semantic'  => array(
					0 => 'akismet/akismet.php',
				),
			)
		);

		$this->assertSame( array( 'akismet/akismet.php', 'jetpack/jetpack.php' ), $mapped['auto_update_plugins'] );
		$this->assertSame( array( 'hello-dolly' ), $mapped['blocked_plugins'] );
		$this->assertSame( array( 'akismet/akismet.php' ), $mapped['auto_update_plugins_patches'] );
	}

	/**
	 * Twicedaily EUM cron maps to twelve-hours and keeps the start time and days list.
	 */
	public function test_cron_twicedaily_maps_to_twelve_hours(): void {
		$this->stub_map_helpers();

		$cron = EasyUpdatesManager::map_cron_options(
			array(
				'advanced' => array(
					'cron_schedule'   => 'twicedaily',
					'cron_time'       => '00:15',
					'cron_week_day'   => 2,
					'cron_days_list'  => array( 1, 2, 3, 4, 5 ),
					'cron_week'       => '1',
					'cron_day_number' => 3,
				),
			)
		);

		$this->assertIsArray( $cron );
		$this->assertSame( 'twelve-hours', $cron['schedule'] );
		$this->assertSame( '00:15', $cron['start_time'] );
		$this->assertSame( 'monday', $cron['day'] );
		$this->assertSame( array( 1, 2, 3, 4, 5 ), $cron['days_list'] );
		$this->assertSame( '3', $cron['day_num'] );
	}

	/**
	 * EUM cron slugs translate to UpdateBee slugs.
	 */
	public function test_cron_slug_translation(): void {
		$this->stub_map_helpers();

		$three = EasyUpdatesManager::map_cron_options(
			array(
				'advanced' => array(
					'cron_schedule' => 'every_3_hours',
				),
			)
		);
		$this->assertSame( 'three-hours', $three['schedule'] );

		$fortnightly = EasyUpdatesManager::map_cron_options(
			array(
				'advanced' => array(
					'cron_schedule' => 'fortnightly',
					'cron_week'     => '2nd',
				),
			)
		);
		$this->assertSame( 'two-weeks', $fortnightly['schedule'] );
		$this->assertSame( '2', $fortnightly['week'] );
	}

	/**
	 * Missing advanced cron keys leave UpdateBee cron alone.
	 */
	public function test_cron_absent_when_advanced_has_no_cron_keys(): void {
		$this->stub_map_helpers();

		$this->assertNull(
			EasyUpdatesManager::map_cron_options(
				array(
					'core' => array(
						'plugin_updates' => 'automatic',
					),
				)
			)
		);
	}

	/**
	 * Delay days and stamps copy from advanced and root maps.
	 */
	public function test_delay_days_and_stamps(): void {
		$this->stub_map_helpers();

		$mapped = EasyUpdatesManager::map_options(
			array(
				'advanced' => array(
					'delay_updates' => 5,
				),
			)
		);
		$this->assertSame( 5, $mapped['delay_updates'] );

		$stamps = EasyUpdatesManager::map_delay_stamps(
			array(
				'plugin_updates' => array(
					'akismet/akismet.php' => array(
						'version' => '5.3',
						'time'    => 1700000000,
					),
				),
				'theme_updates'  => array(
					'twentytwentyfour' => array(
						'version' => '1.2',
						'time'    => 1700000001,
					),
				),
			)
		);

		$this->assertSame( '5.3', $stamps[ Delay_Updates::PLUGIN_OPTION ]['akismet/akismet.php']['version'] );
		$this->assertSame( 1700000000, $stamps[ Delay_Updates::PLUGIN_OPTION ]['akismet/akismet.php']['time'] );
		$this->assertSame( '1.2', $stamps[ Delay_Updates::THEME_OPTION ]['twentytwentyfour']['version'] );
	}

	/**
	 * Email recipients and immediate success reports map from EUM notification flags.
	 */
	public function test_email_recipients_and_success_immediate(): void {
		$this->stub_map_helpers();

		$mapped = EasyUpdatesManager::map_options(
			array(
				'core' => array(
					'notification_core_update_emails' => 'off',
					'email_addresses'                 => array( 'admin@example.com', 'bad-email' ),
				),
			)
		);
		$this->assertFalse( $mapped['enable_core_emails'] );
		$this->assertSame( array( 'admin@example.com' ), $mapped['core_email_recipients'] );

		$success = EasyUpdatesManager::map_success_email_options(
			array(
				'core' => array(
					'plugin_auto_updates_notification_emails' => 'on',
					'theme_auto_updates_notification_emails' => 'off',
					'email_addresses' => array( 'admin@example.com' ),
				),
			)
		);
		$this->assertIsArray( $success );
		$this->assertSame( 'immediately', $success['send_schedule'] );
		$this->assertTrue( $success['include_plugins'] );
		$this->assertFalse( $success['include_themes'] );
		$this->assertSame( array( 'admin@example.com' ), $success['to_field'] );
	}

	/**
	 * Weekly and monthly available-update digests map to custom email schedules.
	 */
	public function test_available_updates_digest_cadence(): void {
		$this->stub_map_helpers();

		$weekly = EasyUpdatesManager::map_available_email_options(
			array(
				'core' => array(
					'update_notification_updates' => 'weekly',
					'email_addresses'             => array( 'ops@example.com' ),
				),
			)
		);
		$this->assertIsArray( $weekly );
		$this->assertSame( 'custom', $weekly['send_schedule'] );
		$this->assertSame( 'weekly', $weekly['schedule'] );
		$this->assertSame( array( 'ops@example.com' ), $weekly['to_field'] );

		$this->assertNull(
			EasyUpdatesManager::map_available_email_options(
				array(
					'core' => array(
						'update_notification_updates' => 'off',
					),
				)
			)
		);
	}

	/**
	 * Slack and log retention map from the logs section.
	 */
	public function test_slack_and_log_retention(): void {
		$this->stub_map_helpers();

		$mapped = EasyUpdatesManager::map_options(
			array(
				'logs' => array(
					'clear_logs'                => 14,
					'eum_logger_type'           => array(
						'mpsum_slack_logger' => '1',
					),
					'logger_additional_options' => array(
						'slack_webhook_url' => 'https://hooks.slack.com/services/T000/B000/XXX',
					),
				),
			)
		);

		$this->assertSame( 14, $mapped['clear_logs_days'] );
		$this->assertTrue( $mapped['slack_enabled'] );
		$this->assertSame( 'https://hooks.slack.com/services/T000/B000/XXX', $mapped['slack_webhook_url'] );
	}

	/**
	 * Legacy automatic_* keys normalize before mapping.
	 */
	public function test_legacy_automatic_keys_normalize(): void {
		$this->stub_map_helpers();

		$mapped = EasyUpdatesManager::map_options(
			array(
				'core' => array(
					'automatic_major_updates'       => 'on',
					'automatic_minor_updates'       => 'on',
					'automatic_plugin_updates'      => 'custom',
					'automatic_theme_updates'       => 'on',
					'automatic_translation_updates' => 'on',
				),
			)
		);

		$this->assertSame( 'on', $mapped['major_auto_updates'] );
		$this->assertSame( 'on', $mapped['minor_auto_updates'] );
		$this->assertSame( 'on', $mapped['plugin_auto_updates'] );
		$this->assertSame( 'on', $mapped['plugin_auto_updates_custom'] );
		$this->assertSame( 'on', $mapped['theme_auto_updates'] );
		$this->assertSame( 'off', $mapped['theme_auto_updates_custom'] );
		$this->assertSame( 'on', $mapped['translation_auto_updates'] );
	}

	/**
	 * Persist mapped settings from a full MPSUM fixture.
	 */
	public function test_run_persists_mapped_settings(): void {
		$this->stub_run_helpers();

		$this->site_options['MPSUM'] = array(
			'core'              => array(
				'plugin_updates'                          => 'individual',
				'theme_updates'                           => 'automatic',
				'core_updates'                            => 'automatic_minor',
				'translation_updates'                     => 'automatic',
				'notification_core_update_emails'         => 'on',
				'email_addresses'                         => array( 'admin@example.com' ),
				'plugin_auto_updates_notification_emails' => 'on',
				'theme_auto_updates_notification_emails'  => 'off',
				'update_notification_updates'             => 'monthly',
			),
			'plugins_automatic' => array( 'akismet/akismet.php' ),
			'advanced'          => array(
				'cron_schedule' => 'twicedaily',
				'cron_time'     => '00:15',
				'delay_updates' => 3,
			),
			'plugin_updates'    => array(
				'akismet/akismet.php' => array(
					'version' => '5.3',
					'time'    => 1700000000,
				),
			),
		);

		$this->assertTrue( EasyUpdatesManager::run() );

		$saved = $this->site_options['updatebee_options'];
		$this->assertSame( 'on', $saved['plugin_auto_updates'] );
		$this->assertSame( 'on', $saved['plugin_auto_updates_custom'] );
		$this->assertSame( array( 'akismet/akismet.php' ), $saved['auto_update_plugins'] );
		$this->assertSame( 3, $saved['delay_updates'] );

		foreach ( array( Updates_Cron::CONTEXT_PLUGINS, Updates_Cron::CONTEXT_THEMES, Updates_Cron::CONTEXT_CORE ) as $context ) {
			$key = Updates_Cron::get_option_key( $context );
			$this->assertSame( 'twelve-hours', $this->site_options[ $key ]['schedule'] );
			$this->assertSame( '00:15', $this->site_options[ $key ]['start_time'] );
		}

		$this->assertSame( '5.3', $this->site_options[ Delay_Updates::PLUGIN_OPTION ]['akismet/akismet.php']['version'] );
		$this->assertSame( 'immediately', $this->site_options[ Email_Successes::OPTIONS_KEY ]['send_schedule'] );
		$this->assertSame( 'custom', $this->site_options[ Email_Updates_Available::OPTIONS_KEY ]['send_schedule'] );
		$this->assertSame( 'monthly', $this->site_options[ Email_Updates_Available::OPTIONS_KEY ]['schedule'] );
	}

	/**
	 * Return false when no MPSUM source exists.
	 */
	public function test_run_returns_false_without_source(): void {
		$this->stub_run_helpers();

		$this->assertFalse( EasyUpdatesManager::run() );
	}

	/**
	 * Hide flag suppresses importer detection for the Tools UI payload.
	 */
	public function test_hide_flag_suppresses_detection_and_ui_payload(): void {
		$this->stub_run_helpers();

		$this->site_options['MPSUM'] = array(
			'core' => array(
				'plugin_updates' => 'automatic',
			),
		);

		$this->assertTrue( EasyUpdatesManager::is_detected() );
		$this->assertTrue( EasyUpdatesManager::should_show_importer() );
		$this->assertTrue( Tools::get_script_data()['eumDetected'] );

		$this->assertTrue( EasyUpdatesManager::hide_importer() );
		$this->assertSame( 1, $this->site_options[ EasyUpdatesManager::HIDE_OPTION ] );
		$this->assertTrue( EasyUpdatesManager::is_importer_hidden() );
		$this->assertTrue( EasyUpdatesManager::is_detected() );
		$this->assertFalse( EasyUpdatesManager::should_show_importer() );
		$this->assertFalse( Tools::get_script_data()['eumDetected'] );
	}

	/**
	 * Importer-only reset clears the hide flag without wiping UpdateBee options.
	 */
	public function test_reset_importer_options_clears_hide_flag_without_wiping_options(): void {
		$this->stub_run_helpers();

		$stored_options = array(
			'major_auto_updates' => 'on',
			'delay_updates'      => 7,
		);

		$this->site_options['updatebee_options']               = $stored_options;
		$this->site_options['MPSUM']                           = array(
			'core' => array(
				'plugin_updates' => 'automatic',
			),
		);
		$this->site_options[ EasyUpdatesManager::HIDE_OPTION ] = 1;

		$this->assertFalse( EasyUpdatesManager::should_show_importer() );

		Options::reset_importer_options();

		$this->assertArrayNotHasKey( EasyUpdatesManager::HIDE_OPTION, $this->site_options );
		$this->assertFalse( EasyUpdatesManager::is_importer_hidden() );
		$this->assertSame( $stored_options, $this->site_options['updatebee_options'] );
		$this->assertArrayHasKey( 'MPSUM', $this->site_options );
		$this->assertTrue( EasyUpdatesManager::should_show_importer() );
		$this->assertTrue( Tools::get_script_data()['eumDetected'] );
	}

	/**
	 * Resetting UpdateBee options clears the hide flag so the importer can return.
	 */
	public function test_reset_options_clears_importer_hide_flag(): void {
		$this->stub_run_helpers();

		$this->site_options['MPSUM']                           = array(
			'core' => array(
				'plugin_updates' => 'automatic',
			),
		);
		$this->site_options[ EasyUpdatesManager::HIDE_OPTION ] = 1;

		$this->assertFalse( EasyUpdatesManager::should_show_importer() );

		Options::reset_options();

		$this->assertArrayNotHasKey( EasyUpdatesManager::HIDE_OPTION, $this->site_options );
		$this->assertFalse( EasyUpdatesManager::is_importer_hidden() );
		$this->assertTrue( EasyUpdatesManager::should_show_importer() );
		$this->assertTrue( Tools::get_script_data()['eumDetected'] );
	}
}
