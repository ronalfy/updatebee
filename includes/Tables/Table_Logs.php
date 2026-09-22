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
	protected static $table_version = '1.0.0';

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

		$table_name = static::get_table_name();
		$limit      = absint( apply_filters( 'updatebee_log_table_limit', 500 ) );
		if ( $limit < 1 ) {
			$limit = 500;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is generated internally.
		$sql = $wpdb->prepare(
			"SELECT * FROM {$table_name} ORDER BY id DESC LIMIT %d",
			$limit
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup.
		$results = $wpdb->get_results( $sql, ARRAY_A );

		if ( empty( $results ) || is_wp_error( $results ) ) {
			return array();
		}

		return $results;
	}
}
