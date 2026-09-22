<?php
/**
 * Ajax handlers for the plugin.
 *
 * @package updatebee
 */

namespace UpdateBee\Admin;

use UpdateBee\Functions;
use UpdateBee\Options;
use UpdateBee\Tables\Table_Plugins;
use UpdateBee\Tables\Table_Themes;

/**
 * Class Ajax.
 */
class Ajax {

	/**
	 * Class runner.
	 */
	public function run(): void {
		add_action( 'wp_ajax_updatebee_get_dashboard_updates', array( $this, 'get_dashboard_updates' ) );
		add_action( 'wp_ajax_updatebee_save_dashboard_settings', array( $this, 'save_dashboard_settings' ) );
		add_action( 'wp_ajax_updatebee_reset_dashboard_settings', array( $this, 'reset_dashboard_settings' ) );
		add_action( 'wp_ajax_updatebee_retrieve_plugins', array( $this, 'retrieve_plugins' ) );
		add_action( 'wp_ajax_updatebee_toggle_plugin_auto_updates', array( $this, 'toggle_plugin_auto_updates' ) );
		add_action( 'wp_ajax_updatebee_toggle_plugin_block_updates', array( $this, 'toggle_plugin_block_updates' ) );
		add_action( 'wp_ajax_updatebee_toggle_plugin_patches', array( $this, 'toggle_plugin_patches' ) );
		add_action( 'wp_ajax_updatebee_bulk_plugin_action', array( $this, 'bulk_plugin_action' ) );
		add_action( 'wp_ajax_updatebee_save_plugin_note', array( $this, 'save_plugin_note' ) );
		add_action( 'wp_ajax_updatebee_retrieve_themes', array( $this, 'retrieve_themes' ) );
		add_action( 'wp_ajax_updatebee_toggle_theme_auto_updates', array( $this, 'toggle_theme_auto_updates' ) );
		add_action( 'wp_ajax_updatebee_toggle_theme_block_updates', array( $this, 'toggle_theme_block_updates' ) );
		add_action( 'wp_ajax_updatebee_toggle_theme_patches', array( $this, 'toggle_theme_patches' ) );
		add_action( 'wp_ajax_updatebee_bulk_theme_action', array( $this, 'bulk_theme_action' ) );
		add_action( 'wp_ajax_updatebee_save_theme_note', array( $this, 'save_theme_note' ) );
	}

	/**
	 * Return dashboard automatic-update options.
	 */
	public function get_dashboard_updates(): void {
		if ( ! Functions::can_access_options() ) {
			wp_send_json_error();
		}

		check_ajax_referer( 'updatebee-retrieve-dashboard-settings', 'nonce' );

		wp_send_json_success( Options::get_options() );
	}

	/**
	 * Save dashboard automatic-update options.
	 */
	public function save_dashboard_settings(): void {
		if ( ! Functions::can_access_options() ) {
			wp_send_json_error();
		}

		check_ajax_referer( 'updatebee-save-dashboard-settings', 'nonce' );

		$options = array();
		foreach ( $this->get_dashboard_option_keys() as $key ) {
			$value           = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
			$options[ $key ] = $this->sanitize_on_off( $value );
		}

		$updated = Options::update_options( '', $options );
		if ( null === $updated ) {
			wp_send_json_error();
		}

		wp_send_json_success( $updated );
	}

	/**
	 * Reset dashboard automatic-update options to defaults.
	 */
	public function reset_dashboard_settings(): void {
		if ( ! Functions::can_access_options() ) {
			wp_send_json_error();
		}

		check_ajax_referer( 'updatebee-reset-dashboard-settings', 'nonce' );

		// Match Options defaults for the dashboard toggles only.
		$updated = Options::update_options( '', $this->get_dashboard_option_defaults() );
		if ( null === $updated ) {
			wp_send_json_error();
		}

		wp_send_json_success( $updated );
	}

