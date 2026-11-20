<?php
/**
 * PHPUnit bootstrap.
 *
 * @package updatebee
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

if ( ! defined( 'UPDATEBEE_FILE' ) ) {
	define( 'UPDATEBEE_FILE', dirname( __DIR__, 2 ) . '/updatebee.php' );
}

if ( ! defined( 'UPDATEBEE_VERSION' ) ) {
	define( 'UPDATEBEE_VERSION', '1.0.0' );
}

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! defined( 'WEEK_IN_SECONDS' ) ) {
	define( 'WEEK_IN_SECONDS', 604800 );
}

if ( ! defined( 'MONTH_IN_SECONDS' ) ) {
	define( 'MONTH_IN_SECONDS', 2592000 );
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Test stub for WordPress i18n.
	 *
	 * @param string $text   Text to translate.
	 * @param string $domain Text domain. Unused in tests.
	 * @return string
	 */
	function __( $text, $domain = 'default' ) {
		unset( $domain );

		return $text;
	}
}

if ( ! function_exists( '_x' ) ) {
	/**
	 * Test stub for contextual translation.
	 *
	 * @param string $text    Text to translate.
	 * @param string $context Context information for translators.
	 * @param string $domain  Text domain.
	 * @return string
	 */
	function _x( $text, $context, $domain = 'default' ) {
		unset( $context, $domain );

		return $text;
	}
}

if ( ! function_exists( 'esc_sql' ) ) {
	/**
	 * Test stub for esc_sql().
	 *
	 * @param string|array $data Data to escape.
	 * @return string|array
	 */
	function esc_sql( $data ) {
		return $data;
	}
}

if ( ! function_exists( 'wp_doing_ajax' ) ) {
	/**
	 * Test stub for wp_doing_ajax().
	 *
	 * @return bool
	 */
	function wp_doing_ajax() {
		return defined( 'DOING_AJAX' ) && DOING_AJAX;
	}
}

if ( ! function_exists( 'wp_doing_cron' ) ) {
	/**
	 * Test stub for wp_doing_cron().
	 *
	 * @return bool
	 */
	function wp_doing_cron() {
		return defined( 'DOING_CRON' ) && DOING_CRON;
	}
}

if ( ! function_exists( 'rest_sanitize_boolean' ) ) {
	/**
	 * Test stub for rest_sanitize_boolean().
	 *
	 * @param mixed $value Value to sanitize.
	 * @return bool
	 */
	function rest_sanitize_boolean( $value ) {
		if ( is_string( $value ) ) {
			$value = strtolower( $value );
			if ( in_array( $value, array( 'false', '0', 'no', 'off', '' ), true ) ) {
				return false;
			}
		}

		return (bool) $value;
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * Test stub for wp_unslash().
	 *
	 * @param mixed $value Value to unslash.
	 * @return mixed
	 */
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * Test stub for escaped translatable strings.
	 *
	 * @param string $text Text to translate.
	 * @return string
	 */
	function esc_html__( $text ) {
		return $text;
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	require_once __DIR__ . '/WP_Error_Stub.php';
}

if ( ! function_exists( 'sanitize_email' ) ) {
	/**
	 * Test stub for sanitize_email().
	 *
	 * @param string $email Email address.
	 * @return string
	 */
	function sanitize_email( $email ) {
		return is_string( $email ) ? trim( $email ) : '';
	}
}

if ( ! function_exists( 'is_email' ) ) {
	/**
	 * Test stub for is_email().
	 *
	 * @param string $email Email address.
	 * @return bool
	 */
	function is_email( $email ) {
		return is_string( $email ) && str_contains( $email, '@' );
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * Test stub for is_wp_error().
	 *
	 * @param mixed $thing Value to check.
	 * @return bool
	 */
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! class_exists( 'WP_Admin_Bar' ) ) {
	require_once __DIR__ . '/WP_Admin_Bar_Stub.php';
}
