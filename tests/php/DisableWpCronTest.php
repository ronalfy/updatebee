<?php
/**
 * Tests for network-wide DISABLE_WP_CRON option helpers.
 *
 * @package updatebee
 */

namespace UpdateBee\Tests;

use Brain\Monkey\Functions as MonkeyFunctions;
use ReflectionClass;
use UpdateBee\Cron\Disable_Wp_Cron;
use UpdateBee\Options;

/**
 * Disable_Wp_Cron test case.
 */
class DisableWpCronTest extends TestCase {

	/**
	 * Reset caches after each test.
	 */
	protected function tearDown(): void {
		Disable_Wp_Cron::reset_state();
		Options::flush_cache();
		parent::tearDown();
	}

	/**
	 * Stub option helpers used by Options.
	 */
	private function stub_option_helpers(): void {
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
	}

	/**
	 * Force predefined-constant snapshot without defining DISABLE_WP_CRON.
	 *
	 * @param bool      $defined Whether the constant was predefined.
	 * @param bool|null $value Predefined value when defined.
	 */
	private function set_predefined_state( bool $defined, ?bool $value ): void {
		$reflection = new ReflectionClass( Disable_Wp_Cron::class );

		$was_defined = $reflection->getProperty( 'constant_was_predefined' );
		$was_defined->setAccessible( true );
		$was_defined->setValue( null, $defined );

		$predefined = $reflection->getProperty( 'predefined_value' );
		$predefined->setAccessible( true );
		$predefined->setValue( null, $value );
	}

	/**
	 * Option defaults to off.
	 */
	public function test_option_defaults_to_false(): void {
		$this->stub_option_helpers();
		MonkeyFunctions\expect( 'get_site_option' )
			->once()
			->with( 'updatebee_options', array() )
			->andReturn( array() );

		$this->assertFalse( Disable_Wp_Cron::is_option_enabled() );
		$this->assertFalse( Options::get_options( Disable_Wp_Cron::OPTION_KEY ) );
	}

	/**
	 * Admin state when wp-config has not defined the constant.
	 */
	public function test_admin_state_when_constant_not_predefined(): void {
		$this->stub_option_helpers();
		MonkeyFunctions\when( 'get_site_option' )->justReturn(
			array(
				'disable_wp_cron' => true,
			)
		);

		$this->set_predefined_state( false, null );

		$state = Disable_Wp_Cron::get_admin_state();

		$this->assertTrue( $state['enabled'] );
		$this->assertFalse( $state['constantDefined'] );
		$this->assertNull( $state['constantValue'] );
		$this->assertTrue( Disable_Wp_Cron::can_manage_option() );
	}

	/**
	 * Admin state when DISABLE_WP_CRON is already true in wp-config.
	 */
	public function test_admin_state_when_constant_predefined_true(): void {
		$this->stub_option_helpers();
		MonkeyFunctions\when( 'get_site_option' )->justReturn( array() );

		$this->set_predefined_state( true, true );

		$state = Disable_Wp_Cron::get_admin_state();

		$this->assertFalse( $state['enabled'] );
		$this->assertTrue( $state['constantDefined'] );
		$this->assertTrue( $state['constantValue'] );
		$this->assertFalse( Disable_Wp_Cron::can_manage_option() );
	}

	/**
	 * Admin state when DISABLE_WP_CRON is forced false in wp-config.
	 */
	public function test_admin_state_when_constant_predefined_false(): void {
		$this->stub_option_helpers();
		MonkeyFunctions\when( 'get_site_option' )->justReturn(
			array(
				'disable_wp_cron' => true,
			)
		);

		$this->set_predefined_state( true, false );

		$state = Disable_Wp_Cron::get_admin_state();

		$this->assertTrue( $state['enabled'] );
		$this->assertTrue( $state['constantDefined'] );
		$this->assertFalse( $state['constantValue'] );
		$this->assertFalse( Disable_Wp_Cron::can_manage_option() );
	}

	/**
	 * Method maybe_define_constant does nothing when the constant was predefined.
	 */
	public function test_maybe_define_constant_skips_when_predefined(): void {
		$this->stub_option_helpers();
		MonkeyFunctions\when( 'get_site_option' )->justReturn(
			array(
				'disable_wp_cron' => true,
			)
		);

		$this->set_predefined_state( true, false );

		Disable_Wp_Cron::maybe_define_constant();

		// Constant must remain undefined in this process (we never define it in tests).
		$this->assertFalse( defined( 'DISABLE_WP_CRON' ) );
	}

	/**
	 * Method maybe_remove_wp_cron_action is a no-op when the constant was predefined.
	 */
	public function test_maybe_remove_action_skips_when_predefined(): void {
		$this->stub_option_helpers();
		MonkeyFunctions\when( 'get_site_option' )->justReturn(
			array(
				'disable_wp_cron' => true,
			)
		);
		MonkeyFunctions\expect( 'remove_action' )->never();

		$this->set_predefined_state( true, true );

		Disable_Wp_Cron::maybe_remove_wp_cron_action();

		$this->assertFalse( Disable_Wp_Cron::can_manage_option() );
	}
}
