<?php
/**
 * Tests for WordPress.org rollback eligibility and package selection.
 *
 * @package updatebee
 */

namespace UpdateBee\Tests;

use Brain\Monkey\Functions as MonkeyFunctions;
use UpdateBee\Updates\Rollback;

/**
 * Version filtering, download-host checks, and eligibility.
 */
class RollbackTest extends TestCase {

	/**
	 * Stub URL parsing used by the package allowlist.
	 */
	protected function setUp(): void {
		parent::setUp();

		MonkeyFunctions\when( 'wp_parse_url' )->alias(
			static function ( $url, $component = -1 ) {
				if ( -1 === $component ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Test double for wp_parse_url.
					return parse_url( (string) $url );
				}

				// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Test double for wp_parse_url.
				return parse_url( (string) $url, $component );
			}
		);
	}

	/**
	 * Directory plugins with a WordPress.org id can roll back.
	 */
	public function test_plugin_is_eligible_for_directory_release(): void {
		$this->assertTrue(
			Rollback::plugin_is_eligible(
				'akismet/akismet.php',
				false,
				'akismet',
				'w.org/plugins/akismet'
			)
		);
	}

	/**
	 * Version-controlled plugins are refused before an install.
	 */
	public function test_version_controlled_plugin_is_not_eligible(): void {
		$this->assertFalse(
			Rollback::plugin_is_eligible(
				'akismet/akismet.php',
				true,
				'akismet',
				'w.org/plugins/akismet'
			)
		);
	}

	/**
	 * Single-file plugins are refused before an install.
	 */
	public function test_single_file_plugin_is_not_eligible(): void {
		$this->assertFalse(
			Rollback::plugin_is_eligible(
				'hello.php',
				false,
				'hello-dolly',
				'w.org/plugins/hello-dolly'
			)
		);
	}

	/**
	 * Third-party plugin ids are refused.
	 */
	public function test_third_party_plugin_is_not_eligible(): void {
		$this->assertFalse(
			Rollback::plugin_is_eligible(
				'premium/premium.php',
				false,
				'premium',
				'https://example.com/premium'
			)
		);
	}

	/**
	 * A missing directory slug is refused.
	 */
	public function test_plugin_without_slug_is_not_eligible(): void {
		$this->assertFalse(
			Rollback::plugin_is_eligible(
				'akismet/akismet.php',
				false,
				'',
				'w.org/plugins/akismet'
			)
		);
	}

	/**
	 * Themes are eligible from a WordPress.org id or theme directory URL.
	 */
	public function test_theme_eligibility(): void {
		$this->assertTrue(
			Rollback::theme_is_eligible(
				'twentytwentyfour',
				false,
				'w.org/themes/twentytwentyfour',
				''
			)
		);
		$this->assertTrue(
			Rollback::theme_is_eligible(
				'twentytwentyfour',
				false,
				'',
				'https://wordpress.org/themes/twentytwentyfour/'
			)
		);
		$this->assertFalse(
			Rollback::theme_is_eligible(
				'twentytwentyfour',
				true,
				'w.org/themes/twentytwentyfour',
				'https://wordpress.org/themes/twentytwentyfour/'
			)
		);
		$this->assertFalse(
			Rollback::theme_is_eligible(
				'custom-theme',
				false,
				'',
				'https://example.com/custom-theme'
			)
		);
	}

	/**
	 * Previous versions drop trunk, current, newer, and foreign packages.
	 */
	public function test_previous_versions_keeps_older_directory_releases(): void {
		$versions = array(
			'2.1'   => 'https://downloads.wordpress.org/plugin/akismet.2.1.zip',
			'2.0'   => 'https://downloads.wordpress.org/plugin/akismet.2.0.zip',
			'1.10'  => 'https://downloads.wordpress.org/plugin/akismet.1.10.zip',
			'1.9'   => 'https://downloads.wordpress.org/plugin/akismet.1.9.zip',
			'trunk' => 'https://downloads.wordpress.org/plugin/akismet.zip',
			'1.8'   => 'https://evil.example/akismet.1.8.zip',
			'1.7'   => 'not-a-url',
		);

		$this->assertSame(
			array( '1.10', '1.9' ),
			Rollback::previous_versions( $versions, '2.0' )
		);
	}

	/**
	 * Integer version keys from the directory API stay in the list.
	 */
	public function test_previous_versions_accepts_integer_keys(): void {
		$this->assertSame(
			array( '1' ),
			Rollback::previous_versions(
				array(
					1 => 'https://downloads.wordpress.org/plugin/akismet.1.zip',
				),
				'2.0'
			)
		);
	}

	/**
	 * An empty installed version does not offer a rollback list.
	 */
	public function test_previous_versions_requires_current_version(): void {
		$this->assertSame(
			array(),
			Rollback::previous_versions(
				array(
					'1.0' => 'https://downloads.wordpress.org/plugin/akismet.1.0.zip',
				),
				''
			)
		);
	}

