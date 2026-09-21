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
 * Class for Todoist collaborators table.
 *
 * @package GF_Todoist
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
	protected static $table_version = '1.0.1';

	/**
	 * Table columns and their schema.
	 *
	 * @var array
	 */
	protected static $columns = array(
		'id'         => array(
			'name'           => 'id',
			'schema'         => 'INT(11) NOT NULL AUTO_INCREMENT',
			'sanitize'       => 'absint',
			'validate'       => 'is_int',
			'prepare_symbol' => '%d',
			'primary_key'    => true,
			'mapped_column'  => null,
		),
		'user_id'    => array(
			'name'           => 'user_id',
			'schema'         => 'VARCHAR(255) NOT NULL',
			'sanitize'       => 'sanitize_text_field',
			'validate'       => 'is_string',
			'prepare_symbol' => '%s',
			'index'          => true,
			'mapped_column'  => 'id',
		),
		'plugin_slug' => array(
			'name'           => 'plugin_slug',
			'schema'         => 'VARCHAR(255) NOT NULL',
			'sanitize'       => 'sanitize_title',
			'validate'       => 'is_string',
			'prepare_symbol' => '%s',
			'index'          => true,
			'mapped_column'  => 'id',
		),
		'notes' => array(
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
	 * Get notes for a specific plugin.
	 *
	 * @param string $plugin_slug The plugin slug.
	 * @return string plugin notes or empty string if none found.
	 */
	public function get_plugin_notes( $plugin_slug ) {
		global $wpdb;
		$query = 'SELECT * FROM ' . parent::get_table_name() . ' WHERE plugin_slug = %s';
		$query = $wpdb->prepare( $query, sanitize_title( $plugin_slug ) );
		$notes = $wpdb->get_results( $query, ARRAY_A );
		if ( is_wp_error( $notes ) || empty( $notes ) ) {
			return '';
		}
		return $notes[0]['notes'];
	}

	/**
	 * Get a plugin by its slug.
	 *
	 * @param string $plugin_slug The plugin to retrieve by slug.
	 * @return array|null The plugin or null if not found.
	 */
	public static function get_plugin_by_slug( string $plugin_slug ) {
		return self::get_by( 'plugin_slug', sanitize_title( $plugin_slug ) );
	}
}
