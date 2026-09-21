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
	 * Array data that must be sanitized.
	 *
	 * @param array $data Data to be sanitized.
	 *
	 * @return array Sanitized data.
	 */
	public static function sanitize_array_recursive( array $data ) {
		$sanitized_data = array();
		foreach ( $data as $key => $value ) {
			if ( '0' === $value ) {
				$value = 0;
			}
			if ( 'true' === $value ) {
				$value = true;
			} elseif ( 'false' === $value ) {
				$value = false;
			}
			if ( is_array( $value ) ) {
				$value                  = self::sanitize_array_recursive( $value );
				$sanitized_data[ $key ] = $value;
				continue;
			}
			if ( is_bool( $value ) ) {
				$sanitized_data[ $key ] = (bool) $value;
				continue;
			}
			if ( is_int( $value ) ) {
				$sanitized_data[ $key ] = (int) $value;
				continue;
			}
			if ( is_string( $value ) ) {
				$sanitized_data[ $key ] = sanitize_text_field( $value );
				continue;
			}
		}
		return $sanitized_data;
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
	 * Recursive argument parsing
	 *
	 * This acts like a multi-dimensional version of wp_parse_args() (minus
	 * the querystring parsing - you must pass arrays).
	 *
	 * @since 1.0.0
	 * @param array $a Array of arguments.
	 * @param array $b Array of arguments.
	 * @return array Array of arguments.
	 */
	public static function parse_args_r( array &$a, array $b ): array {
		$a = (array) $a;
		$b = (array) $b;
		$r = $b;

		foreach ( $a as $k => &$v ) {
			if ( is_array( $v ) && isset( $r[ $k ] ) ) {
				$r[ $k ] = self::parse_args_r( $v, $r[ $k ] );
			} else {
				$r[ $k ] = $v;
			}
		}

		return $r;
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
	 * Gets an array of plugins active on either the current site, or site-wide
	 *
	 * @since 1.0.0
	 *
	 * @return array A list of plugin paths (relative to the plugin directory)
	 */
	public static function get_active_plugins() {

		// Gets all active plugins on the current site.
		$active_plugins = get_option( 'active_plugins' );

		if ( self::is_multisite() ) {
			$network_active_plugins = get_site_option( 'active_sitewide_plugins' );
			if ( ! empty( $network_active_plugins ) ) {
				$network_active_plugins = array_keys( $network_active_plugins );
				$active_plugins         = array_merge( $active_plugins, $network_active_plugins );
			}
		}

		return $active_plugins;
	}

	/**
	 * Get auto-update stats for plugins, themes, or translations.
	 *
	 * @param string $type Type of asset to retrieve stats for.
	 *
	 * @return array Asset stats count (enabled, disabled, blocked).
	 */
	public static function get_auto_update_stats( $type = 'plugins' ) {

		$enabled  = 0;
		$disabled = 0;
		$blocked  = 0;
		switch ( $type ) {
			case 'plugins':
				$plugins = apply_filters( 'all_plugins', get_plugins() );
				foreach ( $plugins as $file => $plugin ) {
					if ( self::auto_updates_enabled( $file, 'plugin' ) ) {
						++$enabled;
					} else {
						++$disabled;
					}
				}
				$blocked = count( Options::get_options( 'blocked_plugins' ) );
				break;
			case 'themes':
				$themes = apply_filters( 'all_themes', wp_get_themes() );
				foreach ( $themes as $slug => $theme ) {
					if ( self::auto_updates_enabled( $slug, 'theme' ) ) {
						++$enabled;
					} else {
						++$disabled;
					}
				}
				$blocked = count( Options::get_options( 'blocked_themes' ) );
				break;
			case 'translations':
				$translations = self::get_all_translations( true );
				foreach ( $translations as $slug => $translation_data ) {
					if ( self::auto_updates_enabled( $slug, 'translation' ) ) {
						++$enabled;
					} else {
						++$disabled;
					}
				}
				$blocked = count( Options::get_options( 'blocked_translations' ) );
				break;
		}
		return array(
			'enabled'  => $enabled,
			'disabled' => $disabled,
			'blocked'  => $blocked,
		);
	}

	/**
	 * Gets an array of plugins active on either the current site, or site-wide
	 *
	 * @since 1.0.0
	 *
	 * @param string $mode active|inactive.
	 *
	 * @return array A list of plugin paths (relative to the plugin directory)
	 */
	public static function get_auto_updates_plugins( $mode = 'active' ) {

		// Gets all active plugins on the current site.
		$plugins                       = apply_filters( 'all_plugins', get_plugins() );
		$auto_updated_plugins          = array();
		$auto_updated_plugins_disabled = array();

		foreach ( $plugins as $key => $plugin ) {
			if ( self::auto_updates_enabled( $key, 'plugin' ) ) {
				$auto_updated_plugins[ $key ] = $plugin;
			} else {
				$auto_updated_plugins_disabled[ $key ] = $plugin;
			}
		}
		if ( 'active' === $mode ) {
			return $auto_updated_plugins;
		}
		return $auto_updated_plugins_disabled;
	}

	/**
	 * Retrieve all the translations for the site.
	 *
	 * @param bool $force Retrieve without caching.
	 *
	 * Dev note by Ronald Huereca <ronald@mediaron.com>: This method is unused, but is left in for demonstration purposes
	 * on how to retrieve per asset the translations. It is not advisable to limit
	 * translations to a per-plugin/per-theme/core as if a language pack is removed
	 * the blocked|disabled status is still stored and it will
	 * be impossible to get the translations back without having to reset all the
	 * translation data. An alternate approach was taken instead to get all the
	 * translations WordPress uses and then allowing the disabling/blocking of those.
	 * The overall conclusion is that translations for individual plugins/themes/core is infeasible.
	 */
	public static function get_all_translations( $force = false ) {
		// Get translated items formatted array.
		$translations_flat = wp_cache_get( 'uau_translations' );

		// Retrieve fresh items if cache is expired or force is true.
		if ( ! $translations_flat || $force ) {

			$translations_flat = array();

			// Retrieve all the translations.
			$core_translations   = wp_get_installed_translations( 'core' );
			$plugin_translations = wp_get_installed_translations( 'plugins' );
			$theme_translations  = wp_get_installed_translations( 'themes' );

			/**
			 * Loop through the core translations.
			 * If translations exist, loop through those and get translation data.
			 * Create slug based on group and locale.
			 * Store merged data in flat array.
			 * Rince/repeat for themes and plugins.
			 */
			foreach ( $core_translations as $group => $translations ) {
				if ( is_array( $translations ) && ! empty( $translations ) ) {
					foreach ( $translations as $locale => $translation_data ) {

						$translation_slug                       = sanitize_title( $group . '-' . $locale ); // admin-da_DK.
						$translations_flat[ $translation_slug ] = array_merge(
							$translation_data,
							array(
								'locale' => $locale,
								'group'  => $group,
								'type'   => 'core',
							)
						);
					}
				}
			}
			foreach ( $plugin_translations as $group => $translations ) {
				if ( is_array( $translations ) && ! empty( $translations ) ) {
					foreach ( $translations as $locale => $translation_data ) {
						$translation_slug = sanitize_title( $group . '-' . $locale ); // admin-da_DK.

						$plugin_updates = get_site_transient( 'update_plugins' );
						$plugins        = apply_filters( 'all_plugins', get_plugins() );
						$plugin_data    = array();
						if ( isset( $plugin_updates->response ) ) {
							foreach ( $plugin_updates->response as $plugin_path => $update_info ) {
								$path = dirname( $plugin_path ); // Get the plugin slug.
								if ( $group === $path ) {
									$plugin_data = $update_info;
									break;
								}
							}
						}
						if ( isset( $plugin_updates->no_update ) ) {
							foreach ( $plugin_updates->no_update as $plugin_path => $update_info ) {
								$path = dirname( $plugin_path ); // Get the plugin slug.
								if ( $group === $path ) {
									$plugin_data = $update_info;
									break;
								}
							}
						}

						$translations_flat[ $translation_slug ] = array_merge(
							$translation_data,
							array(
								'locale' => $locale,
								'group'  => $group,
								'type'   => 'plugin',
								'data'   => $plugin_data,
							)
						);
					}
				}
			}
			foreach ( $theme_translations as $group => $translations ) {
				if ( is_array( $translations ) && ! empty( $translations ) ) {
					foreach ( $translations as $locale => $translation_data ) {
						$translation_slug = sanitize_title( $group . '-' . $locale ); // admin-da_DK.

						$theme                                  = wp_get_theme( $group );
						$translations_flat[ $translation_slug ] = array_merge(
							$translation_data,
							array(
								'locale'     => $locale,
								'group'      => $group,
								'type'       => 'theme',
								'screenshot' => $theme->get_screenshot(),
							)
						);
					}
				}
			}
			// Phew, that was expensive. Let's cache.
			wp_cache_set( 'uau_translations', $translations_flat );
		}

		// Structure:
		//
		// [admin-da_dk] => Array
		// (
		// [POT-Creation-Date] =>
		// [PO-Revision-Date] => 2021-05-05 10:02:38+0000
		// [Project-Id-Version] => WordPress - 5.7.x - Development - Administration
		// [X-Generator] => GlotPress/3.0.0-alpha.2
		// [locale] => da_DK
		// [group] => admin
		// [type] => core
		// )
		// [admin-es_es] => Array
		// (
		// [POT-Creation-Date] =>
		// [PO-Revision-Date] => 2021-03-05 12:23:26+0000
		// [Project-Id-Version] => WordPress - 5.7.x - Development - Administration
		// [X-Generator] => GlotPress/3.0.0-alpha.2
		// [locale] => es_ES
		// [group] => admin
		// [type] => core
		// ).
		return $translations_flat;
	}

	/**
	 * Retrieve an ajax response in an error state.
	 *
	 * @param string $message The message to display in a modal.
	 * @param string $title   The title to display in a modal.
	 *
	 * @return array {
	 *    @type string $type        The type of message. Accepts 'critical', 'warning', 'success', 'info'.
	 *    @type string $message     The message to display in a modal.
	 *    @type string $title       The title to display in a modal.
	 *    @type bool   $dismissable Whether the message can be dismissed.
	 *    @type bool   $persistent  Whether the message is persistent.
	 * }
	 */
	public static function get_ajax_error_response( $message = '', $title = '' ) {
		return array(
			'type'          => 'critical',
			'message'       => $message,
			'title'         => $title,
			'isDismissable' => true,
			'isPersistent'  => true,

		);
	}

	/**
	 * Retrieve an ajax response in a success state.
	 *
	 * @param string $message The message to display in a snackbar.
	 *
	 * @return array {
	 *   @type string $type        The type of message. Accepts 'critical', 'warning', 'success', 'info'.
	 *   @type string $message     The message to display in a snackbar.
	 *   @type bool   $dismissable Whether the message can be dismissed.
	 *   @type bool   $persistent  Whether the message is persistent.
	 * }
	 */
	public static function get_ajax_success_response( $message ) {
		return array(
			'type'          => 'success',
			'message'       => $message,
			'isDismissable' => false,
			'isPersistent'  => false,
		);
	}

	/**
	 * Gets an array of translations active on either the current site, or site-wide
	 *
	 * @since 1.0.0
	 *
	 * @param string $mode  active|inactive.
	 * @param bool   $force Retrieve without caching.
	 *
	 * @return array A list of translation slugs.
	 */
	public static function get_auto_updates_translations( $mode = 'active', $force = false ) {

		$translations = array();
		if ( empty( $translations ) ) {
			require_once ABSPATH . 'wp-admin/includes/translation-install.php';
			$translations = wp_get_available_translations();
		}
		$auto_updated_translations          = array();
		$auto_updated_translations_disabled = array();

		// Now let's group them in buckets of enabled/disabled.
		foreach ( $translations as $key => $translation_data ) {
			if ( self::auto_updates_enabled( $key, 'translation' ) ) {
				$auto_updated_translations[ $key ] = $translation_data;
			} else {
				$auto_updated_translations_disabled[ $key ] = $translation_data;
			}
		}
		if ( 'active' === $mode ) {
			return $auto_updated_translations;
		}
		return $auto_updated_translations_disabled;
	}

	/**
	 * Gets an array of themes active on either the current site, or site-wide
	 *
	 * @since 1.0.0
	 *
	 * @param string $mode active|inactive.
	 *
	 * @return array A list of stylesheet paths (relative to the theme directory)
	 */
	public static function get_auto_updates_themes( $mode = 'active' ) {

		// Gets all active plugins on the current site.
		$themes                       = apply_filters( 'all_themes', wp_get_themes() );
		$auto_updated_themes          = array();
		$auto_updated_themes_disabled = array();

		foreach ( $themes as $slug => $theme ) {
			if ( self::auto_updates_enabled( $slug, 'theme' ) ) {
				$auto_updated_themes[ $slug ] = $theme;
			} else {
				$auto_updated_themes_disabled[ $slug ] = $theme;
			}
		}
		if ( 'active' === $mode ) {
			return $auto_updated_themes;
		}
		return $auto_updated_themes_disabled;
	}

	/**
	 * Checks to see if an asset is activated or not.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Path to the asset.
	 * @param string $type Type to check if it is activated or not.
	 *
	 * @return bool true if activated, false if not.
	 */
	public static function is_activated( $path, $type = 'plugin' ) {

		// Gets all active plugins on the current site.
		$active_plugins = self::is_multisite() ? get_site_option( 'active_sitewide_plugins' ) : get_option( 'active_plugins', array() );
		if ( in_array( $path, $active_plugins, true ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Clears update cache and re-checks version information.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $force_check Whether to force check updates.
	 */
	public static function clear_update_cache( bool $force_check = false ) {
		wp_clean_update_cache();
		wp_update_plugins();
		wp_update_themes();
		wp_version_check( array(), $force_check );
	}

	/**
	 * Checks to see if an asset is blocked from updates or not.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Path to the asset.
	 * @param string $type Type to check if it is blocked or not.
	 *
	 * @return bool true if blocked, false if not.
	 */
	public static function auto_updates_blocked( $path, $type = 'plugin' ) {
		$path = 'plugin' === $type ? sanitize_title( dirname( $path ) ) : sanitize_title( $path );

		switch ( $type ) {
			case 'plugin':
				$blocked_plugin = in_array( $path, Options::get_options( 'blocked_plugins' ), true );
				if ( $blocked_plugin ) {
					return true;
				}
				break;
			case 'theme':
				$blocked_theme = in_array( $path, Options::get_options( 'blocked_themes' ), true );
				if ( $blocked_theme ) {
					return true;
				}
				break;
			case 'translation':
				$blocked_translation = in_array( $path, Options::get_options( 'blocked_translations' ), true );
				if ( $blocked_translation ) {
					return true;
				}
				break;
		}
		return false;
	}

	/**
	 * Checks to see if an asset can be auto-updated or not.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path         Path to the asset.
	 * @param string $type         Type to check if it is activated or not. Values (plugin|translation|theme).
	 * @param bool   $skip_blocked Skip blocked update status.
	 *
	 * @return bool true if enabled, false if not.
	 */
	public static function auto_updates_enabled( $path, $type = 'plugin', $skip_blocked = false ) {
		$path = sanitize_text_field( $path );

		$options = Options::get_options();

		switch ( $type ) {
			case 'plugin':
				$plugin_auto_updates_enabled = isset( $options['plugin_auto_updates'] ) ? $options['plugin_auto_updates'] : 'off';
				$plugin_auto_updates_custom  = isset( $options['plugin_auto_updates_custom'] ) ? $options['plugin_auto_updates_custom'] : 'off';
				$is_active_update            = in_array( $path, $options['auto_update_plugins'], true );

				// Make sure plugin auto-updates are enabled or not.
				if ( ! $plugin_auto_updates_enabled || 'off' === $plugin_auto_updates_enabled ) {
					return false;
				}

				// If plugin is blocked, return false.
				if ( ! $skip_blocked && self::auto_updates_blocked( $path, 'plugin' ) ) {
					return false;
				}

				// Plugin updates are on, custom is enabled, and is not an active update.
				if ( 'on' === $plugin_auto_updates_enabled && 'on' === $plugin_auto_updates_custom && ! $is_active_update ) {
					return false;
				}

				// Plugins updates are enabled and custom plugins flag is off.
				if ( 'on' === $plugin_auto_updates_enabled && 'off' === $plugin_auto_updates_custom ) {
					return true;
				}

				// Return active or inactive state.
				return $is_active_update;
				break; // phpcs:ignore
			case 'theme':
				$theme_auto_update_enabled = isset( $options['theme_updates'] ) ? $options['theme_updates'] : 'off';
				$theme_custom              = isset( $options['theme_custom'] ) ? $options['theme_custom'] : 'off';
				$blocked_themes            = isset( $options['blocked_themes'] ) ? $options['blocked_themes'] : array();
				$is_active_update          = in_array( $path, $options['auto_update_themes'], true );

				// Make sure theme auto-updates are enabled or not.
				if ( ! $theme_auto_update_enabled || 'off' === $theme_auto_update_enabled ) {
					return false;
				}

				// If theme is blocked, return false.
				if ( in_array( $path, $blocked_themes, true ) ) {
					return false;
				}

				// Theme updates are on, custom is enabled, and is not an active update.
				if ( 'on' === $theme_auto_update_enabled && 'on' === $theme_custom && ! $is_active_update ) {
					return false;
				}

				// Theme updates are enabled and custom themes flag is off.
				if ( 'on' === $theme_auto_update_enabled && 'off' === $theme_custom ) {
					return true;
				}

				// Return active or inactive state.
				return $is_active_update;
				break; // phpcs:ignore
			case 'translation':
				$translation_auto_updates_enabled = isset( $options['translation_updates'] ) ? $options['translation_updates'] : 'on';
				$translation_custom               = isset( $options['translation_custom'] ) ? $options['translation_custom'] : 'off';
				$blocked_translations             = isset( $options['blocked_translations'] ) ? $options['blocked_translations'] : array();
				$is_active_update                 = in_array( $path, $options['auto_update_translations'], true );

				// Make sure plugin auto-updates are enabled or not.
				if ( ! $translation_auto_updates_enabled || 'off' === $translation_auto_updates_enabled ) {
					return false;
				}

				// If translation is blocked, return false.
				if ( in_array( $path, $blocked_translations, true ) ) {
					return false;
				}

				// Translation updates are on, custom is enabled, and is not an active update.
				if ( 'on' === $translation_auto_updates_enabled && 'on' === $translation_custom && ! $is_active_update ) {
					return false;
				}

				// Translation updates are enabled and custom plugins flag is off.
				if ( 'on' === $translation_auto_updates_enabled && 'off' === $translation_custom ) {
					return true;
				}

				// Return active or inactive state.
				return $is_active_update;
				break; // phpcs:ignore
		}
		return false;
	}

	/**
	 * Checks whether an asset is limited to patch auto-updates.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Path to the asset.
	 * @param string $type Asset type. Values (plugin|theme).
	 * @return bool True if patches only.
	 */
	public static function auto_updates_patches_only( $path, $type = 'plugin' ): bool {
		$path    = sanitize_text_field( $path );
		$options = Options::get_options();

		if ( 'plugin' === $type ) {
			if ( isset( $options['plugin_auto_updates_patches_only'] ) && 'on' === $options['plugin_auto_updates_patches_only'] ) {
				return true;
			}

			$patches = array();
			if ( isset( $options['auto_update_plugins_patches'] ) && is_array( $options['auto_update_plugins_patches'] ) ) {
				$patches = $options['auto_update_plugins_patches'];
			}

			return in_array( $path, $patches, true );
		}

		if ( 'theme' === $type ) {
			if ( isset( $options['theme_auto_updates_patches_only'] ) && 'on' === $options['theme_auto_updates_patches_only'] ) {
				return true;
			}

			$patches = array();
			if ( isset( $options['auto_update_themes_patches'] ) && is_array( $options['auto_update_themes_patches'] ) ) {
				$patches = $options['auto_update_themes_patches'];
			}

			return in_array( $path, $patches, true );
		}

		return false;
	}

	/**
	 * Whether all plugin updates are blocked from the dashboard.
	 *
	 * @return bool True when plugin updates are fully blocked.
	 */
	public static function plugin_updates_fully_blocked(): bool {
		$options = Options::get_options();
		if ( ! is_array( $options ) ) {
			return false;
		}

		return isset( $options['plugin_updates_disabled'] ) && 'on' === $options['plugin_updates_disabled'];
	}

	/**
	 * Whether the dashboard allows plugin auto-updates.
	 *
	 * @return bool True when auto-updates are on and not fully blocked.
	 */
	public static function plugin_auto_updates_available(): bool {
		if ( self::plugin_updates_fully_blocked() ) {
			return false;
		}

		$options = Options::get_options();
		if ( ! is_array( $options ) ) {
			return false;
		}

		return isset( $options['plugin_auto_updates'] ) && 'on' === $options['plugin_auto_updates'];
	}

	/**
	 * Installed plugin data keyed by plugin file.
	 *
	 * @return array<string, mixed> Plugin data.
	 */
	public static function get_installed_plugin_files(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = apply_filters( 'all_plugins', get_plugins() );
		if ( ! is_array( $all_plugins ) ) {
			return array();
		}

		return $all_plugins;
	}

	/**
	 * Whether a plugin file is installed.
	 *
	 * @param string $file Plugin basename path.
	 * @return bool True when the plugin exists.
	 */
	public static function plugin_file_exists( string $file ): bool {
		$all_plugins = self::get_installed_plugin_files();

		return isset( $all_plugins[ $file ] );
	}

	/**
	 * Add or remove an item from a stored options list.
	 *
	 * @param string $option_key Option array key.
	 * @param string $item       Item to add or remove.
	 * @param bool   $enabled    True to add, false to remove.
	 * @return array<int, mixed>|null Updated list or null on failure.
	 */
	public static function update_option_list( string $option_key, string $item, bool $enabled ) {
		if ( '' === $item ) {
			return null;
		}

		$list = Options::get_options( $option_key );
		if ( ! is_array( $list ) ) {
			$list = array();
		}

		$list = array_values( array_unique( $list ) );

		if ( $enabled ) {
			$list[] = $item;
			$list   = array_values( array_unique( $list ) );
		} else {
			$key = array_search( $item, $list, true );
			if ( false !== $key ) {
				unset( $list[ $key ] );
				$list = array_values( $list );
			}
		}

		$updated = Options::update_options( $option_key, $list );
		if ( null === $updated ) {
			return null;
		}

		$saved = Options::get_options( $option_key );

		return is_array( $saved ) ? $saved : array();
	}

	/**
	 * Enable or disable auto-updates for a plugin file.
	 *
	 * @param string $file    Plugin basename path.
	 * @param bool   $enabled True to enable auto-updates.
	 * @return array<int, mixed>|null Updated auto-update list or null on failure.
	 */
	public static function set_plugin_auto_update( string $file, bool $enabled ) {
		$result = self::update_option_list( 'auto_update_plugins', $file, $enabled );
		if ( ! $enabled ) {
			self::update_option_list( 'auto_update_plugins_patches', $file, false );
		}

		return $result;
	}

	/**
	 * Enable or disable patches-only auto-updates for a plugin file.
	 *
	 * @param string $file    Plugin basename path.
	 * @param bool   $enabled True to limit the plugin to patch auto-updates.
	 * @return array<int, mixed>|null Updated patches list or null on failure.
	 */
	public static function set_plugin_patches_only( string $file, bool $enabled ) {
		if ( $enabled ) {
			self::update_option_list( 'auto_update_plugins', $file, true );
		}

		return self::update_option_list( 'auto_update_plugins_patches', $file, $enabled );
	}

	/**
	 * Block or unblock a plugin from updates.
	 *
	 * @param string $file    Plugin basename path.
	 * @param bool   $blocked True to block updates.
	 * @return array<int, mixed>|null Updated blocked list or null on failure.
	 */
	public static function set_plugin_blocked( string $file, bool $blocked ) {
		$block_id = sanitize_title( dirname( $file ) );

		return self::update_option_list( 'blocked_plugins', $block_id, $blocked );
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
