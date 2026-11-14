<?php
/**
 * Plugins and themes that version control owns.
 *
 * @package updatebee
 */

namespace UpdateBee\Updates;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detect Composer packages and plugin or theme checkouts.
 */
class Version_Controlled {

	/**
	 * Composer installed this directory.
	 */
	public const REASON_COMPOSER = 'composer';

	/**
	 * This directory is a Git checkout.
	 */
	public const REASON_GIT = 'git';

	/**
	 * This directory is a Subversion checkout.
	 */
	public const REASON_SVN = 'svn';

	/**
	 * This directory is a Mercurial checkout.
	 */
	public const REASON_HG = 'hg';

	/**
	 * How many parent directories to search for installed.json.
	 */
	private const MANIFEST_WALK_LIMIT = 5;

	/**
	 * Real Composer install paths, keyed by path.
	 *
	 * @var array<string, true>|null
	 */
	private static $install_paths = null;

	/**
	 * Forget the manifest cache for this request.
	 */
	public static function flush_cache(): void {
		self::$install_paths = null;
	}

	/**
	 * Why a plugin is version-controlled.
	 *
	 * @param string $file Plugin basename path.
	 * @return string|null composer, git, svn, hg, or null.
	 */
	public static function plugin_reason( string $file ): ?string {
		return self::reason_for_directory( self::plugin_directory( $file ) );
	}

	/**
	 * Why a theme is version-controlled.
	 *
	 * @param string $stylesheet Theme directory name.
	 * @return string|null composer, git, svn, hg, or null.
	 */
	public static function theme_reason( string $stylesheet ): ?string {
		return self::reason_for_directory( self::theme_directory( $stylesheet ) );
	}

	/**
	 * Whether a plugin is version-controlled.
	 *
	 * @param string $file Plugin basename path.
	 * @return bool True when automatic updates must stay off.
	 */
	public static function is_plugin( string $file ): bool {
		return null !== self::plugin_reason( $file );
	}

	/**
	 * Whether a theme is version-controlled.
	 *
	 * @param string $stylesheet Theme directory name.
	 * @return bool True when automatic updates must stay off.
	 */
	public static function is_theme( string $stylesheet ): bool {
		return null !== self::theme_reason( $stylesheet );
	}

	/**
	 * Keep a version-controlled plugin from auto-updating.
	 *
	 * @param bool   $update Whether to update.
	 * @param object $item   Plugin update item.
	 * @return bool False when the plugin is version-controlled.
	 */
	public static function veto_plugin( $update, $item ) {
		if ( ! is_object( $item ) || ! isset( $item->plugin ) ) {
			return $update;
		}

		if ( self::is_plugin( (string) $item->plugin ) ) {
			return false;
		}

		return $update;
	}

	/**
	 * Keep a version-controlled theme from auto-updating.
	 *
	 * @param bool   $update Whether to update.
	 * @param object $item   Theme update item.
	 * @return bool False when the theme is version-controlled.
	 */
	public static function veto_theme( $update, $item ) {
		if ( ! is_object( $item ) || ! isset( $item->theme ) ) {
			return $update;
		}

		if ( self::is_theme( (string) $item->theme ) ) {
			return false;
		}

		return $update;
	}

	/**
	 * Directory that holds a plugin's files.
	 *
	 * Single-file plugins have no checkout directory.
	 *
	 * @param string $file Plugin basename path.
	 * @return string Absolute path, or an empty string.
	 */
	public static function plugin_directory( string $file ): string {
		$file = str_replace( '\\', '/', $file );
		$file = ltrim( $file, '/' );

		if ( '' === $file || str_contains( $file, '..' ) || ! defined( 'WP_PLUGIN_DIR' ) ) {
			return '';
		}

		$dir = dirname( $file );
		if ( '.' === $dir || '' === $dir ) {
			return '';
		}

		return rtrim( WP_PLUGIN_DIR, '/\\' ) . '/' . $dir;
	}

	/**
	 * Directory that holds a theme's files.
	 *
	 * @param string $stylesheet Theme directory name.
	 * @return string Absolute path, or an empty string.
	 */
	public static function theme_directory( string $stylesheet ): string {
		$stylesheet = trim( str_replace( '\\', '/', $stylesheet ), '/' );
		if ( '' === $stylesheet || str_contains( $stylesheet, '/' ) || str_contains( $stylesheet, '..' ) ) {
			return '';
		}

		$root = '';
		if ( function_exists( 'get_theme_root' ) ) {
			$root = (string) get_theme_root( $stylesheet );
		} elseif ( defined( 'WP_CONTENT_DIR' ) ) {
			$root = WP_CONTENT_DIR . '/themes';
		}

		if ( '' === $root ) {
			return '';
		}

		return rtrim( $root, '/\\' ) . '/' . $stylesheet;
	}

	/**
	 * Classify one directory.
	 *
	 * A missing directory is not version-controlled. Composer wins when the
	 * directory is also a checkout, because Composer still owns the files.
	 *
	 * @param string $path Plugin or theme directory.
	 * @return string|null Reason, or null when WordPress may manage it.
	 */
	private static function reason_for_directory( string $path ): ?string {
		if ( '' === $path || ! is_dir( $path ) ) {
			return null;
		}

		$real = realpath( $path );
		if ( is_string( $real ) && isset( self::install_paths()[ $real ] ) ) {
			return self::REASON_COMPOSER;
		}

		return self::checkout_reason( is_string( $real ) ? $real : $path );
	}

