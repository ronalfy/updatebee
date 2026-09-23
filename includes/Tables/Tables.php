<?php
/**
 * Abstract class for Todoist tables.
 *
 * @package GFHeyTodos
 */

namespace UpdateBee\Tables;

/**
 * Do not load directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

/**
 * Abstract class for Todoist tables.
 *
 * @package GF_Todoist_Tables
 */
abstract class Tables {

	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected static $table_name = '';

	/**
	 * Table Version.
	 *
	 * @var int
	 */
	protected static $table_version = -1;

	/**
	 * Table columns and their schema.
	 *
	 * @var array
	 */
	protected static $columns = array();

	/**
	 * Variable to hold all the registered tables.
	 *
	 * @var array Holds all the table instances.
	 */
	protected static $tables = array();

	/**
	 * Register the table.
	 *
	 * @param Tables $class_instance The class to register.
	 *
	 * @return Tables|\WP_Error The registered table or WP_Error if the table registration is invalid.
	 */
	public static function register_table( $class_instance ) {

		if ( in_array( $class_instance, static::$tables, true ) ) {
			return $class_instance;
		}

		if ( ! is_callable( array( $class_instance, 'get_instance' ) ) ) {
			return new \WP_Error( 'invalid_table', __( 'Table is not callable.', 'gf-todoist' ) );
		}
		$instance = call_user_func( array( $class_instance, 'get_instance' ) );

		static::$tables[] = $instance;
		return $instance;
	}

	/**
	 * Initialize the tables.
	 */
	public static function init_tables() {
		foreach ( static::$tables as $table ) {

			// Init the table.
			if ( is_callable( array( $table, 'init' ) ) ) {
				call_user_func( array( $table, 'init' ) );
			}
		}

		return self::$tables;
	}

	/**
	 * Get the registered tables.
	 *
	 * @return array
	 */
	public static function get_tables() {
		$tables = array_unique( static::$tables );

		return $tables;
	}

	/**
	 * Create the table schema from columns.
	 *
	 * @return string
	 */
	protected static function get_table_schema() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->base_prefix . static::$table_name;

		$schema = "CREATE TABLE $table_name (";

		// Add column definitions.
		foreach ( static::$columns as $column_name => $definition ) {
			$schema .= "\n$column_name {$definition['schema']},";
		}

		// Add primary key.
		$primary_keys = array_filter(
			static::$columns,
			function ( $column ) {
				return isset( $column['primary_key'] ) && $column['primary_key'];
			}
		);

		if ( ! empty( $primary_keys ) ) {
			$primary_key_columns = array_keys( $primary_keys );
			$schema             .= "\nPRIMARY KEY  (" . implode( ',', $primary_key_columns ) . ')';
		}

		$schema .= "\n) $charset_collate;";

