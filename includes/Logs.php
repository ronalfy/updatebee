<?php
/**
 * Log automatic and manual updates to the database.
 *
 * Credit: Forked from Easy Updates Manager / Ultimate Auto Update.
 *
 * @package updatebee
 */

namespace UpdateBee;

use UpdateBee\Tables\Table_Logs;
use UpdateBee\Updates\Fatal_Detection;

/**
 * Class Logs
 */
class Logs {

	/**
	 * Site option key for core version before an automatic core update.
	 */
	public const CORE_VERSION_OPTION = 'updatebee_core_version';

	/**
	 * Store the updates in a static variable.
	 *
	 * @var array<int, array<string, mixed>> The current updates.
	 */
	private static $current_updates = array();

	/**
	 * Pending upgrader log entries keyed by update type.
	 *
	 * @var array<string, array<string, array<string, mixed>>>
	 */
	private $log_messages = array();

	/**
	 * Whether the current request is processing an automatic update.
	 *
	 * @var bool
	 */
	private $is_auto_update = false;

	/**
	 * Class runner.
	 */
	public function run(): void {
		register_shutdown_function( array( $this, 'perform_shutdown_task' ) );

		add_action( 'pre_auto_update', array( $this, 'pre_auto_update_version_store' ), 10, 3 );

		add_filter( 'upgrader_package_options', array( $this, 'initialize_log_messages' ), 10, 1 );
		add_filter( 'upgrader_pre_download', array( $this, 'initialize_core_log_messages' ), 10, 3 );
		add_action( 'upgrader_process_complete', array( $this, 'log_upgrader_updates' ), 1, 2 );

		add_action( 'automatic_updates_complete', array( $this, 'automatic_updates' ) );
		add_action( 'automatic_updates_complete', array( $this, 'update_translations' ), 9 );
	}

	/**
	 * Store the core version so we have a from/to.
	 *
	 * @param string $type    core|plugin|translation|theme.
	 * @param object $item    The update offering.
	 * @param string $context The filesystem context (a path) against which filesystem access and status should be checked.
	 */
	public function pre_auto_update_version_store( $type, $item, $context ): void {
		$this->is_auto_update = true;

		unset( $item, $context );

		if ( 'core' !== $type ) {
			return;
		}

		include ABSPATH . WPINC . '/version.php';
		update_site_option( self::CORE_VERSION_OPTION, sanitize_text_field( $wp_version ) );
	}

	/**
	 * Hook into automatic updates and store the result in logs.
	 *
	 * @param array<string, array<int, object>> $update_results Array containing update results for core|plugin|translation|theme.
	 */
	public function automatic_updates( $update_results ): void {
		if ( ! is_array( $update_results ) ) {
			return;
		}

		foreach ( $update_results as $type => $results ) {
			if ( ! is_array( $results ) ) {
				continue;
			}

			switch ( $type ) {
				case 'core':
					include ABSPATH . WPINC . '/version.php';
					$core = $results[0] ?? null;
					if ( ! is_object( $core ) ) {
						break;
					}
					$name            = isset( $core->name ) ? (string) $core->name : 'WordPress';
					$slug            = 'core';
					$status          = 'success';
					$update_type     = 'core';
					$prev_version    = get_site_option( self::CORE_VERSION_OPTION );
					$current_version = sanitize_text_field( $wp_version );
					$user_id         = 0;
					$raw             = '';
					$stacktrace      = '';
					if ( isset( $core->messages ) && is_array( $core->messages ) ) {
						foreach ( $core->messages as $message ) {
							$raw .= $message . "\n\r\n\r";
						}
					}
					$resolved   = $this->resolve_automatic_status( $update_type, $slug, $core->result ?? null, $status, $raw );
					$status     = $resolved['status'];
					$raw        = $resolved['raw'];
					$stacktrace = $resolved['stacktrace'];
					delete_site_option( self::CORE_VERSION_OPTION );
					$this->insert_log( $name, $slug, $user_id, (string) $prev_version, $current_version, 'automatic', $update_type, $status, $raw, $stacktrace );
					break;
				case 'plugin':
					foreach ( $results as $plugin ) {
						if ( ! is_object( $plugin ) || ! isset( $plugin->item->plugin ) ) {
							continue;
						}

						$name         = ( isset( $plugin->name ) && ! empty( $plugin->name ) ) ? (string) $plugin->name : (string) $plugin->item->slug;
						$slug         = (string) $plugin->item->slug;
						$plugin_file  = (string) $plugin->item->plugin;
						$status       = 'success';
						$update_type  = 'plugin';
						$prev_version = isset( $plugin->item->current_version ) ? sanitize_text_field( (string) $plugin->item->current_version ) : '';
						$new_version  = isset( $plugin->item->new_version ) ? sanitize_text_field( (string) $plugin->item->new_version ) : '';
						$user_id      = 0;
						$raw          = '';
						$stacktrace   = '';

						// Get plugin info from WordPress Core.
						$plugin_info                 = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_file, false, false );
						$plugin_info_current_version = $plugin_info['Version'] ?? '';

						// If current version isn't greater than the previous version, mark as a failure.
						if ( version_compare( $new_version, $prev_version, '<=' ) || $prev_version === $plugin_info_current_version || empty( $plugin_info_current_version ) ) {
							$status = 'failure';
						}
						if ( isset( $plugin->messages ) && is_array( $plugin->messages ) ) {
							foreach ( $plugin->messages as $message ) {
								$raw .= $message . "\n\r\n\r";
							}
						}
						$resolved   = $this->resolve_automatic_status( $update_type, $slug, $plugin->result ?? null, $status, $raw );
						$status     = $resolved['status'];
						$raw        = $resolved['raw'];
						$stacktrace = $resolved['stacktrace'];
						$this->insert_log( $name, $slug, $user_id, $prev_version, $new_version, 'automatic', $update_type, $status, $raw, $stacktrace );
					}
					break;
				case 'theme':
					foreach ( $results as $theme ) {
						if ( ! is_object( $theme ) || ! isset( $theme->item->theme ) ) {
							continue;
						}
						$name            = isset( $theme->name ) ? (string) $theme->name : (string) $theme->item->theme;
						$slug            = (string) $theme->item->theme;
						$status          = 'success';
						$update_type     = 'theme';
						$prev_version    = isset( $theme->item->current_version ) ? sanitize_text_field( (string) $theme->item->current_version ) : '';
						$current_version = isset( $theme->item->new_version ) ? sanitize_text_field( (string) $theme->item->new_version ) : '';
						$user_id         = 0;
						$raw             = '';
						$stacktrace      = '';
						// If new version is less than or equal to version_from, mark as failure.
						if ( version_compare( $current_version, $prev_version, '<=' ) || $prev_version === $current_version ) {
							$status = 'failure';
						}
						if ( isset( $theme->messages ) && is_array( $theme->messages ) ) {
							foreach ( $theme->messages as $message ) {
								$raw .= $message . "\n\r";
							}
						}
						$resolved   = $this->resolve_automatic_status( $update_type, $slug, $theme->result ?? null, $status, $raw );
						$status     = $resolved['status'];
						$raw        = $resolved['raw'];
						$stacktrace = $resolved['stacktrace'];
						$this->insert_log( $name, $slug, $user_id, $prev_version, $current_version, 'automatic', $update_type, $status, $raw, $stacktrace );
					}
					break;
				case 'translation':
					foreach ( $results as $translation ) {
						if ( is_object( $translation ) ) {
							$this->insert_translations_logs( $translation );
						}
					}
					break;
			}
		}

