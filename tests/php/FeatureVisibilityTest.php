<?php
/**
 * Tests for optional admin feature visibility.
 *
 * @package updatebee
 */

namespace UpdateBee\Tests;

use Brain\Monkey\Functions as MonkeyFunctions;
use UpdateBee\Admin\Feature_Visibility;
use UpdateBee\Options;

/**
 * Feature visibility test case.
 */
class FeatureVisibilityTest extends TestCase {

	/**
	 * Reset the options cache after each test.
	 */
	protected function tearDown(): void {
		Options::flush_cache();
		parent::tearDown();
	}

	/**
	 * Hidden feature keys are allowlisted and deduplicated.
	 */
	public function test_sanitize_hidden_features_allowlists_and_deduplicates(): void {
		MonkeyFunctions\when( 'sanitize_key' )->alias(
			static function ( $key ) {
				return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
			}
		);

		$this->assertSame(
			array( 'upcoming', 'webhooks' ),
			Feature_Visibility::sanitize_hidden_features(
				array( 'upcoming', 'webhooks', 'upcoming', 'unknown', 4, array( 'tools' ) )
			)
		);
	}

	/**
	 * Every hideable top-level tab is removed while core tabs remain.
	 */
	public function test_filter_admin_tabs_hides_all_optional_tabs(): void {
		Options::flush_cache();
		MonkeyFunctions\when( 'apply_filters' )->returnArg( 2 );
		MonkeyFunctions\when( 'wp_parse_args' )->alias(
			static function ( $args, $defaults ) {
				return array_merge( $defaults, is_array( $args ) ? $args : array() );
			}
		);
		MonkeyFunctions\when( 'get_site_option' )->justReturn(
			array(
				'hidden_features' => Feature_Visibility::FEATURE_KEYS,
			)
		);
		MonkeyFunctions\when( 'sanitize_key' )->alias(
			static function ( $key ) {
				return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
			}
		);

		$tabs = array_map(
			static fn( $tab ) => array( 'get' => $tab ),
			array( 'dashboard', 'upcoming', 'webhooks', 'translations', 'emails', 'cron', 'tools', 'plugins', 'themes', 'logs', 'advanced' )
		);

		$filtered = ( new Feature_Visibility() )->filter_admin_tabs( $tabs );

		$this->assertSame(
			array( 'dashboard', 'plugins', 'themes', 'logs', 'advanced' ),
			array_column( $filtered, 'get' )
		);
	}
}
