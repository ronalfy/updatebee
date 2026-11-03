<?php
/**
 * Skip one offered plugin or theme version.
 *
 * @package updatebee
 */

namespace UpdateBee\Updates;

use UpdateBee\Functions;
use UpdateBee\Options;

/**
 * Veto automatic updates for an exact offered version.
 */
class Skip_Version {

	/**
	 * Option key for skipped plugin versions.
	 *
	 * @var string
	 */
	public const PLUGIN_KEY = 'skipped_plugin_versions';

	/**
	 * Option key for skipped theme versions.
	 *
	 * @var string
	 */
	public const THEME_KEY = 'skipped_theme_versions';

	/**
	 * Skip_Version instance.
	 *
	 * @var Skip_Version|null
	 */
	private static $instance = null;

	/**
	 * Return a class instance.
	 *
	 * @return Skip_Version
	 */
	public static function get_instance(): Skip_Version {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Class constructor.
	 */
	private function __construct() {
		$priority = Functions::get_highest_priority( 1 );

		add_filter( 'auto_update_plugin', array( $this, 'auto_update_plugin' ), $priority, 2 );
		add_filter( 'auto_update_theme', array( $this, 'auto_update_theme' ), $priority, 2 );
	}

	/**
	 * Keep an explicit plugin skip after other auto-update filters.
	 *
	 * @param bool   $update Whether to update.
	 * @param object $item   Plugin update item.
	 * @return bool False when this offered version is skipped.
	 */
	public function auto_update_plugin( $update, $item ) {
		if ( ! is_object( $item ) || ! isset( $item->plugin ) ) {
			return $update;
		}

		$version = isset( $item->new_version ) ? (string) $item->new_version : '';
		if ( self::is_skipped( 'plugin', (string) $item->plugin, $version ) ) {
			return false;
		}

		return $update;
	}

	/**
	 * Keep an explicit theme skip after other auto-update filters.
	 *
	 * @param bool   $update Whether to update.
	 * @param object $item   Theme update item.
	 * @return bool False when this offered version is skipped.
	 */
	public function auto_update_theme( $update, $item ) {
		if ( ! is_object( $item ) || ! isset( $item->theme ) ) {
			return $update;
		}

		$version = isset( $item->new_version ) ? (string) $item->new_version : '';
		if ( self::is_skipped( 'theme', (string) $item->theme, $version ) ) {
			return false;
		}

		return $update;
	}

	/**
	 * Whether this exact version is skipped for an asset.
	 *
	 * @param string $type    plugin or theme.
	 * @param string $id      Plugin file or theme stylesheet.
	 * @param string $version Offered version.
	 * @return bool True when the stored skip matches.
	 */
	public static function is_skipped( string $type, string $id, string $version ): bool {
		if ( '' === $id || '' === $version ) {
			return false;
		}

		$map = self::map( $type );

		return isset( $map[ $id ] ) && $map[ $id ] === $version;
	}

	/**
	 * Store or clear a skipped version.
	 *
	 * @param string $type    plugin or theme.
	 * @param string $id      Plugin file or theme stylesheet.
	 * @param string $version Offered version. Ignored when clearing.
	 * @param bool   $enabled True to skip this version, false to clear it.
	 * @return bool True when the option was saved.
	 */
	public static function set_skipped( string $type, string $id, string $version, bool $enabled ): bool {
		$key = self::option_key( $type );
		if ( '' === $key || '' === $id ) {
			return false;
		}

		if ( $enabled && '' === $version ) {
			return false;
		}

		$map = self::map( $type );
		if ( $enabled ) {
			$map[ $id ] = $version;
		} else {
			unset( $map[ $id ] );
		}

		return null !== Options::update_options( $key, $map );
	}

	/**
	 * Option key for a skip map.
	 *
	 * @param string $type plugin or theme.
	 * @return string Option key, or an empty string when the type is unknown.
	 */
	private static function option_key( string $type ): string {
		if ( 'plugin' === $type ) {
			return self::PLUGIN_KEY;
		}

		if ( 'theme' === $type ) {
			return self::THEME_KEY;
		}

		return '';
	}

	/**
	 * Stored skip map of asset id to version.
	 *
	 * @param string $type plugin or theme.
	 * @return array<string, string> Skip map.
	 */
	private static function map( string $type ): array {
		$key = self::option_key( $type );
		if ( '' === $key ) {
			return array();
		}

		$stored = Options::get_options( $key );
		if ( ! is_array( $stored ) ) {
			return array();
		}

		$map = array();
		foreach ( $stored as $id => $version ) {
			if ( ! is_string( $id ) || '' === $id || ! is_string( $version ) || '' === $version ) {
				continue;
			}

			$map[ $id ] = $version;
		}

		return $map;
	}
}
