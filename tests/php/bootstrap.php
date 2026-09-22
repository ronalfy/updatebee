<?php
/**
 * PHPUnit bootstrap.
 *
 * @package updatebee
 */

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

if ( ! defined( 'UPDATEBEE_FILE' ) ) {
	define( 'UPDATEBEE_FILE', dirname( __DIR__, 2 ) . '/updatebee.php' );
}

if ( ! defined( 'UPDATEBEE_VERSION' ) ) {
	define( 'UPDATEBEE_VERSION', '1.0.0' );
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}
