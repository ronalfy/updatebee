<?php
/**
 * Register the Translations tab.
 *
 * @package updatebee
 */

namespace UpdateBee\Admin\Templates;

use UpdateBee\Functions;

/**
 * Output the translations tab and content.
 */
class Translations {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'updatebee_admin_tabs', array( $this, 'add_translations_tab' ), 4 );
		add_action( 'updatebee_output_translations', array( $this, 'output_translations_content' ), 1, 2 );
	}

	/**
	 * Add the translations tab and callback actions.
	 *
	 * @param array<int, array<string, string>> $tabs Array of tabs.
	 * @return array<int, array<string, string>> Array of tabs.
	 */
	public function add_translations_tab( array $tabs ): array {
		$tabs[] = array(
			'get'    => 'translations',
			'action' => 'updatebee_output_translations',
			'url'    => Functions::get_settings_url( 'translations' ),
			'label'  => _x( 'Translations', 'Tab label as Translations', 'updatebee' ),
			'icon'   => 'icon-language',
		);

		return $tabs;
	}

	/**
	 * Output translations screen content.
	 *
	 * @param string      $tab     Current tab.
	 * @param string|null $sub_tab Current sub-tab.
	 */
	public function output_translations_content( string $tab, $sub_tab = '' ): void {
		if ( 'translations' !== $tab ) {
			return;
		}

		$asset_file = Functions::get_plugin_dir( 'build/translations.asset.php' );
		$deps       = array(
			'dependencies' => array(),
			'version'      => Functions::get_plugin_version(),
		);
		if ( file_exists( $asset_file ) ) {
			$loaded = require $asset_file;
			if ( is_array( $loaded ) ) {
				$deps = wp_parse_args( $loaded, $deps );
			}
		}

		$style_deps = array( 'updatebee-admin' );
		if ( wp_style_is( 'wp-dataviews', 'registered' ) ) {
			$style_deps[] = 'wp-dataviews';
		}

		wp_enqueue_script(
			'updatebee-translations',
			Functions::get_plugin_url( 'build/translations.js' ),
			$deps['dependencies'],
			$deps['version'],
			true
		);
		wp_enqueue_style(
			'updatebee-translations',
			Functions::get_plugin_url( 'build/translations.css' ),
			$style_deps,
			is_string( $deps['version'] ) ? $deps['version'] : Functions::get_plugin_version()
		);
		wp_localize_script(
			'updatebee-translations',
			'updatebeeTranslations',
			array(
				'retrieveNonce' => wp_create_nonce( 'updatebee-retrieve-translations' ),
				'bulkNonce'     => wp_create_nonce( 'updatebee-translation-bulk' ),
			)
		);

		unset( $sub_tab );
		?>
		<div id="updatebee-translations-container"></div>
		<?php
	}
}
