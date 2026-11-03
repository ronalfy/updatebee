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
use UpdateBee\Updates\Skip_Version;
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
		$this->reset_skip_singleton();
		parent::tearDown();
	}

	/**
	 * Clear the Skip_Version singleton.
	 */
	private function reset_skip_singleton(): void {
		$reflection = new ReflectionClass( Skip_Version::class );
		$property   = $reflection->getProperty( 'instance' );
		$property->setAccessible( true );
		$property->setValue( null, null );
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
	 * A matching notice does not turn automatic updates on.
	 */
	public function test_plugin_keyword_match_does_not_force_when_auto_updates_are_off(): void {
		$keywords = $this->get_keywords(
			array(
				'upgrade_notice_keywords'         => "security\nCSRF",
				'upgrade_notice_keywords_enabled' => true,
				'plugin_auto_updates'             => 'off',
			)
		);
		$item     = (object) array(
			'plugin'         => 'akismet/akismet.php',
			'upgrade_notice' => 'This is a security release.',
		);

		$this->assertTrue( Upgrade_Notice_Keywords::cuts_the_line( $item ) );
		$this->assertFalse( $keywords->auto_update_plugin( false, $item ) );
	}

	/**
	 * A matching plugin notice allows an update that an earlier rule rejected.
	 */
	public function test_plugin_keyword_match_allows_update_when_auto_updates_are_on(): void {
		$keywords = $this->get_keywords(
			array(
				'upgrade_notice_keywords'         => "security\nCSRF",
				'upgrade_notice_keywords_enabled' => true,
				'plugin_auto_updates'             => 'on',
				'plugin_auto_updates_custom'      => 'off',
			)
		);
		$item     = (object) array(
			'plugin'         => 'akismet/akismet.php',
			'upgrade_notice' => 'This is a security release.',
		);

		$this->assertTrue( $keywords->auto_update_plugin( false, $item ) );
	}

	/**
	 * A matching theme notice allows an update that an earlier rule rejected.
	 */
	public function test_theme_keyword_match_allows_update_when_auto_updates_are_on(): void {
		$keywords = $this->get_keywords(
			array(
				'upgrade_notice_keywords'         => 'CSRF',
				'upgrade_notice_keywords_enabled' => true,
				'theme_auto_updates'              => 'on',
				'theme_auto_updates_custom'       => 'off',
			)
		);
		$item     = (object) array(
			'theme'          => 'twentytwentyfour',
			'upgrade_notice' => 'Fixes a CSRF issue.',
		);

		$this->assertTrue( $keywords->auto_update_theme( false, $item ) );
	}

	/**
	 * A matching theme notice does not turn automatic updates on.
	 */
	public function test_theme_keyword_match_does_not_force_when_auto_updates_are_off(): void {
		$keywords = $this->get_keywords(
			array(
				'upgrade_notice_keywords'         => 'CSRF',
				'upgrade_notice_keywords_enabled' => true,
				'theme_auto_updates'              => 'off',
			)
		);
		$item     = (object) array(
			'theme'          => 'twentytwentyfour',
			'upgrade_notice' => 'Fixes a CSRF issue.',
		);

		$this->assertFalse( $keywords->auto_update_theme( false, $item ) );
	}

	/**
	 * Keyword matching is case-insensitive.
	 */
	public function test_keyword_match_is_case_insensitive(): void {
		$keywords = $this->get_keywords(
			array(
				'upgrade_notice_keywords'         => 'xss',
				'upgrade_notice_keywords_enabled' => true,
				'plugin_auto_updates'             => 'on',
				'plugin_auto_updates_custom'      => 'off',
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
		$this->assertFalse( Upgrade_Notice_Keywords::cuts_the_line( $item ) );
	}

	/**
	 * Site Health fakes never cut the line.
	 */
	public function test_site_health_fake_does_not_cut_the_line(): void {
		$this->get_keywords(
			array(
				'upgrade_notice_keywords'         => 'security',
				'upgrade_notice_keywords_enabled' => true,
			)
		);
		$item = (object) array(
			'plugin'         => 'a-fake-plugin/a-fake-plugin.php',
			'upgrade_notice' => 'This is a security release.',
		);

		$this->assertFalse( Upgrade_Notice_Keywords::cuts_the_line( $item ) );
	}

	/**
	 * An untested-update veto still wins after a keyword match.
	 */
	public function test_untested_veto_wins_after_keyword_match(): void {
		$keywords = $this->get_keywords(
			array(
				'upgrade_notice_keywords'         => 'security',
				'upgrade_notice_keywords_enabled' => true,
				'plugin_auto_updates'             => 'on',
				'plugin_auto_updates_custom'      => 'off',
				'block_untested_updates'          => 'on',
			)
		);
		MonkeyFunctions\when( 'get_bloginfo' )->alias(
			static function ( $show ) {
				return 'version' === $show ? '6.8.1' : '';
			}
		);
		$item = (object) array(
			'plugin'         => 'hello.php',
			'new_version'    => '1.2.4',
			'tested'         => '6.0',
			'upgrade_notice' => 'This is a security release.',
		);

		$this->assertTrue( $keywords->auto_update_plugin( false, $item ) );

		$checker = new UntestedUpdatesHarness();
		$this->assertFalse( $checker->auto_update_plugin( true, $item ) );
		$this->assertSame( 1, $checker->logs );
	}

	/**
	 * Skipping one version still wins after a keyword match.
	 */
	public function test_skip_version_veto_wins_after_keyword_match(): void {
		$keywords = $this->get_keywords(
			array(
				'upgrade_notice_keywords'         => 'security',
				'upgrade_notice_keywords_enabled' => true,
				'plugin_auto_updates'             => 'on',
				'plugin_auto_updates_custom'      => 'off',
				'skipped_plugin_versions'         => array(
					'akismet/akismet.php' => '5.3.2',
				),
			)
		);
		$item     = (object) array(
			'plugin'         => 'akismet/akismet.php',
			'new_version'    => '5.3.2',
			'upgrade_notice' => 'This is a security release.',
		);

		$this->assertTrue( $keywords->auto_update_plugin( false, $item ) );

		$skip = Skip_Version::get_instance();
		$this->assertFalse( $skip->auto_update_plugin( true, $item ) );
	}
}