		return $schema;
	}

	/**
	 * Create the table.
	 *
	 * @return bool|\WP_Error True if the table was created, false otherwise.
	 */
	protected static function create_table() {
		$table_name     = static::get_table_name( false );
		$version_option = $table_name . '_version';
		$schema_option  = $table_name . '_schema';

		$schema      = static::get_table_schema();
		$schema_hash = md5( $schema );

		// The stored version can sit ahead of the real schema when columns change without a
		// version bump, so the schema itself is fingerprinted and checked alongside the version.
		$version            = get_option( $version_option, 0 );
		$is_current_version = version_compare( (string) $version, (string) static::$table_version, '>=' );
		$is_current_schema  = ( get_option( $schema_option, '' ) === $schema_hash );

		if ( $is_current_version && $is_current_schema ) {
			return false;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $schema );

		// An empty dbDelta result only means there was nothing to change, so the table itself
		// is checked to decide whether the migration succeeded.
		if ( ! static::table_exists() ) {
			return new \WP_Error( 'table_creation_failed', __( 'Table creation failed.', 'updatebee' ) );
		}

		update_option( $version_option, static::$table_version );
		update_option( $schema_option, $schema_hash );

		return true;
	}

	/**
	 * Check whether the table exists in the database.
	 *
	 * @return boolean True when the table exists.
	 */
	protected static function table_exists() {
		global $wpdb;

		if ( ! is_object( $wpdb ) ) {
			return false;
		}

		$table_name = static::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema lookup for a known table name.
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) ) );

		return $found === $table_name;
	}

	/**
	 * Check if table needs update.
	 *
	 * @return boolean
	 */
	public static function needs_update() {
		$table_name      = static::get_table_name( false );
		$current_version = get_option( $table_name . '_version', 0 );

		if ( version_compare( (string) $current_version, (string) static::$table_version, '<' ) ) {
			return true;
		}

		return get_option( $table_name . '_schema', '' ) !== md5( static::get_table_schema() );
	}

	/**
	 * Get the table name With the WPDB prefix.
	 *
	 * @param boolean $with_prefix Whether to include the WPDB prefix.
	 *
	 * @return string
	 */
	public static function get_table_name( $with_prefix = true ) {
		global $wpdb;
		return $with_prefix ? $wpdb->base_prefix . static::$table_name : static::$table_name;
	}

	/**
	 * Drop the table.
	 *
	 * @return bool|\WP_Error True if the table was dropped, false otherwise.
	 */
	public static function drop_table() {
		global $wpdb;
		// Initialize the database connection.
		$table_name = sanitize_key( static::get_table_name( true ) );
		$result     = $wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

		// Remove the version and schema fingerprint options.
		delete_option( static::get_table_name( false ) . '_version' );
		delete_option( static::get_table_name( false ) . '_schema' );
		return $result;
	}

	/**
	 * Get the table version.
	 *
	 * @return int
	 */
	public static function get_table_version() {
		return static::$table_version;
	}

	/**
	 * Placeholder for table initialization.
	 */
	abstract protected function init();

	/**
	 * Get the count of projects.
	 *
	 * @return int
	 */
	public static function get_count() {
		global $wpdb;
		$table_name = static::get_table_name();
		$sql        = "SELECT COUNT(*) FROM $table_name";
		$count      = $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( is_wp_error( $count ) || ! is_numeric( $count ) ) {
			return 0;
		}

		return absint( $count );
	}

	/**
	 * Batch insert records into the table.
	 *
	 * @param array $records Array of records to insert.
	 * @return bool|\WP_Error True on success, WP_Error on failure.
	 */
	protected static function batch_insert( $records ) {
		global $wpdb;

		if ( empty( $records ) ) {
			return new \WP_Error( 'no_records', __( 'No records to insert.', 'gf-todoist' ) );
		}

		$table_name = static::get_table_name();

		// Remove ID from columns, as we don't need it for the insert.
		$columns = array_keys( static::$columns );
		$columns = array_diff( $columns, array( 'id' ) );

		$values = array();
		$chunks = array_chunk( $records, 50 ); // Process in chunks of 100 records.

		foreach ( $chunks as $chunk ) {
			$placeholders = array();
			$data         = array();

			foreach ( $chunk as $record ) {
				$row_placeholders = array();

				foreach ( $columns as $index => $column_name ) {
					$column = static::$columns[ $column_name ];

					// If column is mapped to a different column, use that instead.
					if ( isset( $column['mapped_column'] ) ) {
						$column_name = $column['mapped_column'];
					}

					// Get the column from the record.
					$column_value = isset( $record[ $column_name ] ) ? $record[ $column_name ] : null;

					// Apply sanitization if defined.
					if ( isset( $column['sanitize'] ) && is_callable( $column['sanitize'] ) ) {
						$column_value = call_user_func( $column['sanitize'], $column_value );
					}

					$data[]             = $column_value;
					$row_placeholders[] = $column['prepare_symbol'];
				}

				$placeholders[] = '(' . implode( ', ', $row_placeholders ) . ')';
			}

			$sql = sprintf(
				'INSERT INTO %s (`%s`) VALUES %s',
				$table_name,
				implode( '`, `', $columns ),
				implode( ', ', $placeholders )
			);

			$prepared = $wpdb->prepare( $sql, $data ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$result   = $wpdb->query( $prepared ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			if ( 0 === $result || false === $result ) {

				return new \WP_Error( 'insert_failed', __( 'Failed to insert records.', 'gf-todoist' ) );
			}
		}

		return true;
	}

	/**
	 * Batch update records in the table.
	 *
	 * @param array $records Array of records to update.
	 * @return bool|\WP_Error True on success, WP_Error on failure.
	 */
	protected static function batch_update( $records ) {
		global $wpdb;

		if ( empty( $records ) ) {
			return new \WP_Error( 'no_records', __( 'No records to update.', 'updatebee' ) );
		}

		$table_name = static::get_table_name();
		$columns    = array_keys( static::$columns );
		$columns    = array_diff( $columns, array( 'id' ) );
		$chunks     = array_chunk( $records, 50 ); // Process in chunks of 50 records.

		foreach ( $chunks as $chunk ) {
			$cases = array();
			$data  = array();
			$ids   = array();

			foreach ( $chunk as $record ) {
				if ( ! isset( $record['id'] ) ) {
					continue;
				}

				$ids[] = absint( $record['id'] );
				foreach ( $columns as $column_name ) {
					$column = static::$columns[ $column_name ];

					// If column is mapped to a different column, use that instead.
					if ( isset( $column['mapped_column'] ) ) {
						$column_name = $column['mapped_column'];
					}

					// Get the column value from the record.
					$column_value = isset( $record[ $column_name ] ) ? $record[ $column_name ] : null;

					// Apply sanitization if defined.
					if ( isset( $column['sanitize'] ) && is_callable( $column['sanitize'] ) ) {
						$column_value = call_user_func( $column['sanitize'], $column_value );
					}

					$data[]                  = $column_value;
					$cases[ $column_name ][] = sprintf( 'WHEN id = %d THEN %s', $record['id'], $column['prepare_symbol'] );
				}
			}

			if ( empty( $ids ) ) {
				continue;
			}

			// Construct the CASE statements for each column.
			$set_clauses = array();
			foreach ( $cases as $column => $when_clauses ) {
				$set_clauses[] = sprintf(
					'`%s` = (CASE %s END)',
					$column,
					implode( ' ', $when_clauses )
				);
			}

			$sql = sprintf(
				'UPDATE %s SET %s WHERE id IN (%s)',
				$table_name,
				implode( ', ', $set_clauses ),
				implode( ',', $ids )
			);

			$prepared = $wpdb->prepare( $sql, $data ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$result   = $wpdb->query( $prepared ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			if ( false === $result ) {
				return new \WP_Error( 'update_failed', __( 'Failed to update records.', 'gf-todoist' ) );
			}
		}

		return true;
	}

	/**
	 * Batch delete records from the table.
	 *
	 * @param array $records Array of records to delete.
	 * @return bool|\WP_Error True on success, WP_Error on failure.
	 */
	protected static function batch_delete( $records ) {
		global $wpdb;

		if ( empty( $records ) ) {
			return new \WP_Error( 'no_records', __( 'No records to delete.', 'updatebee' ) );
		}

		$table_name = static::get_table_name();
		$chunks     = array_chunk( $records, 50 ); // Process in chunks of 50 records.

		foreach ( $chunks as $chunk ) {
			$ids = array_map(
				function ( $record ) {
					return absint( $record['id'] );
				},
				array_filter(
					$chunk,
					function ( $record ) {
						return isset( $record['id'] );
					}
				)
			);

			if ( empty( $ids ) ) {
				continue;
			}

			// Create placeholders for the IDs.
			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

			$sql    = $wpdb->prepare( "DELETE FROM {$table_name} WHERE id IN ($placeholders)", $ids ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$result = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			if ( false === $result ) {
				return new \WP_Error( 'delete_failed', __( 'Failed to delete records.', 'updatebee' ) );
			}
		}

		return true;
	}

	/**
	 * Get a project by a specific column value.
	 *
	 * @param string $column Column name.
	 * @param mixed  $value  Value to search for.
	 * @return array|false Project data or false if not found.
	 */
	protected static function get_by( $column, $value ) {
		global $wpdb;

		// Verify column exists.
		if ( ! isset( static::$columns[ $column ] ) ) {
			return false;
		}

		$table_name = static::get_table_name();
		$column_def = static::$columns[ $column ];

		// Sanitize the value using the column's sanitize callback.
		if ( is_callable( $column_def['sanitize'] ) ) {
			$value = call_user_func( $column_def['sanitize'], $value );
		}

		// Prepare the query.
		$query = $wpdb->prepare(
			"SELECT * FROM {$table_name} WHERE {$column} = {$column_def['prepare_symbol']} LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$value
		);

		$result = $wpdb->get_row( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return $result ? $result : false;
	}

	/**
	 * Drop all the tables.
	 */
	public static function drop_tables() {
		foreach ( self::$tables as $table ) {
			$table::drop_table();
		}
	}

	/**
	 * Get all records from the table.
	 *
	 * @return array Array of records.
	 */
	protected static function get_all() {
		global $wpdb;

		$table_name = self::get_table_name();
		$results    = $wpdb->get_results( "SELECT * FROM {$table_name}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( empty( $results ) || is_wp_error( $results ) ) {
			return array();
		}

		return $results;
	}
}
