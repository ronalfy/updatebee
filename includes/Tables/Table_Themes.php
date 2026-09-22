<?php
/**
 * Themes table for UpdateBee.
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
 * Class for the themes notes table.
 *
 * @package UpdateBee
 */
class Table_Themes extends Tables {

	/**
	 * Instance of the table.
	 *
	 * @var Table_Themes
	 */
	private static $instance;

	/**
	 * Returns an instance of this class.
	 *
	 * @since 1.0.0
	 *
	 * @return Table_Themes $instance
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
	protected static $table_name = 'ub_themes';

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
			'mapped_column'  => null,
		),
		'theme_slug' => array(
			'name'           => 'theme_slug',
			'schema'         => 'VARCHAR(255) NOT NULL UNIQUE',
			'sanitize'       => 'sanitize_text_field',
			'validate'       => 'is_string',
			'prepare_symbol' => '%s',
			'index'          => true,
			'mapped_column'  => null,
		),
		'notes'      => array(
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
		add_action( 'updatebee_drop_table_themes', array( self::class, 'drop_table' ) );

		// Filter the resource count.
		add_filter( 'updatebee_get_count_themes', array( self::class, 'get_count' ) );

		self::create_table();
	}

	/**
	 * Sanitize a theme stylesheet used as the notes table key.
	 *
	 * @param string $stylesheet Theme stylesheet slug.
	 * @return string Sanitized stylesheet.
	 */
	private static function sanitize_stylesheet( string $stylesheet ): string {
		return sanitize_text_field( $stylesheet );
	}

	/**
	 * Get notes for a specific theme.
	 *
	 * @param string $stylesheet Theme stylesheet slug.
	 * @return string Theme notes or empty string if none found.
	 */
	public static function get_notes( string $stylesheet ): string {
		$row = self::get_theme_by_slug( $stylesheet );
		if ( ! is_array( $row ) || ! isset( $row['notes'] ) ) {
			return '';
		}

		return (string) $row['notes'];
	}

	/**
	 * Get all theme notes keyed by stylesheet.
	 *
	 * @return array<string, string> Stylesheet => notes.
	 */
	public static function get_all_notes(): array {
		global $wpdb;

		$table_name = static::get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table lookup.
		$results = $wpdb->get_results( "SELECT theme_slug, notes FROM {$table_name}", ARRAY_A );

		if ( empty( $results ) || is_wp_error( $results ) ) {
			return array();
		}

		$notes = array();
		foreach ( $results as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['theme_slug'] ) ) {
				continue;
			}

			$stylesheet = (string) $row['theme_slug'];
			if ( '' === $stylesheet ) {
				continue;
			}

			$notes[ $stylesheet ] = isset( $row['notes'] ) ? (string) $row['notes'] : '';
		}

		return $notes;
	}

	/**
	 * Save notes for a theme. Empty notes remove the row.
	 *
	 * @param string $stylesheet Theme stylesheet slug.
	 * @param string $notes      Note text.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function save_notes( string $stylesheet, string $notes ) {
		global $wpdb;

		$stylesheet = self::sanitize_stylesheet( $stylesheet );
		if ( '' === $stylesheet ) {
			return new \WP_Error( 'invalid_theme', __( 'Invalid theme stylesheet.', 'updatebee' ) );
		}

		$notes = sanitize_textarea_field( $notes );
		if ( '' === trim( $notes ) ) {
			return self::delete_notes( $stylesheet );
		}

		$user_id    = (string) get_current_user_id();
		$existing   = self::get_theme_by_slug( $stylesheet );
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
					'theme_slug' => $stylesheet,
				),
				array( '%s', '%s' ),
				array( '%s' )
			);

			if ( false === $result ) {
				return new \WP_Error( 'update_failed', __( 'Failed to update theme notes.', 'updatebee' ) );
			}

			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table insert.
		$result = $wpdb->insert(
			$table_name,
			array(
				'user_id'    => $user_id,
				'theme_slug' => $stylesheet,
				'notes'      => $notes,
			),
			array( '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return new \WP_Error( 'insert_failed', __( 'Failed to save theme notes.', 'updatebee' ) );
		}

		return true;
	}

	/**
	 * Delete notes for a theme.
	 *
	 * @param string $stylesheet Theme stylesheet slug.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function delete_notes( string $stylesheet ) {
		global $wpdb;

		$stylesheet = self::sanitize_stylesheet( $stylesheet );
		if ( '' === $stylesheet ) {
			return new \WP_Error( 'invalid_theme', __( 'Invalid theme stylesheet.', 'updatebee' ) );
		}

		$table_name = static::get_table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete.
		$result = $wpdb->delete(
			$table_name,
			array(
				'theme_slug' => $stylesheet,
			),
			array( '%s' )
		);

		if ( false === $result ) {
			return new \WP_Error( 'delete_failed', __( 'Failed to delete theme notes.', 'updatebee' ) );
		}

		return true;
	}

	/**
	 * Get a theme by its stylesheet key.
	 *
	 * @param string $theme_slug Theme stylesheet slug.
	 * @return array|false Theme row or false if not found.
	 */
	public static function get_theme_by_slug( string $theme_slug ) {
		return self::get_by( 'theme_slug', $theme_slug );
	}
}
