<?php
/**
 * Plugin Name:       UpdateBee
 * Plugin URI:        https://mediaron.com/updatebee/
 * Description:       Take complete control over your automatic updates.
 * Version:           1.0.0
 * Requires at least: 7.0
 * Tested up to:      7.1
 * Requires PHP:      8.0
 * Author:            MediaRon LLC
 * Author URI:        https://mediaron.com
 * License:           GPL v3 or later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       updatebee
 * Domain Path:       /languages
 * Network:           true
 *
 * @package updatebee
 */

defined( 'ABSPATH' ) || exit;

define( 'UPDATEBEE_FILE', __FILE__ );
define( 'UPDATEBEE_VERSION', '1.0.0' );

$updatebee_autoload = __DIR__ . '/vendor/autoload.php';

if ( ! file_exists( $updatebee_autoload ) ) {
	add_action( 'admin_notices', 'updatebee_missing_autoload_notice' );
	add_action( 'network_admin_notices', 'updatebee_missing_autoload_notice' );
	return;
}

require_once $updatebee_autoload;

UpdateBee\Plugin::get_instance();

/**
 * Show an admin notice when Composer dependencies are missing.
 */
function updatebee_missing_autoload_notice(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html__( 'UpdateBee is missing its Composer dependencies. Run composer install in the plugin directory.', 'updatebee' )
	);
}
