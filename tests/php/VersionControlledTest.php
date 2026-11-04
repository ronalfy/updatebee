<?php
/**
 * Tests for version-controlled plugins and themes.
 *
 * @package updatebee
 */

namespace UpdateBee\Tests;

use Brain\Monkey\Functions as MonkeyFunctions;
use ReflectionClass;
use UpdateBee\Functions;
use UpdateBee\Options;
use UpdateBee\Updates\Plugins;
use UpdateBee\Updates\Version_Controlled;

// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_mkdir,WordPress.WP.AlternativeFunctions.file_system_operations_rmdir,WordPress.WP.AlternativeFunctions.unlink_unlink,WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents,WordPress.WP.AlternativeFunctions.json_encode_json_encode

/**
 * Version-control detection and auto-update veto.
 */
class VersionControlledTest extends TestCase {

	/**
	 * Directories and files created by a test.
	 *
	 * @var array<int, string>
	 */
	private $created = array();

	/**
	 * Prepare a clean manifest cache.
	 */
	protected function setUp(): void {
		parent::setUp();

		if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
			define( 'WP_PLUGIN_DIR', ABSPATH . 'wp-content/plugins' );
		}

		if ( ! defined( 'WP_CONTENT_DIR' ) ) {
			define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
		}

		$GLOBALS['updatebee_test_installed_files'] = array();
		Version_Controlled::flush_cache();
		Options::flush_cache();

