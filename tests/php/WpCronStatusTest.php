<?php
/**
 * Tests for the wp-config.php DISABLE_WP_CRON status helper.
 *
 * @package updatebee
 */

namespace UpdateBee\Tests;

use ReflectionClass;
use UpdateBee\Cron\Wp_Cron_Status;

/**
 * Wp_Cron_Status test case.
 */
class WpCronStatusTest extends TestCase {

	/**
	 * Reset the constant snapshot after each test.
	 */
	protected function tearDown(): void {
		Wp_Cron_Status::reset_state();
		parent::tearDown();
	}

	/**
	 * Force the constant snapshot without defining DISABLE_WP_CRON.
	 *
	 * @param bool      $defined Whether the constant is defined.
	 * @param bool|null $value   Constant value when defined.
	 */
	private function set_constant_state( bool $defined, ?bool $value ): void {
		$reflection = new ReflectionClass( Wp_Cron_Status::class );

		$was_defined = $reflection->getProperty( 'constant_defined' );
		$was_defined->setAccessible( true );
		$was_defined->setValue( null, $defined );

		$constant_value = $reflection->getProperty( 'constant_value' );
		$constant_value->setAccessible( true );
		$constant_value->setValue( null, $value );
	}

	/**
	 * Admin state when wp-config.php has not defined the constant.
	 */
	public function test_admin_state_when_constant_is_not_defined(): void {
		$this->set_constant_state( false, null );

		$state = Wp_Cron_Status::get_admin_state();

		$this->assertFalse( $state['constantDefined'] );
		$this->assertNull( $state['constantValue'] );
		$this->assertFalse( Wp_Cron_Status::is_wp_cron_disabled() );
		$this->assertFalse( defined( 'DISABLE_WP_CRON' ) );
	}

	/**
	 * Admin state when DISABLE_WP_CRON is true in wp-config.php.
	 */
	public function test_admin_state_when_constant_is_true(): void {
		$this->set_constant_state( true, true );

		$state = Wp_Cron_Status::get_admin_state();

		$this->assertTrue( $state['constantDefined'] );
		$this->assertTrue( $state['constantValue'] );
		$this->assertTrue( Wp_Cron_Status::is_wp_cron_disabled() );
		$this->assertFalse( defined( 'DISABLE_WP_CRON' ) );
	}

	/**
	 * Admin state when DISABLE_WP_CRON is false in wp-config.php.
	 */
	public function test_admin_state_when_constant_is_false(): void {
		$this->set_constant_state( true, false );

		$state = Wp_Cron_Status::get_admin_state();

		$this->assertTrue( $state['constantDefined'] );
		$this->assertFalse( $state['constantValue'] );
		$this->assertFalse( Wp_Cron_Status::is_wp_cron_disabled() );
		$this->assertFalse( defined( 'DISABLE_WP_CRON' ) );
	}
}
