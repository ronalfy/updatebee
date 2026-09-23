<?php
/**
 * Delay automatic updates by a configured number of days.
 *
 * @package updatebee
 */

namespace UpdateBee\Updates;

use UpdateBee\Functions;
use UpdateBee\Options;
use UpdateBee\Tables\Table_Plugins;
use UpdateBee\Tables\Table_Themes;

/**
 * Stamp first-seen update versions and veto auto-updates until the delay elapses.
 */
class Delay_Updates {

	/**
	 * Site option key for plugin first-seen stamps.
	 *
	 * @var string
	 */
	public const PLUGIN_OPTION = 'updatebee_delayed_plugin_updates';

	/**
	 * Site option key for theme first-seen stamps.
	 *
	 * @var string
	 */
	public const THEME_OPTION = 'updatebee_delayed_theme_updates';

	/**
	 * Delay_Updates instance.
	 *
	 * @var Delay_Updates|null
	 */
	private static $instance = null;

	/**
	 * Return a class instance.
	 *
	 * @return Delay_Updates
	 */
	public static function get_instance(): Delay_Updates {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Class constructor.
	 */
	private function __construct() {
		$auto_priority      = Functions::get_highest_priority( 8 );
		$transient_priority = Functions::get_highest_priority( 1 );

		add_filter( 'auto_update_plugin', array( $this, 'auto_update_plugin' ), $auto_priority, 2 );
		add_filter( 'auto_update_theme', array( $this, 'auto_update_theme' ), $auto_priority, 2 );
		add_filter( 'site_transient_update_plugins', array( $this, 'maybe_set_delay_for_plugin_updates' ), $transient_priority );
		add_filter( 'site_transient_update_themes', array( $this, 'maybe_set_delay_for_theme_updates' ), $transient_priority );
		add_action( 'delete_site_transient_update_plugins', array( $this, 'remove_delayed_plugin_updates' ), $transient_priority );
		add_action( 'delete_site_transient_update_themes', array( $this, 'remove_delayed_theme_updates' ), $transient_priority );
	}

	/**
	 * Whether the item is WordPress Site Health's fake plugin or theme.
	 *
	 * @param mixed $item Update item.
	 * @return bool True when the item is a Site Health diagnostic fake.
	 */
	public static function is_site_health_item( $item ): bool {
		if ( ! is_object( $item ) ) {
			return false;
		}

		if ( isset( $item->theme ) && 'a-fake-theme' === $item->theme ) {
			return true;
		}

		if ( isset( $item->plugin ) && 'a-fake-plugin/a-fake-plugin.php' === $item->plugin ) {
			return true;
		}

		return false;
	}

	/**
	 * Configured delay in whole days.
	 *
	 * @return int Days to wait before auto-updating. Zero means no delay.
	 */
	public function get_delay_days(): int {
		return absint( Options::get_options( 'delay_updates' ) );
	}

	/**
	 * Resolve the delay for one item.
	 *
	 * A null custom value inherits the global delay. Zero and positive values override it.
	 *
	 * @param int|null $custom Per-item delay in days, or null to inherit.
	 * @return int Days to wait. Zero means no delay.
	 */
	public function resolve_delay_days( ?int $custom ): int {
		if ( null === $custom ) {
			return $this->get_delay_days();
		}

		return absint( $custom );
	}

	/**
	 * Decide whether to auto-update a plugin or delay it.
	 *
	 * @param bool   $update Whether to update.
	 * @param object $item   Plugin update item.
	 * @return bool False while delayed, otherwise the incoming decision.
	 */
	public function auto_update_plugin( $update, $item ) {
		if ( ! $update || ! is_object( $item ) || ! isset( $item->plugin ) ) {
			return $update;
		}

		$plugin_file = (string) $item->plugin;
		$delay       = $this->resolve_delay_days( Table_Plugins::get_delay_days( $plugin_file ) );
		if ( 0 === $delay ) {
			return $update;
		}

		$plugin_updates = $this->get_stamps( self::PLUGIN_OPTION );
		if ( ! isset( $plugin_updates[ $plugin_file ]['time'] ) ) {
			return $update;
		}

		if ( time() < (int) $plugin_updates[ $plugin_file ]['time'] + ( $delay * DAY_IN_SECONDS ) ) {
			return self::is_site_health_item( $item );
		}

		unset( $plugin_updates[ $plugin_file ] );
		$this->save_stamps( self::PLUGIN_OPTION, $plugin_updates );

		return $update;
	}

	/**
	 * Decide whether to auto-update a theme or delay it.
	 *
	 * @param bool   $update Whether to update.
	 * @param object $item   Theme update item.
	 * @return bool False while delayed, otherwise the incoming decision.
	 */
	public function auto_update_theme( $update, $item ) {
		if ( ! $update || ! is_object( $item ) || ! isset( $item->theme ) ) {
			return $update;
		}

		$theme_slug = (string) $item->theme;
		$delay      = $this->resolve_delay_days( Table_Themes::get_delay_days( $theme_slug ) );
		if ( 0 === $delay ) {
			return $update;
		}

		$theme_updates = $this->get_stamps( self::THEME_OPTION );
		if ( ! isset( $theme_updates[ $theme_slug ]['time'] ) ) {
			return $update;
		}

		if ( time() < (int) $theme_updates[ $theme_slug ]['time'] + ( $delay * DAY_IN_SECONDS ) ) {
			return self::is_site_health_item( $item );
		}

		unset( $theme_updates[ $theme_slug ] );
		$this->save_stamps( self::THEME_OPTION, $theme_updates );

		return $update;
	}

	/**
	 * Stamp first-seen plugin update versions.
	 *
	 * @param mixed $update Plugin update transient.
	 * @return mixed Unchanged transient.
	 */
	public function maybe_set_delay_for_plugin_updates( $update ) {
		if ( ! is_object( $update ) || empty( $update->response ) || ! is_array( $update->response ) ) {
			return $update;
		}

		$plugin_updates = $this->get_stamps( self::PLUGIN_OPTION );
		$changed        = false;

		foreach ( $update->response as $plugin_file => $data ) {
			if ( ! is_object( $data ) || ! isset( $data->new_version ) ) {
				continue;
			}

			$new_version = (string) $data->new_version;
			if ( $this->should_stamp( $plugin_updates, (string) $plugin_file, $new_version ) ) {
				$plugin_updates[ (string) $plugin_file ] = array(
					'version' => $new_version,
					'time'    => time(),
				);
				$changed = true;
			}
		}

		if ( $changed ) {
			$this->save_stamps( self::PLUGIN_OPTION, $plugin_updates );
		}

		return $update;
	}

	/**
	 * Stamp first-seen theme update versions.
	 *
	 * @param mixed $update Theme update transient.
	 * @return mixed Unchanged transient.
	 */
	public function maybe_set_delay_for_theme_updates( $update ) {
		if ( ! is_object( $update ) || ! isset( $update->response ) || ! is_array( $update->response ) ) {
			return $update;
		}

		$theme_updates = $this->get_stamps( self::THEME_OPTION );
		$changed       = false;

		foreach ( $update->response as $theme_slug => $data ) {
			$new_version = '';
			if ( is_array( $data ) && isset( $data['new_version'] ) ) {
				$new_version = (string) $data['new_version'];
			} elseif ( is_object( $data ) && isset( $data->new_version ) ) {
				$new_version = (string) $data->new_version;
			}

			if ( '' === $new_version ) {
				continue;
			}

			if ( $this->should_stamp( $theme_updates, (string) $theme_slug, $new_version ) ) {
				$theme_updates[ (string) $theme_slug ] = array(
					'version' => $new_version,
					'time'    => time(),
				);
				$changed = true;
			}
		}

		if ( $changed ) {
			$this->save_stamps( self::THEME_OPTION, $theme_updates );
		}

		return $update;
	}

	/**
	 * Clear plugin first-seen stamps when the plugin update cache is deleted.
	 */
	public function remove_delayed_plugin_updates(): void {
		$this->save_stamps( self::PLUGIN_OPTION, array() );
	}

	/**
	 * Clear theme first-seen stamps when the theme update cache is deleted.
	 */
	public function remove_delayed_theme_updates(): void {
		$this->save_stamps( self::THEME_OPTION, array() );
	}

	/**
	 * Whether a stamp should be created or reset for a newer version.
	 *
	 * @param array<string, mixed> $stamps  Existing stamps.
	 * @param string               $key     Item key.
	 * @param string               $version Newly detected version.
	 * @return bool True when the stamp is missing or the version is newer.
	 */
	private function should_stamp( array $stamps, string $key, string $version ): bool {
		if ( empty( $stamps[ $key ] ) || ! isset( $stamps[ $key ]['version'] ) ) {
			return true;
		}

		return version_compare( (string) $stamps[ $key ]['version'], $version, '<' );
	}

	/**
	 * Retrieve first-seen stamps for an option key.
	 *
	 * @param string $option_key Site option key.
	 * @return array<string, mixed> Stamps keyed by asset.
	 */
	private function get_stamps( string $option_key ): array {
		$stamps = get_site_option( $option_key, array() );

		return is_array( $stamps ) ? $stamps : array();
	}

	/**
	 * Persist first-seen stamps.
	 *
	 * @param string               $option_key Site option key.
	 * @param array<string, mixed> $stamps     Stamps keyed by asset.
	 */
	private function save_stamps( string $option_key, array $stamps ): void {
		update_site_option( $option_key, $stamps );
	}
}
