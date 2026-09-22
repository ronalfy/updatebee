<?php
/**
 * Core update filters.
 *
 * @package updatebee
 */

namespace UpdateBee\Updates;

use UpdateBee\Functions;
use UpdateBee\Options;

/**
 * Core automatic update allow/deny filters.
 */
class Core {

	/**
	 * Core instance.
	 *
	 * @var Core|null
	 */
	private static $instance = null;

	/**
	 * Return a class instance.
	 *
	 * @return Core
	 */
	public static function get_instance(): Core {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Class constructor.
	 */
	private function __construct() {
		$options = Options::get_options();
		$major   = isset( $options['major_auto_updates'] ) ? $options['major_auto_updates'] : 'off';
		$minor   = isset( $options['minor_auto_updates'] ) ? $options['minor_auto_updates'] : 'on';

		if ( 'on' === $major ) {
			add_filter( 'allow_major_auto_core_updates', '__return_true', Functions::get_highest_priority( 10 ) );
		} else {
			add_filter( 'allow_major_auto_core_updates', '__return_false', Functions::get_highest_priority( 10 ) );
		}

		if ( 'on' === $minor ) {
			add_filter( 'allow_minor_auto_core_updates', '__return_true', Functions::get_highest_priority( 10 ) );
		} else {
			add_filter( 'allow_minor_auto_core_updates', '__return_false', Functions::get_highest_priority( 10 ) );
		}
	}
}
