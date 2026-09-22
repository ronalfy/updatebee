<?php
/**
 * Log automatic updates to the database.
 *
 * Credit: Forked from Easy Updates Manager / Ultimate Auto Update.
 *
 * @package updatebee
 */

namespace UpdateBee;

use UpdateBee\Tables\Table_Logs;

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
	 * Class runner.
	 */
	public function run(): void {
		add_action( 'pre_auto_update', array( $this, 'pre_auto_update_version_store' ), 10, 3 );

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
					if ( isset( $core->messages ) && is_array( $core->messages ) ) {
						foreach ( $core->messages as $message ) {
							$raw .= $message . "\n\r\n\r";
						}
					}
					delete_site_option( self::CORE_VERSION_OPTION );
					$this->insert_log( $name, $slug, $user_id, (string) $prev_version, $current_version, 'automatic', $update_type, $status, $raw );
					break;
				case 'plugin':
					foreach ( $results as $plugin ) {
						if ( ! is_object( $plugin ) || ! isset( $plugin->item->plugin ) ) {
							continue;
						}

						$name            = ( isset( $plugin->name ) && ! empty( $plugin->name ) ) ? (string) $plugin->name : (string) $plugin->item->slug;
						$slug            = (string) $plugin->item->slug;
						$status          = 'success';
						$update_type     = 'plugin';
						$prev_version    = isset( $plugin->item->current_version ) ? sanitize_text_field( (string) $plugin->item->current_version ) : '';
						$current_version = isset( $plugin->item->new_version ) ? sanitize_text_field( (string) $plugin->item->new_version ) : '';
						$user_id         = 0;
						$raw             = '';
						// If new version is less than or equal to version_from, mark as failure.
						if ( version_compare( $current_version, $prev_version, '<=' ) || $prev_version === $current_version ) {
							$status = 'failure';
						}
						if ( isset( $plugin->messages ) && is_array( $plugin->messages ) ) {
							foreach ( $plugin->messages as $message ) {
								$raw .= $message . "\n\r\n\r";
							}
						}
						$this->insert_log( $name, $slug, $user_id, $prev_version, $current_version, 'automatic', $update_type, $status, $raw );
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
						// If new version is less than or equal to version_from, mark as failure.
						if ( version_compare( $current_version, $prev_version, '<=' ) || $prev_version === $current_version ) {
							$status = 'failure';
						}
						if ( isset( $theme->messages ) && is_array( $theme->messages ) ) {
							foreach ( $theme->messages as $message ) {
								$raw .= $message . "\n\r";
							}
						}
						$this->insert_log( $name, $slug, $user_id, $prev_version, $current_version, 'automatic', $update_type, $status, $raw );
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
	 * @param string $status          Status of the upgrade. success|failure|blocked|install|deactivate|delete|rollback|update.
	 * @param string $notes           Raw upgrade notes.
	 */
	public function insert_log( $name, $slug, $user_id, $prev_version, $current_version, $update_offer, $update_type, $status = 'success', $notes = '' ): void {
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom logs table insert.
		$wpdb->insert(
			$table_name,
			array(
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
			),
			array(
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
			)
		);

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

		self::$current_updates[] = array(
			'name'          => $name,
			'slug'          => $slug,
			'user_id'       => $user_id,
			'from_version'  => $prev_version,
			'to_version'    => $current_version,
			'update_offer'  => $update_offer,
			'update_type'   => $update_type,
			'update_status' => $status,
			'date'          => current_time( 'mysql' ),
		);
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
		return self::get_logs_by_status( 'failure', $supported, $since_last_timestamp );
	}

	/**
	 * Query logs by status.
	 *
	 * @param string              $status               success|failure.
	 * @param array<string, bool> $supported            Supported update types.
	 * @param int|string          $since_last_timestamp Optional UNIX timestamp filter.
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_logs_by_status( string $status, array $supported, $since_last_timestamp ): array {
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

		$tablename = sanitize_text_field( Table_Logs::get_table_name() );

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
			$where .= sprintf(
				' AND UNIX_TIMESTAMP(date) > %d',
				absint( $since_last_timestamp )
			);
		}

		$where .= $wpdb->prepare( ' AND update_status = %s', $status );

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

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query.
		$results = $wpdb->get_results( $query, \ARRAY_A );

		if ( empty( $results ) || ! is_array( $results ) ) {
			return array();
		}

		return $results;
	}
}
