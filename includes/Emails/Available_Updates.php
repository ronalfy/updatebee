<?php
/**
 * Collect pending updates and build report rows.
 *
 * @package updatebee
 */

namespace UpdateBee\Emails;

use UpdateBee\Functions;
use UpdateBee\Updates\Hide_Blocked_Updates;

/**
 * Available update collection for email and Slack reports.
 */
class Available_Updates {

	public const PLUGIN_CAPTURE_TRANSIENT = 'updatebee_plugin_updates';
	public const THEME_CAPTURE_TRANSIENT  = 'updatebee_theme_updates';
	public const CORE_CAPTURE_TRANSIENT   = 'updatebee_core_updates';

	/**
	 * Passed to wp_update_plugins() / wp_update_themes() to bypass the throttle.
	 *
	 * @var array<string, int>
	 */
	private const FORCE_UPDATE_CHECK_STATS = array( 'updatebee' => 1 );

	/**
	 * Force update checks and return captured transients.
	 *
	 * @return array{core: object|null, plugins: object|null, themes: object|null}
	 */
	public static function refresh_and_capture(): array {
		add_filter( 'expiration_of_site_transient_update_plugins', array( self::class, 'capture_plugin_updates' ), 9, 2 );
		add_filter( 'expiration_of_site_transient_update_themes', array( self::class, 'capture_theme_updates' ), 9, 2 );
		add_filter( 'expiration_of_site_transient_update_core', array( self::class, 'capture_core_updates' ), 9, 2 );

		wp_version_check( array(), true );
		wp_update_plugins( self::FORCE_UPDATE_CHECK_STATS );
		wp_update_themes( self::FORCE_UPDATE_CHECK_STATS );

		$data = array(
			'core'    => get_site_transient( self::CORE_CAPTURE_TRANSIENT ),
			'plugins' => get_site_transient( self::PLUGIN_CAPTURE_TRANSIENT ),
			'themes'  => get_site_transient( self::THEME_CAPTURE_TRANSIENT ),
		);

		remove_filter( 'expiration_of_site_transient_update_plugins', array( self::class, 'capture_plugin_updates' ), 9 );
		remove_filter( 'expiration_of_site_transient_update_themes', array( self::class, 'capture_theme_updates' ), 9 );
		remove_filter( 'expiration_of_site_transient_update_core', array( self::class, 'capture_core_updates' ), 9 );

		delete_site_transient( self::PLUGIN_CAPTURE_TRANSIENT );
		delete_site_transient( self::THEME_CAPTURE_TRANSIENT );
		delete_site_transient( self::CORE_CAPTURE_TRANSIENT );

		return self::with_live_transient_fallback( $data );
	}

	/**
	 * Use live update transients when capture copies were not stored.
	 *
	 * WordPress may skip an update HTTP request when data was checked recently; capture
	 * hooks only run when set_site_transient() is called, so fall back to the live transients.
	 *
	 * @param array{core: object|null, plugins: object|null, themes: object|null} $data Captured transients.
	 * @return array{core: object|null, plugins: object|null, themes: object|null}
	 */
	private static function with_live_transient_fallback( array $data ): array {
		if ( ! is_object( $data['core'] ?? null ) ) {
			$core = get_site_transient( 'update_core' );
			if ( is_object( $core ) ) {
				$data['core'] = $core;
			}
		}

		if ( ! is_object( $data['plugins'] ?? null ) ) {
			$plugins = get_site_transient( 'update_plugins' );
			if ( is_object( $plugins ) ) {
				$data['plugins'] = $plugins;
			}
		}

		if ( ! is_object( $data['themes'] ?? null ) ) {
			$themes = get_site_transient( 'update_themes' );
			if ( is_object( $themes ) ) {
				$data['themes'] = $themes;
			}
		}

		return $data;
	}

	/**
	 * Build rows from live update transients (no forced refresh).
	 *
	 * @return array{core: object|null, plugins: object|null, themes: object|null}
	 */
	public static function current_transients(): array {
		return array(
			'core'    => get_site_transient( 'update_core' ),
			'plugins' => get_site_transient( 'update_plugins' ),
			'themes'  => get_site_transient( 'update_themes' ),
		);
	}

