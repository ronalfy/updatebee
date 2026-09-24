<?php
/**
 * Plugins table for UpdateBee.
 *
 * @package UpdateBee
 */

namespace UpdateBee\Tables;

/**
 * Do not load directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

/**
 * Class for the plugins notes table.
 *
 * @package UpdateBee
 */
class Table_Plugins extends Tables {

	/**
	 * Instance of the table.
	 *
	 * @var Table_Plugins
	 */
	private static $instance;

	/**
	 * Returns an instance of this class.
	 *
	 * @since 1.0.0
	 *
	 * @return Table_Plugins $instance
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected static $table_name = 'ub_plugins';

	/**
	 * Table Version.
	 *
	 * @var string
	 */
	protected static $table_version = '1.0.4';

	/**
	 * Table columns and their schema.
	 *
	 * @var array
	 */
	protected static $columns = array(
		'id'                     => array(
			'name'           => 'id',
			'schema'         => 'INT(11) NOT NULL AUTO_INCREMENT',
			'sanitize'       => 'absint',
			'validate'       => 'is_int',
			'prepare_symbol' => '%d',
			'primary_key'    => true,
			'mapped_column'  => null,
		),
		'user_id'                => array(
			'name'           => 'user_id',
			'schema'         => 'VARCHAR(255) NOT NULL',
			'sanitize'       => 'sanitize_text_field',
			'validate'       => 'is_string',
			'prepare_symbol' => '%s',
			'index'          => true,
			'mapped_column'  => null,
		),
		'plugin_slug'            => array(
			'name'           => 'plugin_slug',
			'schema'         => 'VARCHAR(255) NOT NULL UNIQUE',
			'sanitize'       => 'sanitize_text_field',
			'validate'       => 'is_string',
			'prepare_symbol' => '%s',
			'index'          => true,
			'mapped_column'  => null,
		),
		'notes'                  => array(
			'name'           => 'notes',
			'schema'         => 'TEXT NOT NULL',
			'sanitize'       => 'sanitize_textarea_field',
			'validate'       => 'is_string',
			'prepare_symbol' => '%s',
			'mapped_column'  => null,
		),
		'delay_days'             => array(
			'name'           => 'delay_days',
			'schema'         => 'INT(11) NULL DEFAULT NULL',
			'sanitize'       => 'absint',
			'validate'       => 'is_int',
			'prepare_symbol' => '%d',
			'mapped_column'  => null,
		),
		'block_untested_updates' => array(
			'name'           => 'block_untested_updates',
			'schema'         => 'TINYINT(1) NULL DEFAULT NULL',
			'sanitize'       => 'absint',
			'validate'       => 'is_int',
			'prepare_symbol' => '%d',
			'mapped_column'  => null,
		),
	);

	/**
	 * Get the table version.
	 *
	 * @return string
	 */
	public static function get_table_version() {
		return self::$table_version;
	}

	/**
	 * Initialize the table.
	 */
	public function init() {

		// Drop the table when action is triggered.
		add_action( 'updatebee_drop_table_plugins', array( self::class, 'drop_table' ) );

		// Filter the resource count.
		add_filter( 'updatebee_get_count_plugins', array( self::class, 'get_count' ) );

		self::create_table();
	}

	/**
	 * Sanitize a plugin file used as the notes table key.
	 *
	 * @param string $plugin_file Plugin basename path.
	 * @return string Sanitized plugin file.
	 */
	private static function sanitize_plugin_file( string $plugin_file ): string {
		return sanitize_text_field( $plugin_file );
	}

	/**
	 * Get notes for a specific plugin.
	 *
	 * @param string $plugin_file Plugin basename path.
	 * @return string Plugin notes or empty string if none found.
	 */
	public static function get_notes( string $plugin_file ): string {
		$row = self::get_plugin_by_slug( $plugin_file );
		if ( ! is_array( $row ) || ! isset( $row['notes'] ) ) {
			return '';
		}

		return (string) $row['notes'];
	}

	/**
	 * Get all plugin notes keyed by plugin file.
	 *
	 * @return array<string, string> Plugin file => notes.
	 */
	public static function get_all_notes(): array {
		global $wpdb;

		$table_name = static::get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup.
		$results = $wpdb->get_results( "SELECT plugin_slug, notes FROM {$table_name}", ARRAY_A );

		if ( empty( $results ) || is_wp_error( $results ) ) {
			return array();
		}

		$notes = array();
		foreach ( $results as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['plugin_slug'] ) ) {
				continue;
			}

			$plugin_file = (string) $row['plugin_slug'];
			if ( '' === $plugin_file ) {
				continue;
			}

