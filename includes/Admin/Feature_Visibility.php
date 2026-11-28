<?php
/**
 * Admin feature visibility settings.
 *
 * @package updatebee
 */

namespace UpdateBee\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use UpdateBee\Options;

/**
 * Visibility settings for optional admin features.
 */
class Feature_Visibility {

	/**
	 * Supported feature keys that may be hidden.
	 *
	 * @var array<int, string>
	 */
	public const FEATURE_KEYS = array(
		'upcoming',
		'webhooks',
		'translations',
		'emails',
		'scheduling',
		'tools',
	);

	/**
	 * Feature keys mapped to their top-level admin tabs.
	 *
	 * @var array<string, string>
	 */
	private const TABS = array(
		'upcoming'     => 'upcoming',
		'webhooks'     => 'webhooks',
		'translations' => 'translations',
		'emails'       => 'emails',
		'scheduling'   => 'cron',
		'tools'        => 'tools',
	);

	/**
	 * Register visibility filters.
	 */
	public function run(): void {
		add_filter( 'updatebee_admin_tabs', array( $this, 'filter_admin_tabs' ), 20 );
	}

	/**
	 * Retrieve validated hidden feature keys.
	 *
	 * @return array<int, string> Hidden feature keys.
	 */
	public static function get_hidden_features(): array {
		return self::sanitize_hidden_features( Options::get_options( 'hidden_features' ) );
	}

	/**
	 * Sanitize feature keys against the supported list.
	 *
	 * @param mixed $features Feature keys.
	 * @return array<int, string> Supported feature keys.
	 */
	public static function sanitize_hidden_features( $features ): array {
		if ( ! is_array( $features ) ) {
			return array();
		}

		$hidden = array();
		foreach ( $features as $feature ) {
			if ( ! is_string( $feature ) ) {
				continue;
			}

			$feature = sanitize_key( $feature );
			if ( in_array( $feature, self::FEATURE_KEYS, true ) ) {
				$hidden[ $feature ] = $feature;
			}
		}

		return array_values( $hidden );
	}

	/**
	 * Check whether a feature is hidden.
	 *
	 * @param string $feature Feature key.
	 * @return bool True when the feature is hidden.
	 */
	public static function is_hidden( string $feature ): bool {
		return in_array( $feature, self::get_hidden_features(), true );
	}

	/**
	 * Check whether an admin tab is hidden.
	 *
	 * @param string $tab Tab key.
	 * @return bool True when the tab is hidden.
	 */
	public static function is_tab_hidden( string $tab ): bool {
		$feature = array_search( $tab, self::TABS, true );

		return is_string( $feature ) && self::is_hidden( $feature );
	}

	/**
	 * Remove hidden feature tabs from the admin navigation.
	 *
	 * @param array<int, array<string, string>> $tabs Admin tabs.
	 * @return array<int, array<string, string>> Visible admin tabs.
	 */
	public function filter_admin_tabs( array $tabs ): array {
		return array_values(
			array_filter(
				$tabs,
				static function ( $tab ): bool {
					if ( ! is_array( $tab ) || ! isset( $tab['get'] ) || ! is_string( $tab['get'] ) ) {
						return true;
					}

					return ! self::is_tab_hidden( $tab['get'] );
				}
			)
		);
	}
}
