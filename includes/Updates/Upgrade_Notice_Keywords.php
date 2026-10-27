<?php
/**
 * Force automatic updates when an upgrade notice matches configured keywords.
 *
 * @package updatebee
 */

namespace UpdateBee\Updates;

use UpdateBee\Functions;
use UpdateBee\Options;

/**
 * Search an update item's upgrade notice for configured keywords.
 */
class Upgrade_Notice_Keywords {

	/**
	 * Upgrade_Notice_Keywords instance.
	 *
	 * @var Upgrade_Notice_Keywords|null
	 */
	private static $instance = null;

	/**
	 * Return a class instance.
	 *
	 * @return Upgrade_Notice_Keywords
	 */
	public static function get_instance(): Upgrade_Notice_Keywords {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Class constructor.
	 */
	private function __construct() {
		$priority = Functions::get_highest_priority( 6 );

		add_filter( 'auto_update_plugin', array( $this, 'auto_update_plugin' ), $priority, 2 );
		add_filter( 'auto_update_theme', array( $this, 'auto_update_theme' ), $priority, 2 );
	}

	/**
	 * Force a plugin auto-update when the upgrade notice matches a keyword.
	 *
	 * @param bool   $update Whether to update.
	 * @param object $item   Plugin update item.
	 * @return bool True when a keyword matches and the plugin is not blocked.
	 */
	public function auto_update_plugin( $update, $item ) {
		if ( ! is_object( $item ) || ! isset( $item->plugin ) ) {
			return $update;
		}

		if ( ! self::is_enabled() ) {
			return $update;
		}

		if ( Delay_Updates::is_site_health_item( $item ) ) {
			return $update;
		}

		if ( ! self::matches( $item ) ) {
			return $update;
		}

		if ( Functions::auto_updates_blocked( (string) $item->plugin, 'plugin' ) ) {
			return $update;
		}

		return true;
	}

	/**
	 * Force a theme auto-update when the upgrade notice matches a keyword.
	 *
	 * @param bool   $update Whether to update.
	 * @param object $item   Theme update item.
	 * @return bool True when a keyword matches and the theme is not blocked.
	 */
	public function auto_update_theme( $update, $item ) {
		if ( ! is_object( $item ) || ! isset( $item->theme ) ) {
			return $update;
		}

		if ( ! self::is_enabled() ) {
			return $update;
		}

		if ( Delay_Updates::is_site_health_item( $item ) ) {
			return $update;
		}

		if ( ! self::matches( $item ) ) {
			return $update;
		}

		if ( Functions::auto_updates_blocked( (string) $item->theme, 'theme' ) ) {
			return $update;
		}

		return true;
	}

	/**
	 * Whether upgrade-notice keyword matching is enabled.
	 *
	 * @return bool True when the feature is enabled.
	 */
	public static function is_enabled(): bool {
		$value = Options::get_options( 'upgrade_notice_keywords_enabled' );

		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			return 'on' === $value || '1' === $value || 'true' === $value;
		}

		return (bool) $value;
	}

	/**
	 * Retrieve configured keywords.
	 *
	 * @return array<int, string> Non-empty keywords.
	 */
	public static function get_keywords(): array {
		$raw = Options::get_options( 'upgrade_notice_keywords' );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return array();
		}

		return self::split_keywords( $raw );
	}

	/**
	 * Whether an update item's upgrade notice contains a keyword.
	 *
	 * @param mixed $item Plugin or theme update item.
	 * @return bool True when a keyword is found.
	 */
	public static function matches( $item ): bool {
		if ( ! is_object( $item ) || ! isset( $item->upgrade_notice ) || ! is_string( $item->upgrade_notice ) ) {
			return false;
		}

		$notice = wp_strip_all_tags( $item->upgrade_notice );
		if ( '' === $notice ) {
			return false;
		}

		foreach ( self::get_keywords() as $keyword ) {
			if ( false !== stripos( $notice, $keyword ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Normalize posted textarea text to one keyword per line.
	 *
	 * @param mixed $raw Posted keywords.
	 * @return string Sanitized keywords separated by newlines.
	 */
	public static function sanitize( $raw ): string {
		if ( ! is_string( $raw ) ) {
			return '';
		}

		$raw = sanitize_textarea_field( $raw );

		return implode( "\n", self::split_keywords( $raw ) );
	}

	/**
	 * Split raw keyword text into trimmed, non-empty lines.
	 *
	 * @param string $raw Keyword text.
	 * @return array<int, string> Keywords.
	 */
	private static function split_keywords( string $raw ): array {
		$lines = preg_split( '/\r\n|\r|\n/', $raw );
		if ( ! is_array( $lines ) ) {
			return array();
		}

		$keywords = array();
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}

			$keywords[] = $line;
		}

		return $keywords;
	}
}
