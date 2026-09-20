<?php
/**
 * Helper functions for the plugin.
 *
 * @package updatebee
 */

namespace UpdateBee;

/**
 * Class Functions.
 */
class Functions {

	/**
	 * Checks if the plugin is on a multisite install.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $network_admin Check if in network admin.
	 * @return bool True if network-active on multisite, false if not.
	 */
	public static function is_multisite( bool $network_admin = false ): bool {
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( $network_admin && ! is_network_admin() ) {
			return false;
		}

		if ( is_multisite() && is_plugin_active_for_network( self::get_plugin_file() ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Return the URL to the admin screen.
	 *
	 * @param string $tab     Tab path to load.
	 * @param string $sub_tab Subtab path to load.
	 * @return string URL to admin screen. Output is not escaped.
	 */
	public static function get_settings_url( string $tab = '', string $sub_tab = '' ): string {
		$options_url = admin_url( 'tools.php?page=updatebee' );
		if ( self::is_multisite( true ) ) {
			$options_url = network_admin_url( 'settings.php?page=updatebee' );
		}

		if ( ! empty( $tab ) ) {
			$options_url = add_query_arg( array( 'tab' => sanitize_title( $tab ) ), $options_url );
			if ( ! empty( $sub_tab ) ) {
				$options_url = add_query_arg( array( 'subtab' => sanitize_title( $sub_tab ) ), $options_url );
			}
		}

		return $options_url;
	}

	/**
	 * Get the current admin tab.
	 *
	 * @return string|null Current admin tab.
	 */
	public static function get_admin_tab() {
		$tab = filter_input( INPUT_GET, 'tab', FILTER_DEFAULT );
		if ( ! is_string( $tab ) || '' === $tab ) {
			return null;
		}

		return sanitize_text_field( sanitize_title( $tab ) );
	}

	/**
	 * Get the current admin sub-tab.
	 *
	 * @return string|null Current admin sub-tab.
	 */
	public static function get_admin_sub_tab() {
		$tab = filter_input( INPUT_GET, 'tab', FILTER_DEFAULT );
		if ( ! is_string( $tab ) || '' === $tab ) {
			return null;
		}

		$subtab = filter_input( INPUT_GET, 'subtab', FILTER_DEFAULT );
		if ( ! is_string( $subtab ) || '' === $subtab ) {
			return null;
		}

		return sanitize_text_field( sanitize_title( $subtab ) );
	}

	/**
	 * Return the plugin slug.
	 *
	 * @return string Plugin slug.
	 */
	public static function get_plugin_slug(): string {
		return dirname( plugin_basename( UPDATEBEE_FILE ) );
	}

	/**
	 * Return the basefile for the plugin.
	 *
	 * @return string Base file for the plugin.
	 */
	public static function get_plugin_file(): string {
		return plugin_basename( UPDATEBEE_FILE );
	}

	/**
	 * Return the version for the plugin.
	 *
	 * @return string Version for the plugin.
	 */
	public static function get_plugin_version(): string {
		return UPDATEBEE_VERSION;
	}

	/**
	 * Return the plugin name for the plugin.
	 *
	 * @return string Plugin name.
	 */
	public static function get_plugin_name(): string {
		/**
		 * Filter the output of the plugin name.
		 *
		 * @since 1.0.0
		 *
		 * @param string $plugin_name Plugin name.
		 */
		return (string) apply_filters( 'updatebee_plugin_name', __( 'UpdateBee', 'updatebee' ) );
	}

	/**
	 * Retrieve the plugin menu name.
	 *
	 * @return string Plugin menu name.
	 */
	public static function get_plugin_menu_name(): string {
		/**
		 * Filter the output of the plugin menu name.
		 *
		 * @since 1.0.0
		 *
		 * @param string $menu_name Plugin menu name.
		 */
		return (string) apply_filters( 'updatebee_plugin_menu_name', __( 'Automatic Updates', 'updatebee' ) );
	}

	/**
	 * Retrieve the plugin title.
	 *
	 * @return string Plugin title.
	 */
	public static function get_plugin_title(): string {
		/**
		 * Filter the output of the plugin title.
		 *
		 * @since 1.0.0
		 *
		 * @param string $plugin_title Plugin title.
		 */
		return (string) apply_filters( 'updatebee_plugin_menu_title', self::get_plugin_name() );
	}

	/**
	 * Determine the role/capability of the user needing admin access.
	 *
	 * @return bool False if user can't access options, true if they can.
	 */
	public static function can_access_options(): bool {
		if ( self::is_multisite() ) {
			return current_user_can( 'manage_network' );
		}

		return current_user_can( 'manage_options' );
	}

	/**
	 * Returns appropriate html for KSES.
	 *
	 * @param bool $svg         Whether to add SVG data to KSES.
	 * @param bool $with_tables Whether to add table data to KSES.
	 * @return array<string, mixed> Allowed KSES tags.
	 */
	public static function get_kses_allowed_html( bool $svg = true, bool $with_tables = false ): array {
		$allowed_tags = wp_kses_allowed_html( 'post' );

		$allowed_tags['nav']        = array(
			'class' => array(),
		);
		$allowed_tags['a']['class'] = array();

		if ( ! $svg ) {
			return $allowed_tags;
		}

		$allowed_tags['svg'] = array(
			'xmlns'       => array(),
			'fill'        => array(),
			'viewbox'     => array(),
			'role'        => array(),
			'aria-hidden' => array(),
			'focusable'   => array(),
			'class'       => array(),
			'width'       => array(),
			'height'      => array(),
		);

		$allowed_tags['path'] = array(
			'd'       => array(),
			'fill'    => array(),
			'opacity' => array(),
		);

		$allowed_tags['g'] = array();

		$allowed_tags['circle'] = array(
			'cx'     => array(),
			'cy'     => array(),
			'r'      => array(),
			'fill'   => array(),
			'stroke' => array(),
		);

		$allowed_tags['use'] = array(
			'href'       => array(),
			'xlink:href' => array(),
		);

		$allowed_tags['symbol'] = array(
			'aria-hidden' => array(),
			'viewBox'     => array(),
			'id'          => array(),
			'xmls'        => array(),
		);

		if ( $with_tables ) {
			$allowed_tags['table'] = array(
				'class'        => array(),
				'width'        => array(),
				'border'       => array(),
				'cellpadding'  => array(),
				'cellspacing'  => array(),
				'role'         => array(),
				'presentation' => array(),
				'align'        => array(),
				'bgcolor'      => array(),
			);
			$allowed_tags['tbody'] = array();
			$allowed_tags['thead'] = array();
			$allowed_tags['tr']    = array(
				'bgcolor' => array(),
				'align'   => array(),
				'style'   => array(),
			);
			$allowed_tags['th']    = array();
			$allowed_tags['td']    = array(
				'class' => array(),
				'width' => array(),
				'style' => array(),
			);
		}

		return $allowed_tags;
	}

	/**
	 * Take a _ separated field and convert to camelcase.
	 *
	 * @param string $field Field to convert to camelcase.
	 * @return string camelCased field.
	 */
	public static function to_camelcase( string $field ): string {
		return str_replace( '_', '', lcfirst( ucwords( $field, '_' ) ) );
	}

	/**
	 * Get the plugin directory for a path.
	 *
	 * @param string $path The path to the file.
	 * @return string The new path.
	 */
	public static function get_plugin_dir( string $path = '' ): string {
		$dir = rtrim( plugin_dir_path( UPDATEBEE_FILE ), '/' );
		if ( ! empty( $path ) ) {
			$dir .= '/' . ltrim( $path, '/' );
		}

		return $dir;
	}

	/**
	 * Return a plugin URL path.
	 *
	 * @param string $path Path to the file.
	 * @return string URL to the file.
	 */
	public static function get_plugin_url( string $path = '' ): string {
		$dir = rtrim( plugin_dir_url( UPDATEBEE_FILE ), '/' );
		if ( ! empty( $path ) ) {
			$dir .= '/' . ltrim( $path, '/' );
		}

		return $dir;
	}
}
