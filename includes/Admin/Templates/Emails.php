<?php
/**
 * Register the Emails tab.
 *
 * @package updatebee
 */

namespace UpdateBee\Admin\Templates;

use UpdateBee\Functions;
use UpdateBee\Updates\Init as Updates_Init;

/**
 * Output the emails tab and content.
 */
class Emails {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'updatebee_admin_tabs', array( $this, 'add_emails_tab' ), 6 );
		add_action( 'updatebee_output_emails', array( $this, 'output_emails_content' ), 1, 2 );
	}

	/**
	 * Add the emails tab and callback actions.
	 *
	 * @param array<int, array<string, string>> $tabs Array of tabs.
	 * @return array<int, array<string, string>> Array of tabs.
	 */
	public function add_emails_tab( array $tabs ): array {
		$tabs[] = array(
			'get'    => 'emails',
			'action' => 'updatebee_output_emails',
			'url'    => Functions::get_settings_url( 'emails' ),
			'label'  => _x( 'Emails', 'Tab label as Emails', 'updatebee' ),
			'icon'   => 'icon-email',
		);

		return $tabs;
	}

	/**
	 * Output emails screen content.
	 *
	 * @param string      $tab     Current tab.
	 * @param string|null $sub_tab Current sub-tab.
	 */
	public function output_emails_content( string $tab, $sub_tab = '' ): void {
		if ( 'emails' !== $tab ) {
			return;
		}

		$asset_file = Functions::get_plugin_dir( 'build/emails.asset.php' );
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

		wp_enqueue_script(
			'updatebee-emails',
			Functions::get_plugin_url( 'build/emails.js' ),
			$deps['dependencies'],
			$deps['version'],
			true
		);
		wp_enqueue_style(
			'updatebee-emails',
			Functions::get_plugin_url( 'build/emails.css' ),
			$style_deps,
			is_string( $deps['version'] ) ? $deps['version'] : Functions::get_plugin_version()
		);

		wp_localize_script(
			'updatebee-emails',
			'updatebeeEmails',
			array(
				'enableCoreEmails'     => Updates_Init::core_emails_enabled(),
				'coreEmailRecipients'  => Updates_Init::get_core_email_recipients_for_display(),
				'wordpressSaveNonce'   => wp_create_nonce( 'updatebee-save-wordpress-email-settings' ),
				'successRetrieveNonce' => wp_create_nonce( 'updatebee-retrieve-email-success-settings' ),
				'successSaveNonce'     => wp_create_nonce( 'updatebee-save-email-success-settings' ),
				'successResetNonce'    => wp_create_nonce( 'updatebee-reset-email-success-settings' ),
				'failureRetrieveNonce' => wp_create_nonce( 'updatebee-retrieve-email-failure-settings' ),
				'failureSaveNonce'     => wp_create_nonce( 'updatebee-save-email-failure-settings' ),
				'failureResetNonce'    => wp_create_nonce( 'updatebee-reset-email-failure-settings' ),
			)
		);

		unset( $sub_tab );
		?>
		<div id="updatebee-emails-container"></div>
		<?php
	}
}
