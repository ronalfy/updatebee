<?php
/**
 * Admin command palette actions.
 *
 * @package updatebee
 */

namespace UpdateBee\Admin;

use UpdateBee\Functions;

/**
 * Register UpdateBee commands for the wp-admin command palette.
 */
class Command_Palette {

	/**
	 * Script handle.
	 */
	private const SCRIPT_HANDLE = 'updatebee-commands';

	/**
	 * Style handle.
	 */
	private const STYLE_HANDLE = 'updatebee-commands';

	/**
	 * Localized object name.
	 */
	private const OBJECT_NAME = 'updatebeeCommands';

	/**
	 * Class runner.
	 *
	 * The enqueue hook only fires in wp-admin.
	 */
	public function run(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue the command script for users who can refresh or force updates.
	 */
	public function enqueue_assets(): void {
		if ( ! Functions::can_access_options() ) {
			return;
		}

		$asset = array(
			'dependencies' => array(
				'wp-commands',
				'wp-components',
				'wp-element',
				'wp-i18n',
			),
			'version'      => Functions::get_plugin_version(),
		);

		$asset_file = Functions::get_plugin_dir( 'build/commands.asset.php' );
		if ( file_exists( $asset_file ) ) {
			$loaded = require $asset_file;
			if ( is_array( $loaded ) ) {
				$asset = wp_parse_args( $loaded, $asset );
			}
		}

		$version = isset( $asset['version'] ) && is_string( $asset['version'] )
			? $asset['version']
			: Functions::get_plugin_version();

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			Functions::get_plugin_url( 'build/commands.js' ),
			self::get_script_dependencies( $asset ),
			$version,
			true
		);
		wp_localize_script(
			self::SCRIPT_HANDLE,
			self::OBJECT_NAME,
			array(
				'forceUpdatesNonce'   => wp_create_nonce( 'updatebee-force-updates' ),
				'refreshUpdatesNonce' => wp_create_nonce( 'updatebee-refresh-updates' ),
			)
		);

		$style_path = Functions::get_plugin_dir( 'build/commands.css' );
		if ( ! file_exists( $style_path ) ) {
			return;
		}

		wp_enqueue_style(
			self::STYLE_HANDLE,
			Functions::get_plugin_url( 'build/commands.css' ),
			array( 'wp-components' ),
			$version
		);
	}

	/**
	 * Script dependencies, always including the command palette package.
	 *
	 * @param array<string, mixed> $asset Asset file data.
	 * @return string[] Script handles.
	 */
	private static function get_script_dependencies( array $asset ): array {
		$dependencies = array( 'wp-commands' );
		if ( ! isset( $asset['dependencies'] ) || ! is_array( $asset['dependencies'] ) ) {
			return $dependencies;
		}

		foreach ( $asset['dependencies'] as $dependency ) {
			if ( ! is_string( $dependency ) || in_array( $dependency, $dependencies, true ) ) {
				continue;
			}
			$dependencies[] = $dependency;
		}

		return $dependencies;
	}
}
