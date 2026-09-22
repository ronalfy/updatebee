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
}
