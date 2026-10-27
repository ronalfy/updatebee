<?php
/**
 * Tests for upgrade-notice keyword matching.
 *
 * @package updatebee
 */

namespace UpdateBee\Tests;

use Brain\Monkey\Functions as MonkeyFunctions;
use ReflectionClass;
use UpdateBee\Options;
use UpdateBee\Updates\Upgrade_Notice_Keywords;

/**
 * Upgrade_Notice_Keywords test case.
 */
class UpgradeNoticeKeywordsTest extends TestCase {

	/**
	 * In-memory site options.
	 *
	 * @var array<string, mixed>
	 */
	private array $site_options = array();

	/**
	 * Reset singleton and options cache after each test.
	 */
	protected function tearDown(): void {
		Options::flush_cache();
		$this->reset_keywords_singleton();
		parent::tearDown();
	}

	/**
	 * Clear the Upgrade_Notice_Keywords singleton.
	 */
	private function reset_keywords_singleton(): void {
		$reflection = new ReflectionClass( Upgrade_Notice_Keywords::class );
		$property   = $reflection->getProperty( 'instance' );
		$property->setAccessible( true );
		$property->setValue( null, null );
	}

	/**
	 * Stub WordPress helpers used by Options and keyword matching.
	 *
	 * @param array<string, mixed> $options Stored updatebee_options values.
	 */
	private function stub_helpers( array $options = array() ): void {
		MonkeyFunctions\when( 'add_filter' )->justReturn( true );
		MonkeyFunctions\when( 'apply_filters' )->returnArg( 2 );
		MonkeyFunctions\when( 'absint' )->alias(
			static function ( $value ) {
				return abs( (int) $value );
			}
		);
		MonkeyFunctions\when( 'sanitize_text_field' )->returnArg( 1 );
		MonkeyFunctions\when( 'sanitize_title' )->returnArg( 1 );
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
		MonkeyFunctions\when( 'sanitize_textarea_field' )->alias(
			static function ( $value ) {
				return is_string( $value ) ? $value : '';
			}
		);
		MonkeyFunctions\when( 'wp_strip_all_tags' )->alias(
			static function ( $text ) {
				if ( ! is_string( $text ) ) {
					return '';
				}

				return (string) preg_replace( '/<[^>]*>/', '', $text );
			}
		);
		MonkeyFunctions\when( 'get_site_option' )->alias(
			function ( $key, $default_value = false ) {
				return array_key_exists( $key, $this->site_options ) ? $this->site_options[ $key ] : $default_value;
			}
		);

		$this->site_options['updatebee_options'] = $options;
	}

	/**
	 * Return a keyword matcher instance with stubs applied.
	 *
	 * @param array<string, mixed> $options Stored updatebee_options values.
	 * @return Upgrade_Notice_Keywords
	 */
	private function get_keywords( array $options = array() ): Upgrade_Notice_Keywords {
		$this->stub_helpers( $options );

		return Upgrade_Notice_Keywords::get_instance();
	}

	/**
	 * Stored keywords are split, trimmed, and emptied of blank lines.
	 */
	public function test_get_keywords_splits_and_trims(): void {
		$this->stub_helpers(
			array(
				'upgrade_notice_keywords' => " security \n\nXSS\n",
			)
		);

		$this->assertSame( array( 'security', 'XSS' ), Upgrade_Notice_Keywords::get_keywords() );
	}

	/**
	 * An empty option returns no keywords.
	 */
	public function test_get_keywords_empty_returns_empty_array(): void {
		$this->stub_helpers(
			array(
				'upgrade_notice_keywords' => '',
			)
		);

		$this->assertSame( array(), Upgrade_Notice_Keywords::get_keywords() );
	}

	/**
	 * Sanitize normalizes line endings and drops blank lines.
	 */
	public function test_sanitize_normalizes_lines(): void {
		$this->stub_helpers();

		$this->assertSame(
			"security\nCVE",
			Upgrade_Notice_Keywords::sanitize( "security\r\n\r\nCVE\n" )
		);
		$this->assertSame( '', Upgrade_Notice_Keywords::sanitize( array( 'security' ) ) );
	}

	/**
	 * HTML is stripped before matching.
	 */
	public function test_matches_strips_tags(): void {
		$this->stub_helpers(
			array(
				'upgrade_notice_keywords' => 'security',
			)
		);

		$item = (object) array(
			'upgrade_notice' => '<p>This is a <strong>security</strong> release.</p>',
		);

		$this->assertTrue( Upgrade_Notice_Keywords::matches( $item ) );
	}

