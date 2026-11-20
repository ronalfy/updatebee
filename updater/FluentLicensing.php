<?php
/**
 * FluentCart license client.
 *
 * @package updatebee
 */

namespace UpdateBee\Updater;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FluentLicensing {

	private static $instance;

	private $config = array();

	public $settingsKey = '';

	public function register( $config = array() ) {
		if ( self::$instance ) {
			return self::$instance; // Return existing instance if already set.
		}

		if ( empty( $config['basename'] ) || empty( $config['version'] ) || empty( $config['api_url'] ) ) {
			throw new \Exception( 'Invalid configuration provided for FluentLicensing. Please provide basename, version, and api_url.' );
		}

		$this->config = $config;
		$baseName     = isset( $config['basename'] ) ? $config['basename'] : plugin_basename( __FILE__ );

		$slug                 = isset( $config['slug'] ) ? $config['slug'] : explode( '/', $baseName )[0];
		$this->config['slug'] = (string) $slug;

		$this->settingsKey = isset( $config['settings_key'] ) ? $config['settings_key'] : '__' . $this->config['slug'] . '_sl_info';

		if ( empty( $config['store_url'] ) ) {
			$this->config['store_url'] = $this->config['api_url'];
		}

		if ( empty( $config['purchase_url'] ) ) {
			$this->config['purchase_url'] = $this->config['store_url'];
		}

		$config = $this->config;

		if ( empty( $config['license_key'] ) && empty( $config['license_key_callback'] ) ) {
			$config['license_key_callback'] = function () {
				return $this->getCurrentLicenseKey();
			};
		}

		if ( ! class_exists( '\\' . __NAMESPACE__ . '\PluginUpdater' ) ) {
			require_once __DIR__ . '/PluginUpdater.php';
		}

		// Initialize the updater with the provided configuration.
		new PluginUpdater( $config );

		self::$instance = $this; // Set the instance for future use.

		return self::$instance;
	}

	public function getConfig( $key ) {
		if ( isset( $this->config[ $key ] ) ) {
			return $this->config[ $key ]; // Return the requested configuration value.
		}

		return '';
	}

	/**
	 * @return self
	 * @throws \Exception
	 */
	public static function getInstance() {
		if ( ! self::$instance ) {
			throw new \Exception( 'Licensing is not registered. Please call register() method first.' );
		}

		return self::$instance; // Return the singleton instance.
	}