	/**
	 * Capture plugin updates when WordPress refreshes the transient.
	 *
	 * @param int    $expiration Expiration seconds.
	 * @param object $value      New transient value.
	 * @return int
	 */
	public static function capture_plugin_updates( $expiration, $value ) {
		if ( is_object( $value ) ) {
			set_site_transient( self::PLUGIN_CAPTURE_TRANSIENT, $value );
		}

		return (int) $expiration;
	}

	/**
	 * Capture theme updates when WordPress refreshes the transient.
	 *
	 * @param int    $expiration Expiration seconds.
	 * @param object $value      New transient value.
	 * @return int
	 */
	public static function capture_theme_updates( $expiration, $value ) {
		if ( is_object( $value ) ) {
			set_site_transient( self::THEME_CAPTURE_TRANSIENT, $value );
		}

		return (int) $expiration;
	}

	/**
	 * Capture core updates when WordPress refreshes the transient.
	 *
	 * @param int         $expiration Expiration seconds.
	 * @param object|null $value      New transient value.
	 * @return int
	 */
	public static function capture_core_updates( $expiration, $value ) {
		if ( is_object( $value ) ) {
			set_site_transient( self::CORE_CAPTURE_TRANSIENT, $value );
		}

		return (int) $expiration;
	}

	/**
	 * Build log-shaped rows for email and Slack.
	 *
	 * @param object|null         $core            Core update transient.
	 * @param object|null         $plugins         Plugin update transient.
	 * @param object|null         $themes          Theme update transient.
	 * @param array<string, bool> $supported Include flags keyed plugins|themes|core|translations.
	 * @return array<int, array<string, mixed>>
	 */
	public static function build_rows( $core, $plugins, $themes, array $supported ): array {
		$rows = array();
		$date = current_time( 'mysql' );

		if ( ! empty( $supported['core'] ) && is_object( $core ) ) {
			$core_row = self::core_row( $core, $date );
			if ( null !== $core_row ) {
				$rows[] = $core_row;
			}
		}

		if ( ! empty( $supported['plugins'] ) && is_object( $plugins ) && ! empty( $plugins->response ) && is_array( $plugins->response ) ) {
			foreach ( $plugins->response as $plugin_file => $plugin_data ) {
				$row = self::plugin_row( (string) $plugin_file, $plugin_data, $date );
				if ( null !== $row ) {
					$rows[] = $row;
				}
			}
		}

		if ( ! empty( $supported['themes'] ) && is_object( $themes ) && ! empty( $themes->response ) && is_array( $themes->response ) ) {
			foreach ( $themes->response as $theme_slug => $theme_data ) {
				$row = self::theme_row( (string) $theme_slug, $theme_data, $date );
				if ( null !== $row ) {
					$rows[] = $row;
				}
			}
		}

		if ( ! empty( $supported['translations'] ) ) {
			$translations = self::collect_translations( $core, $plugins, $themes );
			foreach ( $translations as $translation ) {
				$row = self::translation_row( $translation, $date );
				if ( null !== $row ) {
					$rows[] = $row;
				}
			}
		}

		return $rows;
	}

	/**
	 * Build rows from captured or live transients array.
	 *
	 * @param array{core: object|null, plugins: object|null, themes: object|null} $data      Transient bundle.
	 * @param array<string, bool>                                                 $supported Include flags.
	 * @return array<int, array<string, mixed>>
	 */
	public static function build_rows_from_data( array $data, array $supported ): array {
		return self::build_rows(
			$data['core'] ?? null,
			$data['plugins'] ?? null,
			$data['themes'] ?? null,
			$supported
		);
	}