	/**
	 * Default values for dashboard automatic-update options.
	 *
	 * @return array<string, string>
	 */
	private function get_dashboard_option_defaults(): array {
		return array(
			'major_auto_updates'               => 'off',
			'minor_auto_updates'               => 'on',
			'plugin_auto_updates'              => 'off',
			'plugin_auto_updates_custom'       => 'off',
			'plugin_updates_disabled'          => 'off',
			'plugin_manual_updates_only'       => 'off',
			'plugin_auto_updates_patches_only' => 'off',
			'theme_auto_updates'               => 'off',
			'theme_auto_updates_custom'        => 'off',
			'theme_updates_disabled'           => 'off',
			'theme_manual_updates_only'        => 'off',
			'theme_auto_updates_patches_only'  => 'off',
			'translation_auto_updates'         => 'on',
			'translation_auto_updates_custom'  => 'off',
			'translation_updates_disabled'     => 'off',
			'translation_manual_updates_only'  => 'off',
		);
	}

	/**
	 * Allowed dashboard automatic-update option keys.
	 *
	 * @return array<int, string>
	 */
	private function get_dashboard_option_keys(): array {
		return array_keys( $this->get_dashboard_option_defaults() );
	}

	/**
	 * Sanitize an on/off option value.
	 *
	 * @param string $value Incoming value.
	 * @return string `on` or `off`.
	 */
	private function sanitize_on_off( string $value ): string {
		return 'on' === $value ? 'on' : 'off';
	}

	/**
	 * Return plugin list data for the Plugins screen.
	 */
	public function retrieve_plugins(): void {
		if ( ! Functions::can_access_options() ) {
			wp_send_json_error();
		}

		check_ajax_referer( 'updatebee-retrieve-plugins', 'nonce' );

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = apply_filters( 'all_plugins', get_plugins() );
		if ( ! is_array( $all_plugins ) ) {
			$all_plugins = array();
		}

		$active_plugin_slugs = Functions::get_active_plugins();
		if ( ! is_array( $active_plugin_slugs ) ) {
			$active_plugin_slugs = array();
		}

		$auto_updated_plugins = Functions::get_auto_updates_plugins();
		if ( ! is_array( $auto_updated_plugins ) ) {
			$auto_updated_plugins = array();
		}

		$plugin_notes = Table_Plugins::get_all_notes();
		if ( ! is_array( $plugin_notes ) ) {
			$plugin_notes = array();
		}

		foreach ( $all_plugins as $file => $plugin ) {
			if ( ! is_string( $file ) ) {
				continue;
			}

			if ( ! is_array( $plugin ) ) {
				$plugin = array();
			}

			$plugin['File']        = $file;
			$plugin['Slug']        = dirname( $file );
			$plugin['EnableNonce'] = wp_create_nonce( 'updatebee-plugin-enable-' . $file );
			$plugin['BlockNonce']  = wp_create_nonce( 'updatebee-plugin-block-' . $file );
			$plugin['PatchNonce']  = wp_create_nonce( 'updatebee-plugin-patches-' . $file );
			$plugin['NoteNonce']   = wp_create_nonce( 'updatebee-plugin-notes-' . $file );
			$plugin['Notes']       = isset( $plugin_notes[ $file ] ) ? (string) $plugin_notes[ $file ] : '';
			$plugin['Active']      = in_array( $file, $active_plugin_slugs, true );
			$plugin['Autoupdates'] = isset( $auto_updated_plugins[ $file ] );
			$plugin['Blocked']     = Functions::auto_updates_blocked( $file, 'plugin' );
			$plugin['PatchesOnly'] = Functions::auto_updates_patches_only( $file, 'plugin' );

			$plugin_file_path = WP_PLUGIN_DIR . '/' . $file;
			$mtime            = file_exists( $plugin_file_path ) ? filemtime( $plugin_file_path ) : false;
			if ( false !== $mtime ) {
				$date_format           = (string) get_option( 'date_format' );
				$time_format           = (string) get_option( 'time_format' );
				$plugin['LastUpdated'] = gmdate( $date_format, $mtime ) . ' ' . _x( 'at', 'Date and time separator', 'updatebee' ) . ' ' . gmdate( $time_format, $mtime );
			} else {
				$plugin['LastUpdated'] = __( 'Unknown', 'updatebee' );
			}

			$all_plugins[ $file ] = $plugin;
		}

		$plugin_info = get_site_transient( 'update_plugins' );
		if ( is_object( $plugin_info ) ) {
			foreach ( $all_plugins as $plugin_file => $plugin_data ) {
				if ( ! is_string( $plugin_file ) || ! is_array( $plugin_data ) ) {
					continue;
				}
				if ( isset( $plugin_data['Description'] ) ) {
					$plugin_data['Description'] = wp_trim_words( wp_strip_all_tags( $plugin_data['Description'] ), 20, '...' );
				}

				$extra = null;
				if ( isset( $plugin_info->response[ $plugin_file ] ) ) {
					$extra = $plugin_info->response[ $plugin_file ];
				} elseif ( isset( $plugin_info->no_update[ $plugin_file ] ) ) {
					$extra = $plugin_info->no_update[ $plugin_file ];
				}

				if ( is_object( $extra ) || is_array( $extra ) ) {
					$all_plugins[ $plugin_file ] = array_merge( (array) $extra, $plugin_data );
				}

				$slug = $all_plugins[ $plugin_file ]['slug'] ?? '';
				if ( is_string( $slug ) && '' !== $slug ) {
					// AJAX is not a network admin request, so self_admin_url() cannot be used here.
					$plugin_install_url                     = Functions::is_multisite() ? network_admin_url( 'plugin-install.php' ) : admin_url( 'plugin-install.php' );
					$all_plugins[ $plugin_file ]['Details'] = esc_url_raw(
						add_query_arg(
							array(
								'tab'       => 'plugin-information',
								'plugin'    => $slug,
								'TB_iframe' => 'true',
								'width'     => 600,
								'height'    => 550,
							),
							$plugin_install_url
						)
					);
				}
			}
		}

		$options = Options::get_options();
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		$status = 'disabled';
		if ( isset( $options['plugin_updates_disabled'] ) && 'on' === $options['plugin_updates_disabled'] ) {
			$status = 'blocked';
		} elseif ( isset( $options['plugin_auto_updates'] ) && 'on' === $options['plugin_auto_updates'] ) {
			if ( isset( $options['plugin_auto_updates_custom'] ) && 'on' === $options['plugin_auto_updates_custom'] ) {
				$status = 'custom';
			} else {
				$status = 'enabled';
			}
		}

		wp_send_json_success(
			array(
				'plugins'              => $all_plugins,
				'auto_updates_enabled' => $status,
			)
		);
	}

