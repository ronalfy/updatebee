<?php
/**
 * Logs table for UpdateBee.
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
 * Class for the update logs table.
 *
 * @package UpdateBee
 */
class Table_Logs extends Tables {

	/**
	 * Instance of the table.
	 *
	 * @var Table_Logs
	 */
	private static $instance;

	/**
	 * Returns an instance of this class.
	 *
	 * @since 1.0.0
	 *
	 * @return Table_Logs $instance
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
	protected static $table_name = 'ub_logs';

	/**
	 * Table Version.
	 *
	 * @var string
	 */
	protected static $table_version = '1.1.0';

	/**
	 * Table columns and their schema.
	 *
	 * @var array
	 */
	protected static $columns = array(
		'id'            => array(
			'name'           => 'id',
			'schema'         => 'BIGINT(20) NOT NULL AUTO_INCREMENT',
			'sanitize'       => 'absint',
			'validate'       => 'is_int',
			'prepare_symbol' => '%d',
			'primary_key'    => true,
			'mapped_column'  => null,
		),
		'name'          => array(
			'name'           => 'name',
			'schema'         => "VARCHAR(255) NOT NULL DEFAULT ''",
			'sanitize'       => 'sanitize_text_field',
			'validate'       => 'is_string',
			'prepare_symbol' => '%s',
			'mapped_column'  => null,
		),
		'slug'          => array(
			'name'           => 'slug',
			'schema'         => "VARCHAR(255) NOT NULL DEFAULT ''",
			'sanitize'       => 'sanitize_text_field',
			'validate'       => 'is_string',
			'prepare_symbol' => '%s',
			'mapped_column'  => null,
		),
		'user_id'       => array(
			'name'           => 'user_id',
			'schema'         => 'BIGINT(20) NOT NULL DEFAULT 0',
			'sanitize'       => 'absint',
			'validate'       => 'is_int',
			'prepare_symbol' => '%d',
			'mapped_column'  => null,
		),
		'from_version'  => array(
			'name'           => 'from_version',
			'schema'         => "VARCHAR(255) NOT NULL DEFAULT ''",
			'sanitize'       => 'sanitize_text_field',
			'validate'       => 'is_string',
			'prepare_symbol' => '%s',
			'mapped_column'  => null,
		),
		'to_version'    => array(
			'name'           => 'to_version',
			'schema'         => "VARCHAR(255) NOT NULL DEFAULT ''",
			'sanitize'       => 'sanitize_text_field',
			'validate'       => 'is_string',
			'prepare_symbol' => '%s',
			'mapped_column'  => null,
		),
		'update_offer'  => array(
			'name'           => 'update_offer',
			'schema'         => "VARCHAR(255) NOT NULL DEFAULT ''",
			'sanitize'       => 'sanitize_text_field',
			'validate'       => 'is_string',
			'prepare_symbol' => '%s',
			'mapped_column'  => null,
		),
		'update_type'   => array(
			'name'           => 'update_type',
			'schema'         => "VARCHAR(255) NOT NULL DEFAULT ''",
			'sanitize'       => 'sanitize_text_field',
			'validate'       => 'is_string',
			'prepare_symbol' => '%s',
			'mapped_column'  => null,
		),
		'update_status' => array(
			'name'           => 'update_status',
			'schema'         => "VARCHAR(255) NOT NULL DEFAULT ''",
			'sanitize'       => 'sanitize_text_field',
			'validate'       => 'is_string',
			'prepare_symbol' => '%s',
			'mapped_column'  => null,
		),
		'raw'           => array(
			'name'           => 'raw',
			'schema'         => 'TEXT NOT NULL',
			'sanitize'       => 'sanitize_textarea_field',
			'validate'       => 'is_string',
			'prepare_symbol' => '%s',
			'mapped_column'  => null,
		),
		'stacktrace'    => array(
			'name'           => 'stacktrace',
			'schema'         => 'TEXT NULL',
			'sanitize'       => 'sanitize_textarea_field',
			'validate'       => 'is_string',
			'prepare_symbol' => '%s',
			'mapped_column'  => null,
		),
		'date'          => array(
			'name'           => 'date',
			'schema'         => 'DATETIME NOT NULL',
			'sanitize'       => 'sanitize_text_field',
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
		add_action( 'updatebee_drop_table_logs', array( self::class, 'drop_table' ) );

		// Filter the resource count.
		add_filter( 'updatebee_get_count_logs', array( self::class, 'get_count' ) );

		self::create_table();
	}

	/**
	 * Get recent log rows, newest first.
	 *
	 * @return array<int, array<string, mixed>> Log rows.
	 */
	public static function get_logs(): array {
		global $wpdb;

		$table_name = \esc_sql( static::get_table_name() );
		$limit      = absint( apply_filters( 'updatebee_log_table_limit', 500 ) );
		if ( $limit < 1 ) {
			$limit = 500;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name escaped with esc_sql.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table_name}` ORDER BY id DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

		if ( empty( $results ) || is_wp_error( $results ) ) {
			return array();
		}

		return $results;
	}

	/**
	 * Get paginated, filtered, and sorted log rows.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return array<string, mixed> Logs and total count.
	 */
	public static function get_logs_paginated( array $args ): array {
		global $wpdb;

		$defaults = array(
			'page'           => 1,
			'per_page'       => 20,
			'sort_field'     => 'id',
			'sort_direction' => 'desc',
			'search'         => '',
			'filters'        => array(),
		);

		$args = wp_parse_args( $args, $defaults );

		$page     = max( 1, absint( $args['page'] ) );
		$per_page = max( 1, absint( $args['per_page'] ) );
		$offset   = ( $page - 1 ) * $per_page;

		$allowed_sort_fields = array( 'id', 'name', 'slug', 'user_id', 'from_version', 'to_version', 'update_offer', 'update_type', 'update_status', 'date' );
		$sort_field          = in_array( $args['sort_field'], $allowed_sort_fields, true ) ? $args['sort_field'] : 'id';
		$sort_direction      = 'asc' === strtolower( $args['sort_direction'] ) ? 'ASC' : 'DESC';

		$table_name = \esc_sql( static::get_table_name() );

		$where  = array( '1=1' );
		$values = array();

		$search = is_string( $args['search'] ) ? trim( $args['search'] ) : '';
		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = "( name LIKE %s OR slug LIKE %s OR user_id IN ( SELECT ID FROM {$wpdb->users} WHERE user_nicename LIKE %s OR display_name LIKE %s ) )";
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}

		$filters        = is_array( $args['filters'] ) ? $args['filters'] : array();
		$allowed_values = array(
			'update_offer'  => array( 'automatic', 'manual' ),
			'update_type'   => array( 'core', 'plugin', 'theme', 'translation' ),
			'update_status' => array( 'success', 'failure', 'fatal', 'blocked', 'skipped', 'delayed' ),
		);

		foreach ( $allowed_values as $column => $valid ) {
			if ( isset( $filters[ $column ] ) && is_string( $filters[ $column ] ) && in_array( $filters[ $column ], $valid, true ) ) {
				$where[]  = "{$column} = %s";
				$values[] = $filters[ $column ];
			}
		}

		$where_sql = implode( ' AND ', $where );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table/column names are escaped or allow-listed; values are prepared.
		if ( ! empty( $values ) ) {
			$total = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM `{$table_name}` WHERE {$where_sql}",
					...$values
				)
			);
		} else {
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table_name}` WHERE {$where_sql}" );
		}

		$query_values   = $values;
		$query_values[] = $per_page;
		$query_values[] = $offset;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table_name}` WHERE {$where_sql} ORDER BY {$sort_field} {$sort_direction} LIMIT %d OFFSET %d",
				...$query_values
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

		if ( empty( $results ) || is_wp_error( $results ) ) {
			$results = array();
		}

		return array(
			'logs'  => $results,
			'total' => $total,
		);
	}

	/**
	 * Get a single log row by ID.
	 *
	 * @param int $id Log row ID.
	 * @return array<string, mixed>|null Log row or null when not found.
	 */
	public static function get_log_by_id( int $id ): ?array {
		global $wpdb;

		if ( $id < 1 ) {
			return null;
		}

		$table_name = \esc_sql( static::get_table_name() );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name escaped with esc_sql.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$table_name}` WHERE id = %d LIMIT 1",
				$id
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