	public function activate( $licenseKey = '' ) {
		if ( ! $licenseKey ) {
			return new \WP_Error( 'license_key_missing', __( 'License key is required for activation.', 'updatebee' ) );
		}

		$response = $this->apiRequest(
			'activate_license',
			array(
				'license_key'      => $licenseKey,
				'platform_version' => get_bloginfo( 'version' ),
				'server_version'   => PHP_VERSION,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response; // Return the error response if there is an error.
		}

		$saveData = $this->applyRemoteLicenseFields(
			array(
				'license_key'       => $licenseKey,
				'status'            => $response['status'] ?? 'valid',
				'variation_id'      => '',
				'variation_title'   => '',
				'expires'           => '',
				'activation_hash'   => '',
				'activations_count' => 0,
				'activation_limit'  => 0,
				'product_title'     => '',
				'renewal_url'       => '',
			),
			$response
		);

		$this->writeLicenseOption( $saveData );

		return $saveData; // Return the saved data.
	}

	public function deactivate() {
		$deactivated = $this->apiRequest(
			'deactivate_license',
			array(
				'license_key' => $this->getCurrentLicenseKey(),
			)
		);

		$this->deleteLicenseOption();

		return $deactivated;
	}

	public function getStatus( $remoteFetch = false ) {
		$currentLicense = $this->readLicenseOption();
		if ( ! $currentLicense || empty( $currentLicense['license_key'] ) ) {
			return $this->emptyLicense();
		}

		if ( ! $remoteFetch ) {
			return $currentLicense; // Return the current license status without fetching from the API.
		}

		$remoteStatus = $this->apiRequest(
			'check_license',
			array(
				'license_key'     => $currentLicense['license_key'] ?? '',
				'activation_hash' => $currentLicense['activation_hash'] ?? '',
				'item_id'         => $this->getConfig( 'item_id' ),
				'site_url'        => home_url(),
			)
		);

		if ( is_wp_error( $remoteStatus ) ) {
			return $remoteStatus; // Return the error response if there is an error.
		}

		$currentLicense = $this->applyRemoteLicenseFields( $currentLicense, $remoteStatus );
		if ( empty( $currentLicense['status'] ) ) {
			$currentLicense['status'] = 'error';
		}

		$this->writeLicenseOption( $currentLicense );

		return $currentLicense;
	}

	public function getCurrentLicenseKey() {
		$status = $this->getStatus();
		return isset( $status['license_key'] ) ? $status['license_key'] : ''; // Return the current license key.
	}

	public function getLicenseMessages() {
		$licenseDetails = $this->getStatus();
		$status         = $licenseDetails['status'];

		if ( $status == 'expired' ) {
			return array(
				'message'         => $this->getExpireMessage( $licenseDetails ),
				'type'            => 'in_app',
				'license_details' => $licenseDetails,
			);
		}

		if ( $status === 'disabled' ) {
			return array(
				'message'         => sprintf(
					/* translators: %s: plugin title. */
					__( 'The license for %s has been disabled. Please contact support for assistance.', 'updatebee' ),
					$this->getConfig( 'plugin_title' )
				),
				'type'            => 'global',
				'license_details' => $licenseDetails,
			);
		}

		if ( $status != 'valid' ) {
			return array(
				'message'         => \sprintf(
					/* translators: 1: plugin title, 2: link to the activation screen. */
					__( 'The %1$s license needs to be activated. %2$s', 'updatebee' ),
					esc_html( $this->getConfig( 'plugin_title' ) ),
					'<a href="' . esc_url( $this->getConfig( 'activate_url' ) ) . '">' . esc_html__( 'Click here to activate', 'updatebee' ) . '</a>'
				),
				'type'            => 'global',
				'license_details' => $licenseDetails,
			);
		}

		return false;
	}

	private function getExpireMessage( $licenseData, $scope = 'global' ) {
		if ( $scope == 'global' ) {
			$renewUrl = $this->getConfig( 'activate_url' );
		} else {
			$renewUrl = $this->getRenewUrl();
		}

		$expiresAt = gmdate( 'd M Y', strtotime( $licenseData['expires'] ) );

		// Every dynamic value is escaped because this message is rendered as HTML.
		return '<p>' . sprintf(
			/* translators: 1: plugin title, 2: license expiry date, 3: renewal link. */
			esc_html__( 'Your %1$s license has expired at %2$s. Please %3$s.', 'updatebee' ),
			esc_html( $this->getConfig( 'plugin_title' ) ),
			'<b>' . esc_html( $expiresAt ) . '</b>',
			'<a href="' . esc_url( $renewUrl ) . '"><b>' . esc_html__( 'Click Here to Renew Your License', 'updatebee' ) . '</b></a>'
		) . '</p>';
	}

	/**
	 * License record used when no key is stored.
	 *
	 * @return array<string, mixed>
	 */
	private function emptyLicense() {
		return array(
			'license_key'       => '',
			'status'            => 'unregistered',
			'variation_id'      => '',
			'variation_title'   => '',
			'expires'           => '',
			'activation_hash'   => '',
			'activations_count' => 0,
			'activation_limit'  => 0,
			'product_title'     => '',
			'renewal_url'       => '',
			'renew_url'         => '',
			'is_expired'        => false,
		);
	}

	/**
	 * Read the stored license from the site option.
	 *
	 * @return array<string, mixed>
	 */
	private function readLicenseOption() {
		$stored = get_site_option( $this->settingsKey, array() );
		if ( ! is_array( $stored ) ) {
			return array();
		}

		return $stored;
	}

	/**
	 * Persist the license as a site option.
	 *
	 * @param array<string, mixed> $data License record.
	 */
	private function writeLicenseOption( array $data ) {
		update_site_option( $this->settingsKey, $data );
	}

	/**
	 * Remove the stored license.
	 */
	private function deleteLicenseOption() {
		delete_site_option( $this->settingsKey );
	}

	/**
	 * Copy public license fields from a FluentCart response onto a stored record.
	 *
	 * @param array<string, mixed> $current Stored license.
	 * @param array<string, mixed> $remote  API payload.
	 * @return array<string, mixed>
	 */
	private function applyRemoteLicenseFields( array $current, array $remote ) {
		if ( array_key_exists( 'expiration_date', $remote ) ) {
			$expires = $remote['expiration_date'];
			if ( $expires === null || $expires === '' ) {
				$current['expires'] = 'lifetime';
			} else {
				$current['expires'] = sanitize_text_field( (string) $expires );
			}
		}

		if ( isset( $remote['status'] ) && $remote['status'] !== '' ) {
			$current['status'] = sanitize_key( (string) $remote['status'] );
		}

		if ( isset( $remote['variation_id'] ) && $remote['variation_id'] !== '' ) {
			$current['variation_id'] = sanitize_text_field( (string) $remote['variation_id'] );
		}

		if ( ! empty( $remote['variation_title'] ) ) {
			$current['variation_title'] = sanitize_text_field( (string) $remote['variation_title'] );
		}

		if ( ! empty( $remote['product_title'] ) ) {
			$current['product_title'] = sanitize_text_field( (string) $remote['product_title'] );
		}

		if ( isset( $remote['activations_count'] ) ) {
			$current['activations_count'] = absint( $remote['activations_count'] );
		}

		if ( isset( $remote['activation_limit'] ) ) {
			$current['activation_limit'] = absint( $remote['activation_limit'] );
		}

		if ( ! empty( $remote['activation_hash'] ) ) {
			$current['activation_hash'] = sanitize_text_field( (string) $remote['activation_hash'] );
		}

		$renewal = '';
		if ( ! empty( $remote['renewal_url'] ) ) {
			$renewal = (string) $remote['renewal_url'];
		} elseif ( ! empty( $remote['renew_url'] ) ) {
			$renewal = (string) $remote['renew_url'];
		}
		if ( $renewal !== '' ) {
			$renewal                = esc_url_raw( $renewal );
			$current['renewal_url'] = $renewal;
			$current['renew_url']   = $renewal;
		}

		$isExpired             = $remote['is_expired'] ?? false;
		$current['is_expired'] = ( $isExpired === true || $isExpired === 1 || $isExpired === '1' || $isExpired === 'yes' );

		if ( ! empty( $remote['error_type'] ) ) {
			$current['error_type']    = sanitize_key( (string) $remote['error_type'] );
			$current['error_message'] = isset( $remote['message'] ) ? sanitize_text_field( (string) $remote['message'] ) : '';
		}

		return $current;
	}

	private function apiRequest( $action, $data = array() ) {
		$url     = $this->config['api_url'];
		$fullUrl = add_query_arg(
			array(
				'fluent-cart' => $action,
			),
			$url
		);

		$defaults = array(
			'item_id'         => $this->config['item_id'],
			'current_version' => $this->config['version'],
			'site_url'        => home_url(),
		);

		$payload = wp_parse_args( $data, $defaults );

		// send the post request to the API.
		$response = wp_remote_post(
			$fullUrl,
			array(
				'timeout' => 15,
				'body'    => $payload,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response; // Return the error response if there is an error.
		}

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$errorData = wp_remote_retrieve_body( $response );
			$message   = sprintf(
				/* translators: %s: HTTP status code. */
				__( 'API request failed with status code: %s', 'updatebee' ),
				wp_remote_retrieve_response_code( $response )
			);
			if ( ! empty( $errorData ) ) {
				$decodedData = json_decode( $errorData, true );
				if ( $decodedData ) {
					$errorData = $decodedData;
				}

				if ( ! empty( $errorData['message'] ) ) {
					$message = (string) $errorData['message'];
				}
			}
			return new \WP_Error( 'api_error', $message, $errorData );
		}

		$responseData = json_decode( wp_remote_retrieve_body( $response ), true ); // Return the decoded response body.

		if ( $responseData ) {
			return $responseData;
		}

		return new \WP_Error( 'api_error', __( 'API request returned an empty or not JSON response.', 'updatebee' ), array() );
	}

	public function getRenewUrl() {
		$licenseKey = $this->getCurrentLicenseKey();
		if ( empty( $licenseKey ) ) {
			return $this->getConfig( 'purchase_url' );
		}

		return add_query_arg(
			array(
				'license_key' => $licenseKey,
				'fluent-cart' => 'renew_license',
			),
			$this->getConfig( 'store_url' )
		);
	}
}
