<?php
/**
 * Tests for email report filtering and options.
 *
 * @package updatebee
 */

namespace UpdateBee\Tests;

use Brain\Monkey\Functions as MonkeyFunctions;
use ReflectionClass;
use UpdateBee\Emails\Email_Failures;
use UpdateBee\Emails\Email_Report;
use UpdateBee\Emails\Email_Successes;

/**
 * Email report test case.
 */
class EmailReportTest extends TestCase {

	/**
	 * Invoke protected filter_updates_for_report.
	 *
	 * @param class-string<Email_Report>       $class   Report class name.
	 * @param array<int, array<string, mixed>> $updates Update rows.
	 * @param array<string, mixed>             $options Report options.
	 * @return array<int, array<string, mixed>>
	 */
	private function filter_updates( string $class, array $updates, array $options ): array {
		$reflection = new ReflectionClass( $class );
		$method     = $reflection->getMethod( 'filter_updates_for_report' );
		$method->setAccessible( true );

		return $method->invoke( null, $updates, $options );
	}

	/**
	 * Test success report keeps only success rows.
	 */
	public function test_success_filter_keeps_success_rows_only(): void {
		$options = Email_Successes::get_defaults();
		$updates = array(
			array(
				'name'          => 'Core',
				'update_type'   => 'core',
				'update_status' => 'success',
			),
			array(
				'name'          => 'Plugin',
				'update_type'   => 'plugin',
				'update_status' => 'failure',
			),
		);

		$filtered = $this->filter_updates( Email_Successes::class, $updates, $options );

		$this->assertCount( 1, $filtered );
		$this->assertSame( 'success', $filtered[0]['update_status'] );
	}

	/**
	 * Test failure report keeps only failure rows.
	 */
	public function test_failure_filter_keeps_failure_rows_only(): void {
		$options = Email_Failures::get_defaults();
		$updates = array(
			array(
				'name'          => 'Core',
				'update_type'   => 'core',
				'update_status' => 'success',
			),
			array(
				'name'          => 'Plugin',
				'update_type'   => 'plugin',
				'update_status' => 'failure',
			),
		);

		$filtered = $this->filter_updates( Email_Failures::class, $updates, $options );

		$this->assertCount( 1, $filtered );
		$this->assertSame( 'failure', $filtered[0]['update_status'] );
	}

	/**
	 * Test include flags limit update types.
	 */
	public function test_filter_respects_include_flags(): void {
		$options = Email_Successes::get_defaults();
		$options['include_core'] = false;

		$updates = array(
			array(
				'name'          => 'Core',
				'update_type'   => 'core',
				'update_status' => 'success',
			),
			array(
				'name'          => 'Plugin',
				'update_type'   => 'plugin',
				'update_status' => 'success',
			),
		);

		$filtered = $this->filter_updates( Email_Successes::class, $updates, $options );

		$this->assertCount( 1, $filtered );
		$this->assertSame( 'plugin', $filtered[0]['update_type'] );
	}

	/**
	 * Test is_enabled reflects send schedule.
	 */
	public function test_is_enabled(): void {
		MonkeyFunctions\when( 'get_site_option' )->justReturn( array() );
		MonkeyFunctions\when( 'wp_parse_args' )->alias(
			static function ( $args, $defaults ) {
				return array_merge( (array) $defaults, (array) $args );
			}
		);

		$this->assertFalse( Email_Successes::is_enabled( array( 'send_schedule' => 'none' ) ) );
		$this->assertTrue( Email_Successes::is_enabled( array( 'send_schedule' => 'immediately' ) ) );
		$this->assertTrue( Email_Successes::is_enabled( array( 'send_schedule' => 'custom' ) ) );
	}
}
