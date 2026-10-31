<?php
/**
 * Detect PHP fatals after automatic plugin and theme updates.
 *
 * @package updatebee
 */

namespace UpdateBee\Updates;

use UpdateBee\Options;

/**
 * Native scrape-based fatal detection. Never deactivates plugins.
 */
class Fatal_Detection {

	/**
	 * Core updater WP_Error codes that mean a fatal was detected.
	 *
	 * @var array<int, string>
	 */
	public const FATAL_RESULT_CODES = array(
		'plugin_update_fatal_error_rollback_successful',
		'plugin_update_fatal_error_rollback_failed',
	);

	/**
	 * PHP error types treated as fatals.
	 *
	 * @var array<int, int>
	 */
	public const FATAL_ERROR_TYPES = array(
		E_CORE_ERROR,
		E_COMPILE_ERROR,
		E_ERROR,
		E_PARSE,
		E_USER_ERROR,
		E_RECOVERABLE_ERROR,
	);

	/**
	 * Findings keyed by "type:slug".
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private static $findings = array();

	/**
	 * Fatal that could not be mapped to a single extension.
	 *
	 * @var array<string, mixed>|null
	 */
	private static $unattributed = null;

	/**
	 * Register hooks.
	 */
	public function run(): void {
		add_action( 'automatic_updates_complete', array( $this, 'inspect_update_results' ), 8, 1 );
	}

	/**
	 * Whether scrape and fatal emails are enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		$value = Options::get_options( 'fatal_error_detection' );

		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			return 'on' === $value || '1' === $value || 'true' === $value;
		}

		return (bool) $value;
	}

	/**
	 * Reset request-local findings. Used by unit tests.
	 */
	public static function reset(): void {
		self::$findings     = array();
		self::$unattributed = null;
	}

	/**
	 * All attributed findings.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_findings(): array {
		return self::$findings;
	}

	/**
	 * Finding for one extension.
	 *
	 * @param string $type plugin|theme|core.
	 * @param string $slug Extension slug.
	 * @return array<string, mixed>|null
	 */
	public static function get_finding( string $type, string $slug ): ?array {
		$key = $type . ':' . $slug;
		if ( ! isset( self::$findings[ $key ] ) || ! is_array( self::$findings[ $key ] ) ) {
			return null;
		}

		return self::$findings[ $key ];
	}

