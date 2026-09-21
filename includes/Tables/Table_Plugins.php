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
	protected static $table_version = '1.0.2';

	/**
	 * Table columns and their schema.
	 *
	 * @var array
	 */
	protected static $columns = array(
		'id'          => array(
			'name'           => 'id',
			'schema'         => 'INT(11) NOT NULL AUTO_INCREMENT',
			'sanitize'       => 'absint',
			'validate'       => 'is_int',
			'prepare_symbol' => '%d',
			'primary_key'    => true,
			'mapped_column'  => null,
		),
		'user_id'     => array(
			'name'           => 'user_id',
			'schema'         => 'VARCHAR(255) NOT NULL',
			'sanitize'       => 'sanitize_text_field',
			'validate'       => 'is_string',
			'prepare_symbol' => '%s',
			'index'          => true,
			'mapped_column'  => null,
		),
		'plugin_slug' => array(
			'name'           => 'plugin_slug',
			'schema'         => 'VARCHAR(255) NOT NULL UNIQUE',
			'sanitize'       => 'sanitize_text_field',
			'validate'       => 'is_string',
			'prepare_symbol' => '%s',
			'index'          => true,
			'mapped_column'  => null,
		),
		'notes'       => array(
			'name'           => 'notes',
			'schema'         => 'TEXT NOT NULL',
			'sanitize'       => 'sanitize_textarea_field',
			'validate'       => 'is_string',
			'prepare_symbol' => '%s',
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
	 * Save notes for a plugin. Empty notes remove the row.
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
