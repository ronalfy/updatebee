<?php
/**
 * Translation auto-update filter.
 *
 * @package updatebee
 */

namespace UpdateBee\Updates;

use UpdateBee\Functions;

/**
 * Enforce per-locale translation auto-update settings.
 */
class Translations {

	/**
	 * Class runner.
	 */
	public function run(): void {
		add_filter( 'auto_update_translation', array( $this, 'maybe_auto_update_translation' ), PHP_INT_MAX, 2 );
	}

	/**
	 * Maybe allow auto-updates for a translation locale.
	 *
	 * @param bool   $update Whether to update the translation.
	 * @param object $item   Translation update item.
	 * @return bool True to update, false to skip.
	 */
	public function maybe_auto_update_translation( $update, $item ): bool {
		unset( $update );

		$locale = '';
		if ( is_object( $item ) && isset( $item->language ) ) {
			$locale = (string) $item->language;
		}

		if ( '' === $locale ) {
			return false;
		}

		return (bool) Functions::auto_updates_enabled( $locale, 'translation' );
	}
}
