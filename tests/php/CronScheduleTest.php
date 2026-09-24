<?php
/**
 * Tests for update cron scheduling and weekday rules.
 *
 * @package updatebee
 */

namespace UpdateBee\Tests;

use Brain\Monkey\Functions as MonkeyFunctions;
use UpdateBee\Cron\Days_Schedule;
use UpdateBee\Cron\Schedules;
use UpdateBee\Cron\Updates_Cron;

/**
 * Cron schedule test case.
 */
class CronScheduleTest extends TestCase {

	/**
	 * Stub time and timezone helpers.
	 */
	private function stub_time_helpers(): void {
		MonkeyFunctions\when( 'get_option' )->alias(
			static function ( $key, $default = false ) {
				if ( 'gmt_offset' === $key ) {
					return 0;
				}
				return $default;
			}
		);
		MonkeyFunctions\when( 'get_date_from_gmt' )->alias(
			static function ( $datetime, $format ) {
				unset( $datetime );
				if ( 'w' === $format ) {
					return '6';
				}
				return '2026-01-03 12:00:00';
			}
		);
		MonkeyFunctions\when( 'absint' )->alias(
			static function ( $value ) {
				return (int) $value;
			}
		);
	}

	/**
	 * Test daily except-list yields zero when every day is excluded.
	 */
	public function test_calculate_next_event_daily_all_days_excluded_returns_zero(): void {
		$this->stub_time_helpers();

		$timestamp = Schedules::calculate_next_event(
			array(
				'schedule'   => 'daily',
				'start_time' => '00:00',
				'days_list'  => array( 1, 2, 3, 4, 5, 6, 7 ),
			),
			true
		);

		$this->assertSame( 0, $timestamp );
	}

	/**
	 * Test sub-daily allow-list returns a positive timestamp when Saturday is allowed.
	 */
	public function test_calculate_next_event_subdaily_with_allowed_saturday(): void {
		$this->stub_time_helpers();

		$timestamp = Schedules::calculate_next_event(
			array(
				'schedule'   => 'twelve-hours',
				'start_time' => '00:00',
				'days_list'  => array( 7 ),
			),
			false
		);

		$this->assertGreaterThan( 0, $timestamp );
	}

	/**
	 * Test weekday gate blocks plugin updates when today is not allowed.
	 */
	public function test_days_schedule_blocks_plugin_on_disallowed_weekday(): void {
		MonkeyFunctions\expect( 'get_site_option' )
			->with( Updates_Cron::get_option_key( Updates_Cron::CONTEXT_PLUGINS ), array() )
			->andReturn(
				array(
					'schedule'   => 'twelve-hours',
					'start_time' => '00:00',
					'days_list'  => array( 1 ),
				)
			);

		MonkeyFunctions\when( 'get_date_from_gmt' )->alias(
			static function ( $datetime, $format ) {
				unset( $datetime );
				if ( 'w' === $format ) {
					return '6';
				}
				return '';
			}
		);

		$allowed = Days_Schedule::apply_weekday_rules(
			true,
			array(
				'schedule'  => 'twelve-hours',
				'days_list' => array( 1 ),
			)
		);

		$this->assertFalse( $allowed );
	}

	/**
	 * Test weekday gate allows theme updates when today is in allow-list.
	 */
	public function test_days_schedule_allows_theme_on_allowed_weekday(): void {
		MonkeyFunctions\when( 'get_date_from_gmt' )->alias(
			static function ( $datetime, $format ) {
				unset( $datetime );
				if ( 'w' === $format ) {
					return '6';
				}
				return '';
			}
		);

		$allowed = Days_Schedule::apply_weekday_rules(
			true,
			array(
				'schedule'  => 'twelve-hours',
				'days_list' => array( 7 ),
			)
		);

		$this->assertTrue( $allowed );
	}

	/**
	 * Test daily except-list skips updates on listed weekdays.
	 */
	public function test_days_schedule_daily_except_list(): void {
		MonkeyFunctions\when( 'get_date_from_gmt' )->alias(
			static function ( $datetime, $format ) {
				unset( $datetime );
				if ( 'w' === $format ) {
					return '6';
				}
				return '';
			}
		);

		$blocked = Days_Schedule::apply_weekday_rules(
			true,
			array(
				'schedule'  => 'daily',
				'days_list' => array( 7 ),
			)
		);

		$this->assertFalse( $blocked );
	}

	/**
	 * Stub home_url for crontab command hints.
	 */
	private function stub_home_url(): void {
		MonkeyFunctions\when( 'home_url' )->justReturn( 'https://example.com/wp-cron.php?doing_wp_cron' );
	}

	/**
	 * Test daily crontab line at configured time.
	 */
	public function test_crontab_equivalent_daily(): void {
		$this->stub_home_url();

		$text = Schedules::get_crontab_equivalent(
			array(
				'schedule'   => 'daily',
				'start_time' => '03:30',
				'days_list'  => array(),
			),
			'wp_update_plugins'
		);

		$this->assertStringContainsString( 'wp cron event run wp_update_plugins', $text );
		$this->assertStringContainsString( '30 3 * * *', $text );
	}

	/**
	 * Test structured crontab commands are full pasteable lines.
	 */
	public function test_crontab_commands_daily(): void {
		$this->stub_home_url();

		$commands = Schedules::get_crontab_commands(
			array(
				'schedule'   => 'daily',
				'start_time' => '03:30',
				'days_list'  => array(),
			),
			'wp_update_plugins'
		);

		$this->assertCount( 3, $commands );
		$this->assertSame(
			'30 3 * * * cd /tmp/wordpress && wp cron event run wp_update_plugins >/dev/null 2>&1',
			$commands[0]['command']
		);
		$this->assertSame(
			'30 3 * * * php -q /tmp/wordpress/wp-cron.php >/dev/null 2>&1',
			$commands[1]['command']
		);
		$this->assertSame(
			'30 3 * * * curl -s "https://example.com/wp-cron.php?doing_wp_cron" >/dev/null 2>&1',
			$commands[2]['command']
		);
	}

	/**
	 * Test twelve-hours crontab uses midnight and noon hours.
	 */
	public function test_crontab_equivalent_twelve_hours(): void {
		$this->stub_home_url();

		$text = Schedules::get_crontab_equivalent(
			array(
				'schedule'   => 'twelve-hours',
				'start_time' => '00:00',
				'days_list'  => array( 1, 2, 3, 4, 5, 6, 7 ),
			),
			'wp_update_themes'
		);

		$this->assertStringContainsString( 'wp cron event run wp_update_themes', $text );
		$this->assertStringContainsString( '0 0,12 * * *', $text );
	}

	/**
	 * Test daily except-list maps to weekday DOW field.
	 */
	public function test_crontab_equivalent_daily_with_except_days(): void {
		$this->stub_home_url();

		$text = Schedules::get_crontab_equivalent(
			array(
				'schedule'   => 'daily',
				'start_time' => '01:15',
				'days_list'  => array( 1, 7 ),
			),
			'wp_version_check'
		);

		$this->assertStringContainsString( '15 1 * * 1-5', $text );
	}
}