		if ( empty( $row ) || ! is_array( $row ) ) {
			return null;
		}

		return $row;
	}

	/**
	 * Delete all log rows.
	 *
	 * @return bool True when the query succeeds.
	 */
	public static function clear_logs(): bool {
		global $wpdb;

		$table_name = \esc_sql( static::get_table_name() );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name escaped with esc_sql.
		$result = $wpdb->query( "DELETE FROM `{$table_name}`" );

		return false !== $result;
	}

	/**
	 * Delete log rows older than the given number of days.
	 *
	 * @param int $days Retention window in days.
	 * @return bool True when the query succeeds.
	 */
	public static function clear_logs_older_than( int $days ): bool {
		if ( $days < 1 ) {
			return false;
		}

		global $wpdb;

		$table_name = \esc_sql( static::get_table_name() );
		$cutoff     = wp_date( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		if ( ! is_string( $cutoff ) || '' === $cutoff ) {
			return false;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name escaped with esc_sql.
		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM `{$table_name}` WHERE date < %s",
				$cutoff
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return false !== $result;
	}

	/**
	 * Get log rows for CSV export.
	 *
	 * @param array<string, mixed> $args Export filters.
	 * @return array<int, array<string, mixed>> Log rows.
	 */
	public static function get_logs_for_export( array $args ): array {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'update_offer'  => 'all',
				'update_type'   => 'all',
				'update_status' => 'all',
				'date_from'     => '',
				'date_to'       => '',
			)
		);

		$table_name = \esc_sql( static::get_table_name() );
		$limit      = absint( apply_filters( 'updatebee_export_log_limit', 500 ) );
		if ( $limit < 1 ) {
			$limit = 500;
		}

		$where  = array( '1=1' );
		$values = array();

		$update_offer = is_string( $args['update_offer'] ) ? $args['update_offer'] : 'all';
		if ( in_array( $update_offer, array( 'automatic', 'manual' ), true ) ) {
			$where[]  = 'update_offer = %s';
			$values[] = $update_offer;
		}

		$update_type = is_string( $args['update_type'] ) ? $args['update_type'] : 'all';
		if ( in_array( $update_type, array( 'core', 'plugin', 'theme', 'translation' ), true ) ) {
			$where[]  = 'update_type = %s';
			$values[] = $update_type;
		}

		$update_status = is_string( $args['update_status'] ) ? $args['update_status'] : 'all';
		if ( in_array( $update_status, array( 'success', 'failure', 'fatal', 'blocked', 'skipped', 'delayed' ), true ) ) {
			$where[]  = 'update_status = %s';
			$values[] = $update_status;
		}

		$date_from = self::sanitize_export_date( $args['date_from'] );
		if ( '' !== $date_from ) {
			$where[]  = 'date >= %s';
			$values[] = $date_from . ' 00:00:00';
		}

		$date_to = self::sanitize_export_date( $args['date_to'] );
		if ( '' !== $date_to ) {
			$where[]  = 'date <= %s';
			$values[] = $date_to . ' 23:59:59';
		}

		$where_sql = implode( ' AND ', $where );
		$values[]  = $limit;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name escaped; WHERE fragments are allow-listed.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT name, slug, user_id, from_version, to_version, update_offer, update_type, update_status, date FROM `{$table_name}` WHERE {$where_sql} ORDER BY id ASC LIMIT %d",
				...$values
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

		if ( empty( $results ) || is_wp_error( $results ) ) {
			return array();
		}

		return $results;
	}

	/**
	 * Sanitize a Y-m-d export date.
	 *
	 * @param mixed $value Submitted date.
	 * @return string Valid Y-m-d date or empty string.
	 */
	private static function sanitize_export_date( $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$value = sanitize_text_field( $value );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return '';
		}

		$date = \DateTime::createFromFormat( 'Y-m-d', $value );
		if ( ! $date instanceof \DateTime || $date->format( 'Y-m-d' ) !== $value ) {
			return '';
		}

		return $value;
	}
}
