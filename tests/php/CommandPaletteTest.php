<?php
/**
 * Tests for the admin command palette update actions.
 *
 * @package updatebee
 */

namespace UpdateBee\Tests;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions as MonkeyFunctions;
use UpdateBee\Admin\Command_Palette;

/**
 * Command palette enqueue and capability tests.
 */
class CommandPaletteTest extends TestCase {

	/**
	 * Run registers the admin enqueue hook.
	 */
	public function test_run_registers_admin_enqueue_hook(): void {
		$this->expectNotToPerformAssertions();
		Actions\expectAdded( 'admin_enqueue_scripts' )->once();

		$palette = new Command_Palette();
		$palette->run();
	}

	/**
	 * Users who cannot manage UpdateBee get neither the script nor the nonce.
	 */
	public function test_enqueue_skipped_without_capability(): void {
		$this->expectNotToPerformAssertions();
		$this->stub_access( false, false );
		MonkeyFunctions\expect( 'wp_enqueue_script' )->never();
		MonkeyFunctions\expect( 'wp_enqueue_style' )->never();
		MonkeyFunctions\expect( 'wp_localize_script' )->never();
		MonkeyFunctions\expect( 'wp_create_nonce' )->never();

		$palette = new Command_Palette();
		$palette->enqueue_assets();
	}

	/**
	 * Network-activated sites require manage_network, not manage_options.
	 */
	public function test_enqueue_skipped_for_manage_options_when_network_activated(): void {
		$this->expectNotToPerformAssertions();
		MonkeyFunctions\when( 'is_multisite' )->justReturn( true );
		MonkeyFunctions\when( 'is_plugin_active_for_network' )->justReturn( true );
		MonkeyFunctions\when( 'plugin_basename' )->justReturn( 'updatebee/updatebee.php' );
		MonkeyFunctions\when( 'current_user_can' )->alias(
			static function ( string $cap ): bool {
				return 'manage_options' === $cap;
			}
		);
		MonkeyFunctions\expect( 'wp_enqueue_script' )->never();
		MonkeyFunctions\expect( 'wp_create_nonce' )->never();

		$palette = new Command_Palette();
		$palette->enqueue_assets();
	}

	/**
	 * Single-site administrators receive both update nonces.
	 */
	public function test_enqueue_localizes_nonce_for_manage_options(): void {
		$this->stub_access( false, true );
		$captured = array(
			'scripts'   => array(),
			'localized' => array(),
		);
		$this->capture_enqueue( $captured );

		$palette = new Command_Palette();
		$palette->enqueue_assets();

		$this->assertCount( 1, $captured['scripts'] );
		$this->assertSame( 'updatebee-commands', $captured['scripts'][0][0] );
		$this->assertContains( 'wp-commands', $captured['scripts'][0][2] );
		$this->assertTrue( $captured['scripts'][0][4] );

		$this->assertCount( 1, $captured['localized'] );
		$this->assertSame( 'updatebee-commands', $captured['localized'][0][0] );
		$this->assertSame( 'updatebeeCommands', $captured['localized'][0][1] );
		$this->assertSame(
			array(
				'forceUpdatesNonce'   => 'nonce-updatebee-force-updates',
				'refreshUpdatesNonce' => 'nonce-updatebee-refresh-updates',
			),
			$captured['localized'][0][2]
		);
	}

	/**
	 * Network administrators receive both commands when the plugin is network-activated.
	 */
	public function test_enqueue_localizes_nonce_for_manage_network(): void {
		$this->stub_access( true, true );
		$captured = array(
			'scripts'   => array(),
			'localized' => array(),
		);
		$this->capture_enqueue( $captured );

		$palette = new Command_Palette();
		$palette->enqueue_assets();

		$this->assertCount( 1, $captured['scripts'] );
		$this->assertSame(
			'nonce-updatebee-force-updates',
			$captured['localized'][0][2]['forceUpdatesNonce']
		);
		$this->assertSame(
			'nonce-updatebee-refresh-updates',
			$captured['localized'][0][2]['refreshUpdatesNonce']
		);
	}

	/**
	 * Stub the checks used by Functions::can_access_options().
	 *
	 * @param bool $network_activated Whether the plugin is network-activated.
	 * @param bool $can_manage        Whether the expected capability passes.
	 */
	private function stub_access( bool $network_activated, bool $can_manage ): void {
		MonkeyFunctions\when( 'is_multisite' )->justReturn( $network_activated );
		MonkeyFunctions\when( 'is_plugin_active_for_network' )->justReturn( $network_activated );
		MonkeyFunctions\when( 'plugin_basename' )->justReturn( 'updatebee/updatebee.php' );
		MonkeyFunctions\when( 'current_user_can' )->alias(
			static function ( string $cap ) use ( $network_activated, $can_manage ): bool {
				$expected = $network_activated ? 'manage_network' : 'manage_options';
				return $can_manage && $expected === $cap;
			}
		);
	}

	/**
	 * Stub asset paths and record enqueue calls.
	 *
	 * @param array{scripts: array<int, array<int, mixed>>, localized: array<int, array<int, mixed>>} $captured Recorded calls.
	 */
	private function capture_enqueue( array &$captured ): void {
		MonkeyFunctions\when( 'plugin_dir_path' )->justReturn( '/tmp/updatebee-missing/' );
		MonkeyFunctions\when( 'plugin_dir_url' )->justReturn( 'https://example.test/wp-content/plugins/updatebee/' );
		MonkeyFunctions\when( 'wp_create_nonce' )->alias(
			static function ( string $action ): string {
				return 'nonce-' . $action;
			}
		);
		MonkeyFunctions\when( 'wp_enqueue_script' )->alias(
			static function () use ( &$captured ): void {
				$captured['scripts'][] = func_get_args();
			}
		);
		MonkeyFunctions\when( 'wp_localize_script' )->alias(
			static function () use ( &$captured ): void {
				$captured['localized'][] = func_get_args();
			}
		);
	}
}