	/**
	 * Core update row.
	 *
	 * @param object $core Core transient.
	 * @param string $date Report date.
	 * @return array<string, mixed>|null
	 */
	private static function core_row( $core, string $date ): ?array {
		if ( empty( $core->updates ) || ! is_array( $core->updates ) ) {
			return null;
		}

		$old_version = isset( $core->version_checked ) ? (string) $core->version_checked : '';
		$new_version = self::offered_core_version( $core );
		if ( '' === $new_version || $old_version === $new_version ) {
			return null;
		}

		return array(
			'name'          => __( 'WordPress', 'updatebee' ),
			'update_type'   => 'core',
			'from_version'  => $old_version,
			'to_version'    => $new_version,
			'update_status' => 'available',
			'date'          => $date,
			'detail_url'    => esc_url( sprintf( 'https://codex.wordpress.org/Version_%s', $new_version ) ),
			'detail_label'  => __( 'Changelog', 'updatebee' ),
		);
	}

	/**
	 * Pick the offered core version from the update transient.
	 *
	 * @param object $core Core transient.
	 * @return string
	 */
	private static function offered_core_version( $core ): string {
		foreach ( $core->updates as $update ) {
			if ( ! is_object( $update ) || ! isset( $update->version ) ) {
				continue;
			}
			if ( isset( $update->response ) && 'autoupdate' === $update->response ) {
				continue;
			}
			if ( isset( $update->response ) && in_array( $update->response, array( 'upgrade', 'latest' ), true ) ) {
				return (string) $update->version;
			}
		}

		$first = reset( $core->updates );
		if ( is_object( $first ) && isset( $first->version ) ) {
			return (string) $first->version;
		}

		return '';
	}

	/**
	 * Pending plugin update for the admin screen.
	 *
	 * The read bypasses the blocked-update filter so a blocked plugin still shows its offer.
	 *
	 * @param string $file Plugin basename.
	 * @return array{new_version: string, upgrade_notice: string, has_package: bool, nonce: string}|null
	 */
	public static function plugin_offer( string $file ): ?array {
		if ( '' === $file ) {
			return null;
		}

		$updates = Hide_Blocked_Updates::unfiltered(
			static function () {
				return get_site_transient( 'update_plugins' );
			}
		);

		if ( ! is_object( $updates ) || empty( $updates->response ) || ! is_array( $updates->response ) || ! isset( $updates->response[ $file ] ) ) {
			return null;
		}

		return self::format_offer(
			$updates->response[ $file ],
			Functions::get_plugin_current_version( $file ),
			'updatebee-plugin-update-' . $file
		);
	}

	/**
	 * Pending theme update for the admin screen.
	 *
	 * The read bypasses the blocked-update filter so a blocked theme still shows its offer.
	 *
	 * @param string $stylesheet Theme stylesheet.
	 * @return array{new_version: string, upgrade_notice: string, has_package: bool, nonce: string}|null
	 */
	public static function theme_offer( string $stylesheet ): ?array {
		if ( '' === $stylesheet ) {
			return null;
		}

		$updates = Hide_Blocked_Updates::unfiltered(
			static function () {
				return get_site_transient( 'update_themes' );
			}
		);

		if ( ! is_object( $updates ) || empty( $updates->response ) || ! is_array( $updates->response ) || ! isset( $updates->response[ $stylesheet ] ) ) {
			return null;
		}

		return self::format_offer(
			$updates->response[ $stylesheet ],
			Functions::get_theme_current_version( $stylesheet ),
			'updatebee-theme-update-' . $stylesheet
		);
	}

	/**
	 * Shape one update offer for the admin screen.
	 *
	 * @param mixed  $item            Update metadata.
	 * @param string $current_version Installed version.
	 * @param string $nonce_action    Nonce action for a manual update.
	 * @return array{new_version: string, upgrade_notice: string, has_package: bool, nonce: string}|null
	 */
	private static function format_offer( $item, string $current_version, string $nonce_action ): ?array {
		$new_version = self::offer_new_version( $item );
		if ( '' === $new_version || $current_version === $new_version ) {
			return null;
		}

		return array(
			'new_version'    => $new_version,
			'upgrade_notice' => self::offer_upgrade_notice( $item ),
			'has_package'    => self::offer_has_package( $item ),
			'nonce'          => wp_create_nonce( $nonce_action ),
		);
	}