		/**
		 * Run when all updates have been logged.
		 *
		 * @param array<int, array<string, mixed>> $update_results Array containing update results for core|plugin|translation|theme.
		 */
		do_action( 'updatebee_automatic_updates_complete', self::$current_updates );
	}

	/**
	 * Store translations in the logs table.
	 *
	 * @param object $translation Translation update object.
	 */
	private function insert_translations_logs( $translation ): void {
		if ( ! isset( $translation->item->language ) ) {
			return;
		}

		$name            = isset( $translation->name ) ? (string) $translation->name : (string) $translation->item->language;
		$slug            = (string) $translation->item->language;
		$status          = ( isset( $translation->result ) && is_wp_error( $translation->result ) ) ? 'failure' : 'success';
		$update_type     = 'translation';
		$prev_version    = '';
		$current_version = isset( $translation->item->version ) ? (string) $translation->item->version : '';
		$user_id         = 0;
		$raw             = '';

		if ( isset( $translation->messages ) && is_array( $translation->messages ) ) {
			foreach ( $translation->messages as $message ) {
				$raw .= $message . "\n\r";
			}
		}
		$this->insert_log( $name, $slug, $user_id, $prev_version, $current_version, 'automatic', $update_type, $status, $raw );
	}

	/**
	 * Run translations when automatic updates are finished.
	 *
	 * Sometimes translations aren't updated on automatic updates.
	 *
	 * @param array<string, mixed> $update_results Update results.
	 */
	public function update_translations( $update_results ): void {
		unset( $update_results );

		$language_updates = wp_get_translation_updates();
		if ( ! $language_updates ) {
			return;
		}

		if ( ! class_exists( '\Language_Pack_Upgrader' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			require_once ABSPATH . 'wp-admin/includes/class-language-pack-upgrader.php';
		}

		// ob_start prevents notices from showing when backup plugins run during forced updates.
		ob_start();
		$language_pack = new \Language_Pack_Upgrader();
		$language_pack->bulk_upgrade( $language_updates );
		ob_end_clean();
	}

	/**
	 * Inserts result of upgrade process message to log table.
	 *
	 * @param string $name            Upgrade name.
	 * @param string $slug            Slug for the update.
	 * @param int    $user_id         User responsible for the upgrade.
	 * @param string $prev_version    The previous version for the upgrade.
	 * @param string $current_version The current version for the upgrade.
	 * @param string $update_offer    Manual or Automatic (manual|automatic).
	 * @param string $update_type     The type of the update. plugin|theme|translation|core.
	 * @param string $status          Status of the upgrade. success|failure|blocked|skipped|delayed|install|deactivate|delete|rollback|update.
	 * @param string $notes           Raw upgrade notes.
	 * @param string $stacktrace      Serialized stack trace for manual debugging.
	 */
	public function insert_log( $name, $slug, $user_id, $prev_version, $current_version, $update_offer, $update_type, $status = 'success', $notes = '', $stacktrace = '' ): void {
		global $wpdb;

		$table_name = Table_Logs::get_table_name();
		$notes      = str_replace( '&#8230;', '', (string) $notes );

		// Strip URLs from notes.
		$notes = preg_replace( '/\?.*/', '', $notes );

		/**
		 * Filter whether logs can be stored or not.
		 *
		 * @param bool $allow_insert True to allow log insert, false to not.
		 */
		$allow_insert = apply_filters( 'updatebee_allow_log_insert', true );

		/**
		 * Run an action before saving the log entry.
		 *
		 * @param string $name            Upgrade name.
		 * @param string $slug            Slug for the update.
		 * @param int    $user_id         User responsible for the upgrade.
		 * @param string $prev_version    The previous version for the upgrade.
		 * @param string $current_version The current version for the upgrade.
		 * @param string $update_offer    Manual or automatic.
		 * @param string $update_type     The type of the update. plugin|theme|translation|core.
		 * @param string $status          Status of the upgrade.
		 * @param string $notes           Raw upgrade notes.
		 */
		do_action( 'updatebee_pre_log_insert', $name, $slug, $user_id, $prev_version, $current_version, $update_offer, $update_type, $status, $notes );

		if ( ! $allow_insert ) {
			return;
		}

		$row = array(
			'name'          => $name,
			'slug'          => $slug,
			'user_id'       => $user_id,
			'from_version'  => $prev_version,
			'to_version'    => $current_version,
			'update_offer'  => $update_offer,
			'update_type'   => $update_type,
			'update_status' => $status,
			'raw'           => $notes,
			'date'          => current_time( 'mysql' ),
		);

		$formats = array(
			'%s',
			'%s',
			'%d',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
		);

		if ( '' !== (string) $stacktrace ) {
			$row['stacktrace'] = $stacktrace;
			$formats[]         = '%s';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom logs table insert.
		$wpdb->insert( $table_name, $row, $formats );

		/**
		 * Run an action after saving the log entry.
		 *
		 * @param string $name            Upgrade name.
		 * @param string $slug            Slug for the update.
		 * @param int    $user_id         User responsible for the upgrade.
		 * @param string $prev_version    The previous version for the upgrade.
		 * @param string $current_version The current version for the upgrade.
		 * @param string $update_type     The type of the update. plugin|theme|translation|core.
		 * @param string $status          Status of the upgrade.
		 * @param string $notes           Raw upgrade notes.
		 */
		do_action( 'updatebee_after_log_insert', $name, $slug, $user_id, $prev_version, $current_version, $update_type, $status, $notes );

		$entry = array(
			'name'          => $name,
			'slug'          => $slug,
			'user_id'       => $user_id,
			'from_version'  => $prev_version,
			'to_version'    => $current_version,
			'update_offer'  => $update_offer,
			'update_type'   => $update_type,
			'update_status' => $status,
			'date'          => current_time( 'mysql' ),
			'raw'           => $notes,
		);

		if ( 'fatal' === $status ) {
			$entry['fatal_message'] = self::summarize_fatal_message( (string) $notes );
			$entry['detail_url']    = Functions::get_settings_url( 'logs' );
			$entry['detail_label']  = __( 'View logs', 'updatebee' );
		}

		self::$current_updates[] = $entry;
	}

	/**
	 * Resolve automatic-update status from WP_Error results and fatal findings.
	 *
	 * @param string $type            plugin|theme|core.
	 * @param string $slug            Extension slug.
	 * @param mixed  $result          Upgrader result.
	 * @param string $version_status  Status from version comparison.
	 * @param string $raw             Accumulated notes.
	 * @return array{status: string, raw: string, stacktrace: string}
	 */
	private function resolve_automatic_status( string $type, string $slug, $result, string $version_status, string $raw ): array {
		$status     = $version_status;
		$stacktrace = '';

		$result_status = Fatal_Detection::status_from_result( $result );
		if ( is_string( $result_status ) ) {
			$status = $result_status;
			if ( is_wp_error( $result ) ) {
				$messages = $result->get_error_messages();
				if ( is_array( $messages ) ) {
					foreach ( $messages as $message ) {
						$raw .= $message . "\n";
					}
				}
			}
		}

		$finding = Fatal_Detection::get_finding( $type, $slug );
		if ( null === $finding && in_array( $type, array( 'plugin', 'theme' ), true ) && 'success' === $status ) {
			$finding = Fatal_Detection::get_unattributed();
		}

		if ( is_array( $finding ) && isset( $finding['error'] ) && is_array( $finding['error'] ) ) {
			$can_apply = ( 'failure' !== $status ) || Fatal_Detection::is_fatal_result( $result );
			if ( $can_apply ) {
				$status     = 'fatal';
				$raw       .= self::format_fatal_error_notes( $finding['error'] );
				$encoded    = wp_json_encode( $finding['error'] );
				$stacktrace = is_string( $encoded ) ? $encoded : '';
			}
		}

		return array(
			'status'     => $status,
			'raw'        => $raw,
			'stacktrace' => $stacktrace,
		);
	}

	/**
	 * Format scrape/core fatal details for the log notes column.
	 *
	 * @param array<string, mixed> $error Error payload.
	 * @return string
	 */
	private static function format_fatal_error_notes( array $error ): string {
		$parts = array();
		if ( ! empty( $error['message'] ) ) {
			$parts[] = sprintf(
				/* translators: %s: PHP error message. */
				__( 'Error: %s', 'updatebee' ),
				(string) $error['message']
			);
		}
		if ( ! empty( $error['file'] ) ) {
			$parts[] = sprintf(
				/* translators: %s: File path. */
				__( 'File: %s', 'updatebee' ),
				(string) $error['file']
			);
		}
		if ( ! empty( $error['line'] ) ) {
			$parts[] = sprintf(
				/* translators: %d: Line number. */
				__( 'Line: %d', 'updatebee' ),
				(int) $error['line']
			);
		}

		return empty( $parts ) ? '' : implode( "\n", $parts ) . "\n";
	}

	/**
	 * First line of fatal notes for email tables.
	 *
	 * @param string $notes Raw notes.
	 * @return string
	 */
	private static function summarize_fatal_message( string $notes ): string {
		$notes = trim( $notes );
		if ( '' === $notes ) {
			return __( 'A PHP fatal error was detected after this update.', 'updatebee' );
		}

		$lines = preg_split( '/\r\n|\r|\n/', $notes );
		if ( ! is_array( $lines ) || empty( $lines[0] ) ) {
			return __( 'A PHP fatal error was detected after this update.', 'updatebee' );
		}

		return (string) $lines[0];
	}

	/**
	 * Flush pending manual log entries on shutdown (e.g. after fatal errors).
	 */
	public function perform_shutdown_task(): void {
		$this->maybe_flush_log_messages();

		if ( $this->is_auto_update || empty( self::$current_updates ) ) {
			return;
		}

		/**
		 * Run when manual updates logged in this request have finished.
		 *
		 * @param array<int, array<string, mixed>> $updates Log rows from this request.
		 */
		do_action( 'updatebee_manual_updates_complete', self::$current_updates );
	}

	/**
	 * Initialize plugin, theme, or translation log metadata before an upgrader run.
	 *
	 * @param array<string, mixed> $options Package options used by the upgrader.
	 * @return array<string, mixed>
	 */
	public function initialize_log_messages( $options ) {
		if ( ! is_array( $options ) ) {
			return $options;
		}

		if ( isset( $options['hook_extra'], $options['hook_extra']['action'] ) && 'update' !== $options['hook_extra']['action'] ) {
			return $options;
		}

		if ( isset( $options['hook_extra']['plugin'] ) ) {
			$plugin_file = $options['hook_extra']['plugin'];
			$current     = get_site_transient( 'update_plugins' );
			if ( is_object( $current ) && isset( $current->response[ $plugin_file ] ) ) {
				$plugins = get_plugins();
				if ( ! isset( $this->log_messages['plugin'] ) ) {
					$this->log_messages['plugin'] = array();
				}
				$this->log_messages['plugin'][ $plugin_file ] = array(
					'name'   => ( isset( $plugins[ $plugin_file ]['Name'] ) ) ? $plugins[ $plugin_file ]['Name'] : 'Unknown',
					'from'   => ( isset( $plugins[ $plugin_file ]['Version'] ) ) ? $plugins[ $plugin_file ]['Version'] : '0',
					'to'     => ( isset( $current->response[ $plugin_file ]->new_version ) ) ? $current->response[ $plugin_file ]->new_version : '0',
					'status' => 0,
				);
			}
		} elseif ( isset( $options['hook_extra']['theme'] ) ) {
			$theme_stylesheet = $options['hook_extra']['theme'];
			$current          = get_site_transient( 'update_themes' );
			if ( is_object( $current ) && isset( $current->response[ $theme_stylesheet ] ) ) {
				$themes = wp_get_themes();
				if ( ! isset( $this->log_messages['theme'] ) ) {
					$this->log_messages['theme'] = array();
				}
				$theme_obj                                        = $themes[ $theme_stylesheet ] ?? null;
				$this->log_messages['theme'][ $theme_stylesheet ] = array(
					'name'   => ( $theme_obj instanceof \WP_Theme ) ? $theme_obj->get( 'Name' ) : 'Unknown',
					'from'   => ( $theme_obj instanceof \WP_Theme ) ? $theme_obj->get( 'Version' ) : '0',
					'to'     => $current->response[ $theme_stylesheet ]['new_version'] ?? '0',
					'status' => 0,
				);
			}
		} elseif ( isset( $options['hook_extra']['language_update_type'], $options['hook_extra']['language_update'] ) ) {
			if ( ! isset( $this->log_messages['translation'] ) ) {
				$this->log_messages['translation'] = array();
			}

			$language_update = $options['hook_extra']['language_update'];

			switch ( $options['hook_extra']['language_update_type'] ) {
				case 'plugin':
					$current = get_site_transient( 'update_plugins' );
					if ( ! is_object( $current ) || empty( $current->translations ) ) {
						break;
					}
					$plugins         = get_plugins();
					$plugins_by_slug = wp_parse_args(
						wp_list_pluck( $current->no_update, 'plugin', 'slug' ),
						wp_list_pluck( $current->response, 'plugin', 'slug' )
					);
					foreach ( $current->translations as $translation ) {
						if ( isset( $language_update->slug ) && $translation['slug'] !== $language_update->slug ) {
							continue;
						}
						if ( ! isset( $plugins_by_slug[ $translation['slug'] ], $plugins[ $plugins_by_slug[ $translation['slug'] ] ] ) ) {
							continue;
						}
						$plugin_data = $plugins[ $plugins_by_slug[ $translation['slug'] ] ];
						$this->log_messages['translation'][ $translation['slug'] ] = array(
							'name'   => $plugin_data['Name'] . ' (' . $translation['language'] . ')',
							'from'   => $language_update->version ?? '0',
							'to'     => $translation['version'] ?? '0',
							'status' => 0,
						);
						break;
					}
					break;
				case 'theme':
					$current = get_site_transient( 'update_themes' );
					if ( ! is_object( $current ) || empty( $current->translations ) ) {
						break;
					}
					$themes = wp_get_themes();
					foreach ( $current->translations as $translation ) {
						if ( isset( $language_update->slug ) && $translation['slug'] !== $language_update->slug ) {
							continue;
						}
						if ( ! isset( $themes[ $translation['slug'] ] ) ) {
							continue;
						}
						$theme_obj = $themes[ $translation['slug'] ];
						$this->log_messages['translation'][ $translation['slug'] ] = array(
							'name'   => $theme_obj->get( 'Name' ) . ' (' . $translation['language'] . ')',
							'from'   => $language_update->version ?? '0',
							'to'     => $translation['version'] ?? '0',
							'status' => 0,
						);
						break;
					}
					break;
				default:
					$current = get_site_transient( 'update_core' );
					if ( ! is_object( $current ) || empty( $current->translations ) ) {
						break;
					}
					foreach ( $current->translations as $translation ) {
						$this->log_messages['translation'][ 'wordpress_default_' . $translation['language'] ] = array(
							'name'   => 'WordPress (' . $translation['language'] . ')',
							'from'   => $language_update->version ?? '0',
							'to'     => $translation['version'] ?? '0',
							'status' => 0,
						);
					}
					break;
			}
		}

		return $options;
	}

	/**
	 * Initialize core update metadata before the package downloads.
	 *
	 * @param bool|mixed          $reply    Whether to bail without returning the package.
	 * @param string              $package  The package file name.
	 * @param \WP_Upgrader|object $upgrader The WP_Upgrader instance.
	 * @return bool|mixed
	 */
	public function initialize_core_log_messages( $reply, $package, $upgrader ) {
		add_filter( 'upgrader_post_install', array( $this, 'set_update_status_by_result' ), 10, 3 );

		if ( ! is_object( $upgrader ) || ! is_a( $upgrader, 'Core_Upgrader' ) ) {
			return $reply;
		}

		global $wp_version;

		$current = get_site_transient( 'update_core' );
		if ( ! isset( $this->log_messages['core'] ) || ! is_array( $this->log_messages['core'] ) ) {
			$this->log_messages['core'] = array();
		}

		$item = array(
			'name'   => 'WordPress',
			'from'   => $wp_version,
			'to'     => '0',
			'status' => 0,
			'notes'  => '',
		);

		add_action( '_core_updated_successfully', array( $this, 'set_core_update_success_status' ) );
		add_filter( 'update_feedback', array( $this, 'set_core_update_notes' ), 1, 1 );

		if ( is_object( $current ) && ! empty( $current->updates ) ) {
			foreach ( $current->updates as $update ) {
				if ( ( $this->is_auto_update && 'autoupdate' !== $update->response ) || ( ! $this->is_auto_update && 'autoupdate' === $update->response ) ) {
					continue;
				}
				if ( empty( $update->packages ) ) {
					continue;
				}
				foreach ( $update->packages as $download_url ) {
					if ( $download_url === $package ) {
						$item['to']    = $update->current;
						$item['name'] .= " ({$update->locale})";
						break;
					}
				}
			}
		}

		if ( version_compare( $wp_version, $item['to'], '==' ) && empty( $this->log_messages['core'] ) ) {
			$item['name']                           .= ' (Reinstall)';
			$this->log_messages['core']['reinstall'] = $item;
		} elseif ( version_compare( $wp_version, $item['to'], '<' ) && empty( $this->log_messages['core'] ) ) {
			$this->log_messages['core']['new'] = $item;
		} elseif ( ! empty( $this->log_messages['core'] ) ) {
			$item['name']                          .= ' (Rollback)';
			$item['from']                           = $this->log_messages['core']['new']['to'] ?? $item['from'];
			$item['to']                             = $this->log_messages['core']['new']['from'] ?? $item['to'];
			$this->log_messages['core']['rollback'] = $item;
		}

		return $reply;
	}

	/**
	 * Log manual updates when the upgrader finishes.
	 *
	 * @param \WP_Upgrader|object $wp_upgrader WP_Upgrader instance.
	 * @param array<string,mixed> $hook_extra  Extra arguments passed to hooked filters.
	 */
	public function log_upgrader_updates( $wp_upgrader, $hook_extra ): void {
		if ( $this->is_auto_update ) {
			return;
		}

		if ( isset( $hook_extra['action'] ) && 'update' !== $hook_extra['action'] ) {
			return;
		}

		if ( ! is_array( $hook_extra ) || ! isset( $hook_extra['type'] ) ) {
			return;
		}

		$stacktrace = $this->capture_stacktrace();
		$notes      = '';

		switch ( $hook_extra['type'] ) {
			case 'translation':
				if ( empty( $hook_extra['translations'] ) || ! is_array( $hook_extra['translations'] ) ) {
					break;
				}
				foreach ( $hook_extra['translations'] as $translation ) {
					if ( ! is_array( $translation ) ) {
						continue;
					}
					$key = '';
					if ( 'core' !== ( $translation['type'] ?? '' ) && isset( $translation['slug'], $this->log_messages['translation'][ $translation['slug'] ] ) ) {
						$key = (string) $translation['slug'];
					} elseif ( 'core' === ( $translation['type'] ?? '' ) && isset( $translation['language'], $this->log_messages['translation'][ 'wordpress_default_' . $translation['language'] ] ) ) {
						$key = 'wordpress_default_' . $translation['language'];
					}
					if ( '' === $key || ! isset( $this->log_messages['translation'][ $key ] ) ) {
						continue;
					}
					$data = $this->log_messages['translation'][ $key ];
					$this->commit_manual_log(
						(string) $data['name'],
						$key,
						'translation',
						$data['from'],
						$data['to'],
						$data['status'],
						$notes,
						$stacktrace
					);
					unset( $this->log_messages['translation'][ $key ] );
				}
				if ( empty( $this->log_messages['translation'] ) ) {
					unset( $this->log_messages['translation'] );
				}
				break;
			case 'core':
				if ( empty( $this->log_messages['core'] ) || ! is_array( $this->log_messages['core'] ) ) {
					break;
				}
				foreach ( $this->log_messages['core'] as $core_type => $data ) {
					$entry_notes = $notes ? $notes : ( $data['notes'] ?? '' );
					$this->commit_manual_log(
						(string) $data['name'],
						'core',
						'core',
						$data['from'],
						$data['to'],
						$data['status'],
						(string) $entry_notes,
						$stacktrace
					);
					unset( $this->log_messages['core'][ $core_type ] );
				}
				if ( empty( $this->log_messages['core'] ) ) {
					unset( $this->log_messages['core'] );
				}
				break;
			case 'plugin':
				$plugins = array();
				if ( isset( $hook_extra['plugins'] ) && is_array( $hook_extra['plugins'] ) ) {
					$plugins = $hook_extra['plugins'];
				} elseif ( isset( $hook_extra['plugin'] ) ) {
					$plugins = (array) $hook_extra['plugin'];
				}
				foreach ( $plugins as $plugin_file ) {
					if ( ! isset( $this->log_messages['plugin'][ $plugin_file ] ) ) {
						continue;
					}
					$data = $this->log_messages['plugin'][ $plugin_file ];
					$this->commit_manual_log(
						(string) $data['name'],
						$this->plugin_file_to_slug( (string) $plugin_file ),
						'plugin',
						$data['from'],
						$data['to'],
						$data['status'],
						$notes,
						$stacktrace
					);
					unset( $this->log_messages['plugin'][ $plugin_file ] );
				}
				if ( empty( $this->log_messages['plugin'] ) ) {
					unset( $this->log_messages['plugin'] );
				}
				break;
			case 'theme':
				$themes = array();
				if ( isset( $hook_extra['themes'] ) && is_array( $hook_extra['themes'] ) ) {
					$themes = $hook_extra['themes'];
				} elseif ( isset( $hook_extra['theme'] ) ) {
					$themes = (array) $hook_extra['theme'];
				}
				foreach ( $themes as $theme_stylesheet ) {
					if ( ! isset( $this->log_messages['theme'][ $theme_stylesheet ] ) ) {
						continue;
					}
					$data = $this->log_messages['theme'][ $theme_stylesheet ];
					$this->commit_manual_log(
						(string) $data['name'],
						(string) $theme_stylesheet,
						'theme',
						$data['from'],
						$data['to'],
						$data['status'],
						$notes,
						$stacktrace
					);
					unset( $this->log_messages['theme'][ $theme_stylesheet ] );
				}
				if ( empty( $this->log_messages['theme'] ) ) {
					unset( $this->log_messages['theme'] );
				}
				break;
		}

		$this->maybe_flush_log_messages();
	}

	/**
	 * Append core update feedback to pending core log metadata.
	 *
	 * @param string|mixed $note The core update feedback message.
	 * @return string|mixed
	 */
	public function set_core_update_notes( $note ) {
		if ( ! is_array( $this->log_messages['core'] ?? null ) || empty( $this->log_messages['core'] ) || ! is_string( $note ) ) {
			return $note;
		}

		end( $this->log_messages['core'] );
		$core_key = key( $this->log_messages['core'] );
		if ( ! is_string( $core_key ) ) {
			reset( $this->log_messages['core'] );
			return $note;
		}

		if ( ! isset( $this->log_messages['core'][ $core_key ]['notes'] ) || ! is_string( $this->log_messages['core'][ $core_key ]['notes'] ) ) {
			$this->log_messages['core'][ $core_key ]['notes'] = '';
		}
		$this->log_messages['core'][ $core_key ]['notes'] .= $note . "\n\r\n\r";
		reset( $this->log_messages['core'] );

		return $note;
	}

	/**
	 * Mark the latest core log entry as successful.
	 */
	public function set_core_update_success_status(): void {
		remove_filter( 'update_feedback', array( $this, 'set_core_update_notes' ), 1 );

		if ( ! is_array( $this->log_messages['core'] ?? null ) || empty( $this->log_messages['core'] ) ) {
			return;
		}

		end( $this->log_messages['core'] );
		$core_key = key( $this->log_messages['core'] );
		if ( is_string( $core_key ) ) {
			$this->log_messages['core'][ $core_key ]['status'] = 1;
		}
		reset( $this->log_messages['core'] );
	}

	/**
	 * Set plugin, theme, or translation status from the upgrader install result.
	 *
	 * @param bool|mixed          $response   Installation response.
	 * @param array<string,mixed> $hook_extra Extra arguments passed to hooked filters.
	 * @param mixed               $result     Installation result data.
	 * @return bool|mixed
	 */
	public function set_update_status_by_result( $response, $hook_extra, $result ) {
		if ( isset( $hook_extra['action'] ) && 'update' !== $hook_extra['action'] ) {
			return $response;
		}

		if ( ! is_array( $hook_extra ) ) {
			return $response;
		}

		$success = $result && ! is_wp_error( $result );

		if ( isset( $hook_extra['plugin'], $this->log_messages['plugin'][ $hook_extra['plugin'] ] ) ) {
			$this->log_messages['plugin'][ $hook_extra['plugin'] ]['status'] = $success ? 1 : 0;
		} elseif ( isset( $hook_extra['theme'], $this->log_messages['theme'][ $hook_extra['theme'] ] ) ) {
			$this->log_messages['theme'][ $hook_extra['theme'] ]['status'] = $success ? 1 : 0;
		} elseif ( isset( $hook_extra['language_update_type'], $hook_extra['language_update'] ) ) {
			$language_update = $hook_extra['language_update'];
			if ( 'core' === $hook_extra['language_update_type'] && isset( $language_update->language ) ) {
				$key = 'wordpress_default_' . $language_update->language;
				if ( isset( $this->log_messages['translation'][ $key ] ) ) {
					$this->log_messages['translation'][ $key ]['status'] = $success ? 1 : 0;
				}
			} elseif ( isset( $language_update->slug, $this->log_messages['translation'][ $language_update->slug ] ) ) {
				$this->log_messages['translation'][ $language_update->slug ]['status'] = $success ? 1 : 0;
			}
		}

		return $response;
	}

	/**
	 * Write any remaining buffered manual log entries.
	 */
	private function maybe_flush_log_messages(): void {
		if ( $this->is_auto_update ) {
			$this->log_messages = array();
			return;
		}

		if ( empty( $this->log_messages ) ) {
			return;
		}

		$stacktrace = $this->capture_stacktrace();

		foreach ( $this->log_messages as $update_type => $entities ) {
			if ( ! is_array( $entities ) || empty( $entities ) ) {
				continue;
			}
			foreach ( $entities as $entity_key => $data ) {
				if ( ! is_array( $data ) ) {
					continue;
				}
				$slug = $this->manual_log_slug_for_type( (string) $update_type, $entity_key );
				$this->commit_manual_log(
					(string) ( $data['name'] ?? '' ),
					$slug,
					(string) $update_type,
					$data['from'] ?? '',
					$data['to'] ?? '',
					$data['status'] ?? 0,
					(string) ( $data['notes'] ?? '' ),
					$stacktrace
				);
			}
		}

		$this->log_messages = array();
	}

	/**
	 * Persist a manual update row via insert_log().
	 *
	 * @param string   $name         Upgrade name.
	 * @param string   $slug         Entity slug.
	 * @param string   $update_type  plugin|theme|translation|core.
	 * @param mixed    $from_version Previous version.
	 * @param mixed    $to_version   Target version.
	 * @param int|bool $status_flag  EUM-style 1/0 success flag.
	 * @param string   $notes        Raw notes.
	 * @param string   $stacktrace   Serialized stack trace.
	 */
	private function commit_manual_log( $name, $slug, $update_type, $from_version, $to_version, $status_flag, $notes, $stacktrace = '' ): void {
		$from = (string) $from_version;
		if ( '' === $from ) {
			$from = '0.00';
		}

		$status = ( 1 === (int) $status_flag ) ? 'success' : 'failure';

		$this->insert_log(
			$name,
			$slug,
			get_current_user_id(),
			$from,
			(string) $to_version,
			'manual',
			$update_type,
			$status,
			$notes,
			$stacktrace
		);
	}

	/**
	 * Resolve slug for shutdown flush by update type.
	 *
	 * @param string $update_type Entity type key.
	 * @param mixed  $entity_key  Buffer key for the entity.
	 * @return string
	 */
	private function manual_log_slug_for_type( string $update_type, $entity_key ): string {
		if ( 'core' === $update_type ) {
			return 'core';
		}
		if ( 'plugin' === $update_type ) {
			return $this->plugin_file_to_slug( (string) $entity_key );
		}

		return (string) $entity_key;
	}

	/**
	 * Derive a plugin slug from a plugin file path.
	 *
	 * @param string $plugin_file Plugin basename path.
	 * @return string
	 */
	private function plugin_file_to_slug( string $plugin_file ): string {
		if ( str_contains( $plugin_file, '/' ) ) {
			$slug = dirname( $plugin_file );
			if ( '.' !== $slug && '\\' !== $slug ) {
				return $slug;
			}
		}

		return basename( $plugin_file, '.php' );
	}

	/**
	 * Capture a normalized, serialized debug backtrace for log rows.
	 *
	 * @return string
	 */
	private function capture_stacktrace(): string {
		try {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- Used for manual update diagnostics.
			$backtrace  = debug_backtrace( false );
			$normalized = apply_filters(
				'updatebee_normalized_call_stack_args',
				$this->normalise_call_stack_args( $backtrace )
			);

			return (string) maybe_serialize( $normalized );
		} catch ( \Exception $exception ) {
			unset( $exception );
			return (string) maybe_serialize( array() );
		} catch ( \Error $error ) {
			unset( $error );
			return (string) maybe_serialize( array() );
		}
	}

	/**
	 * Normalise debug_backtrace() output for storage.
	 *
	 * @param array<int, array<string, mixed>> $backtrace Backtrace from debug_backtrace().
	 * @return array<int, array<string, mixed>>
	 */
	private function normalise_call_stack_args( array $backtrace ): array {
		$normalised_backtrace = $backtrace;

		foreach ( $backtrace as $index => $element ) {
			if ( ! isset( $backtrace[ $index ]['args'] ) || ! is_array( $backtrace[ $index ]['args'] ) ) {
				continue;
			}

			$normalised_backtrace[ $index ]['args'] = array();

			foreach ( $backtrace[ $index ]['args'] as $arg_index => $arg ) {
				if ( is_object( $arg ) ) {
					$normalised_backtrace[ $index ]['args'][ $arg_index ] = 'Object(' . get_class( $arg ) . ')';
				} elseif ( is_array( $arg ) ) {
					$normalised_backtrace[ $index ]['args'][ $arg_index ] = 'Array(' . count( $arg ) . ')';
				} elseif ( ! is_string( $arg ) ) {
					$normalised_backtrace[ $index ]['args'][ $arg_index ] = '';
				} else {
					$normalised_backtrace[ $index ]['args'][ $arg_index ] = $arg;
				}
			}
		}

		return $normalised_backtrace;
	}

	/**
	 * Format a serialized stack trace for display in the admin UI.
	 *
	 * @param string $stacktrace Serialized call stack from log storage.
	 * @return string
	 */
	public static function format_stacktrace_for_display( string $stacktrace ): string {
		if ( '' === trim( $stacktrace ) ) {
			return '';
		}

		$decoded = maybe_unserialize( $stacktrace );
		if ( ! is_array( $decoded ) ) {
			return '';
		}

		$trace          = array_reverse( $decoded );
		$lines          = array();
		$frame_number   = 1;
		$truncate_paths = array(
			wp_normalize_path( WP_CONTENT_DIR ),
			wp_normalize_path( ABSPATH ),
		);

		foreach ( $trace as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}

			$line = '';

			if ( isset( $node['file'] ) && isset( $node['line'] ) && $node['line'] ) {
				$filename = (string) $node['file'];
				$line     = '#' . $frame_number . ' ' . str_replace(
					$truncate_paths,
					'',
					wp_normalize_path( $filename )
				) . ' (' . $node['line'] . '): ';
			}

			if ( isset( $node['class'] ) ) {
				$type     = isset( $node['type'] ) ? (string) $node['type'] : '->';
				$function = isset( $node['function'] ) ? (string) $node['function'] : '';
				$line    .= (string) $node['class'] . $type . $function;
				$line    .= '(';
				$args     = '';

				if ( isset( $node['args'] ) && is_array( $node['args'] ) ) {
					foreach ( $node['args'] as $arg ) {
						if ( ! is_string( $arg ) ) {
							continue;
						}

						if ( '' !== $args ) {
							$args .= ', ';
						}

						$args .= ( '' === $arg ) ? "''" : $arg;
					}
				}

				$line .= $args . ')';
			} elseif ( isset( $node['function'] ) ) {
				$function = (string) $node['function'];
				$args     = isset( $node['args'] ) && is_array( $node['args'] ) ? $node['args'] : array();

				if ( in_array( $function, array( 'do_action', 'apply_filters', 'do_action_ref_array', 'apply_filters_ref_array' ), true ) ) {
					$hook  = isset( $args[0] ) && is_string( $args[0] ) ? $args[0] : '';
					$line .= $function . "('" . $hook . "')";
				} elseif ( in_array( $function, array( 'include', 'include_once', 'require', 'require_once' ), true ) ) {
					$filename = isset( $args[0] ) && is_string( $args[0] ) ? $args[0] : '';
					$line    .= $function . "('" . str_replace( $truncate_paths, '', wp_normalize_path( $filename ) ) . "')";
				} else {
					$line .= $function;
				}
			}

			if ( '' !== $line ) {
				$lines[] = $line;
			}

			++$frame_number;
		}

		return implode( "\n", $lines );
	}

	/**
	 * Get successful logs based on params.
	 *
	 * @param array<string, bool> $supported {
	 *     Items that are supported.
	 *
	 *     @type bool $plugins      Whether to use plugins. Default true.
	 *     @type bool $themes       Whether to use themes. Default true.
	 *     @type bool $core         Whether to use core. Default true.
	 *     @type bool $translations Whether to use translations. Default true.
	 * }
	 * @param int|string          $since_last_timestamp Timestamp to get the logs since (optional).
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_success_logs( array $supported = array(), $since_last_timestamp = '' ): array {
		return self::get_logs_by_status( 'success', $supported, $since_last_timestamp );
	}

	/**
	 * Get failure logs based on params.
	 *
	 * @param array<string, bool> $supported {
	 *     Items that are supported.
	 *
	 *     @type bool $plugins      Whether to use plugins. Default true.
	 *     @type bool $themes       Whether to use themes. Default true.
	 *     @type bool $core         Whether to use core. Default true.
	 *     @type bool $translations Whether to use translations. Default true.
	 * }
	 * @param int|string          $since_last_timestamp Timestamp to get the logs since (optional).
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_failure_logs( array $supported = array(), $since_last_timestamp = '' ): array {
		return self::get_logs_by_status( array( 'failure', 'fatal' ), $supported, $since_last_timestamp );
	}

	/**
	 * Query logs by status.
	 *
	 * @param string|array<int, string> $status               success|failure|fatal or a list of those.
	 * @param array<string, bool>       $supported            Supported update types.
	 * @param int|string                $since_last_timestamp Optional UNIX timestamp filter.
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_logs_by_status( $status, array $supported, $since_last_timestamp ): array {
		global $wpdb;

		$defaults  = array(
			'plugins'      => true,
			'themes'       => true,
			'core'         => true,
			'translations' => true,
		);
		$supported = wp_parse_args( $supported, $defaults );

		$types = array();
		if ( ! empty( $supported['core'] ) ) {
			$types[] = "'core'";
		}
		if ( ! empty( $supported['plugins'] ) ) {
			$types[] = "'plugin'";
		}
		if ( ! empty( $supported['themes'] ) ) {
			$types[] = "'theme'";
		}
		if ( ! empty( $supported['translations'] ) ) {
			$types[] = "'translation'";
		}

		if ( empty( $types ) ) {
			return array();
		}

		$allowed_statuses = array( 'success', 'failure', 'fatal', 'blocked', 'skipped', 'delayed' );
		$statuses         = is_array( $status ) ? $status : array( $status );
		$statuses         = array_values(
			array_intersect(
				array_map( 'strval', $statuses ),
				$allowed_statuses
			)
		);
		if ( empty( $statuses ) ) {
			return array();
		}

		$tablename = \esc_sql( Table_Logs::get_table_name() );

		$valid_timestamp      = false;
		$since_last_timestamp = absint( $since_last_timestamp );
		if ( is_numeric( $since_last_timestamp ) && (int) $since_last_timestamp === $since_last_timestamp && 0 !== $since_last_timestamp ) {
			$valid_timestamp = true;
		}

		$table_limit = absint( apply_filters( 'updatebee_log_table_limit', 100 ) );
		if ( $table_limit < 1 ) {
			$table_limit = 100;
		}

		$where   = '';
		$limit   = sprintf( ' LIMIT %d', $table_limit );
		$orderby = 'date';
		$order   = 'DESC';

		if ( $valid_timestamp ) {
			$cutoff_datetime = wp_date( 'Y-m-d H:i:s', $since_last_timestamp );
			if ( false === $cutoff_datetime ) {
				$cutoff_datetime = date_i18n( 'Y-m-d H:i:s', $since_last_timestamp );
			}
			$where .= $wpdb->prepare( ' AND date > %s', $cutoff_datetime );
		}

		$status_placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholders are generated from a counted list of statuses.
		$where .= $wpdb->prepare( ' AND update_status IN (' . $status_placeholders . ')', ...$statuses );

		$where .= ' AND update_type IN (' . implode( ',', $types ) . ')';

		$order_sql = sanitize_sql_orderby( $orderby . ' ' . $order );
		if ( ! is_string( $order_sql ) ) {
			$order_sql = 'date DESC';
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Table name is internal; IN list is built from fixed literals.
		$query = sprintf(
			'SELECT name, user_id, from_version, to_version, update_offer, update_type, update_status, date FROM %s WHERE 1=1 %s ORDER BY %s %s',
			$tablename,
			$where,
			$order_sql,
			$limit
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is assembled from prepared fragments and an escaped table name.
		$results = $wpdb->get_results( $query, \ARRAY_A );

		if ( empty( $results ) || ! is_array( $results ) ) {
			return array();
		}

		return $results;
	}
}
