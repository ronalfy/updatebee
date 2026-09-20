<?php
/**
 * Settings page chrome and tabs.
 *
 * @package updatebee
 */

namespace UpdateBee\Admin;

use UpdateBee\Functions;

/**
 * Class Settings_Page.
 */
class Settings_Page {

	/**
	 * Registers and outputs placeholder for settings.
	 *
	 * @since 1.0.0
	 */
	public static function settings_page(): void {
		?>
		<div class="updatebee-wrap updatebee-settings-header">
			<?php
			self::get_settings_header();
			?>
		</div>
		<div class="updatebee-wrap updatebee-settings-tabs">
			<?php
			self::get_settings_tabs();
			?>
		</div>
		<?php
		self::get_settings_footer();
	}

	/**
	 * Output the settings header.
	 */
	public static function get_settings_header(): void {
		?>
		<header class="updatebee-settings-header">
			<h1 class="updatebee-settings-header__title">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=updatebee' ) ); ?>">
					<img src="<?php echo esc_url( Functions::get_plugin_url( '/assets/logo.png' ) ); ?>" alt="<?php echo esc_attr( Functions::get_plugin_name() ); ?>" />
				</a>
			</h1>
		</header>
		<?php
	}

	/**
	 * Output the top-level admin tabs.
	 */
	public static function get_settings_tabs(): void {
		$tabs = array();

		/**
		 * Filter the output of the tab output.
		 *
		 * Potentially modify or add your own tabs.
		 *
		 * @since 1.0.0
		 *
		 * @param array<int, array<string, string>> $tabs Associative array of tabs.
		 */
		$tabs = apply_filters( 'updatebee_admin_tabs', $tabs );

		if ( ! is_array( $tabs ) || empty( $tabs ) ) {
			return;
		}

		$tab_html   = '<nav class="nav-tab-wrapper">';
		$tabs_count = count( $tabs );
		$active_tab = Functions::get_admin_tab();
		if ( null === $active_tab ) {
			$active_tab = 'dashboard';
		}

		$is_tab_match = false;
		foreach ( $tabs as $tab ) {
			if ( ! is_array( $tab ) ) {
				continue;
			}
			$tab_get = isset( $tab['get'] ) ? (string) $tab['get'] : '';
			if ( $active_tab === $tab_get ) {
				$is_tab_match = true;
			}
		}

		if ( ! $is_tab_match ) {
			$active_tab = 'dashboard';
		}

		$do_action = false;
		foreach ( $tabs as $tab ) {
			if ( ! is_array( $tab ) ) {
				continue;
			}

			$classes = array( 'nav-tab' );
			$tab_get = isset( $tab['get'] ) ? (string) $tab['get'] : '';
			if ( $active_tab === $tab_get ) {
				$classes[] = 'nav-tab-active';
				$do_action = isset( $tab['action'] ) ? (string) $tab['action'] : false;
			} elseif ( ! $is_tab_match && 'dashboard' === $tab_get ) {
				$classes[] = 'nav-tab-active';
				$do_action = isset( $tab['action'] ) ? (string) $tab['action'] : false;
			}

			$tab_url   = isset( $tab['url'] ) ? (string) $tab['url'] : '';
			$tab_label = isset( $tab['label'] ) ? (string) $tab['label'] : '';
			$tab_html .= sprintf(
				'<a href="%s" class="%s" id="updatebee-%s"><span>%s</span></a>',
				esc_url( $tab_url ),
				esc_attr( implode( ' ', $classes ) ),
				esc_attr( $tab_get ),
				esc_html( $tab_label )
			);
		}
		$tab_html .= '</nav>';

		if ( $tabs_count > 0 ) {
			echo wp_kses( $tab_html, Functions::get_kses_allowed_html( false ) );
		}

		$current_tab     = Functions::get_admin_tab();
		$current_sub_tab = Functions::get_admin_sub_tab();

		/**
		 * Filter the output of the sub-tab output.
		 *
		 * Potentially modify or add your own sub-tabs.
		 *
		 * @since 1.0.0
		 *
		 * @param array<int, array<string, string>> $sub_tabs        Associative array of tabs.
		 * @param string|null                       $current_tab     Tab.
		 * @param string|null                       $current_sub_tab Sub tab.
		 */
		$sub_tabs = apply_filters( 'updatebee_admin_sub_tabs', array(), $current_tab, $current_sub_tab );

		if ( null === $current_tab && null === $current_sub_tab ) {
			$current_tab = 'dashboard';
		}

		if ( is_array( $sub_tabs ) && ! empty( $sub_tabs ) ) {
			if ( null === $current_sub_tab ) {
				$current_sub_tab = '';
			}

			$is_tab_match      = false;
			$first_sub_tab     = current( $sub_tabs );
			$first_sub_tab_get = ( is_array( $first_sub_tab ) && isset( $first_sub_tab['get'] ) ) ? (string) $first_sub_tab['get'] : '';
			$active_tab        = $current_sub_tab;

			foreach ( $sub_tabs as $tab ) {
				if ( ! is_array( $tab ) ) {
					continue;
				}
				$tab_get = isset( $tab['get'] ) ? (string) $tab['get'] : '';
				if ( $active_tab === $tab_get ) {
					$is_tab_match = true;
				}
			}

			if ( ! $is_tab_match ) {
				$active_tab = $first_sub_tab_get;
			}

			$sub_tab_html_array = array();
			$do_subtab_action   = false;
			foreach ( $sub_tabs as $sub_tab ) {
				if ( ! is_array( $sub_tab ) ) {
					continue;
				}

				$classes = array( 'updatebee-sub-tab' );
				$tab_get = isset( $sub_tab['get'] ) ? (string) $sub_tab['get'] : '';
				if ( $active_tab === $tab_get ) {
					$classes[]        = 'updatebee-sub-tab-active';
					$do_subtab_action = true;
					$current_sub_tab  = $tab_get;
				} elseif ( ! $is_tab_match && $first_sub_tab_get === $tab_get ) {
					$classes[]        = 'updatebee-sub-tab-active';
					$do_subtab_action = true;
					$current_sub_tab  = $first_sub_tab_get;
				}

				$tab_url = isset( $sub_tab['url'] ) ? (string) $sub_tab['url'] : '';
				if ( $current_sub_tab === $tab_get ) {
					$sub_tab_html_array[] = sprintf(
						'<span class="%s" id="updatebee-tab-%s">%s</span>',
						esc_attr( implode( ' ', $classes ) ),
						esc_attr( $tab_get ),
						esc_html( (string) $sub_tab['label'] )
					);
				} else {
					$sub_tab_html_array[] = sprintf(
						'<a href="%s" class="%s" id="updatebee-tab-%s">%s</a>',
						esc_url( $tab_url ),
						esc_attr( implode( ' ', $classes ) ),
						esc_attr( $tab_get ),
						esc_html( (string) $sub_tab['label'] )
					);
				}
			}

			if ( ! empty( $sub_tab_html_array ) ) {
				echo '<nav class="updatebee-sub-links">' . wp_kses_post( implode( ' | ', $sub_tab_html_array ) ) . '</nav>';
			}

			if ( $do_subtab_action && is_string( $current_tab ) && is_string( $current_sub_tab ) ) {
				/**
				 * Perform a sub tab action.
				 *
				 * @since 1.0.0
				 *
				 * @param string $current_sub_tab Sub tab.
				 */
				do_action(
					sprintf(
						'updatebee_admin_sub_tab_%s_%s',
						sanitize_title( $current_tab ),
						sanitize_title( $current_sub_tab )
					),
					$current_sub_tab
				);
			}
		}

		if ( $do_action ) {
			/**
			 * Perform a tab action.
			 *
			 * @since 1.0.0
			 *
			 * @param string      $current_tab     Tab.
			 * @param string|null $current_sub_tab Sub tab.
			 */
			do_action( $do_action, $current_tab, $current_sub_tab );
		}
	}

	/**
	 * Run script and enqueue stylesheets and stuff like that.
	 */
	public static function get_settings_footer(): void {
		// Footer hook reserved for later feature work.
	}
}