	/**
	 * Checkout marker inside this directory only.
	 *
	 * A site-root checkout above this directory is ignored.
	 *
	 * @param string $directory Plugin or theme directory.
	 * @return string|null git, svn, hg, or null.
	 */
	private static function checkout_reason( string $directory ): ?string {
		$directory = rtrim( $directory, '/\\' );

		if ( is_dir( $directory . '/.git' ) || is_file( $directory . '/.git' ) ) {
			return self::REASON_GIT;
		}

		if ( is_dir( $directory . '/.svn' ) ) {
			return self::REASON_SVN;
		}

		if ( is_dir( $directory . '/.hg' ) ) {
			return self::REASON_HG;
		}

		return null;
	}

	/**
	 * Real paths Composer installed, keyed for lookup.
	 *
	 * @return array<string, true> Install paths.
	 */
	private static function install_paths(): array {
		if ( null !== self::$install_paths ) {
			return self::$install_paths;
		}

		self::$install_paths = array();
		foreach ( self::manifest_files() as $file ) {
			foreach ( self::paths_from_manifest( $file ) as $path ) {
				self::$install_paths[ $path ] = true;
			}
		}

		return self::$install_paths;
	}

	/**
	 * Installed.json files for this site.
	 *
	 * @return array<int, string> Absolute paths.
	 */
	private static function manifest_files(): array {
		$files = self::discover_manifest_files();
		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filters the Composer installed.json files UpdateBee reads.
			 *
			 * This is the only file whose contents are read. A plugin's own
			 * composer.json is not a manifest.
			 *
			 * @since 1.0.0
			 *
			 * @param array<int, string> $files Absolute paths to installed.json files.
			 */
			$filtered = apply_filters( 'updatebee_composer_installed_files', $files );
			if ( is_array( $filtered ) ) {
				$files = $filtered;
			}
		}

		$manifests = array();
		foreach ( $files as $file ) {
			if ( ! is_string( $file ) || '' === $file ) {
				continue;
			}

			$real = realpath( $file );
			if ( ! is_string( $real ) || ! is_readable( $real ) || 'installed.json' !== basename( $real ) ) {
				continue;
			}

			$manifests[] = $real;
		}

		return array_values( array_unique( $manifests ) );
	}

	/**
	 * Look above WordPress for vendor/composer/installed.json.
	 *
	 * @return array<int, string> Candidate paths. They may not exist.
	 */
	private static function discover_manifest_files(): array {
		$roots = array();
		if ( defined( 'ABSPATH' ) ) {
			$roots[] = ABSPATH;
		}
		if ( defined( 'WP_CONTENT_DIR' ) ) {
			$roots[] = WP_CONTENT_DIR;
		}

		$found = array();
		$seen  = array();
		foreach ( $roots as $root ) {
			$current = rtrim( str_replace( '\\', '/', (string) $root ), '/' );
			for ( $level = 0; $level < self::MANIFEST_WALK_LIMIT; $level++ ) {
				if ( '' === $current || isset( $seen[ $current ] ) ) {
					break;
				}

				$seen[ $current ] = true;
				$found[]          = $current . '/vendor/composer/installed.json';

				$parent = dirname( $current );
				if ( $parent === $current ) {
					break;
				}

				$current = $parent;
			}
		}

		return $found;
	}

	/**
	 * Install paths recorded in one Composer manifest.
	 *
	 * Supports Composer 2's packages list and Composer 1's flat package list.
	 *
	 * @param string $file Absolute path to installed.json.
	 * @return array<int, string> Real install paths.
	 */
	private static function paths_from_manifest( string $file ): array {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- installed.json is the only file UpdateBee reads for Composer ownership.
		$raw = file_get_contents( $file );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return array();
		}

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			return array();
		}

		if ( isset( $data['packages'] ) && is_array( $data['packages'] ) ) {
			$packages = $data['packages'];
		} elseif ( array_is_list( $data ) ) {
			$packages = $data;
		} else {
			return array();
		}

		$base  = dirname( $file );
		$paths = array();
		foreach ( $packages as $package ) {
			if ( ! is_array( $package ) ) {
				continue;
			}

			$relative = '';
			if ( isset( $package['install-path'] ) && is_string( $package['install-path'] ) ) {
				$relative = $package['install-path'];
			}

			$resolved = self::resolve_install_path( $base, $relative );
			if ( null !== $resolved ) {
				$paths[] = $resolved;
			}
		}

		return $paths;
	}

	/**
	 * Resolve a Composer install path against vendor/composer.
	 *
	 * @param string $base     Directory that contains installed.json.
	 * @param string $relative Install path from the manifest.
	 * @return string|null Real path, or null when it does not exist.
	 */
	private static function resolve_install_path( string $base, string $relative ): ?string {
		$relative = trim( str_replace( '\\', '/', $relative ) );
		if ( '' === $relative ) {
			return null;
		}

		$base = rtrim( str_replace( '\\', '/', $base ), '/' );
		if ( self::is_absolute_path( $relative ) ) {
			$joined = $relative;
		} else {
			$joined = $base . '/' . $relative;
		}

		$real = realpath( $joined );
		if ( ! is_string( $real ) ) {
			return null;
		}

		return $real;
	}

	/**
	 * Whether a path starts at the filesystem root or a Windows drive.
	 *
	 * @param string $path Path from a Composer manifest.
	 * @return bool True when the path is absolute.
	 */
	private static function is_absolute_path( string $path ): bool {
		if ( '' === $path ) {
			return false;
		}

		if ( '/' === $path[0] ) {
			return true;
		}

		return 1 === preg_match( '/^[A-Za-z]:\//', $path );
	}
}