	/**
	 * Offered version from a plugin object or theme array.
	 *
	 * @param mixed $item Update metadata.
	 * @return string
	 */
	private static function offer_new_version( $item ): string {
		$version = '';
		if ( is_object( $item ) && isset( $item->new_version ) ) {
			$version = (string) $item->new_version;
		} elseif ( is_array( $item ) && isset( $item['new_version'] ) ) {
			$version = (string) $item['new_version'];
		}

		return sanitize_text_field( $version );
	}

	/**
	 * Sanitized upgrade notice, or an empty string when the offer has none.
	 *
	 * @param mixed $item Update metadata.
	 * @return string
	 */
	private static function offer_upgrade_notice( $item ): string {
		$notice = '';
		if ( is_object( $item ) && isset( $item->upgrade_notice ) && is_string( $item->upgrade_notice ) ) {
			$notice = $item->upgrade_notice;
		} elseif ( is_array( $item ) && isset( $item['upgrade_notice'] ) && is_string( $item['upgrade_notice'] ) ) {
			$notice = $item['upgrade_notice'];
		}

		return trim( wp_kses_post( $notice ) );
	}

	/**
	 * Whether the offer includes an installable package.
	 *
	 * The package URL stays on the server.
	 *
	 * @param mixed $item Update metadata.
	 * @return bool
	 */
	private static function offer_has_package( $item ): bool {
		$package = '';
		if ( is_object( $item ) && isset( $item->package ) && is_string( $item->package ) ) {
			$package = $item->package;
		} elseif ( is_array( $item ) && isset( $item['package'] ) && is_string( $item['package'] ) ) {
			$package = $item['package'];
		}

		return '' !== $package;
	}

	/**
	 * Plugin update row.
	 *
	 * @param string $plugin_file Plugin basename.
	 * @param object $plugin_data Update metadata.
	 * @param string $date        Report date.
	 * @return array<string, mixed>|null
	 */
	private static function plugin_row( string $plugin_file, $plugin_data, string $date ): ?array {
		if ( ! is_object( $plugin_data ) || ! isset( $plugin_data->new_version ) ) {
			return null;
		}

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;
		if ( ! file_exists( $plugin_path ) ) {
			return null;
		}

		$plugin_meta = get_plugin_data( $plugin_path, false, true );
		$old_version = isset( $plugin_meta['Version'] ) ? (string) $plugin_meta['Version'] : '';
		$new_version = (string) $plugin_data->new_version;
		if ( $old_version === $new_version ) {
			return null;
		}

		$row = array(
			'name'          => isset( $plugin_meta['Name'] ) ? (string) $plugin_meta['Name'] : $plugin_file,
			'update_type'   => 'plugin',
			'from_version'  => $old_version,
			'to_version'    => $new_version,
			'update_status' => 'available',
			'date'          => $date,
		);

		if ( isset( $plugin_data->id ) && is_string( $plugin_data->id ) && '' !== $plugin_data->id ) {
			$row['detail_url']   = esc_url( $plugin_data->id . '#developers' );
			$row['detail_label'] = __( 'Changelog', 'updatebee' );
		}

		return $row;
	}

	/**
	 * Theme update row.
	 *
	 * @param string               $theme_slug  Theme stylesheet.
	 * @param array<string, mixed> $theme_data  Update metadata.
	 * @param string               $date        Report date.
	 * @return array<string, mixed>|null
	 */
	private static function theme_row( string $theme_slug, $theme_data, string $date ): ?array {
		if ( ! is_array( $theme_data ) || ! isset( $theme_data['new_version'] ) ) {
			return null;
		}

		$theme       = wp_get_theme( $theme_slug );
		$old_version = $theme->exists() ? (string) $theme->get( 'Version' ) : '';
		$new_version = (string) $theme_data['new_version'];
		if ( $old_version === $new_version ) {
			return null;
		}

		$row = array(
			'name'          => $theme->exists() ? (string) $theme->get( 'Name' ) : $theme_slug,
			'update_type'   => 'theme',
			'from_version'  => $old_version,
			'to_version'    => $new_version,
			'update_status' => 'available',
			'date'          => $date,
		);

		if ( ! empty( $theme_data['url'] ) && is_string( $theme_data['url'] ) ) {
			$row['detail_url']   = esc_url( $theme_data['url'] );
			$row['detail_label'] = __( 'Theme details', 'updatebee' );
		}

		return $row;
	}

