<?php
/**
 * Roll WordPress.org plugins and themes back to an older directory release.
 *
 * @package updatebee
 */

namespace UpdateBee\Updates;

use UpdateBee\Functions;

/**
 * List older directory releases and install one through the core upgrader.
 */
class Rollback {

	/**
	 * Plugin package overlay for the current rollback install.
	 *
	 * @var array{file: string, slug: string, version: string, package: string}|null
	 */
	private static $plugin_offer = null;

	/**
	 * Theme package overlay for the current rollback install.
	 *
	 * @var array{file: string, slug: string, version: string, package: string}|null
	 */
	private static $theme_offer = null;

	/**
	 * Whether a plugin can be rolled back to a WordPress.org release.
	 *
	 * @param string $file                Plugin basename.
	 * @param bool   $version_controlled  Whether version control owns the plugin.
	 * @param string $slug                Directory slug from the update transient.
	 * @param string $directory_id        Update id, such as w.org/plugins/akismet.
	 * @return bool
	 */
	public static function plugin_is_eligible( string $file, bool $version_controlled, string $slug, string $directory_id ): bool {
		if ( $version_controlled || ! self::is_directory_plugin_file( $file ) || ! self::is_directory_slug( $slug ) ) {
			return false;
		}

		return self::is_wordpress_org_id( $directory_id );
	}

	/**
	 * Whether a theme can be rolled back to a WordPress.org release.
	 *
	 * @param string $stylesheet         Theme stylesheet.
	 * @param bool   $version_controlled Whether version control owns the theme.
	 * @param string $directory_id       Update id, such as w.org/themes/twentytwentyfour.
	 * @param string $url                Theme directory URL.
	 * @return bool
	 */
	public static function theme_is_eligible( string $stylesheet, bool $version_controlled, string $directory_id, string $url ): bool {
		if ( $version_controlled || ! self::is_directory_slug( $stylesheet ) ) {
			return false;
		}

		if ( self::is_wordpress_org_id( $directory_id ) ) {
			return true;
		}

		return (bool) preg_match( '#WordPress\.org/themes#i', $url );
	}

	/**
	 * Installed WordPress.org plugin identity, when rollback is allowed.
	 *
	 * @param string $file Plugin basename.
	 * @return array{slug: string, id: string}|null
	 */
	public static function plugin_context( string $file ): ?array {
		if ( ! Functions::plugin_file_exists( $file ) ) {
			return null;
		}

		$meta = self::plugin_directory_meta( $file );
		if ( ! self::plugin_is_eligible( $file, Version_Controlled::is_plugin( $file ), $meta['slug'], $meta['id'] ) ) {
			return null;
		}

		return $meta;
	}

	/**
	 * Installed WordPress.org theme identity, when rollback is allowed.
	 *
	 * @param string $stylesheet Theme stylesheet.
	 * @return array{slug: string, id: string, url: string}|null
	 */
	public static function theme_context( string $stylesheet ): ?array {
		if ( ! Functions::theme_stylesheet_exists( $stylesheet ) ) {
			return null;
		}

		$meta = self::theme_directory_meta( $stylesheet );
		if ( ! self::theme_is_eligible( $stylesheet, Version_Controlled::is_theme( $stylesheet ), $meta['id'], $meta['url'] ) ) {
			return null;
		}

		return array(
			'slug' => $stylesheet,
			'id'   => $meta['id'],
			'url'  => $meta['url'],
		);
	}

	/**
	 * Older directory versions, newest first.
	 *
	 * Trunk, the installed version, newer releases, and packages outside downloads.wordpress.org are omitted.
	 *
	 * @param array<mixed> $versions Version to package URL map from the directory API.
	 * @param string       $current  Installed version.
	 * @return array<int, string>
	 */
	public static function previous_versions( array $versions, string $current ): array {
		if ( '' === $current ) {
			return array();
		}

		$older = array();
		foreach ( $versions as $version => $url ) {
			if ( is_int( $version ) ) {
				$version = (string) $version;
			}
			if ( ! is_string( $version ) || ! is_string( $url ) ) {
				continue;
			}

			$version = trim( $version );
			if ( '' === $version || 'trunk' === strtolower( $version ) || ! self::package_url_is_allowed( $url ) ) {
				continue;
			}

			if ( version_compare( $version, $current, '>=' ) ) {
				continue;
			}

			$older[] = $version;
		}

		usort(
			$older,
			static function ( string $left, string $right ): int {
				return version_compare( $right, $left );
			}
		);

		return $older;
	}

