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
		<div class="updatebee-settings-header-wrapper">
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
		<header class="updatebee-settings-header updatebee-wrap">
			<h1 class="updatebee-settings-header__title">
				<a href="<?php echo esc_url( Functions::get_settings_url() ); ?>">
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
			$tab_icon  = isset( $tab['icon'] ) ? (string) $tab['icon'] : '';
			$icon_html = '';
			if ( '' !== $tab_icon ) {
				$icon_html = sprintf(
					'<svg class="updatebee-icon updatebee-icon-tab" aria-hidden="true"><use href="#%1$s" xlink:href="#%1$s"></use></svg>',
					esc_attr( $tab_icon )
				);
			}

			$tab_html .= sprintf(
				'<a href="%s" class="%s" id="updatebee-%s">%s<span>%s</span></a>',
				esc_url( $tab_url ),
				esc_attr( implode( ' ', $classes ) ),
				esc_attr( $tab_get ),
				$icon_html,
				esc_html( $tab_label )
			);
		}
		$tab_html .= '</nav>';

		if ( $tabs_count > 0 ) {
			echo '<div class="updatebee-settings-layout">';
			echo wp_kses( $tab_html, Functions::get_kses_allowed_html() );
			echo '<div class="updatebee-settings-content">';
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

		if ( $tabs_count > 0 ) {
			echo '</div>';
			echo '</div>';
		}
	}

	/**
	 * Run script and enqueue stylesheets and stuff like that.
	 */
	public static function get_settings_footer(): void {
		?>
		<svg xmlns="http://www.w3.org/2000/svg" class="updatebee-hidden">
			<defs>
				<symbol viewBox="0 0 64 64" id="icon-database">
				<path d="M46.255 11A3.75 3.75 0 0150 14.745v4.511A3.749 3.749 0 0146.255 23h-28.51A3.75 3.75 0 0114 19.255v-4.511A3.749 3.749 0 0117.745 11h28.51zm0 15.5A3.75 3.75 0 0150 30.245v4.511a3.75 3.75 0 01-3.745 3.745h-28.51A3.75 3.75 0 0114 34.755v-4.511a3.75 3.75 0 013.745-3.745h28.51zm0 15.5A3.75 3.75 0 0150 45.745v4.511A3.749 3.749 0 0146.255 54h-28.51A3.75 3.75 0 0114 50.255v-4.511A3.749 3.749 0 0117.745 42h28.51z"/>
				</symbol>
				<symbol viewBox="0 0 72 72" id="icon-email">
				<path d="M36.346 12.08c13.973 0 23.422 8.758 23.422 21.664 0 8.988-4.494 14.549-11.956 14.549-3.774 0-6.453-1.527-7.462-4.235h-.604c-1.152 2.853-3.227 4.321-6.511 4.321-5.992 0-9.939-4.868-9.939-12.157 0-7 3.717-11.581 9.306-11.581 3.14 0 5.531 1.412 6.655 4.033h.633v-.144c0-2.507 1.354-3.89 3.803-3.89 2.479 0 3.832 1.383 3.832 3.89v10.371c0 2.103.807 3.312 2.247 3.312 2.189 0 3.515-2.967 3.515-7.461 0-10.343-6.598-16.939-16.939-16.939-10.832 0-18.38 7.403-18.38 18.466 0 11.034 7.692 18.236 19.82 18.236a40.78 40.78 0 003.14-.115c.548-.029 1.037-.058 1.613-.058 2.074 0 2.968 1.037 2.968 2.593 0 1.383-.576 2.103-1.786 2.621-1.238.519-3.141.721-6.741.721-15.269 0-25.524-9.594-25.524-24.113-.003-14.231 10.195-24.084 24.888-24.084zm2.765 24.459c0-3.371-1.383-5.388-3.602-5.388-2.246 0-3.601 2.017-3.601 5.388 0 3.342 1.325 5.301 3.601 5.301 2.249 0 3.602-1.959 3.602-5.301z"/>
				</symbol>
				<symbol viewBox="0 0 72 72" id="icon-language">
				<path d="M22.191 13.74c-2.96.097-4.268.707-5.8 1.504s-3.253 2.429-4.004 3.973c-1.46 2.996-1.555 5.156-1.385 7.826l-.025 16.02c.006 1.654.557 3.595 1.341 5.126.783 1.538 2.506 3.225 3.952 4.17 1.374.898 3.178 1.43 5.033 1.489l.683.007.002 4.002c0 .956.08 2.38 1.033 3.522.744.89 1.847 1.297 3.067 1.434 1.302.145 2.697-.218 3.351-.526.671-.316 1.543-.965 2.75-1.976l7.545-6.461 10.055.027c2.253 0 4.095-.542 5.643-1.393a10.357 10.357 0 003.824-3.619c.904-1.463 1.76-3.269 1.742-5.88l.016-17.645c-.003-2.225-.243-3.422-1.09-5.395-.973-2.266-2.904-4.099-4.817-5.03-1.552-.757-2.84-1.173-5.093-1.173L22.19 13.74zM43.35 23.97a2 2 0 012 2v1h6a2 2 0 010 4h-.936c-.33 1.465-1.28 4.164-3.935 7.3l2.285 2.286a2 2 0 11-2.828 2.828l-2.26-2.26a34.817 34.817 0 01-3.186 2.488 2.002 2.002 0 01-2.785-.502 2 2 0 01.502-2.783c.99-.687 1.865-1.369 2.635-2.037l-1.908-1.906a2 2 0 112.828-2.828l1.88 1.88c1.513-1.843 2.256-3.418 2.618-4.466h-9.91a2 2 0 010-4h5v-1a2 2 0 012-2zm-16.348 1.959c.874-.037 1.545.575 1.82 1.31L33.83 41.18c.381 1.015-.03 2.192-.918 2.627-.224.11-.46.137-.687.162-.732.082-1.735-.31-2.018-1.069l-.71-1.921-5.093.025-.76 2.01c-.378 1.015-1.71 1.225-2.597.79-.887-.434-1.298-1.61-.918-2.624l5.012-13.953c.275-.736.876-1.257 1.861-1.3zm-.014 8.025l-1.06 3.022h2.158l-1.098-3.022z"/>
				</symbol>
				<symbol viewBox="0 0 64 64" id="icon-maintenance">
				<path d="M45.781 9.027c-3.2.098-7.297 1.278-9.636 3.618-3.547 3.545-4.058 8.43-1.666 12.82-.727.68-2.477 2.225-4.872 4.314L19 19.172v-1.967c0-.563-.271-1.092-.73-1.42l-4.93-3.52a1.422 1.422 0 00-1.832.153l-2.09 2.09a1.422 1.422 0 00-.152 1.832l3.52 4.93c.327.459.855.73 1.42.73h1.966l10.414 10.414c-5.915 5.146-12.798 11.109-13.79 12.1a6.15 6.15 0 000 8.69A6.132 6.132 0 0017.144 55a6.126 6.126 0 004.345-1.797C22.904 51.787 38.23 34 40.533 31.523c1.787.974 3.656 1.475 5.485 1.475 2.662 0 5.235-1.039 7.337-3.14C57.1 26.112 57.856 17.855 56 16c-.697-.697-1.51-.49-2 0l-7.182 7.088c-.714-.065-1.71-.394-2.611-1.295-.901-.901-1.23-1.898-1.295-2.611L50 12c.731-.731.501-1.499 0-2-.7-.7-2.298-1.031-4.219-.973zm-4.906 27.856l-7.404 8.77 7.447 7.445A6.445 6.445 0 0045.508 55a6.451 6.451 0 004.592-1.902 6.498 6.498 0 000-9.18l-6.623-6.621-2.602-.414z"/>
				</symbol>
				<symbol viewBox="0 0 64 64" id="icon-roller-brush">
				<path d="M15 8c-2.757 0-5 2.243-5 5v6c0 2.757 2.243 5 5 5h28c2.757 0 5-2.243 5-5v-1h1c.552 0 1 .449 1 1v6.693a.866.866 0 01-.678.842l-18.494 4.022A4.888 4.888 0 0027 35.307V37a3 3 0 00-3 3v13a3 3 0 003 3h4a3 3 0 003-3V40a3 3 0 00-3-3v-1.693c0-.402.285-.757.678-.842l18.494-4.022A4.888 4.888 0 0054 25.693V19c0-2.757-2.243-5-5-5h-1v-1c0-2.757-2.243-5-5-5H15z"/>
				</symbol>
				<symbol viewBox="0 0 64 64" id="icon-settings">
				<path d="M52.339 28.023A2 2 0 0154 29.994v4.012a2 2 0 01-1.661 1.971l-3.464.596a17.356 17.356 0 01-1.707 4.129l2.026 2.868a2 2 0 01-.219 2.568l-2.837 2.837a1.999 1.999 0 01-2.568.219l-2.868-2.025a17.397 17.397 0 01-4.129 1.707l-.596 3.464A2 2 0 0134.006 54h-4.012a2 2 0 01-1.971-1.661l-.596-3.464a17.356 17.356 0 01-4.129-1.707l-2.868 2.025a2 2 0 01-2.568-.219l-2.837-2.837a2 2 0 01-.219-2.568l2.026-2.868a17.356 17.356 0 01-1.707-4.129l-3.464-.596A1.998 1.998 0 0110 34.006v-4.012a2 2 0 011.661-1.971l3.464-.596a17.356 17.356 0 011.707-4.129l-2.026-2.868a2 2 0 01.219-2.568l2.837-2.837a2 2 0 012.568-.219l2.868 2.026a17.397 17.397 0 014.129-1.707l.596-3.464A2 2 0 0129.994 10h4.012a2 2 0 011.971 1.661l.596 3.464c1.461.395 2.846.969 4.129 1.707l2.868-2.026a2 2 0 012.568.219l2.837 2.837a2 2 0 01.219 2.568l-2.026 2.868a17.356 17.356 0 011.707 4.129l3.464.596zM32 40a8 8 0 100-16 8 8 0 000 16z"/>
				</symbol>
				<symbol viewBox="0 0 72 72" id="icon-timer">
					<path d="M42.92,37.65l2.86,2.9C49.79,44.63,52,50.04,52,55.8c0,2.87-2.32,5.2-5.17,5.2H25.17C22.32,61,20,58.67,20,55.8	c0-5.76,2.21-11.17,6.22-15.25l2.86-2.9c0.43-0.43,0.67-1.03,0.67-1.65s-0.24-1.22-0.67-1.65l-2.94-2.99	c-2.849-2.892-4.776-6.467-5.634-10.36C20.172,19.481,20,17.914,20,16.32c0-2.98,2.32-5.32,5.16-5.32h21.68	c2.84,0,5.16,2.34,5.16,5.22c0,1.635-0.174,3.235-0.514,4.78c-0.862,3.916-2.786,7.477-5.626,10.36l-2.94,2.99	c-0.43,0.43-0.67,1.03-0.67,1.65S42.49,37.22,42.92,37.65z M30.414,27.149l3.461,3.471c1.172,1.175,3.076,1.175,4.248,0l3.457-3.467	c1.364-1.385,2.43-2.997,3.173-4.757C45.034,21.736,44.545,21,43.827,21H28.162c-0.718,0-1.207,0.736-0.927,1.397	C27.978,24.15,29.046,25.761,30.414,27.149z"></path>
				</symbol>
				<symbol viewBox="0 0 64 64" id="icon-plug">
					<path d="M 39 11 C 38.48825 11 37.976438 11.195438 37.585938 11.585938 L 32 17.171875 L 27.414062 12.585938 C 26.633062 11.804937 25.366937 11.804937 24.585938 12.585938 C 23.804938 13.366937 23.804938 14.633062 24.585938 15.414062 L 25.158203 15.986328 L 16.197266 24.787109 C 14.854266 26.105109 14.111516 27.869953 14.103516 29.751953 C 14.094516 31.632953 14.822344 33.400469 16.152344 34.730469 L 18.710938 37.289062 L 18.585938 37.414062 C 17.804938 38.195063 17.804938 39.461188 18.585938 40.242188 L 19.757812 41.414062 L 11.585938 49.585938 C 10.804938 50.366938 10.804937 51.633063 11.585938 52.414062 C 11.976937 52.805062 12.488 53 13 53 C 13.512 53 14.023062 52.805062 14.414062 52.414062 L 22.585938 44.242188 L 23.757812 45.414062 C 24.538813 46.195063 25.804938 46.195062 26.585938 45.414062 L 26.710938 45.289062 L 29.082031 47.660156 C 30.442031 49.021156 32.238203 49.703125 34.033203 49.703125 C 35.807203 49.703125 37.5815 49.038078 38.9375 47.705078 L 47.988281 38.816406 L 48.585938 39.414062 C 48.976938 39.805063 49.488 40 50 40 C 50.512 40 51.023063 39.805062 51.414062 39.414062 C 52.195063 38.633062 52.195062 37.366937 51.414062 36.585938 L 46.828125 32 L 52.414062 26.414062 C 53.195063 25.633062 53.195062 24.366937 52.414062 23.585938 C 51.633062 22.804938 50.366937 22.804938 49.585938 23.585938 L 44 29.171875 L 34.828125 20 L 40.414062 14.414062 C 41.195062 13.633063 41.195062 12.366938 40.414062 11.585938 C 40.023562 11.195437 39.51175 11 39 11 z"></path>
				</symbol>
				<symbol viewBox="0 0 48 48" id="icon-wordpress">
					<path d="M 24 4 C 12.972066 4 4 12.972074 4 24 C 4 35.027926 12.972066 44 24 44 C 35.027934 44 44 35.027926 44 24 C 44 12.972074 35.027934 4 24 4 z M 24 7 C 33.406615 7 41 14.593391 41 24 C 41 33.406609 33.406615 41 24 41 C 14.593385 41 7 33.406609 7 24 C 7 14.593391 14.593385 7 24 7 z M 24 9 C 19.08 9 14.720469 11.369297 11.980469 15.029297 C 13.580469 14.959297 15.509766 14.769531 15.509766 14.769531 L 15.599609 14.769531 C 16.429609 14.769531 16.509687 16.019609 15.679688 16.099609 C 15.679687 16.099609 14.769531 16.179531 13.769531 16.269531 L 19.75 34.060547 L 23.330078 23.330078 L 20.75 16.349609 C 19.84 16.269609 19.009766 16.179688 19.009766 16.179688 C 18.169766 16.179688 18.259844 14.849609 19.089844 14.849609 L 19.169922 14.849609 C 19.169922 14.849609 21.920234 15.099609 23.490234 15.099609 C 25.240234 15.099609 27.900391 14.849609 27.900391 14.849609 L 27.980469 14.849609 C 28.820469 14.849609 28.900312 16.099688 28.070312 16.179688 C 28.070313 16.179688 27.150156 16.269609 26.160156 16.349609 L 32.060547 33.970703 L 33.720703 28.490234 C 34.550703 26.320234 34.970703 24.579922 34.970703 23.169922 C 34.970703 21.169922 34.300625 19.759687 33.640625 18.679688 C 32.810625 17.349687 32.060547 16.179609 32.060547 14.849609 C 32.060547 13.869609 32.599375 12.829062 33.359375 12.289062 C 30.799375 10.229063 27.54 9 24 9 z M 38.179688 19.099609 C 38.009687 20.359609 37.590938 21.990078 36.960938 23.580078 L 32.580078 36.300781 C 36.460078 33.590781 39 29.09 39 24 C 39 22.28 38.709688 20.639609 38.179688 19.099609 z M 9.640625 19.689453 C 9.220625 21.059453 9 22.5 9 24 C 9 29.26 11.710313 33.890313 15.820312 36.570312 L 9.640625 19.689453 z M 23.990234 25.410156 L 19.589844 38.330078 C 20.989844 38.770078 22.47 39 24 39 C 25.64 39 27.230938 38.730234 28.710938 38.240234 L 23.990234 25.410156 z"></path>
				</symbol>
				<symbol viewBox="0 0 50 50" id="icon-labs">
					<path d="M44.1,13.9l-9-9C34.5,4.3,33.8,4,33,4s-1.5,0.3-2.1,0.9c-1,1-1.1,2.4-0.5,3.6L5.1,33.7c-2.8,2.8-2.8,7.4,0,10.2	c1.4,1.4,3.2,2.1,5.1,2.1s3.7-0.7,5.1-2.1l25.3-25.3C41,18.9,41.5,19,42,19c0.8,0,1.5-0.3,2.1-0.9C45.3,17,45.3,15,44.1,13.9z M42.7,16.7C42.5,16.9,42.3,17,42,17s-0.5-0.1-0.7-0.3l-0.1-0.1L37,12.4l-1.4,1.4l3.5,3.5L35.3,21H20.7l11-11.1l1,1l1.4-1.4	l-1.8-1.8l-0.1-0.1c-0.4-0.4-0.4-1,0-1.4C32.5,6.1,32.7,6,33,6s0.5,0.1,0.7,0.3l9,9C43.1,15.7,43.1,16.3,42.7,16.7z"></path>
				</symbol>
			</defs>
			</svg>
			<div id="updatebee-admin-container-slot"></div>
		<?php
	}
}