		MonkeyFunctions\when( 'apply_filters' )->alias(
			static function ( $hook, $value ) {
				if ( 'updatebee_composer_installed_files' === $hook ) {
					return $GLOBALS['updatebee_test_installed_files'];
				}

				return $value;
			}
		);
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
		MonkeyFunctions\when( 'sanitize_text_field' )->returnArg( 1 );
		MonkeyFunctions\when( 'sanitize_title' )->returnArg( 1 );
		MonkeyFunctions\when( 'absint' )->returnArg( 1 );
		MonkeyFunctions\when( 'add_filter' )->justReturn( true );
	}

	/**
	 * Remove fixtures and caches.
	 */
	protected function tearDown(): void {
		foreach ( array_reverse( $this->created ) as $path ) {
			$this->remove_path( $path );
		}

		unset( $GLOBALS['updatebee_test_installed_files'] );
		Version_Controlled::flush_cache();
		Options::flush_cache();
		$this->reset_plugins_singleton();
		parent::tearDown();
	}

	/**
	 * A .git directory or gitlink file is git, without reading the file.
	 */
	public function test_git_directory_and_gitlink_are_git(): void {
		$directory = $this->plugin_directory( 'vc-git' );
		mkdir( $directory . '/.git' );
		$this->assertSame( Version_Controlled::REASON_GIT, Version_Controlled::plugin_reason( 'vc-git/vc-git.php' ) );

		$link = $this->plugin_directory( 'vc-gitlink' );
		file_put_contents( $link . '/.git', 'not-a-gitdir-pointer' );
		$this->assertSame( Version_Controlled::REASON_GIT, Version_Controlled::plugin_reason( 'vc-gitlink/vc-gitlink.php' ) );
	}

	/**
	 * A .svn directory is svn.
	 */
	public function test_svn_directory_is_svn(): void {
		$directory = $this->plugin_directory( 'vc-svn' );
		mkdir( $directory . '/.svn' );

		$this->assertSame( Version_Controlled::REASON_SVN, Version_Controlled::plugin_reason( 'vc-svn/vc-svn.php' ) );
	}

	/**
	 * A checkout above the plugin directory does not mark the plugin.
	 */
	public function test_parent_git_does_not_mark_plugin(): void {
		$this->plugin_directory( 'vc-nested' );
		$parent = rtrim( WP_PLUGIN_DIR, '/\\' );
		$marker = $parent . '/.git';
		if ( ! is_dir( $marker ) && ! is_file( $marker ) ) {
			mkdir( $marker );
			$this->created[] = $marker;
		}

		$this->assertNull( Version_Controlled::plugin_reason( 'vc-nested/vc-nested.php' ) );
	}

	/**
	 * An install path wins over a checkout in the same directory.
	 */
	public function test_composer_install_path_wins_over_git(): void {
		$directory = $this->plugin_directory( 'vc-composer' );
		mkdir( $directory . '/.git' );
		$this->point_manifest_at( array( $directory ) );

		$this->assertSame( Version_Controlled::REASON_COMPOSER, Version_Controlled::plugin_reason( 'vc-composer/vc-composer.php' ) );
	}

	/**
	 * A plugin that only ships composer.json is not version-controlled.
	 */
	public function test_own_composer_json_is_not_version_controlled(): void {
		$directory = $this->plugin_directory( 'vc-own-json' );
		file_put_contents( $directory . '/composer.json', '{"name":"acme/plugin"}' );

		$this->assertNull( Version_Controlled::plugin_reason( 'vc-own-json/vc-own-json.php' ) );
	}

	/**
	 * Global auto-updates stay off for a checkout and on for a normal plugin.
	 */
	public function test_maybe_auto_update_plugin_vetoes_version_controlled_plugin(): void {
		$controlled = $this->plugin_directory( 'vc-controlled' );
		mkdir( $controlled . '/.git' );
		$this->plugin_directory( 'vc-normal' );

		MonkeyFunctions\when( 'get_site_option' )->justReturn(
			array(
				'plugin_auto_updates'        => 'on',
				'plugin_auto_updates_custom' => 'off',
			)
		);

		$plugins = Plugins::get_instance();
		$git     = (object) array(
			'plugin' => 'vc-controlled/vc-controlled.php',
		);
		$normal  = (object) array(
			'plugin' => 'vc-normal/vc-normal.php',
		);

		$this->assertFalse( $plugins->maybe_auto_update_plugin( true, $git ) );
		$this->assertTrue( $plugins->maybe_auto_update_plugin( false, $normal ) );
	}

	/**
	 * Enabling auto-updates fails, and turning them off still saves.
	 */
	public function test_set_plugin_auto_update_refuses_enable_and_allows_disable(): void {
		$directory = $this->plugin_directory( 'vc-locked' );
		mkdir( $directory . '/.git' );
		$file = 'vc-locked/vc-locked.php';

		$this->assertNull( Functions::set_plugin_auto_update( $file, true ) );

		MonkeyFunctions\when( 'get_site_option' )->justReturn(
			array(
				'auto_update_plugins'         => array( $file ),
				'auto_update_plugins_patches' => array(),
			)
		);
		MonkeyFunctions\when( 'update_site_option' )->justReturn( true );

		$saved = Functions::set_plugin_auto_update( $file, false );
		$this->assertIsArray( $saved );
		$this->assertNotContains( $file, $saved );
	}

	/**
	 * A theme install path in the same manifest is version-controlled.
	 */
	public function test_theme_install_path_is_composer(): void {
		$plugin = $this->plugin_directory( 'vc-shared' );
		$theme  = $this->theme_directory( 'vc-shared-theme' );
		$this->point_manifest_at( array( $plugin, $theme ) );

		$this->assertSame( Version_Controlled::REASON_COMPOSER, Version_Controlled::theme_reason( 'vc-shared-theme' ) );
		$this->assertSame( Version_Controlled::REASON_COMPOSER, Version_Controlled::plugin_reason( 'vc-shared/vc-shared.php' ) );
	}

	/**
	 * Create a plugin directory under WP_PLUGIN_DIR.
	 *
	 * @param string $slug Plugin directory name.
	 * @return string Absolute directory.
	 */
	private function plugin_directory( string $slug ): string {
		$directory = rtrim( WP_PLUGIN_DIR, '/\\' ) . '/' . $slug;
		$this->make_directory( $directory );

		return $directory;
	}

	/**
	 * Create a theme directory under the theme root.
	 *
	 * @param string $stylesheet Theme directory name.
	 * @return string Absolute directory.
	 */
	private function theme_directory( string $stylesheet ): string {
		$root = WP_CONTENT_DIR . '/themes';
		if ( function_exists( 'get_theme_root' ) ) {
			$root = (string) get_theme_root( $stylesheet );
		}

		$directory = rtrim( $root, '/\\' ) . '/' . $stylesheet;
		$this->make_directory( $directory );

		return $directory;
	}

	/**
	 * Point the manifest filter at these directories.
	 *
	 * @param array<int, string> $directories Absolute install paths.
	 */
	private function point_manifest_at( array $directories ): void {
		$base = sys_get_temp_dir() . '/updatebee-installed-' . uniqid( '', true ) . '/vendor/composer';
		$this->make_directory( $base );

		$packages = array();
		foreach ( $directories as $directory ) {
			$packages[] = array(
				'name'         => 'acme/' . basename( $directory ),
				'install-path' => $this->relative_path( $base, $directory ),
			);
		}

		$file = $base . '/installed.json';
		file_put_contents(
			$file,
			(string) json_encode(
				array(
					'packages' => $packages,
				)
			)
		);
		$this->created[]                           = dirname( $base, 2 );
		$GLOBALS['updatebee_test_installed_files'] = array( $file );
		Version_Controlled::flush_cache();
	}

	/**
	 * Relative path from a Composer manifest directory to an install path.
	 *
	 * @param string $from Directory that will contain installed.json.
	 * @param string $to   Install directory.
	 * @return string Relative path.
	 */
	private function relative_path( string $from, string $to ): string {
		$from_real  = realpath( $from );
		$to_real    = realpath( $to );
		$from_parts = explode( '/', trim( (string) $from_real, '/' ) );
		$to_parts   = explode( '/', trim( (string) $to_real, '/' ) );

		while ( $from_parts && $to_parts && $from_parts[0] === $to_parts[0] ) {
			array_shift( $from_parts );
			array_shift( $to_parts );
		}

		return str_repeat( '../', count( $from_parts ) ) . implode( '/', $to_parts );
	}

	/**
	 * Create a directory and remember it for cleanup.
	 *
	 * @param string $directory Absolute path.
	 */
	private function make_directory( string $directory ): void {
		if ( ! is_dir( $directory ) ) {
			mkdir( $directory, 0777, true );
			$this->created[] = $directory;
		}
	}

	/**
	 * Delete a file or directory tree.
	 *
	 * @param string $path Absolute path.
	 */
	private function remove_path( string $path ): void {
		if ( is_link( $path ) || is_file( $path ) ) {
			unlink( $path );
			return;
		}

		if ( ! is_dir( $path ) ) {
			return;
		}

		$items = scandir( $path );
		if ( is_array( $items ) ) {
			foreach ( $items as $item ) {
				if ( '.' === $item || '..' === $item ) {
					continue;
				}

				$this->remove_path( $path . '/' . $item );
			}
		}

		rmdir( $path );
	}

	/**
	 * Clear the plugins update singleton.
	 */
	private function reset_plugins_singleton(): void {
		$reflection = new ReflectionClass( Plugins::class );
		$property   = $reflection->getProperty( 'instance' );
		$property->setAccessible( true );
		$property->setValue( null, null );
	}
}
