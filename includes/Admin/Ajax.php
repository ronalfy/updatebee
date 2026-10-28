<?php
/**
 * Ajax handlers for the plugin.
 *
 * @package updatebee
 */

namespace UpdateBee\Admin;

use UpdateBee\Cron\Clear_Logs;
use UpdateBee\Emails\Email_Failures;
use UpdateBee\Emails\Email_Successes;
use UpdateBee\Functions;
use UpdateBee\Logs;
use UpdateBee\Options;
use UpdateBee\Updates\Init as Updates_Init;
use UpdateBee\Updates\Upgrade_Notice_Keywords;
use UpdateBee\Admin\Migrate\EasyUpdatesManager;
use UpdateBee\Tables\Table_Logs;
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
		// Dashboard and Settings Ajax.
		add_action( 'wp_ajax_updatebee_get_dashboard_updates', array( $this, 'get_dashboard_updates' ) );
		add_action( 'wp_ajax_updatebee_save_dashboard_settings', array( $this, 'save_dashboard_settings' ) );
		add_action( 'wp_ajax_updatebee_reset_dashboard_settings', array( $this, 'reset_dashboard_settings' ) );

		// Plugin Ajax actions.
		add_action( 'wp_ajax_updatebee_retrieve_plugins', array( $this, 'retrieve_plugins' ) );
		add_action( 'wp_ajax_updatebee_toggle_plugin_auto_updates', array( $this, 'toggle_plugin_auto_updates' ) );
		add_action( 'wp_ajax_updatebee_toggle_plugin_block_updates', array( $this, 'toggle_plugin_block_updates' ) );
		add_action( 'wp_ajax_updatebee_toggle_plugin_patches', array( $this, 'toggle_plugin_patches' ) );
		add_action( 'wp_ajax_updatebee_bulk_plugin_action', array( $this, 'bulk_plugin_action' ) );
		add_action( 'wp_ajax_updatebee_save_plugin_note', array( $this, 'save_plugin_note' ) );
		add_action( 'wp_ajax_updatebee_save_plugin_settings', array( $this, 'save_plugin_settings' ) );

		// Theme Ajax actions.
		add_action( 'wp_ajax_updatebee_retrieve_themes', array( $this, 'retrieve_themes' ) );
		add_action( 'wp_ajax_updatebee_toggle_theme_auto_updates', array( $this, 'toggle_theme_auto_updates' ) );
		add_action( 'wp_ajax_updatebee_toggle_theme_block_updates', array( $this, 'toggle_theme_block_updates' ) );
		add_action( 'wp_ajax_updatebee_toggle_theme_patches', array( $this, 'toggle_theme_patches' ) );
		add_action( 'wp_ajax_updatebee_bulk_theme_action', array( $this, 'bulk_theme_action' ) );
		add_action( 'wp_ajax_updatebee_save_theme_note', array( $this, 'save_theme_note' ) );
		add_action( 'wp_ajax_updatebee_save_theme_settings', array( $this, 'save_theme_settings' ) );

		// Translation Ajax actions.
		add_action( 'wp_ajax_updatebee_retrieve_translations', array( $this, 'retrieve_translations' ) );
		add_action( 'wp_ajax_updatebee_toggle_translation_auto_updates', array( $this, 'toggle_translation_auto_updates' ) );
		add_action( 'wp_ajax_updatebee_toggle_translation_block_updates', array( $this, 'toggle_translation_block_updates' ) );
		add_action( 'wp_ajax_updatebee_bulk_translation_action', array( $this, 'bulk_translation_action' ) );

		// Log Ajax actions.
		add_action( 'wp_ajax_updatebee_retrieve_logs', array( $this, 'retrieve_logs' ) );
		add_action( 'wp_ajax_updatebee_retrieve_log_detail', array( $this, 'retrieve_log_detail' ) );
		add_action( 'wp_ajax_updatebee_export_logs', array( $this, 'export_logs' ) );
		add_action( 'wp_ajax_updatebee_clear_logs', array( $this, 'clear_logs' ) );
		add_action( 'wp_ajax_updatebee_save_logs_settings', array( $this, 'save_logs_settings' ) );

		// Email Ajax actions.
		add_action( 'wp_ajax_updatebee_retrieve_email_settings', array( $this, 'retrieve_email_settings' ) );
		add_action( 'wp_ajax_updatebee_save_wordpress_email_settings', array( $this, 'save_wordpress_email_settings' ) );

		// Tools Ajax actions.
		add_action( 'wp_ajax_updatebee_force_updates', array( $this, 'force_updates' ) );
		add_action( 'wp_ajax_updatebee_trigger_update_cron', array( $this, 'trigger_update_cron' ) );
		add_action( 'wp_ajax_updatebee_refresh_updates', array( $this, 'refresh_updates' ) );
		add_action( 'wp_ajax_updatebee_migrate_eum', array( $this, 'migrate_eum' ) );

		// Advanced Ajax actions.
		add_action( 'wp_ajax_updatebee_save_advanced_settings', array( $this, 'save_advanced_settings' ) );
		add_action( 'wp_ajax_updatebee_reset_options', array( $this, 'reset_options' ) );
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

		$plugin_delays = Table_Plugins::get_all_delay_settings();
		if ( ! is_array( $plugin_delays ) ) {
			$plugin_delays = array();
		}

		foreach ( $all_plugins as $file => $plugin ) {
			if ( ! is_string( $file ) ) {
				continue;
			}

			if ( ! is_array( $plugin ) ) {
				$plugin = array();
			}

			$plugin['File']          = $file;
			$plugin['Slug']          = dirname( $file );
			$plugin['EnableNonce']   = wp_create_nonce( 'updatebee-plugin-enable-' . $file );
			$plugin['BlockNonce']    = wp_create_nonce( 'updatebee-plugin-block-' . $file );
			$plugin['PatchNonce']    = wp_create_nonce( 'updatebee-plugin-patches-' . $file );
			$plugin['NoteNonce']     = wp_create_nonce( 'updatebee-plugin-notes-' . $file );
			$plugin['Notes']         = isset( $plugin_notes[ $file ] ) ? (string) $plugin_notes[ $file ] : '';
			$plugin['SettingsNonce'] = wp_create_nonce( 'updatebee-plugin-settings-' . $file );
			$plugin['DelayDays']     = isset( $plugin_delays[ $file ]['delay_days'] ) ? $plugin_delays[ $file ]['delay_days'] : null;
			$plugin['AllowUntested'] = ! empty( $plugin_delays[ $file ]['allow_untested'] );
			$plugin['Active']        = in_array( $file, $active_plugin_slugs, true );
			$plugin['Autoupdates']   = isset( $auto_updated_plugins[ $file ] );
			$plugin['Blocked']       = Functions::auto_updates_blocked( $file, 'plugin' );
			$plugin['PatchesOnly']   = Functions::auto_updates_patches_only( $file, 'plugin' );

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
				'plugins'                => $all_plugins,
				'auto_updates_enabled'   => $status,
				'delay_updates'          => absint( $options['delay_updates'] ?? 0 ),
				'block_untested_updates' => 'on' === ( $options['block_untested_updates'] ?? 'off' ),
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
	 * Save delay settings for a single plugin.
	 */
	public function save_plugin_settings(): void {
		if ( ! Functions::can_access_options() ) {
			wp_send_json_error();
		}

		$file = $this->get_posted_plugin_file();
		check_ajax_referer( 'updatebee-plugin-settings-' . $file, 'nonce' );

		if ( ! Functions::plugin_file_exists( $file ) ) {
			wp_send_json_error();
		}

		$saved = Table_Plugins::save_delay_settings( $file, $this->get_posted_delay_days() );
		if ( is_wp_error( $saved ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Plugin settings could not be saved.', 'updatebee' ),
				)
			);
		}

		$untested = Table_Plugins::save_untested_setting( $file, $this->get_posted_allow_untested() );
		if ( is_wp_error( $untested ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Plugin settings could not be saved.', 'updatebee' ),
				)
			);
		}

		wp_send_json_success(
			array(
				'message'        => __( 'Plugin settings have been saved.', 'updatebee' ),
				'delay_days'     => $saved['delay_days'],
				'allow_untested' => $untested['allow_untested'],
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

		$theme_delays = Table_Themes::get_all_delay_settings();
		if ( ! is_array( $theme_delays ) ) {
			$theme_delays = array();
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
				'SettingsNonce'  => wp_create_nonce( 'updatebee-theme-settings-' . $stylesheet ),
				'DelayDays'      => isset( $theme_delays[ $stylesheet ]['delay_days'] ) ? $theme_delays[ $stylesheet ]['delay_days'] : null,
				'AllowUntested'  => ! empty( $theme_delays[ $stylesheet ]['allow_untested'] ),
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
				'themes'                 => $all_themes,
				'auto_updates_enabled'   => $status,
				'delay_updates'          => absint( $options['delay_updates'] ?? 0 ),
				'block_untested_updates' => 'on' === ( $options['block_untested_updates'] ?? 'off' ),
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
	 * Save delay settings for a single theme.
	 */
	public function save_theme_settings(): void {
		if ( ! Functions::can_access_options() ) {
			wp_send_json_error();
		}

		$stylesheet = $this->get_posted_theme_stylesheet();
		check_ajax_referer( 'updatebee-theme-settings-' . $stylesheet, 'nonce' );

		if ( ! Functions::theme_stylesheet_exists( $stylesheet ) ) {
			wp_send_json_error();
		}

		$saved = Table_Themes::save_delay_settings( $stylesheet, $this->get_posted_delay_days() );
		if ( is_wp_error( $saved ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Theme settings could not be saved.', 'updatebee' ),
				)
			);
		}

		$untested = Table_Themes::save_untested_setting( $stylesheet, $this->get_posted_allow_untested() );
		if ( is_wp_error( $untested ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Theme settings could not be saved.', 'updatebee' ),
				)
			);
		}

		wp_send_json_success(
			array(
				'message'        => __( 'Theme settings have been saved.', 'updatebee' ),
				'delay_days'     => $saved['delay_days'],
				'allow_untested' => $untested['allow_untested'],
			)
		);
	}

	/**
	 * Return translation locale data for the Translations screen.
	 */
	public function retrieve_translations(): void {
		if ( ! Functions::can_access_options() ) {
			wp_send_json_error();
		}

		check_ajax_referer( 'updatebee-retrieve-translations', 'nonce' );

		$catalog = Functions::get_available_translations();
		$options = Options::get_options();
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		$auto_update_translations = isset( $options['auto_update_translations'] ) && is_array( $options['auto_update_translations'] )
			? $options['auto_update_translations']
			: array();

		$all_translations = array();
		foreach ( $catalog as $locale => $translation ) {
			if ( ! is_string( $locale ) || ! is_array( $translation ) ) {
				continue;
			}

			$all_translations[] = array(
				'Locale'      => $locale,
				'EnglishName' => isset( $translation['english_name'] ) ? (string) $translation['english_name'] : $locale,
				'NativeName'  => isset( $translation['native_name'] ) ? (string) $translation['native_name'] : $locale,
				'Autoupdates' => in_array( $locale, $auto_update_translations, true ),
				'Blocked'     => Functions::auto_updates_blocked( $locale, 'translation' ),
				'EnableNonce' => wp_create_nonce( 'updatebee-translation-enable-' . $locale ),
				'BlockNonce'  => wp_create_nonce( 'updatebee-translation-block-' . $locale ),
			);
		}

		$status = 'disabled';
		if ( isset( $options['translation_updates_disabled'] ) && 'on' === $options['translation_updates_disabled'] ) {
			$status = 'blocked';
		} elseif ( isset( $options['translation_auto_updates'] ) && 'on' === $options['translation_auto_updates'] ) {
			if ( isset( $options['translation_auto_updates_custom'] ) && 'on' === $options['translation_auto_updates_custom'] ) {
				$status = 'custom';
			} else {
				$status = 'enabled';
			}
		}

		wp_send_json_success(
			array(
				'translations'         => $all_translations,
				'auto_updates_enabled' => $status,
			)
		);
	}

	/**
	 * Return update log rows for the Logs screen.
	 */
	public function retrieve_logs(): void {
		if ( ! Functions::can_access_options() ) {
			wp_send_json_error();
		}

		check_ajax_referer( 'updatebee-retrieve-logs', 'nonce' );

		$page     = isset( $_POST['page'] ) ? absint( wp_unslash( $_POST['page'] ) ) : 1;
		$per_page = isset( $_POST['per_page'] ) ? absint( wp_unslash( $_POST['per_page'] ) ) : 25;

		$sort_field     = isset( $_POST['sort_field'] ) ? sanitize_text_field( wp_unslash( $_POST['sort_field'] ) ) : 'id';
		$sort_direction = isset( $_POST['sort_direction'] ) ? sanitize_text_field( wp_unslash( $_POST['sort_direction'] ) ) : 'desc';

		$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

		$filters = array();
		if ( isset( $_POST['filters'] ) && is_array( $_POST['filters'] ) ) {
			$raw_filters = Functions::sanitize_array_recursive( wp_unslash( $_POST['filters'] ) );
			foreach ( $raw_filters as $filter ) {
				if ( ! is_array( $filter ) || ! isset( $filter['field'] ) || ! isset( $filter['value'] ) ) {
					continue;
				}
				$field             = sanitize_text_field( $filter['field'] );
				$value             = sanitize_text_field( $filter['value'] );
				$filters[ $field ] = $value;
			}
		}

		$field_map = array(
			'Name'         => 'name',
			'User'         => 'user_id',
			'FromVersion'  => 'from_version',
			'ToVersion'    => 'to_version',
			'UpdateOffer'  => 'update_offer',
			'UpdateType'   => 'update_type',
			'UpdateStatus' => 'update_status',
			'Date'         => 'date',
		);

		if ( isset( $field_map[ $sort_field ] ) ) {
			$sort_field = $field_map[ $sort_field ];
		}

		$mapped_filters = array();
		foreach ( $filters as $key => $value ) {
			$mapped_key                    = isset( $field_map[ $key ] ) ? $field_map[ $key ] : $key;
			$mapped_filters[ $mapped_key ] = $value;
		}

		$result = Table_Logs::get_logs_paginated(
			array(
				'page'           => $page,
				'per_page'       => $per_page,
				'sort_field'     => $sort_field,
				'sort_direction' => $sort_direction,
				'search'         => $search,
				'filters'        => $mapped_filters,
			)
		);

		$rows  = $result['logs'];
		$total = $result['total'];

		$logs = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$user_id = isset( $row['user_id'] ) ? absint( $row['user_id'] ) : 0;
			$user    = _x( 'None', 'No user found', 'updatebee' );
			if ( $user_id > 0 ) {
				$user_object = get_user_by( 'id', $user_id );
				if ( $user_object instanceof \WP_User ) {
					$user = $user_object->user_nicename;
				}
			}

			$raw        = isset( $row['raw'] ) ? (string) $row['raw'] : '';
			$stacktrace = isset( $row['stacktrace'] ) ? (string) $row['stacktrace'] : '';

			$logs[] = array(
				'Id'            => isset( $row['id'] ) ? absint( $row['id'] ) : 0,
				'Name'          => isset( $row['name'] ) ? (string) $row['name'] : '',
				'Slug'          => isset( $row['slug'] ) ? (string) $row['slug'] : '',
				'UserId'        => $user_id,
				'User'          => $user,
				'FromVersion'   => isset( $row['from_version'] ) ? (string) $row['from_version'] : '',
				'ToVersion'     => isset( $row['to_version'] ) ? (string) $row['to_version'] : '',
				'UpdateOffer'   => isset( $row['update_offer'] ) ? (string) $row['update_offer'] : '',
				'UpdateType'    => isset( $row['update_type'] ) ? (string) $row['update_type'] : '',
				'UpdateStatus'  => isset( $row['update_status'] ) ? (string) $row['update_status'] : '',
				'Date'          => isset( $row['date'] ) ? (string) $row['date'] : '',
				'HasNotes'      => '' !== trim( $raw ),
				'HasStacktrace' => '' !== trim( $stacktrace ),
			);
		}

		$total_pages = (int) ceil( $total / $per_page );

		wp_send_json_success(
			array(
				'logs'           => $logs,
				'paginationInfo' => array(
					'totalItems' => $total,
					'totalPages' => $total_pages,
				),
			)
		);
	}

	/**
	 * Return notes and formatted stack trace for a single log row.
	 */
	public function retrieve_log_detail(): void {
		if ( ! Functions::can_access_options() ) {
			wp_send_json_error();
		}

		check_ajax_referer( 'updatebee-retrieve-log-detail', 'nonce' );

		$log_id = isset( $_POST['log_id'] ) ? absint( wp_unslash( $_POST['log_id'] ) ) : 0;
		if ( $log_id < 1 ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid log entry.', 'updatebee' ),
				)
			);
		}

		$row = Table_Logs::get_log_by_id( $log_id );
		if ( null === $row ) {
			wp_send_json_error(
				array(
					'message' => __( 'Log entry not found.', 'updatebee' ),
				)
			);
		}

		$raw        = isset( $row['raw'] ) ? (string) $row['raw'] : '';
		$stacktrace = isset( $row['stacktrace'] ) ? (string) $row['stacktrace'] : '';

		wp_send_json_success(
			array(
				'Notes'      => $raw,
				'Stacktrace' => Logs::format_stacktrace_for_display( $stacktrace ),
			)
		);
	}

	/**
	 * Download filtered log rows as CSV.
	 */
	public function export_logs(): void {
		if ( ! Functions::can_access_options() ) {
			wp_die( esc_html__( 'You do not have permission to export logs.', 'updatebee' ) );
		}

		check_ajax_referer( 'updatebee-export-logs', 'nonce' );

		$update_offer  = isset( $_GET['update_offer'] ) ? sanitize_text_field( wp_unslash( $_GET['update_offer'] ) ) : 'all';
		$update_type   = isset( $_GET['update_type'] ) ? sanitize_text_field( wp_unslash( $_GET['update_type'] ) ) : 'all';
		$update_status = isset( $_GET['update_status'] ) ? sanitize_text_field( wp_unslash( $_GET['update_status'] ) ) : 'all';
		$date_from     = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
		$date_to       = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';

		$rows = Table_Logs::get_logs_for_export(
			array(
				'update_offer'  => $update_offer,
				'update_type'   => $update_type,
				'update_status' => $update_status,
				'date_from'     => $date_from,
				'date_to'       => $date_to,
			)
		);

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=updatebee-update-logs.csv' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Stream CSV to the browser.
		$output = fopen( 'php://output', 'w' );
		if ( false === $output ) {
			wp_die( esc_html__( 'Could not export logs.', 'updatebee' ) );
		}

		fputcsv(
			$output,
			array(
				'Name',
				'Slug',
				'User',
				'Version From',
				'Version To',
				'Update Offer',
				'Update Type',
				'Status',
				'Date',
			)
		);

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$user_id = isset( $row['user_id'] ) ? absint( $row['user_id'] ) : 0;
			$user    = _x( 'None', 'No user found', 'updatebee' );
			if ( $user_id > 0 ) {
				$user_object = get_user_by( 'id', $user_id );
				if ( $user_object instanceof \WP_User ) {
					$user = $user_object->user_nicename;
				}
			}

			fputcsv(
				$output,
				array(
					isset( $row['name'] ) ? (string) $row['name'] : '',
					isset( $row['slug'] ) ? (string) $row['slug'] : '',
					$user,
					isset( $row['from_version'] ) ? (string) $row['from_version'] : '',
					isset( $row['to_version'] ) ? (string) $row['to_version'] : '',
					isset( $row['update_offer'] ) ? (string) $row['update_offer'] : '',
					isset( $row['update_type'] ) ? (string) $row['update_type'] : '',
					isset( $row['update_status'] ) ? (string) $row['update_status'] : '',
					isset( $row['date'] ) ? (string) $row['date'] : '',
				)
			);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the php://output stream.
		fclose( $output );
		exit;
	}

	/**
	 * Delete all log rows.
	 */
	public function clear_logs(): void {
		if ( ! Functions::can_access_options() ) {
			wp_send_json_error();
		}

		check_ajax_referer( 'updatebee-clear-logs', 'nonce' );

		$cleared = Table_Logs::clear_logs();
		if ( ! $cleared ) {
			wp_send_json_error(
				array(
					'message' => __( 'Logs could not be cleared.', 'updatebee' ),
				)
			);
		}

		wp_send_json_success(
			array(
				'message' => __( 'Logs have been cleared.', 'updatebee' ),
			)
		);
	}

	/**
	 * Save log retention and Slack enabled settings.
	 */
	public function save_logs_settings(): void {
		if ( ! Functions::can_access_options() ) {
			wp_send_json_error();
		}

		check_ajax_referer( 'updatebee-save-logs-settings', 'nonce' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Days are passed through absint.
		$days = isset( $_POST['clear_logs_days'] ) ? absint( wp_unslash( $_POST['clear_logs_days'] ) ) : 0;

		$slack_enabled = false;
		if ( isset( $_POST['slack_enabled'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Boolean is sanitized below.
			$slack_enabled = rest_sanitize_boolean( wp_unslash( $_POST['slack_enabled'] ) );
		}

		$updated = Options::update_options(
			'',
			array(
				'clear_logs_days' => $days,
				'slack_enabled'   => $slack_enabled,
			)
		);
		if ( null === $updated ) {
			wp_send_json_error(
				array(
					'message' => __( 'Log settings could not be saved.', 'updatebee' ),
				)
			);
		}

		Clear_Logs::sync_schedule();

		wp_send_json_success(
			array(
				'message'         => __( 'Log settings have been saved.', 'updatebee' ),
				'clear_logs_days' => $days,
				'slack_enabled'   => $slack_enabled,
			)
		);
	}

	/**
	 * Enable or disable auto-updates for a single translation locale.
	 */
	public function toggle_translation_auto_updates(): void {
		if ( ! Functions::can_access_options() ) {
			wp_send_json_error();
		}

		$locale = $this->get_posted_translation_locale();
		check_ajax_referer( 'updatebee-translation-enable-' . $locale, 'nonce' );

		if ( ! Functions::translation_locale_exists( $locale ) || ! Functions::translation_auto_updates_available() ) {
			wp_send_json_error();
		}

		$updated = Functions::set_translation_auto_update( $locale, $this->get_posted_enabled() );
		if ( null === $updated ) {
			wp_send_json_error();
		}

		wp_send_json_success();
	}

	/**
	 * Block or unblock updates for a single translation locale.
	 */
	public function toggle_translation_block_updates(): void {
		if ( ! Functions::can_access_options() ) {
			wp_send_json_error();
		}

		$locale = $this->get_posted_translation_locale();
		check_ajax_referer( 'updatebee-translation-block-' . $locale, 'nonce' );

		if ( ! Functions::translation_locale_exists( $locale ) || Functions::translation_updates_fully_blocked() ) {
			wp_send_json_error();
		}

		$updated = Functions::set_translation_blocked( $locale, $this->get_posted_enabled() );
		if ( null === $updated ) {
			wp_send_json_error();
		}

		wp_send_json_success();
	}

	/**
	 * Apply a bulk action to selected translation locales.
	 */
	public function bulk_translation_action(): void {
		if ( ! Functions::can_access_options() ) {
			wp_send_json_error();
		}

		check_ajax_referer( 'updatebee-translation-bulk', 'nonce' );

		$bulk_action = isset( $_POST['bulk_action'] ) ? sanitize_key( wp_unslash( $_POST['bulk_action'] ) ) : '';
		$allowed     = array( 'enable', 'disable', 'block', 'unblock' );
		if ( ! in_array( $bulk_action, $allowed, true ) ) {
			wp_send_json_error();
		}

		$needs_auto_updates = in_array( $bulk_action, array( 'enable', 'disable' ), true );
		if ( $needs_auto_updates && ! Functions::translation_auto_updates_available() ) {
			wp_send_json_error();
		}

		$is_block_action = in_array( $bulk_action, array( 'block', 'unblock' ), true );
		if ( $is_block_action && Functions::translation_updates_fully_blocked() ) {
			wp_send_json_error();
		}

		$locales = $this->get_posted_translation_locales();
		foreach ( $locales as $locale ) {
			switch ( $bulk_action ) {
				case 'enable':
					Functions::set_translation_auto_update( $locale, true );
					break;
				case 'disable':
					Functions::set_translation_auto_update( $locale, false );
					break;
				case 'block':
					Functions::set_translation_blocked( $locale, true );
					break;
				case 'unblock':
					Functions::set_translation_blocked( $locale, false );
					break;
			}
		}

		wp_send_json_success();
	}

	/**
	 * Sanitize a posted translation locale.
	 *
	 * @return string Locale key such as `da_DK`.
	 */
	private function get_posted_translation_locale(): string {
		// Locale is required to verify the per-locale nonce in the caller.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$locale = isset( $_POST['locale'] ) ? sanitize_text_field( wp_unslash( $_POST['locale'] ) ) : '';

		return sanitize_text_field( urldecode( $locale ) );
	}

	/**
	 * Sanitize posted translation locales for a bulk action.
	 *
	 * @return array<int, string> Catalog locales.
	 */
	private function get_posted_translation_locales(): array {
		// Callers verify `updatebee-translation-bulk` before reading locales.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$locales = isset( $_POST['locales'] ) ? Functions::sanitize_array_recursive( wp_unslash( $_POST['locales'] ) ) : array();
		if ( ! is_array( $locales ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $locales as $locale ) {
			if ( ! is_string( $locale ) ) {
				continue;
			}

			$locale = sanitize_text_field( urldecode( $locale ) );
			if ( ! Functions::translation_locale_exists( $locale ) ) {
				continue;
			}

			$sanitized[] = $locale;
		}

		return array_values( array_unique( $sanitized ) );
	}

	/**
	 * Read delay_days from the posted settings JSON.
	 *
	 * The caller verifies the per-item nonce before this runs. A missing, null,
	 * or empty value means inherit the site default delay.
	 *
	 * @return int|null Delay days, or null to inherit the global delay.
	 */
	private function get_posted_delay_days(): ?int {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Caller verifies the nonce.
		if ( ! isset( $_POST['settings'] ) ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Caller verifies the nonce.
		$raw      = wp_unslash( $_POST['settings'] );
		$settings = array();
		if ( is_array( $raw ) ) {
			$settings = $raw;
		} elseif ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				$settings = $decoded;
			}
		}

		if ( ! array_key_exists( 'delay_days', $settings ) ) {
			return null;
		}

		$value = $settings['delay_days'];
		if ( null === $value || '' === $value ) {
			return null;
		}

		return absint( $value );
	}

	/**
	 * Read allow_untested from the posted settings JSON.
	 *
	 * True allows untested updates for this item. Null inherits the site setting.
	 *
	 * @return bool|null Exemption, or null to inherit.
	 */
	private function get_posted_allow_untested(): ?bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Caller verifies the nonce.
		if ( ! isset( $_POST['settings'] ) ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Caller verifies the nonce.
		$raw      = wp_unslash( $_POST['settings'] );
		$settings = array();
		if ( is_array( $raw ) ) {
			$settings = $raw;
		} elseif ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				$settings = $decoded;
			}
		}

		if ( ! array_key_exists( 'allow_untested', $settings ) || null === $settings['allow_untested'] || '' === $settings['allow_untested'] ) {
			return null;
		}

		return rest_sanitize_boolean( $settings['allow_untested'] ) ? true : null;
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
	 * Force updates for core, translations, plugins, and themes with automatic updates enabled.
	 */
	public function force_updates(): void {
		if ( ! Functions::can_access_options() ) {
			wp_send_json_error();
		}

		check_ajax_referer( 'updatebee-force-updates', 'nonce' );

		if ( ! defined( 'UPDATEBEE_DOING_FORCE_UPDATES' ) ) {
			define( 'UPDATEBEE_DOING_FORCE_UPDATES', true );
		}

		// Disable auto-backups from occurring with UpdraftPlus Premium during force updates.
		add_filter( 'updraftplus_boot_backup', '__return_false', 10, 1 );

		$updater_lock = get_option( 'auto_updater.lock' );
		/**
		 * Whether to delete the auto update lock file.
		 *
		 * @since 1.0.0
		 *
		 * @param bool   $disable_lock Ignore deleting the lock file (default true).
		 * @param string $updater_lock The lock file expiration if exists.
		 */
		$disable_lock = apply_filters( 'updatebee_force_updates_disable_lock', true, $updater_lock );
		if ( true === $disable_lock ) {
			delete_option( 'auto_updater.lock' );
		}

		add_filter( 'wp_doing_cron', '__return_true' );
		wp_maybe_auto_update();
		remove_filter( 'wp_doing_cron', '__return_true' );

		wp_send_json_success(
			array(
				'message' => __( 'Force updates completed.', 'updatebee' ),
			)
		);
	}

	/**
	 * Schedule WordPress update cron events to run immediately.
	 */
	public function trigger_update_cron(): void {
		if ( ! Functions::can_access_options() ) {
			wp_send_json_error();
		}

		check_ajax_referer( 'updatebee-trigger-update-cron', 'nonce' );

		if ( ! defined( 'DOING_CRON' ) ) {
			define( 'DOING_CRON', true );
		}

		wp_schedule_single_event( time(), 'wp_update_plugins' );
		wp_schedule_single_event( time(), 'wp_update_themes' );
		wp_schedule_single_event( time(), 'wp_version_check', array( array(), true ) );

		wp_send_json_success(
			array(
				'message' => __( 'Update cron events have been scheduled.', 'updatebee' ),
			)
		);
	}

	/**
	 * Clear the update cache and force a fresh version check.
	 */
	public function refresh_updates(): void {
		if ( ! Functions::can_access_options() ) {
			wp_send_json_error();
		}

		check_ajax_referer( 'updatebee-refresh-updates', 'nonce' );

		Functions::clear_update_cache( true );

		wp_send_json_success(
			array(
				'message' => __( 'Updates have been force refreshed.', 'updatebee' ),
			)
		);
	}

	/**
	 * Migrate settings from Easy Updates Manager to UpdateBee.
	 */
	public function migrate_eum(): void {
		if ( ! Functions::can_access_options() ) {
			wp_send_json_error();
		}

		check_ajax_referer( 'updatebee-migrate-eum', 'nonce' );

		$migrated = EasyUpdatesManager::run();
		if ( ! $migrated ) {
			wp_send_json_error(
				array(
					'message' => __( 'No Easy Updates Manager settings were found to migrate.', 'updatebee' ),
				)
			);
		}

		wp_send_json_success(
			array(
				'message' => __( 'Easy Updates Manager settings have been migrated to UpdateBee.', 'updatebee' ),
			)
		);
	}

	/**
	 * Reset selected option groups from the Advanced screen.
	 */
	public function reset_options(): void {
		if ( ! Functions::can_access_options() ) {
			wp_send_json_error();
		}

		check_ajax_referer( 'updatebee-reset-options', 'nonce' );

		$raw_items = isset( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! is_array( $raw_items ) ) {
			$raw_items = array();
		}

		$items    = Functions::sanitize_array_recursive( $raw_items );
		$allowed  = array(
			'plugin_auto',
			'plugin_blocked',
			'theme_auto',
			'theme_blocked',
			'translation_auto',
			'translation_blocked',
			'all',
		);
		$selected = array();

		foreach ( $items as $item ) {
			if ( ! is_string( $item ) ) {
				continue;
			}

			$item = sanitize_key( $item );
			if ( in_array( $item, $allowed, true ) ) {
				$selected[] = $item;
			}
		}

		$selected = array_values( array_unique( $selected ) );

		if ( empty( $selected ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You did not select any options to reset.', 'updatebee' ),
				)
			);
		}

		if ( in_array( 'all', $selected, true ) ) {
			Options::reset_options();
			Clear_Logs::sync_schedule();
			wp_send_json_success(
				array(
					'message' => __( 'Options have been reset.', 'updatebee' ),
				)
			);
		}

		$handlers = array(
			'plugin_auto'         => array( Options::class, 'reset_plugin_auto_updates' ),
			'plugin_blocked'      => array( Options::class, 'reset_plugin_blocked_updates' ),
			'theme_auto'          => array( Options::class, 'reset_theme_auto_updates' ),
			'theme_blocked'       => array( Options::class, 'reset_theme_blocked_updates' ),
			'translation_auto'    => array( Options::class, 'reset_translation_auto_updates' ),
			'translation_blocked' => array( Options::class, 'reset_translation_blocked_updates' ),
		);

		foreach ( $selected as $item ) {
			if ( isset( $handlers[ $item ] ) && is_callable( $handlers[ $item ] ) ) {
				call_user_func( $handlers[ $item ] );
			}
		}

		wp_send_json_success(
			array(
				'message' => __( 'Your selected options have been reset.', 'updatebee' ),
			)
		);
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
	 * Retrieve WordPress email toggle and report enabled flags for the Emails tab.
	 */
	public function retrieve_email_settings(): void {
		if ( ! Functions::can_access_options() ) {
			wp_send_json_error();
		}

		check_ajax_referer( 'updatebee-retrieve-email-settings', 'nonce' );

		$success_options = Email_Successes::get_options();
		$failure_options = Email_Failures::get_options();

		wp_send_json_success(
			array(
				'enable_core_emails'      => Updates_Init::core_emails_enabled(),
				'email_successes_enabled' => Email_Successes::is_enabled( $success_options ),
				'email_failures_enabled'  => Email_Failures::is_enabled( $failure_options ),
			)
		);
	}

	/**
	 * Save the WordPress native automatic update email toggle.
	 */
	public function save_wordpress_email_settings(): void {
		if ( ! Functions::can_access_options() ) {
			wp_send_json_error();
		}

		check_ajax_referer( 'updatebee-save-wordpress-email-settings', 'nonce' );

		$enabled    = $this->get_posted_enabled();
		$recipients = $this->get_posted_email_recipients();

		if ( $enabled && empty( $recipients ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You must enter at least one email address.', 'updatebee' ),
				)
			);
		}

		$updated = Options::update_options(
			'enable_core_emails',
			array(
				'enable_core_emails' => $enabled,
			)
		);

		if ( null === $updated ) {
			wp_send_json_error();
		}

		$updated = Options::update_options(
			'core_email_recipients',
			array(
				'core_email_recipients' => $recipients,
			)
		);

		if ( null === $updated ) {
			wp_send_json_error();
		}

		wp_send_json_success(
			array(
				'enable_core_emails'    => Updates_Init::core_emails_enabled(),
				'core_email_recipients' => Updates_Init::get_core_email_recipients(),
				'message'               => __( 'Email settings saved.', 'updatebee' ),
			)
		);
	}

	/**
	 * Save delayed automatic update days, keywords, and untested updates.
	 */
	public function save_advanced_settings(): void {
		if ( ! Functions::can_access_options() ) {
			wp_send_json_error();
		}

		check_ajax_referer( 'updatebee-save-advanced-settings', 'nonce' );

		$to_save = array();

		if ( isset( $_POST['delay_updates'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Days are passed through absint.
			$to_save['delay_updates'] = absint( wp_unslash( $_POST['delay_updates'] ) );
		}

		if ( isset( $_POST['block_untested_updates'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Boolean is sanitized below.
			$enabled                           = rest_sanitize_boolean( wp_unslash( $_POST['block_untested_updates'] ) );
			$to_save['block_untested_updates'] = $enabled ? 'on' : 'off';
		}

		if ( isset( $_POST['upgrade_notice_keywords'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Keywords are sanitized below.
			$raw_keywords                       = wp_unslash( $_POST['upgrade_notice_keywords'] );
			$to_save['upgrade_notice_keywords'] = Upgrade_Notice_Keywords::sanitize( $raw_keywords );
		}

		if ( isset( $_POST['upgrade_notice_keywords_enabled'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Boolean is sanitized below.
			$enabled                                    = rest_sanitize_boolean( wp_unslash( $_POST['upgrade_notice_keywords_enabled'] ) );
			$to_save['upgrade_notice_keywords_enabled'] = $enabled;
		}

		if ( isset( $_POST['slack_webhook_url'] ) ) {
			$raw = sanitize_text_field( wp_unslash( $_POST['slack_webhook_url'] ) );
			$to_save['slack_webhook_url'] = '' !== $raw ? esc_url_raw( $raw ) : '';
		}

		if ( empty( $to_save ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Advanced settings could not be saved.', 'updatebee' ),
				)
			);
		}

		$updated = Options::update_options( '', $to_save );
		if ( null === $updated ) {
			wp_send_json_error(
				array(
					'message' => __( 'Advanced settings could not be saved.', 'updatebee' ),
				)
			);
		}

		if (
			( isset( $to_save['upgrade_notice_keywords'] ) || isset( $to_save['upgrade_notice_keywords_enabled'] ) )
			&& ! isset( $to_save['delay_updates'] )
			&& ! isset( $to_save['block_untested_updates'] )
			&& ! isset( $to_save['slack_webhook_url'] )
		) {
			$message = __( 'Upgrade notice keywords have been saved.', 'updatebee' );
		} elseif ( isset( $to_save['block_untested_updates'] ) && ! isset( $to_save['delay_updates'] ) && ! isset( $to_save['slack_webhook_url'] ) ) {
			$message = __( 'Untested updates setting has been saved.', 'updatebee' );
		} elseif ( isset( $to_save['slack_webhook_url'] ) && ! isset( $to_save['delay_updates'] ) && ! isset( $to_save['block_untested_updates'] ) && ! isset( $to_save['upgrade_notice_keywords'] ) && ! isset( $to_save['upgrade_notice_keywords_enabled'] ) ) {
			$message = __( 'Slack webhook has been saved.', 'updatebee' );
		} else {
			$message = __( 'Update delay has been saved.', 'updatebee' );
		}

		$response = array(
			'message' => $message,
		);

		if ( isset( $to_save['delay_updates'] ) ) {
			$response['delay_updates'] = $to_save['delay_updates'];
		}

		if ( isset( $to_save['block_untested_updates'] ) ) {
			$response['block_untested_updates'] = 'on' === $to_save['block_untested_updates'];
		}

		if ( isset( $to_save['upgrade_notice_keywords'] ) ) {
			$response['upgrade_notice_keywords'] = $to_save['upgrade_notice_keywords'];
		}

		if ( isset( $to_save['upgrade_notice_keywords_enabled'] ) ) {
			$response['upgrade_notice_keywords_enabled'] = (bool) $to_save['upgrade_notice_keywords_enabled'];
		}

		if ( isset( $to_save['slack_webhook_url'] ) ) {
			$response['slack_webhook_url'] = $to_save['slack_webhook_url'];
		}

		wp_send_json_success( $response );
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

	/**
	 * Sanitize posted WordPress update email recipients.
	 *
	 * @return array<int, string> Valid email addresses.
	 */
	private function get_posted_email_recipients(): array {
		// Callers verify a nonce before reading recipients.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$recipients = isset( $_POST['recipients'] ) ? wp_unslash( $_POST['recipients'] ) : array();
		if ( ! is_array( $recipients ) ) {
			return array();
		}

		$sanitized = array();
		foreach ( $recipients as $email ) {
			if ( ! is_string( $email ) ) {
				continue;
			}

			$email = sanitize_email( $email );
			if ( is_email( $email ) ) {
				$sanitized[] = $email;
			}
		}

		return array_values( array_unique( $sanitized ) );
	}
}
