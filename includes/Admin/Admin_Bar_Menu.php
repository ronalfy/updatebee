<?php
/**
 * Toolbar shortcut menu for UpdateBee screens.
 *
 * @package updatebee
 */

namespace UpdateBee\Admin;

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
		$white     = 'M2864.67,2149.02C2794.27,2059.26 2688.59,1999.3 2576.46,1980.84C2519.63,1971.86 2461.48,1964.68 2404.18,1973.06C2435.3,1926.62 2454.25,1871.85 2458.23,1816.05C2461.91,1770.27 2450.93,1724.48 2434.58,1682.02C2493.09,1613.25 2574.58,1558.78 2666.51,1551.54C2700.71,1546.54 2734.86,1553.47 2768.88,1557.03C2802.72,1560.17 2830.52,1521.08 2818.58,1489.9C2811.46,1468.72 2789.81,1457.14 2768.94,1454.13C2667.6,1434.1 2559.56,1458.77 2473.37,1514.45C2438.02,1536.46 2407.98,1565.48 2377.82,1593.83C2358.88,1568.92 2333.97,1549.13 2308.09,1531.94C2334.51,1490.32 2362.56,1448.46 2374.86,1400.08C2399.66,1323.29 2395.01,1238.9 2365.51,1164.11C2353.75,1134.13 2340.84,1095.82 2305.25,1087.86C2269.42,1076.1 2226.6,1116.21 2243.55,1152.34C2273.22,1205.48 2293.55,1267.25 2283.42,1328.66C2276.96,1388.2 2247.71,1441.94 2214.23,1490.5C2103.06,1461.55 1979.76,1501 1902.43,1585.14C1887.35,1464.56 1841.15,1344.28 1754.47,1257.06C1674.24,1170.26 1559.57,1115.73 1441.64,1108.55C1341.45,1105.05 1239.03,1139.62 1165.5,1208.68C1081.71,1283.84 1033.64,1396.58 1036.77,1509.02C1041,1613.86 1082.98,1718.15 1157.59,1792.71C1234.99,1873.41 1344.65,1917.87 1454.73,1929.03C1450.99,1934.34 1447.13,1939.59 1442.55,1944.05C1397.43,1984.28 1362.63,2034.53 1330.41,2085.321C1271.54,2179.72 1242.41,2291.01 1241.32,2401.88C1241.74,2483.13 1262.85,2562.58 1292.83,2637.61C1257.24,2701.01 1213.51,2759.34 1170.5,2817.79C1159.46,2833.84 1146.98,2851.57 1149.63,2872.14C1153.49,2902.72 1193.12,2922.57 1219,2903.45C1284.39,2856.16 1344.83,2801.81 1412.27,2757.29C1472.35,2779.85 1533.94,2801.32 1598.84,2803.38C1722.8,2807.05 1846.76,2767.54 1948.4,2697.03C2030.68,2640.03 2095.76,2561.19 2145.53,2474.99C2192.15,2610.65 2294.34,2732.98 2432.71,2780.33C2539.06,2818.88 2661.75,2809.59 2760.01,2753.01C2856.1,2701.19 2925.53,2605.1 2948.69,2498.82C2977.41,2377.39 2942.24,2245.47 2864.67,2149.02Z';
		$transform = 'matrix(0.246624,0,0,0.246624,-255.656793,-267.777)';

		return '<span class="ab-icon" aria-hidden="true">'
			. '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 475 451" focusable="false">'
			. '<g transform="' . $transform . '">'
			. '<path fill="currentColor" d="' . $white . '"/>'
			. '</g>'
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
				padding: 6px 0;
				/* The mobile toolbar sets a dashicons font shorthand with !important, which resets line-height. */
				line-height: 0 !important;
			}
			#wpadminbar #wp-admin-bar-updatebee .ab-icon svg {
				display: block;
				width: 20px;
				height: 20px;
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