	/**
	 * Package URL for one directory version.
	 *
	 * @param array<mixed> $versions Version to package URL map.
	 * @param string       $version  Requested version.
	 * @return string|null Zip URL, or null when the version cannot be installed.
	 */
	public static function package_for_version( array $versions, string $version ): ?string {
		$version = trim( $version );
		if ( '' === $version || 'trunk' === strtolower( $version ) || ! isset( $versions[ $version ] ) || ! is_string( $versions[ $version ] ) ) {
			return null;
		}

		$url = $versions[ $version ];

		return self::package_url_is_allowed( $url ) ? $url : null;
	}

	/**
	 * Whether a package URL is an HTTPS zip on downloads.wordpress.org.
	 *
	 * @param string $url Package URL.
	 * @return bool
	 */
	public static function package_url_is_allowed( string $url ): bool {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return false;
		}

		$scheme = isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : '';
		$host   = isset( $parts['host'] ) ? strtolower( (string) $parts['host'] ) : '';
		$path   = isset( $parts['path'] ) ? (string) $parts['path'] : '';

		if ( 'https' !== $scheme || 'downloads.wordpress.org' !== $host ) {
			return false;
		}

		return (bool) preg_match( '/\.zip$/i', $path );
	}

	/**
	 * Version map from the WordPress.org plugin API.
	 *
	 * @param string $slug Plugin slug.
	 * @return array<string, string>|null Version to zip URL, or null when the request fails.
	 */
	public static function plugin_version_map( string $slug ): ?array {
		if ( ! self::is_directory_slug( $slug ) ) {
			return null;
		}

		if ( ! function_exists( 'plugins_api' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		}

		$api = plugins_api(
			'plugin_information',
			array(
				'slug'   => $slug,
				'fields' => self::version_fields(),
			)
		);

		if ( is_wp_error( $api ) || ! is_object( $api ) ) {
			return null;
		}

		if ( ! isset( $api->versions ) ) {
			return array();
		}

		return self::normalize_version_map( $api->versions );
	}

	/**
	 * Version map from the WordPress.org theme API.
	 *
	 * @param string $slug Theme slug.
	 * @return array<string, string>|null Version to zip URL, or null when the request fails.
	 */
	public static function theme_version_map( string $slug ): ?array {
		if ( ! self::is_directory_slug( $slug ) ) {
			return null;
		}

		if ( ! function_exists( 'themes_api' ) ) {
			require_once ABSPATH . 'wp-admin/includes/theme.php';
		}

		$api = themes_api(
			'theme_information',
			array(
				'slug'   => $slug,
				'fields' => self::version_fields(),
			)
		);

		if ( is_wp_error( $api ) || ! is_object( $api ) ) {
			return null;
		}

		if ( ! isset( $api->versions ) ) {
			return array();
		}

		return self::normalize_version_map( $api->versions );
	}

	/**
	 * Overlay one plugin package while a callback runs.
	 *
	 * The object passed into the transient filter is cloned. The stored transient is left unchanged.
	 *
	 * @param string   $file     Plugin basename.
	 * @param string   $slug     WordPress.org slug.
	 * @param string   $version  Version to install.
	 * @param string   $package  downloads.wordpress.org zip URL.
	 * @param callable $callback Upgrade callback.
	 * @return mixed Callback result.
	 */
	public static function with_plugin_offer( string $file, string $slug, string $version, string $package, callable $callback ) {
		$previous           = self::$plugin_offer;
		self::$plugin_offer = array(
			'file'    => $file,
			'slug'    => $slug,
			'version' => $version,
			'package' => $package,
		);

		add_filter( 'site_transient_update_plugins', array( self::class, 'filter_plugin_transient' ) );

		try {
			return $callback();
		} finally {
			remove_filter( 'site_transient_update_plugins', array( self::class, 'filter_plugin_transient' ) );
			self::$plugin_offer = $previous;
		}
	}

	/**
	 * Overlay one theme package while a callback runs.
	 *
	 * @param string   $stylesheet Theme stylesheet.
	 * @param string   $slug       WordPress.org slug.
	 * @param string   $version    Version to install.
	 * @param string   $package    downloads.wordpress.org zip URL.
	 * @param callable $callback   Upgrade callback.
	 * @return mixed Callback result.
	 */
	public static function with_theme_offer( string $stylesheet, string $slug, string $version, string $package, callable $callback ) {
		$previous          = self::$theme_offer;
		self::$theme_offer = array(
			'file'    => $stylesheet,
			'slug'    => $slug,
			'version' => $version,
			'package' => $package,
		);

		add_filter( 'site_transient_update_themes', array( self::class, 'filter_theme_transient' ) );

		try {
			return $callback();
		} finally {
			remove_filter( 'site_transient_update_themes', array( self::class, 'filter_theme_transient' ) );
			self::$theme_offer = $previous;
		}
	}

	/**
	 * Replace one plugin offer while a rollback install is running.
	 *
	 * @param mixed $transient Update transient.
	 * @return mixed
	 */
	public static function filter_plugin_transient( $transient ) {
		if ( ! is_array( self::$plugin_offer ) ) {
			return $transient;
		}

		return self::overlay_plugin_transient( $transient, self::$plugin_offer );
	}

	/**
	 * Replace one theme offer while a rollback install is running.
	 *
	 * @param mixed $transient Update transient.
	 * @return mixed
	 */
	public static function filter_theme_transient( $transient ) {
		if ( ! is_array( self::$theme_offer ) ) {
			return $transient;
		}

		return self::overlay_theme_transient( $transient, self::$theme_offer );
	}

	/**
	 * Directory slug and id stored for an installed plugin.
	 *
	 * @param string $file Plugin basename.
	 * @return array{slug: string, id: string}
	 */
	private static function plugin_directory_meta( string $file ): array {
		$extra = self::update_extra( 'update_plugins', $file );
		$slug  = isset( $extra['slug'] ) && is_string( $extra['slug'] ) ? $extra['slug'] : '';
		$id    = isset( $extra['id'] ) && is_string( $extra['id'] ) ? $extra['id'] : '';

		return array(
			'slug' => $slug,
			'id'   => $id,
		);
	}

	/**
	 * Directory id and URL stored for an installed theme.
	 *
	 * @param string $stylesheet Theme stylesheet.
	 * @return array{id: string, url: string}
	 */
	private static function theme_directory_meta( string $stylesheet ): array {
		$extra = self::update_extra( 'update_themes', $stylesheet );
		$id    = isset( $extra['id'] ) && is_string( $extra['id'] ) ? $extra['id'] : '';
		$url   = isset( $extra['url'] ) && is_string( $extra['url'] ) ? $extra['url'] : '';

		return array(
			'id'  => $id,
			'url' => $url,
		);
	}

	/**
	 * Update-transient row for one plugin file or theme stylesheet.
	 *
	 * @param string $transient_name update_plugins or update_themes.
	 * @param string $item           Plugin basename or theme stylesheet.
	 * @return array<string, mixed>
	 */
	private static function update_extra( string $transient_name, string $item ): array {
		$updates = Hide_Blocked_Updates::unfiltered(
			static function () use ( $transient_name ) {
				return get_site_transient( $transient_name );
			}
		);

		$extra = null;
		if ( is_object( $updates ) ) {
			if ( isset( $updates->response[ $item ] ) ) {
				$extra = $updates->response[ $item ];
			} elseif ( isset( $updates->no_update[ $item ] ) ) {
				$extra = $updates->no_update[ $item ];
			}
		}

		if ( is_object( $extra ) ) {
			$extra = (array) $extra;
		}

		return is_array( $extra ) ? $extra : array();
	}

	/**
	 * Clone a plugin transient and point one plugin at a directory package.
	 *
	 * @param mixed                                                               $transient Stored transient.
	 * @param array{file: string, slug: string, version: string, package: string} $offer Overlay.
	 * @return object
	 */
	private static function overlay_plugin_transient( $transient, array $offer ) {
		if ( is_object( $transient ) ) {
			$transient = clone $transient;
		} else {
			$transient = new \stdClass();
		}

		$response = array();
		if ( isset( $transient->response ) && is_array( $transient->response ) ) {
			$response = $transient->response;
		}

		$current = $response[ $offer['file'] ] ?? null;
		if ( is_object( $current ) ) {
			$row = clone $current;
		} elseif ( is_array( $current ) ) {
			$row = (object) $current;
		} else {
			$row      = new \stdClass();
			$row->id  = 'w.org/plugins/' . $offer['slug'];
			$row->url = 'https://wordpress.org/plugins/' . $offer['slug'] . '/';
		}

		$row->slug                  = $offer['slug'];
		$row->plugin                = $offer['file'];
		$row->new_version           = $offer['version'];
		$row->package               = $offer['package'];
		$response[ $offer['file'] ] = $row;
		$transient->response        = $response;

		return $transient;
	}

	/**
	 * Clone a theme transient and point one theme at a directory package.
	 *
	 * @param mixed                                                               $transient Stored transient.
	 * @param array{file: string, slug: string, version: string, package: string} $offer Overlay.
	 * @return object
	 */
	private static function overlay_theme_transient( $transient, array $offer ) {
		if ( is_object( $transient ) ) {
			$transient = clone $transient;
		} else {
			$transient = new \stdClass();
		}

		$response = array();
		if ( isset( $transient->response ) && is_array( $transient->response ) ) {
			$response = $transient->response;
		}

		$current = $response[ $offer['file'] ] ?? null;
		if ( is_array( $current ) ) {
			$row = $current;
		} elseif ( is_object( $current ) ) {
			$row = (array) $current;
		} else {
			$row = array(
				'url' => 'https://wordpress.org/themes/' . $offer['slug'] . '/',
			);
		}

		$row['theme']               = $offer['file'];
		$row['new_version']         = $offer['version'];
		$row['package']             = $offer['package'];
		$response[ $offer['file'] ] = $row;
		$transient->response        = $response;

		return $transient;
	}

	/**
	 * Keep string version keys and string package URLs.
	 *
	 * @param mixed $versions Raw versions payload.
	 * @return array<string, string>
	 */
	private static function normalize_version_map( $versions ): array {
		if ( is_object( $versions ) ) {
			$versions = (array) $versions;
		}
		if ( ! is_array( $versions ) ) {
			return array();
		}

		$map = array();
		foreach ( $versions as $version => $url ) {
			if ( is_int( $version ) ) {
				$version = (string) $version;
			}
			if ( ! is_string( $version ) || ! is_string( $url ) ) {
				continue;
			}
			$map[ $version ] = $url;
		}

		return $map;
	}

	/**
	 * Fields requested from the plugin and theme information APIs.
	 *
	 * @return array<string, bool>
	 */
	private static function version_fields(): array {
		return array(
			'versions'          => true,
			'sections'          => false,
			'description'       => false,
			'short_description' => false,
			'rating'            => false,
			'ratings'           => false,
			'downloaded'        => false,
			'downloadlink'      => false,
			'last_updated'      => false,
			'added'             => false,
			'tags'              => false,
			'compatibility'     => false,
			'homepage'          => false,
			'donate_link'       => false,
			'reviews'           => false,
			'banners'           => false,
			'icons'             => false,
			'active_installs'   => false,
			'contributors'      => false,
			'requires'          => false,
			'requires_php'      => false,
			'tested'            => false,
		);
	}

	/**
	 * Whether a plugin basename is a directory plugin core can replace.
	 *
	 * @param string $file Plugin basename.
	 * @return bool
	 */
	private static function is_directory_plugin_file( string $file ): bool {
		if ( ! str_contains( $file, '/' ) || str_contains( $file, '..' ) || str_starts_with( $file, '/' ) ) {
			return false;
		}

		return '.' !== dirname( $file );
	}

	/**
	 * Whether a slug is safe to send to the WordPress.org info API.
	 *
	 * @param string $slug Plugin or theme slug.
	 * @return bool
	 */
	private static function is_directory_slug( string $slug ): bool {
		return (bool) preg_match( '/^[a-z0-9][a-z0-9-]*$/', $slug );
	}

	/**
	 * Whether an update id points at WordPress.org.
	 *
	 * @param string $directory_id Update id.
	 * @return bool
	 */
	private static function is_wordpress_org_id( string $directory_id ): bool {
		return '' !== $directory_id && (bool) preg_match( '/w\.org/i', $directory_id );
	}
}