	/**
	 * Missing or non-string notices never match.
	 */
	public function test_matches_rejects_invalid_notices(): void {
		$this->stub_helpers(
			array(
				'upgrade_notice_keywords' => 'security',
			)
		);

		$this->assertFalse( Upgrade_Notice_Keywords::matches( null ) );
		$this->assertFalse( Upgrade_Notice_Keywords::matches( (object) array() ) );
		$this->assertFalse( Upgrade_Notice_Keywords::matches( (object) array( 'upgrade_notice' => array( 'security' ) ) ) );
	}

	/**
	 * A matching plugin upgrade notice forces an automatic update.
	 */
	public function test_plugin_keyword_match_forces_update(): void {
		$keywords = $this->get_keywords(
			array(
				'upgrade_notice_keywords'         => "security\nCSRF",
				'upgrade_notice_keywords_enabled' => true,
			)
		);
		$item     = (object) array(
			'plugin'         => 'akismet/akismet.php',
			'upgrade_notice' => 'This is a security release.',
		);

		$this->assertTrue( $keywords->auto_update_plugin( false, $item ) );
	}

	/**
	 * A matching theme upgrade notice forces an automatic update.
	 */
	public function test_theme_keyword_match_forces_update(): void {
		$keywords = $this->get_keywords(
			array(
				'upgrade_notice_keywords'         => 'CSRF',
				'upgrade_notice_keywords_enabled' => true,
			)
		);
		$item     = (object) array(
			'theme'          => 'twentytwentyfour',
			'upgrade_notice' => 'Fixes a CSRF issue.',
		);

		$this->assertTrue( $keywords->auto_update_theme( false, $item ) );
	}

	/**
	 * Keyword matching is case-insensitive.
	 */
	public function test_keyword_match_is_case_insensitive(): void {
		$keywords = $this->get_keywords(
			array(
				'upgrade_notice_keywords'         => 'xss',
				'upgrade_notice_keywords_enabled' => true,
			)
		);
		$item     = (object) array(
			'plugin'         => 'akismet/akismet.php',
			'upgrade_notice' => 'Fixes an XSS vulnerability.',
		);

		$this->assertTrue( $keywords->auto_update_plugin( false, $item ) );
	}

	/**
	 * A non-matching notice leaves the incoming decision unchanged.
	 */
	public function test_plugin_keyword_miss_keeps_incoming_decision(): void {
		$keywords = $this->get_keywords(
			array(
				'upgrade_notice_keywords' => 'security',
			)
		);
		$item     = (object) array(
			'plugin'         => 'akismet/akismet.php',
			'upgrade_notice' => 'Performance improvements.',
		);

		$this->assertFalse( $keywords->auto_update_plugin( false, $item ) );
		$this->assertTrue( $keywords->auto_update_plugin( true, $item ) );
	}

	/**
	 * An empty keyword list never forces an update.
	 */
	public function test_empty_keywords_never_force_update(): void {
		$keywords = $this->get_keywords(
			array(
				'upgrade_notice_keywords' => '',
			)
		);
		$item     = (object) array(
			'plugin'         => 'akismet/akismet.php',
			'upgrade_notice' => 'This is a security release.',
		);

		$this->assertFalse( $keywords->auto_update_plugin( false, $item ) );
	}

	/**
	 * A disabled feature never forces an update.
	 */
	public function test_disabled_feature_never_forces_update(): void {
		$keywords = $this->get_keywords(
			array(
				'upgrade_notice_keywords'         => 'security',
				'upgrade_notice_keywords_enabled' => false,
			)
		);
		$item     = (object) array(
			'plugin'         => 'akismet/akismet.php',
			'upgrade_notice' => 'This is a security release.',
		);

		$this->assertFalse( $keywords->auto_update_plugin( false, $item ) );
	}

	/**
	 * A blocked plugin is not forced even when the notice matches.
	 */
	public function test_blocked_plugin_is_not_forced(): void {
		$keywords = $this->get_keywords(
			array(
				'upgrade_notice_keywords'         => 'security',
				'upgrade_notice_keywords_enabled' => true,
				'blocked_plugins'                 => array( 'akismet' ),
			)
		);
		$item     = (object) array(
			'plugin'         => 'akismet/akismet.php',
			'upgrade_notice' => 'This is a security release.',
		);

		$this->assertFalse( $keywords->auto_update_plugin( false, $item ) );
	}
}
