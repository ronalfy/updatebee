<?php
/**
 * Block automatic updates that have not been tested with this WordPress version.
 *
 * @package updatebee
 */

namespace UpdateBee\Updates;

use UpdateBee\Logs;
use UpdateBee\Options;
use UpdateBee\Tables\Table_Plugins;
use UpdateBee\Tables\Table_Themes;

/**
 * Veto plugin and theme auto-updates when Tested up to is older than this site.
 */
class Untested_Updates {

	/**
	 * Untested_Updates instance.
	 *
	 * @var Untested_Updates|null
	 */
	private static $instance = null;

	/**
	 * Return a class instance.
	 *
	 * @return Untested_Updates
	 */
	public static function get_instance(): Untested_Updates {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_filter( 'auto_update_plugin', array( $this, 'auto_update_plugin' ), PHP_INT_MAX - 5, 2 );
		add_filter( 'auto_update_theme', array( $this, 'auto_update_theme' ), PHP_INT_MAX - 5, 2 );
	}

	/**
	 * Decide whether to auto-update a plugin.
	 *
	 * @param bool   $update Whether to update.
	 * @param object $item   Plugin update item.
	 * @return bool False when the plugin is untested with this WordPress version.
	 */
	public function auto_update_plugin( $update, $item ) {
		if ( ! $update || ! is_object( $item ) || ! isset( $item->plugin ) ) {
			return $update;
		}

		if ( Delay_Updates::is_site_health_item( $item ) ) {
			return $update;
		}

		if ( ! $this->is_enabled() || $this->item_allows_untested( 'plugin', (string) $item->plugin ) ) {
			return $update;
		}

		if ( $this->is_tested_with_current_wordpress( $item ) ) {
			return $update;
		}

		$this->log_block( 'plugin', (string) $item->plugin, $item );

		return false;
	}

	/**
	 * Decide whether to auto-update a theme.
	 *
	 * @param bool   $update Whether to update.
	 * @param object $item   Theme update item.
	 * @return bool False when the theme is untested with this WordPress version.
	 */
	public function auto_update_theme( $update, $item ) {
		if ( ! $update || ! is_object( $item ) || ! isset( $item->theme ) ) {
			return $update;
		}

		if ( Delay_Updates::is_site_health_item( $item ) ) {
			return $update;
		}

		if ( ! $this->is_enabled() || $this->item_allows_untested( 'theme', (string) $item->theme ) ) {
			return $update;
		}

		if ( $this->is_tested_with_current_wordpress( $item ) ) {
			return $update;
		}

		$this->log_block( 'theme', (string) $item->theme, $item );

		return false;
	}

	/**
	 * Whether the site blocks untested updates.
	 *
	 * @return bool True when the global switch is on.
	 */
	public function is_enabled(): bool {
		return 'on' === Options::get_options( 'block_untested_updates' );
	}

	/**
	 * Whether an update offer has been tested with this site's WordPress version.
	 *
	 * A missing or unparsable tested value is treated as compatible. Premium packages often omit it.
	 *
	 * @param object $item Update offer.
	 * @return bool True when the update may proceed.
	 */
	public function is_tested_with_current_wordpress( $item ): bool {
		if ( ! is_object( $item ) || ! isset( $item->tested ) || '' === (string) $item->tested ) {
			return true;
		}

		$tested = self::major_minor( (string) $item->tested );
		$site   = self::major_minor( $this->wordpress_version() );
		if ( '' === $tested || '' === $site ) {
			return true;
		}

		return version_compare( $site, $tested, '<=' );
	}

	/**
	 * Reduce a version to major.minor.
	 *
	 * @param string $version Version string.
	 * @return string Major.minor, or an empty string when it cannot be parsed.
	 */
	public static function major_minor( string $version ): string {
		if ( preg_match( '/^(\d+)\.(\d+)/', $version, $matches ) ) {
			return $matches[1] . '.' . $matches[2];
		}

		return '';
	}

	/**
	 * Current WordPress version.
	 *
	 * @return string Version string.
	 */
	protected function wordpress_version(): string {
		return (string) get_bloginfo( 'version' );
	}

	/**
	 * Whether this item is allowed to update even when it is untested.
	 *
	 * @param string $kind plugin|theme.
	 * @param string $id   Plugin file or theme stylesheet.
	 * @return bool True when the item opts out.
	 */
	protected function item_allows_untested( string $kind, string $id ): bool {
		if ( 'plugin' === $kind ) {
			return Table_Plugins::allows_untested_updates( $id );
		}

		return Table_Themes::allows_untested_updates( $id );
	}

	/**
	 * Record a blocked automatic update.
	 *
	 * @param string $kind plugin|theme.
	 * @param string $id   Plugin file or theme stylesheet.
	 * @param object $item Update offer.
	 */
	protected function log_block( string $kind, string $id, $item ): void {
		$name    = isset( $item->slug ) && '' !== (string) $item->slug ? (string) $item->slug : $id;
		$version = isset( $item->new_version ) ? (string) $item->new_version : '';
		$tested  = isset( $item->tested ) ? (string) $item->tested : '';
		$notes   = sprintf(
			/* translators: 1: tested WordPress version, 2: current WordPress version. */
			__( 'Automatic update blocked because it was tested with WordPress %1$s and this site is running WordPress %2$s.', 'updatebee' ),
			$tested,
			$this->wordpress_version()
		);

		$logs = new Logs();
		$logs->insert_log( $name, $id, 0, $version, $version, 'automatic', $kind, 'blocked', $notes );
	}
}
