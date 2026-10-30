<?php
/**
 * Tests for branding / white-label resolver.
 *
 * @package updatebee
 */

namespace UpdateBee\Tests;

use Brain\Monkey\Functions as MonkeyFunctions;
use UpdateBee\Branding;
use UpdateBee\Options;

/**
 * Branding test case.
 */
class BrandingTest extends TestCase {

	/**
	 * Reset caches after each test.
	 */
	protected function tearDown(): void {
		Options::flush_cache();
		Branding::flush_cache();
		parent::tearDown();
	}

	/**
	 * Stub option helpers with optional stored branding values.
	 *
	 * @param array<string, mixed> $stored Stored option overrides.
	 */
	private function stub_options( array $stored = array() ): void {
		MonkeyFunctions\when( 'apply_filters' )->returnArg( 2 );
		MonkeyFunctions\when( 'wp_parse_args' )->alias(
			static function ( $args, $defaults ) {
				if ( ! is_array( $args ) ) {
					$args = array();
				}
				if ( ! is_array( $defaults ) ) {
					$defaults = array();
				}
				return array_merge( $defaults, $args );
			}
		);
		MonkeyFunctions\when( 'get_site_option' )->alias(
			static function ( $key, $fallback = false ) use ( $stored ) {
				if ( 'updatebee_options' === $key ) {
					return $stored;
				}

				return $fallback;
			}
		);
		MonkeyFunctions\when( 'plugin_dir_url' )->alias(
			static function () {
				return 'https://example.com/wp-content/plugins/updatebee/';
			}
		);
		MonkeyFunctions\when( 'absint' )->alias(
			static function ( $value ) {
				return abs( (int) $value );
			}
		);
	}

	/**
	 * Empty branding options fall back to the default plugin name.
	 */
	public function test_filter_plugin_name_falls_back_when_empty(): void {
		$this->stub_options();
		$branding = new Branding();

		$this->assertSame( 'UpdateBee', $branding->filter_plugin_name( 'UpdateBee' ) );
	}

	/**
	 * A saved name replaces the default display name.
	 */
	public function test_filter_plugin_name_uses_saved_value(): void {
		$this->stub_options(
			array(
				'branding_name' => 'Agency Updates',
			)
		);
		$branding = new Branding();

		$this->assertSame( 'Agency Updates', $branding->filter_plugin_name( 'UpdateBee' ) );
	}

	/**
	 * The Plugins screen row is left alone when branding fields are empty.
	 */
	public function test_filter_all_plugins_leaves_defaults_when_empty(): void {
		$this->stub_options();
		MonkeyFunctions\when( 'plugin_basename' )->justReturn( 'updatebee/updatebee.php' );

		$plugins = array(
			'updatebee/updatebee.php' => array(
				'Name'        => 'UpdateBee',
				'Title'       => 'UpdateBee',
				'Description' => 'Take complete control over your automatic updates.',
				'Author'      => 'MediaRon LLC',
				'AuthorName'  => 'MediaRon LLC',
				'PluginURI'   => 'https://mediaron.com/updatebee/',
				'AuthorURI'   => 'https://mediaron.com',
			),
		);

		$branding = new Branding();
		$filtered = $branding->filter_all_plugins( $plugins );

		$this->assertSame( 'UpdateBee', $filtered['updatebee/updatebee.php']['Name'] );
		$this->assertSame( 'MediaRon LLC', $filtered['updatebee/updatebee.php']['Author'] );
		$this->assertSame( 'https://mediaron.com/updatebee/', $filtered['updatebee/updatebee.php']['PluginURI'] );
	}

