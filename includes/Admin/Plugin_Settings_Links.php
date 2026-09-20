<?php
/**
 * Register the plugin settings links.
 *
 * @package updatebee
 */

namespace UpdateBee\Admin;

use UpdateBee\Functions;

/**
 * Add settings links to the plugin menu.
 */
class Plugin_Settings_Links {

	/**
	 * Class runner.
	 */
	public function run(): void {
		if ( ! Functions::is_multisite() ) {
			add_filter(
				'plugin_action_links_' . Functions::get_plugin_file(),
				array( $this, 'add_settings_link' )
			);
		} elseif ( Functions::is_multisite( true ) ) {
			add_filter(
				'network_admin_plugin_action_links_' . Functions::get_plugin_file(),
				array( $this, 'add_settings_link' )
			);
		}
	}

	/**
	 * Add a settings link to the plugin's options.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, string> $links Array of plugin options.
	 * @return array<string, string> Array of plugin options.
	 */
	public function add_settings_link( array $links ): array {
		$can_manage = ( Functions::is_multisite() && current_user_can( 'manage_network' ) )
			|| ( ! Functions::is_multisite() && ! is_network_admin() && current_user_can( 'manage_options' ) );

		if ( ! $can_manage ) {
			return $links;
		}

		$options_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( Functions::get_settings_url() ),
			_x( 'Options', 'Plugin settings link on the plugins page', 'updatebee' )
		);
		array_unshift( $links, $options_link );

		return $links;
	}
}