	/**
	 * Enable or disable auto-updates for a single plugin.
	 */
	public function toggle_plugin_auto_updates(): void {
		if ( ! Functions::can_access_options() ) {
			wp_send_json_error();
		}

		$file = $this->get_posted_plugin_file();
		check_ajax_referer( 'updatebee-plugin-enable-' . $file, 'nonce' );

		if ( ! Functions::plugin_file_exists( $file ) || ! Functions::plugin_auto_updates_available() ) {
			wp_send_json_error();
		}

		$updated = Functions::set_plugin_auto_update( $file, $this->get_posted_enabled() );
		if ( null === $updated ) {
			wp_send_json_error();
		}

		wp_send_json_success();
	}

	/**
	 * Block or unblock updates for a single plugin.
	 */
	public function toggle_plugin_block_updates(): void {
		if ( ! Functions::can_access_options() ) {
			wp_send_json_error();
		}

		$file = $this->get_posted_plugin_file();
		check_ajax_referer( 'updatebee-plugin-block-' . $file, 'nonce' );

		if ( ! Functions::plugin_file_exists( $file ) || Functions::plugin_updates_fully_blocked() ) {
			wp_send_json_error();
		}

		$updated = Functions::set_plugin_blocked( $file, $this->get_posted_enabled() );
		if ( null === $updated ) {
			wp_send_json_error();
		}

		wp_send_json_success();
	}

	/**
	 * Enable or disable patches-only auto-updates for a single plugin.
	 */
	public function toggle_plugin_patches(): void {
		if ( ! Functions::can_access_options() ) {
			wp_send_json_error();
		}

		$file = $this->get_posted_plugin_file();
		check_ajax_referer( 'updatebee-plugin-patches-' . $file, 'nonce' );

		if ( ! Functions::plugin_file_exists( $file ) || ! Functions::plugin_auto_updates_available() ) {
			wp_send_json_error();
		}

		$updated = Functions::set_plugin_patches_only( $file, $this->get_posted_enabled() );
		if ( null === $updated ) {
			wp_send_json_error();
		}

		wp_send_json_success();
	}