	/**
	 * Saved branding overrides this plugin's Plugins screen row.
	 */
	public function test_filter_all_plugins_applies_saved_branding(): void {
		$this->stub_options(
			array(
				'branding_name'        => 'Agency Updates',
				'branding_description' => 'Managed automatic updates.',
				'branding_author'      => 'Agency Co',
				'branding_url'         => 'https://agency.example/',
			)
		);
		MonkeyFunctions\when( 'plugin_basename' )->justReturn( 'updatebee/updatebee.php' );

		$plugins = array(
			'updatebee/updatebee.php' => array(
				'Name'        => 'UpdateBee',
				'Title'       => 'UpdateBee',
				'Description' => 'Take complete control over your automatic updates.',
				'Author'      => 'MediaRon LLC',
				'AuthorName'  => 'MediaRon LLC',
				'PluginURI'   => 'https://mediaron.com/updatebee/',
				'AuthorURI'   => 'https://mediaron.com',
			),
			'akismet/akismet.php'     => array(
				'Name' => 'Akismet',
			),
		);

		$branding = new Branding();
		$filtered = $branding->filter_all_plugins( $plugins );

		$this->assertSame( 'Agency Updates', $filtered['updatebee/updatebee.php']['Name'] );
		$this->assertSame( 'Agency Updates', $filtered['updatebee/updatebee.php']['Title'] );
		$this->assertSame( 'Managed automatic updates.', $filtered['updatebee/updatebee.php']['Description'] );
		$this->assertSame( 'Agency Co', $filtered['updatebee/updatebee.php']['Author'] );
		$this->assertSame( 'Agency Co', $filtered['updatebee/updatebee.php']['AuthorName'] );
		$this->assertSame( 'https://agency.example/', $filtered['updatebee/updatebee.php']['PluginURI'] );
		$this->assertSame( 'https://agency.example/', $filtered['updatebee/updatebee.php']['AuthorURI'] );
		$this->assertSame( 'Akismet', $filtered['akismet/akismet.php']['Name'] );
	}

	/**
	 * The bundled logo URL is used when no custom name is set.
	 */
	public function test_get_default_logo_url_returns_bundled_asset(): void {
		$this->stub_options();

		$this->assertSame(
			'https://example.com/wp-content/plugins/updatebee/assets/logo.png',
			Branding::get_default_logo_url()
		);
	}

	/**
	 * Sanitize branding settings and trim fields.
	 */
	public function test_sanitize_settings_cleans_fields(): void {
		MonkeyFunctions\when( 'sanitize_text_field' )->alias(
			static function ( $value ) {
				return is_string( $value ) ? trim( $value ) : '';
			}
		);
		MonkeyFunctions\when( 'sanitize_textarea_field' )->alias(
			static function ( $value ) {
				return is_string( $value ) ? trim( $value ) : '';
			}
		);
		MonkeyFunctions\when( 'esc_url_raw' )->alias(
			static function ( $value ) {
				return is_string( $value ) ? $value : '';
			}
		);

		$sanitized = Branding::sanitize_settings(
			array(
				'branding_name'        => ' Agency Updates ',
				'branding_description' => ' Managed updates. ',
				'branding_author'      => ' Agency Co ',
				'branding_url'         => 'https://agency.example/',
			)
		);

		$this->assertSame( 'Agency Updates', $sanitized['branding_name'] );
		$this->assertSame( 'Managed updates.', $sanitized['branding_description'] );
		$this->assertSame( 'Agency Co', $sanitized['branding_author'] );
		$this->assertSame( 'https://agency.example/', $sanitized['branding_url'] );
		$this->assertArrayNotHasKey( 'branding_logo_id', $sanitized );
	}

	/**
	 * Header defaults come from plugin header data.
	 */
	public function test_get_header_defaults_uses_plugin_data(): void {
		$this->stub_options();
		MonkeyFunctions\when( 'get_plugin_data' )->justReturn(
			array(
				'Name'        => 'UpdateBee',
				'Description' => 'Take complete control over your automatic updates.',
				'Author'      => '<a href="https://mediaron.com">MediaRon LLC</a>',
				'PluginURI'   => 'https://mediaron.com/updatebee/',
			)
		);
		MonkeyFunctions\when( 'wp_strip_all_tags' )->alias(
			static function ( $value ) {
				return trim( preg_replace( '/<[^>]*>/', '', (string) $value ) );
			}
		);

		$defaults = Branding::get_header_defaults();

		$this->assertSame( 'UpdateBee', $defaults['name'] );
		$this->assertSame( 'Take complete control over your automatic updates.', $defaults['description'] );
		$this->assertSame( 'MediaRon LLC', $defaults['author'] );
		$this->assertSame( 'https://mediaron.com/updatebee/', $defaults['url'] );
		$this->assertArrayNotHasKey( 'logo_url', $defaults );
	}
}