	/**
	 * Merge translation offers from core, plugin, and theme transients.
	 *
	 * @param object|null $core    Core transient.
	 * @param object|null $plugins Plugin transient.
	 * @param object|null $themes  Theme transient.
	 * @return array<int, array<string, mixed>>
	 */
	private static function collect_translations( $core, $plugins, $themes ): array {
		$merged  = array();
		$sources = array( $core, $plugins, $themes );
		foreach ( $sources as $source ) {
			if ( ! is_object( $source ) || empty( $source->translations ) || ! is_array( $source->translations ) ) {
				continue;
			}
			foreach ( $source->translations as $translation ) {
				if ( ! is_array( $translation ) ) {
					continue;
				}
				$key = self::translation_key( $translation );
				if ( '' === $key ) {
					continue;
				}
				$merged[ $key ] = $translation;
			}
		}

		return array_values( $merged );
	}

	/**
	 * Stable key for a translation offer.
	 *
	 * @param array<string, mixed> $translation Translation row.
	 * @return string
	 */
	private static function translation_key( array $translation ): string {
		$type     = isset( $translation['type'] ) ? (string) $translation['type'] : '';
		$slug     = isset( $translation['slug'] ) ? (string) $translation['slug'] : '';
		$language = isset( $translation['language'] ) ? (string) $translation['language'] : '';
		if ( '' === $language ) {
			return '';
		}

		return $type . '|' . $slug . '|' . $language;
	}

	/**
	 * Translation update row.
	 *
	 * @param array<string, mixed> $translation Translation offer.
	 * @param string               $date        Report date.
	 * @return array<string, mixed>|null
	 */
	private static function translation_row( array $translation, string $date ): ?array {
		$version = isset( $translation['version'] ) ? (string) $translation['version'] : '';
		if ( '' === $version ) {
			return null;
		}

		return array(
			'name'          => self::translation_name( $translation ),
			'update_type'   => 'translation',
			'from_version'  => '',
			'to_version'    => $version,
			'update_status' => 'available',
			'date'          => $date,
		);
	}

	/**
	 * Human-readable translation label.
	 *
	 * @param array<string, mixed> $translation Translation offer.
	 * @return string
	 */
	private static function translation_name( array $translation ): string {
		$type     = isset( $translation['type'] ) ? (string) $translation['type'] : 'core';
		$slug     = isset( $translation['slug'] ) ? (string) $translation['slug'] : '';
		$language = isset( $translation['language'] ) ? (string) $translation['language'] : '';

		if ( 'core' === $type ) {
			/* translators: %s: Locale code. */
			return sprintf( __( 'WordPress (%s)', 'updatebee' ), $language );
		}

		if ( 'plugin' === $type && '' !== $slug ) {
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$plugins = get_plugins();
			foreach ( $plugins as $file => $data ) {
				$parts = explode( '/', $file );
				if ( isset( $parts[0] ) && $parts[0] === $slug ) {
					$name = isset( $data['Name'] ) ? (string) $data['Name'] : $slug;
					/* translators: 1: Plugin name, 2: Locale code. */
					return sprintf( __( '%1$s (%2$s)', 'updatebee' ), $name, $language );
				}
			}
		}

		if ( 'theme' === $type && '' !== $slug ) {
			$theme = wp_get_theme( $slug );
			if ( $theme->exists() ) {
				/* translators: 1: Theme name, 2: Locale code. */
				return sprintf( __( '%1$s (%2$s)', 'updatebee' ), (string) $theme->get( 'Name' ), $language );
			}
		}

		/* translators: 1: Slug, 2: Locale code. */
		return sprintf( __( '%1$s (%2$s)', 'updatebee' ), $slug ? $slug : $type, $language );
	}
}