	/**
	 * Apply a bulk action to selected plugin files.
	 */
	public function bulk_plugin_action(): void {
		if ( ! Functions::can_access_options() ) {
			wp_send_json_error();
		}

		check_ajax_referer( 'updatebee-plugin-bulk', 'nonce' );

		$bulk_action = isset( $_POST['bulk_action'] ) ? sanitize_key( wp_unslash( $_POST['bulk_action'] ) ) : '';
		$allowed     = array( 'enable', 'disable', 'block', 'unblock', 'patches' );
		if ( ! in_array( $bulk_action, $allowed, true ) ) {
			wp_send_json_error();
		}

		$needs_auto_updates = in_array( $bulk_action, array( 'enable', 'disable', 'patches' ), true );
		if ( $needs_auto_updates && ! Functions::plugin_auto_updates_available() ) {
			wp_send_json_error();
		}

		$is_block_action = in_array( $bulk_action, array( 'block', 'unblock' ), true );
		if ( $is_block_action && Functions::plugin_updates_fully_blocked() ) {
			wp_send_json_error();
		}

		$files = $this->get_posted_plugin_files();
		foreach ( $files as $file ) {
			switch ( $bulk_action ) {
				case 'enable':
					Functions::set_plugin_auto_update( $file, true );
					break;
				case 'disable':
					Functions::set_plugin_auto_update( $file, false );
					break;
				case 'block':
					Functions::set_plugin_blocked( $file, true );
					break;
				case 'unblock':
					Functions::set_plugin_blocked( $file, false );
					break;
				case 'patches':
					Functions::set_plugin_patches_only( $file, true );
					break;
			}
		}

		wp_send_json_success();
	}

	/**
	 * Save or remove notes for a single plugin.
	 */
	public function save_plugin_note(): void {
		if ( ! Functions::can_access_options() ) {
			wp_send_json_error();
		}

		$file = $this->get_posted_plugin_file();
		check_ajax_referer( 'updatebee-plugin-notes-' . $file, 'nonce' );

		if ( ! Functions::plugin_file_exists( $file ) ) {
			wp_send_json_error();
		}

		$notes = '';
		if ( isset( $_POST['notes'] ) && is_string( $_POST['notes'] ) ) {
			$notes = sanitize_textarea_field( wp_unslash( $_POST['notes'] ) );
		}

		$saved = Table_Plugins::save_notes( $file, $notes );
		if ( is_wp_error( $saved ) ) {
			wp_send_json_error();
		}

		wp_send_json_success(
			array(
				'notes' => Table_Plugins::get_notes( $file ),
			)
		);
	}