	/**
	 * Package lookup accepts only an allowlisted zip for that version.
	 */
	public function test_package_for_version(): void {
		$versions = array(
			'1.0'   => 'https://downloads.wordpress.org/plugin/akismet.1.0.zip',
			'trunk' => 'https://downloads.wordpress.org/plugin/akismet.zip',
			'0.9'   => 'http://downloads.wordpress.org/plugin/akismet.0.9.zip',
		);

		$this->assertSame(
			'https://downloads.wordpress.org/plugin/akismet.1.0.zip',
			Rollback::package_for_version( $versions, '1.0' )
		);
		$this->assertNull( Rollback::package_for_version( $versions, 'trunk' ) );
		$this->assertNull( Rollback::package_for_version( $versions, '0.9' ) );
		$this->assertNull( Rollback::package_for_version( $versions, '9.9' ) );
	}

	/**
	 * Download URLs must be HTTPS zips on downloads.wordpress.org.
	 */
	public function test_package_url_allowlist(): void {
		$this->assertTrue(
			Rollback::package_url_is_allowed( 'https://downloads.wordpress.org/plugin/akismet.1.0.zip' )
		);
		$this->assertTrue(
			Rollback::package_url_is_allowed( 'https://downloads.wordpress.org/theme/twentytwentyfour.1.0.zip' )
		);
		$this->assertFalse(
			Rollback::package_url_is_allowed( 'http://downloads.wordpress.org/plugin/akismet.1.0.zip' )
		);
		$this->assertFalse(
			Rollback::package_url_is_allowed( 'https://evil.example/plugin.zip' )
		);
		$this->assertFalse(
			Rollback::package_url_is_allowed( 'https://downloads.wordpress.org.evil.com/plugin/akismet.zip' )
		);
		$this->assertFalse(
			Rollback::package_url_is_allowed( 'https://user:pass@downloads.wordpress.org/plugin/akismet.1.0.zip' )
		);
		$this->assertFalse(
			Rollback::package_url_is_allowed( 'https://downloads.wordpress.org/plugin/akismet.1.0.tar.gz' )
		);
		$this->assertFalse( Rollback::package_url_is_allowed( '' ) );
	}

	/**
	 * The plugin overlay replaces one offer and leaves the stored transient unchanged.
	 */
	public function test_plugin_offer_overlay_does_not_change_stored_transient(): void {
		$stored = (object) array(
			'response' => array(
				'akismet/akismet.php' => (object) array(
					'new_version' => '5.0',
					'package'     => 'https://downloads.wordpress.org/plugin/akismet.5.0.zip',
				),
			),
		);

		$hooked = false;
		$seen   = null;
		Rollback::with_plugin_offer(
			'akismet/akismet.php',
			'akismet',
			'4.0',
			'https://downloads.wordpress.org/plugin/akismet.4.0.zip',
			static function () use ( $stored, &$seen, &$hooked ) {
				$hooked = has_filter( 'site_transient_update_plugins', array( Rollback::class, 'filter_plugin_transient' ) );
				$seen   = Rollback::filter_plugin_transient( $stored );
			}
		);

		$this->assertNotFalse( $hooked );
		$this->assertIsObject( $seen );
		$this->assertNotSame( $stored, $seen );
		$this->assertSame( '4.0', $seen->response['akismet/akismet.php']->new_version );
		$this->assertSame(
			'https://downloads.wordpress.org/plugin/akismet.4.0.zip',
			$seen->response['akismet/akismet.php']->package
		);
		$this->assertSame( '5.0', $stored->response['akismet/akismet.php']->new_version );
		$this->assertFalse( has_filter( 'site_transient_update_plugins', array( Rollback::class, 'filter_plugin_transient' ) ) );

		$after = Rollback::filter_plugin_transient( $stored );
		$this->assertSame( $stored, $after );
		$this->assertSame( '5.0', $after->response['akismet/akismet.php']->new_version );
	}

	/**
	 * The theme overlay replaces one offer and leaves the stored transient unchanged.
	 */
	public function test_theme_offer_overlay_does_not_change_stored_transient(): void {
		$stored = (object) array(
			'response' => array(
				'twentytwentyfour' => array(
					'new_version' => '1.3',
					'package'     => 'https://downloads.wordpress.org/theme/twentytwentyfour.1.3.zip',
				),
			),
		);

		$seen = null;
		Rollback::with_theme_offer(
			'twentytwentyfour',
			'twentytwentyfour',
			'1.0',
			'https://downloads.wordpress.org/theme/twentytwentyfour.1.0.zip',
			static function () use ( $stored, &$seen ) {
				$seen = Rollback::filter_theme_transient( $stored );
			}
		);

		$this->assertIsObject( $seen );
		$this->assertNotSame( $stored, $seen );
		$this->assertSame( '1.0', $seen->response['twentytwentyfour']['new_version'] );
		$this->assertSame(
			'https://downloads.wordpress.org/theme/twentytwentyfour.1.0.zip',
			$seen->response['twentytwentyfour']['package']
		);
		$this->assertSame( '1.3', $stored->response['twentytwentyfour']['new_version'] );
		$this->assertFalse( has_filter( 'site_transient_update_themes', array( Rollback::class, 'filter_theme_transient' ) ) );

		$after = Rollback::filter_theme_transient( $stored );
		$this->assertSame( $stored, $after );
		$this->assertSame( '1.3', $after->response['twentytwentyfour']['new_version'] );
	}
}
