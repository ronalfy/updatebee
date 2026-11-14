<?php
/**
 * Toolbar shortcut menu for UpdateBee screens.
 *
 * @package updatebee
 */

namespace UpdateBee\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use UpdateBee\Functions;
use UpdateBee\Options;
use WP_Admin_Bar;

/**
 * Add a top-level admin bar menu when the Advanced setting is enabled.
 */
class Admin_Bar_Menu {

	/**
	 * Option key for the master toolbar toggle.
	 */
	public const OPTION_ENABLED = 'admin_bar_enabled';

	/**
	 * Option key for showing the menu in wp-admin.
	 */
	public const OPTION_SHOW_ADMIN = 'admin_bar_show_admin';

	/**
	 * Option key for showing the menu on the frontend.
	 */
	public const OPTION_SHOW_FRONTEND = 'admin_bar_show_frontend';

	/**
	 * Class runner.
	 */
	public function run(): void {
		add_action( 'admin_bar_menu', array( $this, 'add_menu_items' ), 80 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_icon_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_icon_styles' ) );
	}

	/**
	 * Whether the shortcut menu should render for this request.
	 *
	 * Does not force the toolbar itself to display.
	 *
	 * @return bool True when the menu should be added.
	 */
	public static function should_display(): bool {
		if ( ! Functions::can_access_options() ) {
			return false;
		}

		if ( ! self::is_enabled() ) {
			return false;
		}

		if ( is_admin() ) {
			return self::show_in_admin();
		}

		return self::show_on_frontend();
	}

	/**
	 * Inject the shortcut menu.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 */
	public function add_menu_items( WP_Admin_Bar $wp_admin_bar ): void {
		if ( ! self::should_display() ) {
			return;
		}

		$label = Functions::get_plugin_menu_name();

		// Keep the SVG outside sprintf(). Percent signs in attributes are format specifiers.
		$title = self::get_icon_markup() . sprintf(
			'<span class="ab-label">%s</span>',
			esc_html( $label )
		);

		$wp_admin_bar->add_node(
			array(
				'id'    => 'updatebee',
				'title' => $title,
				'href'  => Functions::get_settings_url( 'dashboard' ),
				'meta'  => array(
					'title' => $label,
				),
			)
		);

		foreach ( self::get_menu_items() as $item ) {
			$wp_admin_bar->add_node( $item );
		}
	}

	/**
	 * Child links, in toolbar order.
	 *
	 * @return array<int, array{id: string, parent: string, title: string, href: string}> Admin bar nodes.
	 */
	public static function get_menu_items(): array {
		$items = array(
			array(
				'id'    => 'updatebee-settings',
				'tab'   => 'dashboard',
				'title' => _x( 'Settings', 'Tab label as Settings', 'updatebee' ),
			),
			array(
				'id'    => 'updatebee-plugins',
				'tab'   => 'plugins',
				'title' => _x( 'Plugins', 'Tab label as Plugins', 'updatebee' ),
			),
			array(
				'id'    => 'updatebee-themes',
				'tab'   => 'themes',
				'title' => _x( 'Themes', 'Tab label as Themes', 'updatebee' ),
			),
			array(
				'id'    => 'updatebee-logs',
				'tab'   => 'logs',
				'title' => _x( 'Logs', 'Tab label as Logs', 'updatebee' ),
			),
			array(
				'id'    => 'updatebee-tools',
				'tab'   => 'tools',
				'title' => _x( 'Tools', 'Tab label as Tools', 'updatebee' ),
			),
			array(
				'id'    => 'updatebee-emails',
				'tab'   => 'emails',
				'title' => _x( 'Emails', 'Tab label as Emails', 'updatebee' ),
			),
			array(
				'id'    => 'updatebee-advanced',
				'tab'   => 'advanced',
				'title' => _x( 'Advanced', 'Tab label as Advanced', 'updatebee' ),
			),
		);

		$nodes = array();
		foreach ( $items as $item ) {
			$nodes[] = array(
				'id'     => $item['id'],
				'parent' => 'updatebee',
				'title'  => $item['title'],
				'href'   => Functions::get_settings_url( $item['tab'] ),
			);
		}

		return $nodes;
	}

	/**
	 * Whether the master toggle is on.
	 *
	 * @return bool True when the menu is enabled.
	 */
	private static function is_enabled(): bool {
		return (bool) Options::get_options( self::OPTION_ENABLED );
	}

	/**
	 * Whether the menu is allowed in wp-admin.
	 *
	 * @return bool True when the admin location is on.
	 */
	private static function show_in_admin(): bool {
		return (bool) Options::get_options( self::OPTION_SHOW_ADMIN );
	}

	/**
	 * Whether the menu is allowed on the frontend.
	 *
	 * @return bool True when the frontend location is on.
	 */
	private static function show_on_frontend(): bool {
		return (bool) Options::get_options( self::OPTION_SHOW_FRONTEND );
	}

	/**
	 * Add toolbar icon CSS when this menu is showing.
	 *
	 * Runs on the front end and in wp-admin. Rules attach to the core admin-bar
	 * stylesheet so the settings-screen bundle stays unloaded.
	 */
	public function enqueue_icon_styles(): void {
		if ( ! is_admin_bar_showing() || ! self::should_display() ) {
			return;
		}

		wp_add_inline_style( 'admin-bar', self::get_icon_css() );
	}

	/**
	 * Static bee icon for the toolbar parent item.
	 *
	 * Not passed through sprintf(); a "%" in the markup is not a format specifier.
	 * The SVG is developer markup and is printed as HTML.
	 *
	 * @return string Icon HTML.
	 */
	private static function get_icon_markup(): string {

		$white = 'M 12 0 L 12 2.25 C 6.503906 2.761719 2.1875 7.371094 2.1875 13 C 2.1875 15.933594 3.351563 18.582031 5.25 20.53125 L 7.96875 18.09375 C 6.660156 16.792969 5.84375 14.988281 5.84375 13 C 5.84375 9.390625 8.523438 6.398438 12 5.90625 L 12 8 L 18.40625 4 Z M 20.75 5.46875 L 18.0625 7.90625 C 19.371094 9.207031 20.15625 11.015625 20.15625 13 C 20.15625 16.613281 17.480469 19.574219 14 20.0625 L 14 18 L 7.59375 22 L 14 26 L 14 23.75 C 19.496094 23.238281 23.8125 18.628906 23.8125 13 C 23.8125 10.066406 22.648438 7.417969 20.75 5.46875 Z';

		return '<span class="ab-icon" aria-hidden="true">'
			. '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 26 26" focusable="false">'
			. '<path fill="currentColor" d="' . esc_attr( $white ) . '"/>'
			. '</svg>'
			. '</span>';
	}

	/**
	 * CSS that fits the bee in the toolbar icon slot.
	 *
	 * @return string CSS rules.
	 */
	private static function get_icon_css(): string {
		return '
			#wpadminbar #wp-admin-bar-updatebee .ab-icon {
				width: 20px;
				height: 20px;
				padding: 2px 0;
				/* The mobile toolbar sets a dashicons font shorthand with !important, which resets line-height. */
				line-height: 0 !important;
			}
			#wpadminbar #wp-admin-bar-updatebee .ab-icon svg {
				display: block;
				width: 20px;
				height: 20px;
				padding: 2px 0;
			}
			@media screen and (max-width: 782px) {
				#wpadminbar #wp-admin-bar-updatebee .ab-icon {
					width: 20px;
					height: 20px;
					margin: 0;
					padding: 13px 16px;
				}
			}
		';
	}
}
