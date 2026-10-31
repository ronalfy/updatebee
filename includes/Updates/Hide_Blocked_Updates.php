<?php
/**
 * Hide blocked updates from the WordPress updater.
 *
 * @package updatebee
 */

namespace UpdateBee\Updates;

use UpdateBee\Functions;

/**
 * Remove blocked plugin, theme, and translation offers when WordPress reads update transients.
 */
class Hide_Blocked_Updates {

	/**
	 * Hide_Blocked_Updates instance.
	 *
	 * @var Hide_Blocked_Updates|null
	 */
	private static $instance = null;

	/**
	 * Whether UpdateBee is reading the stored offers for its own screens.
	 *
	 * @var bool
	 */
	private static $suspended = false;

	/**
	 * Return a class instance.
	 *
	 * @return Hide_Blocked_Updates
	 */
	public static function get_instance(): Hide_Blocked_Updates {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Run a callback without hiding blocked updates.
	 *
	 * UpdateBee's own screens and abilities use this so they can still report that a blocked offer exists.
	 *
	 * @param callable $callback Callback that reads update transients.
	 * @return mixed Callback result.
	 */
	public static function unfiltered( callable $callback ) {
		$previous        = self::$suspended;
		self::$suspended = true;

		try {
			return $callback();
		} finally {
			self::$suspended = $previous;
		}
	}

	/**
	 * Class constructor.
	 */
	private function __construct() {
		add_filter( 'site_transient_update_plugins', array( $this, 'filter_plugins' ), PHP_INT_MAX );
		add_filter( 'site_transient_update_themes', array( $this, 'filter_themes' ), PHP_INT_MAX );
		add_filter( 'site_transient_update_core', array( $this, 'filter_core' ), PHP_INT_MAX );
	}

	/**
	 * Remove blocked plugins and blocked translation locales from the plugin update transient.
	 *
	 * @param mixed $updates Update transient.
	 * @return mixed Filtered transient.
	 */
	public function filter_plugins( $updates ) {
		if ( self::$suspended || ! is_object( $updates ) ) {
			return $updates;
		}

		$copy    = clone $updates;
		$changed = $this->remove_blocked_responses( $copy, 'plugin' );
		$changed = $this->remove_blocked_translations( $copy ) || $changed;

		return $changed ? $copy : $updates;
	}

	/**
	 * Remove blocked themes and blocked translation locales from the theme update transient.
	 *
	 * @param mixed $updates Update transient.
	 * @return mixed Filtered transient.
	 */
	public function filter_themes( $updates ) {
		if ( self::$suspended || ! is_object( $updates ) ) {
			return $updates;
		}

		$copy    = clone $updates;
		$changed = $this->remove_blocked_responses( $copy, 'theme' );
		$changed = $this->remove_blocked_translations( $copy ) || $changed;

		return $changed ? $copy : $updates;
	}

	/**
	 * Remove blocked translation locales from the core update transient.
	 *
	 * Core version offers stay in place. UpdateBee has no switch that blocks core updates.
	 *
	 * @param mixed $updates Update transient.
	 * @return mixed Filtered transient.
	 */
	public function filter_core( $updates ) {
		if ( self::$suspended || ! is_object( $updates ) ) {
			return $updates;
		}

		$copy = clone $updates;
		if ( ! $this->remove_blocked_translations( $copy ) ) {
			return $updates;
		}

		return $copy;
	}

	/**
	 * Drop plugin or theme offers that UpdateBee has blocked.
	 *
	 * @param object $updates Update transient. Cloned by the caller.
	 * @param string $type    plugin or theme.
	 * @return bool True when the response list changed.
	 */
	private function remove_blocked_responses( object $updates, string $type ): bool {
		if ( ! isset( $updates->response ) || ! is_array( $updates->response ) ) {
			return false;
		}

		$fully_blocked = 'theme' === $type ? Functions::theme_updates_fully_blocked() : Functions::plugin_updates_fully_blocked();
		if ( $fully_blocked ) {
			if ( array() === $updates->response ) {
				return false;
			}

			$updates->response = array();
			return true;
		}

		$response = $updates->response;
		$changed  = false;
		foreach ( array_keys( $response ) as $path ) {
			if ( ! is_string( $path ) ) {
				continue;
			}

			if ( Functions::auto_updates_blocked( $path, $type ) ) {
				unset( $response[ $path ] );
				$changed = true;
			}
		}

		if ( $changed ) {
			$updates->response = $response;
		}

		return $changed;
	}

	/**
	 * Drop translation offers for blocked locales, or all of them when translation updates are disabled.
	 *
	 * @param object $updates Update transient. Cloned by the caller.
	 * @return bool True when the translation list changed.
	 */
	private function remove_blocked_translations( object $updates ): bool {
		if ( ! isset( $updates->translations ) || ! is_array( $updates->translations ) ) {
			return false;
		}

		if ( Functions::translation_updates_fully_blocked() ) {
			if ( array() === $updates->translations ) {
				return false;
			}

			$updates->translations = array();
			return true;
		}

		$kept    = array();
		$changed = false;
		foreach ( $updates->translations as $translation ) {
			$language = $this->translation_language( $translation );
			if ( '' !== $language && Functions::auto_updates_blocked( $language, 'translation' ) ) {
				$changed = true;
				continue;
			}

			$kept[] = $translation;
		}

		if ( $changed ) {
			$updates->translations = $kept;
		}

		return $changed;
	}

	/**
	 * Locale from a translation offer.
	 *
	 * @param mixed $translation Translation offer.
	 * @return string Locale, or an empty string when the offer has none.
	 */
	private function translation_language( $translation ): string {
		if ( is_object( $translation ) && isset( $translation->language ) ) {
			return (string) $translation->language;
		}

		if ( is_array( $translation ) && isset( $translation['language'] ) ) {
			return (string) $translation['language'];
		}

		return '';
	}
}