	/**
	 * Return theme list data for the Themes screen.
	 */
	public function retrieve_themes(): void {
		if ( ! Functions::can_access_options() ) {
			wp_send_json_error();
		}

		check_ajax_referer( 'updatebee-retrieve-themes', 'nonce' );

		$installed_themes    = Functions::get_installed_themes();
		$auto_updated_themes = Functions::get_auto_updates_themes();
		if ( ! is_array( $auto_updated_themes ) ) {
			$auto_updated_themes = array();
		}

		$theme_notes = Table_Themes::get_all_notes();
		if ( ! is_array( $theme_notes ) ) {
			$theme_notes = array();
		}

		$current_stylesheet = get_stylesheet();
		$all_themes         = array();

		foreach ( $installed_themes as $slug => $theme ) {
			if ( ! is_string( $slug ) || ! $theme instanceof \WP_Theme ) {
				continue;
			}

			$stylesheet = $theme->get_stylesheet();
			$parent     = $theme->parent();
			$screenshot = $theme->get_screenshot();
			$style_css  = $theme->get_stylesheet_directory() . '/style.css';
			$mtime      = file_exists( $style_css ) ? filemtime( $style_css ) : false;
			if ( false !== $mtime ) {
				$date_format  = (string) get_option( 'date_format' );
				$time_format  = (string) get_option( 'time_format' );
				$last_updated = gmdate( $date_format, $mtime ) . ' ' . _x( 'at', 'Date and time separator', 'updatebee' ) . ' ' . gmdate( $time_format, $mtime );
			} else {
				$last_updated = __( 'Unknown', 'updatebee' );
			}

			$all_themes[ $stylesheet ] = array(
				'Stylesheet'     => $stylesheet,
				'Template'       => $theme->get_template(),
				'Slug'           => $stylesheet,
				'Name'           => (string) $theme->get( 'Name' ),
				'Description'    => (string) $theme->get( 'Description' ),
				'Version'        => (string) $theme->get( 'Version' ),
				'AuthorName'     => (string) $theme->display( 'Author', false ),
				'AuthorURI'      => (string) $theme->get( 'AuthorURI' ),
				'ThemeURI'       => (string) $theme->get( 'ThemeURI' ),
				'Screenshot'     => is_string( $screenshot ) ? $screenshot : '',
				'ParentName'     => $parent instanceof \WP_Theme ? (string) $parent->get( 'Name' ) : '',
				'EnableNonce'    => wp_create_nonce( 'updatebee-theme-enable-' . $stylesheet ),
				'BlockNonce'     => wp_create_nonce( 'updatebee-theme-block-' . $stylesheet ),
				'PatchNonce'     => wp_create_nonce( 'updatebee-theme-patches-' . $stylesheet ),
				'NoteNonce'      => wp_create_nonce( 'updatebee-theme-notes-' . $stylesheet ),
				'Notes'          => isset( $theme_notes[ $stylesheet ] ) ? (string) $theme_notes[ $stylesheet ] : '',
				'Active'         => $stylesheet === $current_stylesheet,
				'NetworkEnabled' => Functions::is_multisite() ? (bool) $theme->is_allowed( 'network' ) : false,
				'Autoupdates'    => isset( $auto_updated_themes[ $stylesheet ] ),
				'Blocked'        => Functions::auto_updates_blocked( $stylesheet, 'theme' ),
				'PatchesOnly'    => Functions::auto_updates_patches_only( $stylesheet, 'theme' ),
				'LastUpdated'    => $last_updated,
			);
		}

		$theme_info = get_site_transient( 'update_themes' );
		if ( is_object( $theme_info ) ) {
			foreach ( $all_themes as $stylesheet => $theme_data ) {
				if ( ! is_string( $stylesheet ) || ! is_array( $theme_data ) ) {
					continue;
				}
				if ( isset( $theme_data['Description'] ) ) {
					$theme_data['Description'] = wp_trim_words( wp_strip_all_tags( $theme_data['Description'] ), 20, '...' );
				}

				$extra = null;
				if ( isset( $theme_info->response[ $stylesheet ] ) ) {
					$extra = $theme_info->response[ $stylesheet ];
				} elseif ( isset( $theme_info->no_update[ $stylesheet ] ) ) {
					$extra = $theme_info->no_update[ $stylesheet ];
				}

				if ( is_object( $extra ) || is_array( $extra ) ) {
					$all_themes[ $stylesheet ] = array_merge( (array) $extra, $theme_data );
				} else {
					$all_themes[ $stylesheet ] = $theme_data;
				}

				$theme_slug = $all_themes[ $stylesheet ]['theme'] ?? $stylesheet;
				$theme_id   = $all_themes[ $stylesheet ]['id'] ?? '';
				$theme_url  = $all_themes[ $stylesheet ]['url'] ?? '';
				$is_dot_org = ( is_string( $theme_id ) && false !== stripos( $theme_id, 'w.org' ) )
					|| ( is_string( $theme_url ) && false !== stripos( $theme_url, 'wordpress.org/themes' ) );

				if ( $is_dot_org && is_string( $theme_slug ) && '' !== $theme_slug ) {
					// AJAX is not a network admin request, so self_admin_url() cannot be used here.
					$theme_install_url                    = Functions::is_multisite() ? network_admin_url( 'theme-install.php' ) : admin_url( 'theme-install.php' );
					$all_themes[ $stylesheet ]['Details'] = esc_url_raw(
						add_query_arg(
							array(
								'tab'       => 'theme-information',
								'theme'     => $theme_slug,
								'TB_iframe' => 'true',
								'width'     => 600,
								'height'    => 550,
							),
							$theme_install_url
						)
					);
				}
			}
		}

		$options = Options::get_options();
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		$status = 'disabled';
		if ( isset( $options['theme_updates_disabled'] ) && 'on' === $options['theme_updates_disabled'] ) {
			$status = 'blocked';
		} elseif ( isset( $options['theme_auto_updates'] ) && 'on' === $options['theme_auto_updates'] ) {
			if ( isset( $options['theme_auto_updates_custom'] ) && 'on' === $options['theme_auto_updates_custom'] ) {
				$status = 'custom';
			} else {
				$status = 'enabled';
			}
		}

		wp_send_json_success(
			array(
				'themes'               => $all_themes,
				'auto_updates_enabled' => $status,
			)
		);
	}

