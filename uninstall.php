<?php
/**
 * Remove plugin data when the plugin is deleted from the Plugins screen.
 *
 * @package updatebee
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$updatebee_autoload = __DIR__ . '/vendor/autoload.php';

if ( ! is_readable( $updatebee_autoload ) ) {
	return;
}

require_once $updatebee_autoload;

UpdateBee\Uninstall::clear_data();
