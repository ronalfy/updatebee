<?php
/**
 * White-label branding resolver.
 *
 * @package updatebee
 */

namespace UpdateBee;

/**
 * Apply saved branding to plugin identity surfaces.
 */
class Branding {

	/**
	 * Cached plugin header defaults.
	 *
	 * @var array<string, string>|null
	 */
	private static $header = null;

	/**
	 * Register branding filters.
	 */
	public function run(): void {
		add_filter( 'updatebee_plugin_name', array( $this, 'filter_plugin_name' ) );
		add_filter( 'all_plugins', array( $this, 'filter_all_plugins' ) );
	}

	/**
	 * Reset cached header defaults.
	 */
	public static function flush_cache(): void {
		self::$header = null;
	}

	/**
	 * Replace the display name when a branded name is saved.
	 *
	 * @param string $plugin_name Default plugin name.
	 * @return string
	 */
	public function filter_plugin_name( string $plugin_name ): string {
		$branded = self::get_saved_name();
		if ( '' !== $branded ) {
			return $branded;
		}

		return $plugin_name;
	}

	/**
	 * Override this plugin's row on the Plugins screen.
	 *
	 * @param array<string, mixed> $plugins Installed plugins.
	 * @return array<string, mixed>
	 */
	public function filter_all_plugins( array $plugins ): array {
		$file = Functions::get_plugin_file();
		if ( ! isset( $plugins[ $file ] ) || ! is_array( $plugins[ $file ] ) ) {
			return $plugins;
		}

		$name = self::get_saved_name();
		if ( '' !== $name ) {
			$plugins[ $file ]['Name']  = $name;
			$plugins[ $file ]['Title'] = $name;
		}

		$description = self::get_saved_description();
		if ( '' !== $description ) {
			$plugins[ $file ]['Description'] = $description;
		}

		$author = self::get_saved_author();
		if ( '' !== $author ) {
			$plugins[ $file ]['Author']     = $author;
			$plugins[ $file ]['AuthorName'] = $author;
		}

		$url = self::get_saved_url();
		if ( '' !== $url ) {
			$plugins[ $file ]['PluginURI'] = $url;
			$plugins[ $file ]['AuthorURI'] = $url;
		}

		return $plugins;
	}

	/**
	 * Saved plugin name, or empty when using the default.
	 *
	 * @return string
	 */
	public static function get_saved_name(): string {
		$value = Options::get_options( 'branding_name' );
		return is_string( $value ) ? trim( $value ) : '';
	}

	/**
	 * Saved plugin description, or empty when using the default.
	 *
	 * @return string
	 */
	public static function get_saved_description(): string {
		$value = Options::get_options( 'branding_description' );
		return is_string( $value ) ? trim( $value ) : '';
	}

	/**
	 * Saved plugin author, or empty when using the default.
	 *
	 * @return string
	 */
	public static function get_saved_author(): string {
		$value = Options::get_options( 'branding_author' );
		return is_string( $value ) ? trim( $value ) : '';
	}

	/**
	 * Saved plugin URL, or empty when using the default.
	 *
	 * @return string
	 */
	public static function get_saved_url(): string {
		$value = Options::get_options( 'branding_url' );
		return is_string( $value ) ? trim( $value ) : '';
	}

	/**
	 * Bundled default logo URL.
	 *
	 * @return string
	 */
	public static function get_default_logo_url(): string {
		return Functions::get_plugin_url( '/assets/logo.png' );
	}

	/**
	 * Plugin-header defaults used as placeholders.
	 *
	 * @return array<string, string>
	 */
	public static function get_header_defaults(): array {
		if ( is_array( self::$header ) ) {
			return self::$header;
		}

		$defaults = array(
			'name'        => __( 'UpdateBee', 'updatebee' ),
			'description' => '',
			'author'      => '',
			'url'         => '',
		);

		if ( defined( 'UPDATEBEE_FILE' ) ) {
			if ( ! function_exists( 'get_plugin_data' ) ) {
				$plugin_file = ABSPATH . 'wp-admin/includes/plugin.php';
				if ( file_exists( $plugin_file ) ) {
					require_once $plugin_file;
				}
			}

			if ( function_exists( 'get_plugin_data' ) ) {
				$data = get_plugin_data( UPDATEBEE_FILE, false, false );
				if ( is_array( $data ) ) {
					if ( ! empty( $data['Name'] ) ) {
						$defaults['name'] = (string) $data['Name'];
					}
					if ( ! empty( $data['Description'] ) ) {
						$defaults['description'] = (string) $data['Description'];
					}
					if ( ! empty( $data['Author'] ) ) {
						$defaults['author'] = wp_strip_all_tags( (string) $data['Author'] );
					}
					if ( ! empty( $data['PluginURI'] ) ) {
						$defaults['url'] = (string) $data['PluginURI'];
					}
				}
			}
		}

		self::$header = $defaults;

		return $defaults;
	}

	/**
	 * Sanitize branding fields for storage.
	 *
	 * @param array<string, mixed> $input Raw branding fields.
	 * @return array<string, string>
	 */
	public static function sanitize_settings( array $input ): array {
		$name = '';
		if ( isset( $input['branding_name'] ) ) {
			$name = sanitize_text_field( (string) $input['branding_name'] );
		}

		$description = '';
		if ( isset( $input['branding_description'] ) ) {
			$description = sanitize_textarea_field( (string) $input['branding_description'] );
		}

		$author = '';
		if ( isset( $input['branding_author'] ) ) {
			$author = sanitize_text_field( (string) $input['branding_author'] );
		}

		$url = '';
		if ( isset( $input['branding_url'] ) ) {
			$raw = sanitize_text_field( (string) $input['branding_url'] );
			$url = '' !== $raw ? esc_url_raw( $raw ) : '';
		}

		return array(
			'branding_name'        => $name,
			'branding_description' => $description,
			'branding_author'      => $author,
			'branding_url'         => $url,
		);
	}
}