	/**
	 * Enable or disable auto-updates for a single theme.
	 */
	public function toggle_theme_auto_updates(): void {
		if ( ! Functions::can_access_options() ) {
			wp_send_json_error();
		}

		$stylesheet = $this->get_posted_theme_stylesheet();
		check_ajax_referer( 'updatebee-theme-enable-' . $stylesheet, 'nonce' );

		if ( ! Functions::theme_stylesheet_exists( $stylesheet ) || ! Functions::theme_auto_updates_available() ) {
			wp_send_json_error();
		}

		$updated = Functions::set_theme_auto_update( $stylesheet, $this->get_posted_enabled() );
		if ( null === $updated ) {
			wp_send_json_error();
		}

		wp_send_json_success();
	}

	/**
	 * Block or unblock updates for a single theme.
	 */
	public function toggle_theme_block_updates(): void {
		if ( ! Functions::can_access_options() ) {
			wp_send_json_error();
		}

		$stylesheet = $this->get_posted_theme_stylesheet();
		check_ajax_referer( 'updatebee-theme-block-' . $stylesheet, 'nonce' );

		if ( ! Functions::theme_stylesheet_exists( $stylesheet ) || Functions::theme_updates_fully_blocked() ) {
			wp_send_json_error();
		}

		$updated = Functions::set_theme_blocked( $stylesheet, $this->get_posted_enabled() );
		if ( null === $updated ) {
			wp_send_json_error();
		}

		wp_send_json_success();
	}

	/**
	 * Enable or disable patches-only auto-updates for a single theme.
	 */
	public function toggle_theme_patches(): void {
		if ( ! Functions::can_access_options() ) {
			wp_send_json_error();
		}

		$stylesheet = $this->get_posted_theme_stylesheet();
		check_ajax_referer( 'updatebee-theme-patches-' . $stylesheet, 'nonce' );

		if ( ! Functions::theme_stylesheet_exists( $stylesheet ) || ! Functions::theme_auto_updates_available() ) {
			wp_send_json_error();
		}

		$updated = Functions::set_theme_patches_only( $stylesheet, $this->get_posted_enabled() );
		if ( null === $updated ) {
			wp_send_json_error();
		}

		wp_send_json_success();
	}

	/**
	 * Apply a bulk action to selected theme stylesheets.
	 */
	public function bulk_theme_action(): void {
		if ( ! Functions::can_access_options() ) {
			wp_send_json_error();
		}

		check_ajax_referer( 'updatebee-theme-bulk', 'nonce' );

		$bulk_action = isset( $_POST['bulk_action'] ) ? sanitize_key( wp_unslash( $_POST['bulk_action'] ) ) : '';
		$allowed     = array( 'enable', 'disable', 'block', 'unblock', 'patches' );
		if ( ! in_array( $bulk_action, $allowed, true ) ) {
			wp_send_json_error();
		}

		$needs_auto_updates = in_array( $bulk_action, array( 'enable', 'disable', 'patches' ), true );
		if ( $needs_auto_updates && ! Functions::theme_auto_updates_available() ) {
			wp_send_json_error();
		}

		$is_block_action = in_array( $bulk_action, array( 'block', 'unblock' ), true );
		if ( $is_block_action && Functions::theme_updates_fully_blocked() ) {
			wp_send_json_error();
		}

		$stylesheets = $this->get_posted_theme_stylesheets();
		foreach ( $stylesheets as $stylesheet ) {
			switch ( $bulk_action ) {
				case 'enable':
					Functions::set_theme_auto_update( $stylesheet, true );
					break;
				case 'disable':
					Functions::set_theme_auto_update( $stylesheet, false );
					break;
				case 'block':
					Functions::set_theme_blocked( $stylesheet, true );
					break;
				case 'unblock':
					Functions::set_theme_blocked( $stylesheet, false );
					break;
				case 'patches':
					Functions::set_theme_patches_only( $stylesheet, true );
					break;
			}
		}

		wp_send_json_success();
	}