			$notes[ $plugin_file ] = isset( $row['notes'] ) ? (string) $row['notes'] : '';
		}

		return $notes;
	}

	/**
	 * Delay settings for one plugin.
	 *
	 * @param string $plugin_file Plugin basename path.
	 * @return array{delay_days: int|null} Delay settings.
	 */
	public static function get_delay_settings( string $plugin_file ): array {
		return array(
			'delay_days' => self::get_delay_days( $plugin_file ),
		);
	}

	/**
	 * Delay settings for every plugin that has a row.
	 *
	 * @return array<string, array{delay_days: int|null, allow_untested: bool}> Plugin file => settings.
	 */
	public static function get_all_delay_settings(): array {
		global $wpdb;

		if ( ! is_object( $wpdb ) ) {
			return array();
		}

		$table_name = static::get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup.
		$results = $wpdb->get_results( "SELECT plugin_slug, delay_days, block_untested_updates FROM {$table_name}", ARRAY_A );

		if ( empty( $results ) || is_wp_error( $results ) ) {
			return array();
		}

		$settings = array();
		foreach ( $results as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['plugin_slug'] ) ) {
				continue;
			}

			$plugin_file = (string) $row['plugin_slug'];
			if ( '' === $plugin_file ) {
				continue;
			}

			$settings[ $plugin_file ] = array(
				'delay_days'     => self::delay_days_from_row( $row ),
				'allow_untested' => self::allows_untested_from_row( $row ),
			);
		}

		return $settings;
	}

	/**
	 * Saved delay override for one plugin.
	 *
	 * @param string $plugin_file Plugin basename path.
	 * @return int|null Days to wait, or null when the plugin uses the global delay.
	 */
	public static function get_delay_days( string $plugin_file ): ?int {
		global $wpdb;

		if ( ! is_object( $wpdb ) ) {
			return null;
		}

		$plugin_file = self::sanitize_plugin_file( $plugin_file );
		if ( '' === $plugin_file ) {
			return null;
		}

		$row = self::get_plugin_by_slug( $plugin_file );
		if ( ! is_array( $row ) ) {
			return null;
		}

		return self::delay_days_from_row( $row );
	}

	/**
	 * Save or clear a plugin delay override.
	 *
	 * @param string   $plugin_file Plugin basename path.
	 * @param int|null $delay_days  Days to wait, or null to inherit the global delay.
	 * @return array{delay_days: int|null}|\WP_Error Saved delay settings, or WP_Error on failure.
	 */
	public static function save_delay_settings( string $plugin_file, ?int $delay_days ) {
		global $wpdb;

		if ( ! is_object( $wpdb ) ) {
			return new \WP_Error( 'db_unavailable', __( 'Delay settings could not be saved.', 'updatebee' ) );
		}

		$plugin_file = self::sanitize_plugin_file( $plugin_file );
		if ( '' === $plugin_file ) {
			return new \WP_Error( 'invalid_plugin', __( 'Invalid plugin file.', 'updatebee' ) );
		}

		$existing   = self::get_plugin_by_slug( $plugin_file );
		$notes      = is_array( $existing ) && isset( $existing['notes'] ) ? (string) $existing['notes'] : '';
		$user_id    = (string) get_current_user_id();
		$table_name = static::get_table_name();

		if ( null === $delay_days ) {
			if ( ! is_array( $existing ) ) {
				return array(
					'delay_days' => null,
				);
			}

			if ( '' === trim( $notes ) && ! self::allows_untested_from_row( $existing ) ) {
				$deleted = self::delete_plugin_row( $plugin_file );
				if ( is_wp_error( $deleted ) ) {
					return $deleted;
				}

				return array(
					'delay_days' => null,
				);
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update. Null becomes SQL NULL.
			$result = $wpdb->update(
				$table_name,
				array(
					'user_id'    => $user_id,
					'delay_days' => null,
				),
				array(
					'plugin_slug' => $plugin_file,
				),
				array( '%s', '%d' ),
				array( '%s' )
			);
			if ( false === $result ) {
				return new \WP_Error( 'update_failed', __( 'Failed to save delay settings.', 'updatebee' ) );
			}

			return array(
				'delay_days' => null,
			);
		}

		$days = absint( $delay_days );

		if ( is_array( $existing ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update.
			$result = $wpdb->update(
				$table_name,
				array(
					'user_id'    => $user_id,
					'delay_days' => $days,
				),
				array(
					'plugin_slug' => $plugin_file,
				),
				array( '%s', '%d' ),
				array( '%s' )
			);

			if ( false === $result ) {
				return new \WP_Error( 'update_failed', __( 'Failed to save delay settings.', 'updatebee' ) );
			}

			return array(
				'delay_days' => $days,
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table insert.
		$result = $wpdb->insert(
			$table_name,
			array(
				'user_id'     => $user_id,
				'plugin_slug' => $plugin_file,
				'notes'       => '',
				'delay_days'  => $days,
			),
			array( '%s', '%s', '%s', '%d' )
		);

		if ( false === $result ) {
			return new \WP_Error( 'insert_failed', __( 'Failed to save delay settings.', 'updatebee' ) );
		}

		return array(
			'delay_days' => $days,
		);
	}

	/**
	 * Save notes for a plugin. Empty notes remove the row when no delay is stored.
	 *
	 * @param string $plugin_file Plugin basename path.
	 * @param string $notes       Note text.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function save_notes( string $plugin_file, string $notes ) {
		global $wpdb;

		$plugin_file = self::sanitize_plugin_file( $plugin_file );
		if ( '' === $plugin_file ) {
			return new \WP_Error( 'invalid_plugin', __( 'Invalid plugin file.', 'updatebee' ) );
		}

		$notes = sanitize_textarea_field( $notes );
		if ( '' === trim( $notes ) ) {
			return self::delete_notes( $plugin_file );
		}

		$user_id    = (string) get_current_user_id();
		$existing   = self::get_plugin_by_slug( $plugin_file );
		$table_name = static::get_table_name();

		if ( is_array( $existing ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update.
			$result = $wpdb->update(
				$table_name,
				array(
					'user_id' => $user_id,
					'notes'   => $notes,
				),
				array(
					'plugin_slug' => $plugin_file,
				),
				array( '%s', '%s' ),
				array( '%s' )
			);

			if ( false === $result ) {
				return new \WP_Error( 'update_failed', __( 'Failed to update plugin notes.', 'updatebee' ) );
			}

			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table insert.
		$result = $wpdb->insert(
			$table_name,
			array(
				'user_id'     => $user_id,
				'plugin_slug' => $plugin_file,
				'notes'       => $notes,
			),
			array( '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return new \WP_Error( 'insert_failed', __( 'Failed to save plugin notes.', 'updatebee' ) );
		}

		return true;
	}

	/**
	 * Delete notes for a plugin.
	 *
	 * @param string $plugin_file Plugin basename path.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function delete_notes( string $plugin_file ) {
		global $wpdb;

		$plugin_file = self::sanitize_plugin_file( $plugin_file );
		if ( '' === $plugin_file ) {
			return new \WP_Error( 'invalid_plugin', __( 'Invalid plugin file.', 'updatebee' ) );
		}

		$existing = self::get_plugin_by_slug( $plugin_file );
		if ( ! is_array( $existing ) ) {
			return true;
		}

		if ( null !== self::delay_days_from_row( $existing ) || self::allows_untested_from_row( $existing ) ) {
			$table_name = static::get_table_name();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update.
			$result = $wpdb->update(
				$table_name,
				array(
					'user_id' => (string) get_current_user_id(),
					'notes'   => '',
				),
				array(
					'plugin_slug' => $plugin_file,
				),
				array( '%s', '%s' ),
				array( '%s' )
			);

			if ( false === $result ) {
				return new \WP_Error( 'update_failed', __( 'Failed to update plugin notes.', 'updatebee' ) );
			}

			return true;
		}

		return self::delete_plugin_row( $plugin_file );
	}

	/**
	 * Whether this plugin is allowed to receive untested updates.
	 *
	 * @param string $plugin_file Plugin basename path.
	 * @return bool True when the plugin opts out of the site block.
	 */
	public static function allows_untested_updates( string $plugin_file ): bool {
		global $wpdb;

		if ( ! is_object( $wpdb ) ) {
			return false;
		}

		$plugin_file = self::sanitize_plugin_file( $plugin_file );
		if ( '' === $plugin_file ) {
			return false;
		}

		$row = self::get_plugin_by_slug( $plugin_file );
		if ( ! is_array( $row ) ) {
			return false;
		}

		return self::allows_untested_from_row( $row );
	}

	/**
	 * Save or clear a plugin untested-update exemption.
	 *
	 * @param string    $plugin_file    Plugin basename path.
	 * @param bool|null $allow_untested True to allow untested updates, or null to inherit the site setting.
	 * @return array{allow_untested: bool}|\WP_Error Saved setting, or WP_Error on failure.
	 */
	public static function save_untested_setting( string $plugin_file, ?bool $allow_untested ) {
		global $wpdb;

		if ( ! is_object( $wpdb ) ) {
			return new \WP_Error( 'db_unavailable', __( 'Plugin settings could not be saved.', 'updatebee' ) );
		}

		$plugin_file = self::sanitize_plugin_file( $plugin_file );
		if ( '' === $plugin_file ) {
			return new \WP_Error( 'invalid_plugin', __( 'Invalid plugin file.', 'updatebee' ) );
		}

		$existing   = self::get_plugin_by_slug( $plugin_file );
		$notes      = is_array( $existing ) && isset( $existing['notes'] ) ? (string) $existing['notes'] : '';
		$user_id    = (string) get_current_user_id();
		$table_name = static::get_table_name();
		$stored     = true === $allow_untested ? 0 : null;

		if ( null === $stored ) {
			if ( ! is_array( $existing ) ) {
				return array(
					'allow_untested' => false,
				);
			}

			$has_notes = '' !== trim( $notes );
			$has_delay = null !== self::delay_days_from_row( $existing );
			if ( ! $has_notes && ! $has_delay ) {
				$deleted = self::delete_plugin_row( $plugin_file );
				if ( is_wp_error( $deleted ) ) {
					return $deleted;
				}

				return array(
					'allow_untested' => false,
				);
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update. Null becomes SQL NULL.
			$result = $wpdb->update(
				$table_name,
				array(
					'user_id'                => $user_id,
					'block_untested_updates' => null,
				),
				array(
					'plugin_slug' => $plugin_file,
				),
				array( '%s', '%d' ),
				array( '%s' )
			);
			if ( false === $result ) {
				return new \WP_Error( 'update_failed', __( 'Failed to save plugin settings.', 'updatebee' ) );
			}

			return array(
				'allow_untested' => false,
			);
		}

		if ( is_array( $existing ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update.
			$result = $wpdb->update(
				$table_name,
				array(
					'user_id'                => $user_id,
					'block_untested_updates' => 0,
				),
				array(
					'plugin_slug' => $plugin_file,
				),
				array( '%s', '%d' ),
				array( '%s' )
			);

			if ( false === $result ) {
				return new \WP_Error( 'update_failed', __( 'Failed to save plugin settings.', 'updatebee' ) );
			}

			return array(
				'allow_untested' => true,
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table insert.
		$result = $wpdb->insert(
			$table_name,
			array(
				'user_id'                => $user_id,
				'plugin_slug'            => $plugin_file,
				'notes'                  => '',
				'block_untested_updates' => 0,
			),
			array( '%s', '%s', '%s', '%d' )
		);

		if ( false === $result ) {
			return new \WP_Error( 'insert_failed', __( 'Failed to save plugin settings.', 'updatebee' ) );
		}

		return array(
			'allow_untested' => true,
		);
	}

	/**
	 * Read delay_days from a table row.
	 *
	 * @param array<string, mixed> $row Table row.
	 * @return int|null Delay days, or null when the plugin uses the global delay.
	 */
	private static function delay_days_from_row( array $row ): ?int {
		if ( ! array_key_exists( 'delay_days', $row ) || null === $row['delay_days'] || '' === $row['delay_days'] ) {
			return null;
		}

		return absint( $row['delay_days'] );
	}

	/**
	 * Whether a row stores an untested-update exemption.
	 *
	 * A stored 0 allows untested updates for this item. Null inherits the site setting.
	 *
	 * @param array<string, mixed> $row Table row.
	 * @return bool True when untested updates are allowed for this item.
	 */
	private static function allows_untested_from_row( array $row ): bool {
		if ( ! array_key_exists( 'block_untested_updates', $row ) || null === $row['block_untested_updates'] || '' === $row['block_untested_updates'] ) {
			return false;
		}

		return 0 === (int) $row['block_untested_updates'];
	}

	/**
	 * Delete a plugin row.
	 *
	 * @param string $plugin_file Sanitized plugin basename path.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	private static function delete_plugin_row( string $plugin_file ) {
		global $wpdb;

		$table_name = static::get_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete.
		$result = $wpdb->delete(
			$table_name,
			array(
				'plugin_slug' => $plugin_file,
			),
			array( '%s' )
		);

		if ( false === $result ) {
			return new \WP_Error( 'delete_failed', __( 'Failed to delete plugin notes.', 'updatebee' ) );
		}

		return true;
	}

	/**
	 * Get a plugin by its file key.
	 *
	 * @param string $plugin_slug Plugin basename path.
	 * @return array|false Plugin row or false if not found.
	 */
	public static function get_plugin_by_slug( string $plugin_slug ) {
		return self::get_by( 'plugin_slug', $plugin_slug );
	}
}