	/**
	 * Unattributed fatal payload, if any.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function get_unattributed(): ?array {
		return self::$unattributed;
	}

	/**
	 * Whether a WP_Error came from core fatal rollback.
	 *
	 * @param mixed $result Upgrader result.
	 * @return bool
	 */
	public static function is_fatal_result( $result ): bool {
		if ( ! is_wp_error( $result ) ) {
			return false;
		}

		$codes = $result->get_error_codes();
		if ( ! is_array( $codes ) ) {
			return false;
		}

		/**
		 * Filter WP_Error codes treated as fatal update results.
		 *
		 * @since 1.0.0
		 *
		 * @param array<int, string> $codes Error codes.
		 */
		$fatal_codes = apply_filters( 'updatebee_fatal_result_codes', self::FATAL_RESULT_CODES );
		if ( ! is_array( $fatal_codes ) ) {
			$fatal_codes = self::FATAL_RESULT_CODES;
		}

		foreach ( $fatal_codes as $code ) {
			if ( in_array( $code, $codes, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Map an upgrader result to a log status.
	 *
	 * @param mixed $result Upgrader result.
	 * @return string|null fatal, failure, or null when not an error.
	 */
	public static function status_from_result( $result ): ?string {
		if ( ! is_wp_error( $result ) ) {
			return null;
		}

		return self::is_fatal_result( $result ) ? 'fatal' : 'failure';
	}

	/**
	 * Whether a PHP error type is fatal.
	 *
	 * @param mixed $type error_get_last() type.
	 * @return bool
	 */
	public static function is_fatal_error_type( $type ): bool {
		return is_int( $type ) && in_array( $type, self::FATAL_ERROR_TYPES, true );
	}

	/**
	 * Parse a native WordPress scrape response body.
	 *
	 * @param string $body       HTTP body.
	 * @param string $scrape_key Scrape key.
	 * @return array<string, mixed> outcome ok|fatal|inconclusive.
	 */
	public static function parse_scrape_body( string $body, string $scrape_key ): array {
		$needle_start = '###### wp_scraping_result_start:' . $scrape_key . ' ######';
		$needle_end   = '###### wp_scraping_result_end:' . $scrape_key . ' ######';
		$position     = strpos( $body, $needle_start );

		if ( false === $position ) {
			return array(
				'outcome' => 'inconclusive',
				'code'    => 'loopback_request_failed',
			);
		}

		$error_output = substr( $body, $position + strlen( $needle_start ) );
		$end_position = strpos( $error_output, $needle_end );
		if ( false === $end_position ) {
			return array(
				'outcome' => 'inconclusive',
				'code'    => 'json_parse_error',
			);
		}

		$decoded = json_decode( trim( substr( $error_output, 0, $end_position ) ), true );
		if ( true === $decoded ) {
			return array(
				'outcome' => 'ok',
			);
		}

		if ( ! is_array( $decoded ) ) {
			return array(
				'outcome' => 'inconclusive',
				'code'    => 'json_parse_error',
			);
		}

		if ( isset( $decoded['type'] ) && self::is_fatal_error_type( (int) $decoded['type'] ) ) {
			return array(
				'outcome' => 'fatal',
				'error'   => array(
					'type'    => (int) $decoded['type'],
					'message' => isset( $decoded['message'] ) ? (string) $decoded['message'] : '',
					'file'    => isset( $decoded['file'] ) ? (string) $decoded['file'] : '',
					'line'    => isset( $decoded['line'] ) ? (int) $decoded['line'] : 0,
				),
			);
		}

		return array(
			'outcome' => 'inconclusive',
			'code'    => isset( $decoded['code'] ) ? (string) $decoded['code'] : 'json_parse_error',
		);
	}

	/**
	 * Map a fatal file path to a plugin or theme in this update batch.
	 *
	 * @param string                           $file           Error file path.
	 * @param array<string, array<int, mixed>> $update_results Automatic update results.
	 * @return array{type: string, slug: string}|null
	 */
	public static function map_error_file_to_extension( string $file, array $update_results ): ?array {
		$absolute = self::absolute_path( $file );
		if ( '' === $absolute ) {
			return null;
		}

		$plugin_dir = self::normalize_path( (string) WP_PLUGIN_DIR );
		if ( self::path_is_inside( $absolute, $plugin_dir ) ) {
			$relative = ltrim( substr( $absolute, strlen( rtrim( $plugin_dir, '/' ) ) ), '/' );
			$slug     = strstr( $relative, '/' ) ? substr( $relative, 0, (int) strpos( $relative, '/' ) ) : $relative;
			if ( '' === $slug ) {
				return null;
			}

			$matched = self::match_plugin_in_results( $relative, $slug, $update_results );
			if ( is_array( $matched ) ) {
				return $matched;
			}

			return array(
				'type' => 'plugin',
				'slug' => $slug,
			);
		}

		$theme_root = self::normalize_path( self::get_theme_root_path() );
		if ( self::path_is_inside( $absolute, $theme_root ) ) {
			$relative = ltrim( substr( $absolute, strlen( rtrim( $theme_root, '/' ) ) ), '/' );
			$slug     = strstr( $relative, '/' ) ? substr( $relative, 0, (int) strpos( $relative, '/' ) ) : $relative;
			if ( '' === $slug ) {
				return null;
			}

			$matched = self::match_theme_in_results( $slug, $update_results );
			if ( is_array( $matched ) ) {
				return $matched;
			}

			return array(
				'type' => 'theme',
				'slug' => $slug,
			);
		}

		return null;
	}

	/**
	 * Inspect updater results, harvest core fatals, then scrape if enabled.
	 *
	 * @param mixed $update_results Results from WP_Automatic_Updater.
	 */
	public function inspect_update_results( $update_results ): void {
		self::reset();

		if ( defined( 'WP_SANDBOX_SCRAPING' ) && WP_SANDBOX_SCRAPING ) {
			return;
		}

		if ( ! is_array( $update_results ) ) {
			return;
		}

		$this->harvest_result_errors( $update_results );

		if ( ! self::is_enabled() ) {
			return;
		}

		if ( ! $this->has_scrape_candidates( $update_results ) ) {
			return;
		}

		$this->scrape_after_updates( $update_results );
	}

	/**
	 * Store WP_Error fatal codes from core's updater.
	 *
	 * @param array<string, array<int, mixed>> $update_results Update results.
	 */
	private function harvest_result_errors( array $update_results ): void {
		foreach ( array( 'plugin', 'theme', 'core' ) as $type ) {
			if ( empty( $update_results[ $type ] ) || ! is_array( $update_results[ $type ] ) ) {
				continue;
			}

			foreach ( $update_results[ $type ] as $item ) {
				if ( ! is_object( $item ) || ! isset( $item->result ) || ! self::is_fatal_result( $item->result ) ) {
					continue;
				}

				$slug = self::item_slug( $type, $item );
				if ( '' === $slug ) {
					continue;
				}

				$messages                              = $item->result->get_error_messages();
				self::$findings[ $type . ':' . $slug ] = array(
					'type'   => $type,
					'slug'   => $slug,
					'source' => 'core',
					'error'  => array(
						'type'    => 0,
						'message' => is_array( $messages ) ? implode( "\n", $messages ) : (string) $item->result->get_error_message(),
						'file'    => '',
						'line'    => 0,
					),
				);
			}
		}
	}

	/**
	 * Loopback-scrape home, then wp-admin, after successful plugin/theme updates.
	 *
	 * @param array<string, array<int, mixed>> $update_results Update results.
	 */
	private function scrape_after_updates( array $update_results ): void {
		$switched = false;
		if ( is_multisite() ) {
			$main_site_id = (int) get_main_site_id();
			if ( $main_site_id > 0 && get_current_blog_id() !== $main_site_id ) {
				switch_to_blog( $main_site_id );
				$switched = true;
			}
		}

		$urls = array(
			home_url( '/' ),
			admin_url( '/' ),
		);

		/**
		 * Filter loopback URLs used for post-update fatal detection.
		 *
		 * @since 1.0.0
		 *
		 * @param array<int, string>               $urls           URLs to scrape.
		 * @param array<string, array<int, mixed>> $update_results Update results.
		 */
		$urls = apply_filters( 'updatebee_fatal_detection_urls', $urls, $update_results );
		if ( ! is_array( $urls ) ) {
			$urls = array();
		}

		foreach ( $urls as $url ) {
			if ( ! is_string( $url ) || '' === $url ) {
				continue;
			}

			$parsed = $this->perform_loopback( $url );
			if ( 'ok' === $parsed['outcome'] ) {
				continue;
			}

			if ( 'fatal' === $parsed['outcome'] && isset( $parsed['error'] ) && is_array( $parsed['error'] ) ) {
				$this->store_scrape_fatal( $parsed['error'], $update_results );
			}

			break;
		}

		if ( $switched ) {
			restore_current_blog();
		}
	}

	/**
	 * Store a scrape fatal against one extension or the whole batch.
	 *
	 * @param array<string, mixed>             $error          Scrape error payload.
	 * @param array<string, array<int, mixed>> $update_results Update results.
	 */
	private function store_scrape_fatal( array $error, array $update_results ): void {
		$file   = isset( $error['file'] ) ? (string) $error['file'] : '';
		$mapped = self::map_error_file_to_extension( $file, $update_results );

		if ( is_array( $mapped ) && $this->batch_contains( $update_results, $mapped['type'], $mapped['slug'] ) ) {
			self::$findings[ $mapped['type'] . ':' . $mapped['slug'] ] = array(
				'type'   => $mapped['type'],
				'slug'   => $mapped['slug'],
				'source' => 'scrape',
				'error'  => $error,
			);
			return;
		}

		self::$unattributed = array(
			'type'   => '',
			'slug'   => '',
			'source' => 'scrape',
			'error'  => $error,
		);
	}

	/**
	 * Whether any plugin or theme update succeeded and should be scraped.
	 *
	 * @param array<string, array<int, mixed>> $update_results Update results.
	 * @return bool
	 */
	private function has_scrape_candidates( array $update_results ): bool {
		foreach ( array( 'plugin', 'theme' ) as $type ) {
			if ( empty( $update_results[ $type ] ) || ! is_array( $update_results[ $type ] ) ) {
				continue;
			}

			foreach ( $update_results[ $type ] as $item ) {
				if ( ! is_object( $item ) ) {
					continue;
				}
				if ( isset( $item->result ) && is_wp_error( $item->result ) ) {
					continue;
				}

				return true;
			}
		}

		return false;
	}

	/**
	 * Perform one native scrape loopback request.
	 *
	 * @param string $url URL to request.
	 * @return array<string, mixed>
	 */
	private function perform_loopback( string $url ): array {
		$scrape_key   = md5( (string) wp_rand() );
		$scrape_nonce = (string) wp_rand();
		$transient    = 'scrape_key_' . $scrape_key;
		set_transient( $transient, $scrape_nonce, 60 );

		$scrape_params = array(
			'wp_scrape_key'   => $scrape_key,
			'wp_scrape_nonce' => $scrape_nonce,
		);
		$headers       = array(
			'Cache-Control' => 'no-cache',
		);

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Used only to copy request cookies for loopback, matching core.
		$cookies = wp_unslash( $_COOKIE );

		if ( isset( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic auth header copy, matching core.
			$headers['Authorization'] = 'Basic ' . base64_encode( wp_unslash( $_SERVER['PHP_AUTH_USER'] ) . ':' . wp_unslash( $_SERVER['PHP_AUTH_PW'] ) );
		}

		if ( function_exists( 'set_time_limit' ) ) {
			// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Match core loopback scrape timeout.
			set_time_limit( 300 );
		}

		$timeout = 50;
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core filter, matching core loopback.
		$sslverify = apply_filters( 'https_local_ssl_verify', false, $url );
		$request   = add_query_arg( $scrape_params, $url );
		$response  = wp_safe_remote_get(
			$request,
			array(
				'cookies'   => $cookies,
				'headers'   => $headers,
				'timeout'   => $timeout,
				'sslverify' => $sslverify,
			)
		);

		$body = '';
		if ( ! is_wp_error( $response ) ) {
			$body = (string) wp_remote_retrieve_body( $response );
		}

		$parsed = self::parse_scrape_body( $body, $scrape_key );
		delete_transient( $transient );

		if ( is_wp_error( $response ) && 'fatal' !== $parsed['outcome'] ) {
			return array(
				'outcome' => 'inconclusive',
				'code'    => 'loopback_request_failed',
			);
		}

		return $parsed;
	}

	/**
	 * Whether results include a plugin or theme slug.
	 *
	 * @param array<string, array<int, mixed>> $update_results Update results.
	 * @param string                           $type           plugin|theme.
	 * @param string                           $slug           Slug.
	 * @return bool
	 */
	private function batch_contains( array $update_results, string $type, string $slug ): bool {
		if ( empty( $update_results[ $type ] ) || ! is_array( $update_results[ $type ] ) ) {
			return false;
		}

		foreach ( $update_results[ $type ] as $item ) {
			if ( is_object( $item ) && self::item_slug( $type, $item ) === $slug ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Slug for an updater result item.
	 *
	 * @param string $type plugin|theme|core.
	 * @param object $item Result object.
	 * @return string
	 */
	private static function item_slug( string $type, object $item ): string {
		if ( 'plugin' === $type && isset( $item->item->slug ) ) {
			return (string) $item->item->slug;
		}
		if ( 'theme' === $type && isset( $item->item->theme ) ) {
			return (string) $item->item->theme;
		}
		if ( 'core' === $type ) {
			return 'core';
		}

		return '';
	}

	/**
	 * Match a plugin path against this batch.
	 *
	 * @param string                           $relative       Path relative to the plugins directory.
	 * @param string                           $slug           First path segment.
	 * @param array<string, array<int, mixed>> $update_results Update results.
	 * @return array{type: string, slug: string}|null
	 */
	private static function match_plugin_in_results( string $relative, string $slug, array $update_results ): ?array {
		if ( empty( $update_results['plugin'] ) || ! is_array( $update_results['plugin'] ) ) {
			return null;
		}

		foreach ( $update_results['plugin'] as $plugin ) {
			if ( ! is_object( $plugin ) || ! isset( $plugin->item->plugin ) ) {
				continue;
			}

			$plugin_file = (string) $plugin->item->plugin;
			$plugin_slug = isset( $plugin->item->slug ) ? (string) $plugin->item->slug : '';
			if ( $plugin_file === $relative || $plugin_slug === $slug || str_starts_with( $relative, $plugin_slug . '/' ) ) {
				return array(
					'type' => 'plugin',
					'slug' => '' !== $plugin_slug ? $plugin_slug : $slug,
				);
			}
		}

		return null;
	}

	/**
	 * Match a theme stylesheet against this batch.
	 *
	 * @param string                           $slug           Theme stylesheet.
	 * @param array<string, array<int, mixed>> $update_results Update results.
	 * @return array{type: string, slug: string}|null
	 */
	private static function match_theme_in_results( string $slug, array $update_results ): ?array {
		if ( empty( $update_results['theme'] ) || ! is_array( $update_results['theme'] ) ) {
			return null;
		}

		foreach ( $update_results['theme'] as $theme ) {
			if ( ! is_object( $theme ) || ! isset( $theme->item->theme ) ) {
				continue;
			}

			if ( (string) $theme->item->theme === $slug ) {
				return array(
					'type' => 'theme',
					'slug' => $slug,
				);
			}
		}

		return null;
	}

	/**
	 * Theme directory root.
	 *
	 * @return string
	 */
	private static function get_theme_root_path(): string {
		if ( function_exists( 'get_theme_root' ) ) {
			$root = get_theme_root();
			if ( is_string( $root ) && '' !== $root ) {
				return $root;
			}
		}

		$content = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : ABSPATH . 'wp-content';

		return $content . '/themes';
	}

	/**
	 * Turn a scrape file path into an absolute path.
	 *
	 * @param string $file File from error_get_last().
	 * @return string
	 */
	private static function absolute_path( string $file ): string {
		$file = self::normalize_path( $file );
		if ( '' === $file ) {
			return '';
		}

		if ( str_starts_with( $file, '/' ) || preg_match( '/^[A-Za-z]:\//', $file ) ) {
			return $file;
		}

		return self::normalize_path( rtrim( ABSPATH, '/\\' ) . '/' . ltrim( $file, '/' ) );
	}

	/**
	 * Whether $path is inside $directory.
	 *
	 * @param string $path      Absolute file path.
	 * @param string $directory Directory path.
	 * @return bool
	 */
	private static function path_is_inside( string $path, string $directory ): bool {
		$directory = rtrim( $directory, '/' ) . '/';

		return rtrim( $directory, '/' ) === $path || str_starts_with( $path, $directory );
	}

	/**
	 * Normalize directory separators.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	private static function normalize_path( string $path ): string {
		return str_replace( '\\', '/', $path );
	}
}