	/**
	 * Save or remove notes for a single theme.
	 */
	public function save_theme_note(): void {
		if ( ! Functions::can_access_options() ) {
			wp_send_json_error();
		}

		$stylesheet = $this->get_posted_theme_stylesheet();
		check_ajax_referer( 'updatebee-theme-notes-' . $stylesheet, 'nonce' );

		if ( ! Functions::theme_stylesheet_exists( $stylesheet ) ) {
			wp_send_json_error();
		}

		$notes = '';
		if ( isset( $_POST['notes'] ) && is_string( $_POST['notes'] ) ) {
			$notes = sanitize_textarea_field( wp_unslash( $_POST['notes'] ) );
		}

		$saved = Table_Themes::save_notes( $stylesheet, $notes );
		if ( is_wp_error( $saved ) ) {
			wp_send_json_error();
		}

		wp_send_json_success(
			array(
				'notes' => Table_Themes::get_notes( $stylesheet ),
			)
		);
	}

	/**
	 * Sanitize a posted plugin file path.
	 *
	 * @return string Plugin basename path.
	 */
	private function get_posted_plugin_file(): string {
		// File is required to verify the per-plugin nonce in the caller.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$file = isset( $_POST['file'] ) ? sanitize_text_field( wp_unslash( $_POST['file'] ) ) : '';

		return sanitize_text_field( urldecode( $file ) );
	}

	/**
	 * Sanitize a posted theme stylesheet slug.
	 *
	 * @return string Theme stylesheet slug.
	 */
	private function get_posted_theme_stylesheet(): string {
		// Stylesheet is required to verify the per-theme nonce in the caller.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$stylesheet = isset( $_POST['stylesheet'] ) ? sanitize_text_field( wp_unslash( $_POST['stylesheet'] ) ) : '';

		return sanitize_text_field( urldecode( $stylesheet ) );
	}

	/**
	 * Sanitize posted theme stylesheets for a bulk action.
	 *
	 * @return array<int, string> Installed theme stylesheets.
	 */
	private function get_posted_theme_stylesheets(): array {
		// Callers verify `updatebee-theme-bulk` before reading stylesheets.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$stylesheets = isset( $_POST['stylesheets'] ) ? Functions::sanitize_array_recursive( wp_unslash( $_POST['stylesheets'] ) ) : array();
		if ( ! is_array( $stylesheets ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $stylesheets as $stylesheet ) {
			if ( ! is_string( $stylesheet ) ) {
				continue;
			}

			$stylesheet = sanitize_text_field( urldecode( $stylesheet ) );
			if ( ! Functions::theme_stylesheet_exists( $stylesheet ) ) {
				continue;
			}

			$sanitized[] = $stylesheet;
		}

		return array_values( array_unique( $sanitized ) );
	}

	/**
	 * Sanitize posted plugin files for a bulk action.
	 *
	 * @return array<int, string> Installed plugin files.
	 */
	private function get_posted_plugin_files(): array {
		// Callers verify `updatebee-plugin-bulk` before reading files.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$files = isset( $_POST['files'] ) ? Functions::sanitize_array_recursive( wp_unslash( $_POST['files'] ) ) : array();
		if ( ! is_array( $files ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $files as $file ) {
			if ( ! is_string( $file ) ) {
				continue;
			}

			$file = sanitize_text_field( urldecode( $file ) );
			if ( ! Functions::plugin_file_exists( $file ) ) {
				continue;
			}

			$sanitized[] = $file;
		}

		return array_values( array_unique( $sanitized ) );
	}

	/**
	 * Sanitize a posted enabled flag.
	 *
	 * @return bool True when enabled.
	 */
	private function get_posted_enabled(): bool {
		// Callers verify a nonce before reading the enabled flag.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['enabled'] ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$value = wp_unslash( $_POST['enabled'] );
		if ( is_array( $value ) ) {
			return false;
		}

		return (bool) rest_sanitize_boolean( $value );
	}
}
