<?php
/**
 * Abilities API registration.
 *
 * @package updatebee
 */

namespace UpdateBee\Abilities;

use UpdateBee\Functions;

/**
 * Registers UpdateBee abilities for REST clients and AI agents.
 */
class Registry {

	/**
	 * Hook registration when the Abilities API is available.
	 */
	public static function boot(): void {
		add_action( 'wp_abilities_api_categories_init', array( self::class, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( self::class, 'register_abilities' ) );
	}

	/**
	 * Register the updatebee ability category.
	 */
	public static function register_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			'updatebee',
			array(
				'label'       => __( 'UpdateBee', 'updatebee' ),
				'description' => __( 'Inspect and control automatic WordPress updates.', 'updatebee' ),
			)
		);
	}

	/**
	 * Register public UpdateBee abilities.
	 */
	public static function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		self::register(
			'get-update-policy',
			__( 'Get Update Policy', 'updatebee' ),
			__( 'Returns the site-wide automatic-update policy for core, plugins, themes, and translations, including delay days, untested-update blocking, upgrade-notice keywords, fatal detection, and whether core update emails are enabled. slack_configured is true when a Slack webhook is saved. The webhook URL and branding fields are not returned.', 'updatebee' ),
			array( Read::class, 'get_update_policy' ),
			self::annotations( true, false, true ),
			self::policy_schema()
		);

		self::register(
			'list-plugins',
			__( 'List Plugins', 'updatebee' ),
			__( 'Lists installed plugins. Each row includes the plugin basename in file, name, version, whether it is active, whether an update is available, and the effective auto-update, blocked, and patches-only state, plus delay days, the untested-update exemption, and notes. auto_update is whether that plugin would auto-update under the current site policy.', 'updatebee' ),
			array( Read::class, 'list_plugins' ),
			self::annotations( true, false, true ),
			self::object_schema(
				array(
					'plugins' => array(
						'type'        => 'array',
						'description' => __( 'Installed plugins.', 'updatebee' ),
						'items'       => self::plugin_schema(),
					),
				),
				array( 'plugins' )
			)
		);

		self::register(
			'list-themes',
			__( 'List Themes', 'updatebee' ),
			__( 'Lists installed themes. Each row includes stylesheet, name, version, whether it is the active theme, whether an update is available, and the effective auto-update, blocked, and patches-only state, plus delay days, the untested-update exemption, and notes.', 'updatebee' ),
			array( Read::class, 'list_themes' ),
			self::annotations( true, false, true ),
			self::object_schema(
				array(
					'themes' => array(
						'type'        => 'array',
						'description' => __( 'Installed themes.', 'updatebee' ),
						'items'       => self::theme_schema(),
					),
				),
				array( 'themes' )
			)
		);

		self::register(
			'list-translations',
			__( 'List Translations', 'updatebee' ),
			__( 'Lists WordPress core languages. slug is the locale, for example da_DK. type is core. auto_update and blocked describe the effective translation policy for that locale.', 'updatebee' ),
			array( Read::class, 'list_translations' ),
			self::annotations( true, false, true ),
			self::object_schema(
				array(
					'translations' => array(
						'type'        => 'array',
						'description' => __( 'WordPress language catalog.', 'updatebee' ),
						'items'       => self::translation_schema(),
					),
				),
				array( 'translations' )
			)
		);

		self::register(
			'get-available-updates',
			__( 'Get Available Updates', 'updatebee' ),
			__( 'Returns pending core, plugin, theme, and translation updates from the WordPress update transients. This is separate from update policy: an item can be listed here even when automatic updates are off. Versions are reported as from_version and to_version where WordPress provides both.', 'updatebee' ),
			array( Read::class, 'get_available_updates' ),
			self::annotations( true, false, true ),
			self::available_updates_schema()
		);

		self::register(
			'get-logs',
			__( 'Get Update Logs', 'updatebee' ),
			__( 'Returns paginated UpdateBee log rows. Filter with update_offer (automatic or manual), update_type (core, plugin, theme, or translation), and update_status (success, failure, fatal, blocked, skipped, or delayed). per_page is capped at 50. Use get-log for notes and the stack trace.', 'updatebee' ),
			array( Read::class, 'get_logs' ),
			self::annotations( true, false, true ),
			self::log_page_schema(),
			self::log_query_schema()
		);

		self::register(
			'get-log',
			__( 'Get Update Log', 'updatebee' ),
			__( 'Returns one UpdateBee log row by id, including the stored notes and stack trace. Returns an error when the id does not exist.', 'updatebee' ),
			array( Read::class, 'get_log' ),
			self::annotations( true, false, true ),
			self::log_detail_schema(),
			self::object_schema(
				array(
					'id' => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => __( 'Log row id.', 'updatebee' ),
					),
				),
				array( 'id' ),
				true
			)
		);

		self::register(
			'get-fatal-findings',
			__( 'Get Fatal Findings', 'updatebee' ),
			__( 'Returns fatal-error findings recorded for the current request after an update, plus any fatal that could not be attributed to one extension. enabled reports whether fatal detection is turned on. Findings are empty when this request has not inspected an update.', 'updatebee' ),
			array( Read::class, 'get_fatal_findings' ),
			self::annotations( true, false, true ),
			self::object_schema(
				array(
					'enabled'      => self::bool_property( __( 'Whether fatal detection is enabled.', 'updatebee' ) ),
					'findings'     => array(
						'type'        => 'array',
						'description' => __( 'Fatals attributed to an updated extension.', 'updatebee' ),
						'items'       => array(
							'type' => 'object',
						),
					),
					'unattributed' => array(
						'description' => __( 'A fatal that could not be attributed to one extension, or null.', 'updatebee' ),
						'type'        => array( 'object', 'null' ),
					),
				),
				array( 'enabled', 'findings', 'unattributed' )
			)
		);

		self::register(
			'get-update-schedule',
			__( 'Get Update Schedule', 'updatebee' ),
			__( 'Returns the next scheduled run for wp_update_plugins, wp_update_themes, and wp_version_check. wp_cron_disabled is true when UpdateBee is set to disable WP-Cron. This ability does not change that setting.', 'updatebee' ),
			array( Read::class, 'get_update_schedule' ),
			self::annotations( true, false, true ),
			self::schedule_schema()
		);

		self::register(
			'set-update-policy',
			__( 'Set Update Policy', 'updatebee' ),
			__( 'Changes site-wide automatic-update settings. Send only the fields to change. On/off fields accept "on" or "off": major and minor core updates, and for plugins, themes, and translations the auto, custom, manual-only, patches-only, and disabled switches, plus block_untested_updates. delay_updates is a non-negative number of days. Unknown keys are rejected. This does not change Slack, branding, email recipients, or installed software.', 'updatebee' ),
			array( Policy::class, 'set_update_policy' ),
			self::annotations( false, false, true ),
			self::policy_schema(),
			self::policy_input_schema()
		);

		self::register(
			'set-plugin-policy',
			__( 'Set Plugin Policy', 'updatebee' ),
			__( 'Changes auto-update, block, patches-only, delay, untested-update, or note settings for one installed plugin. file is the plugin basename, for example akismet/akismet.php. Only fields you send are changed. delay_days and allow_untested accept null to inherit the site setting. The returned auto_update flag is whether that plugin would auto-update under the current site policy. This does not install, update, or delete the plugin.', 'updatebee' ),
			array( Policy::class, 'set_plugin_policy' ),
			self::annotations( false, false, true ),
			self::plugin_schema(),
			self::extension_input_schema(
				'file',
				__( 'Plugin basename, for example akismet/akismet.php.', 'updatebee' )
			)
		);

		self::register(
			'set-theme-policy',
			__( 'Set Theme Policy', 'updatebee' ),
			__( 'Changes auto-update, block, patches-only, delay, untested-update, or note settings for one installed theme. stylesheet is the theme directory name. Only fields you send are changed. delay_days and allow_untested accept null to inherit the site setting. This does not install, update, or delete the theme.', 'updatebee' ),
			array( Policy::class, 'set_theme_policy' ),
			self::annotations( false, false, true ),
			self::theme_schema(),
			self::extension_input_schema(
				'stylesheet',
				__( 'Theme stylesheet directory, for example twentytwentyfour.', 'updatebee' )
			)
		);

		self::register(
			'set-translation-policy',
			__( 'Set Translation Policy', 'updatebee' ),
			__( 'Enables or blocks automatic updates for one WordPress language. slug is the locale, for example da_DK. Send auto_update, blocked, or both. The returned auto_update flag is whether that locale would auto-update under the current site policy. This does not install translations.', 'updatebee' ),
			array( Policy::class, 'set_translation_policy' ),
			self::annotations( false, false, true ),
			self::translation_schema(),
			self::object_schema(
				array(
					'slug'        => array(
						'type'        => 'string',
						'minLength'   => 1,
						'description' => __( 'Locale slug, for example da_DK.', 'updatebee' ),
					),
					'auto_update' => self::bool_property( __( 'Whether this locale is on the auto-update list.', 'updatebee' ) ),
					'blocked'     => self::bool_property( __( 'Whether updates for this locale are blocked.', 'updatebee' ) ),
				),
				array( 'slug' ),
				true
			)
		);

		self::register(
			'refresh-updates',
			__( 'Refresh Updates', 'updatebee' ),
			__( 'Clears the WordPress update cache and checks core, plugins, and themes for new versions. This does not install updates or change installed versions.', 'updatebee' ),
			array( Actions::class, 'refresh_updates' ),
			self::annotations( false, false, true ),
			self::message_schema()
		);

		self::register(
			'schedule-update-checks',
			__( 'Schedule Update Checks', 'updatebee' ),
			__( 'Schedules the WordPress plugin, theme, and core version-check events to run on the next cron pass. This does not install updates.', 'updatebee' ),
			array( Actions::class, 'schedule_update_checks' ),
			self::annotations( false, false, true ),
			self::message_schema()
		);

		self::register(
			'run-auto-updates',
			__( 'Run Automatic Updates', 'updatebee' ),
			__( 'Runs WordPress automatic updates now for core, plugins, themes, and translations that the current policy allows. This can change the site by installing those updates. Items the policy blocks or leaves on manual updates are not updated.', 'updatebee' ),
			array( Actions::class, 'run_auto_updates' ),
			self::annotations( false, true, false ),
			self::message_schema()
		);
	}

	/**
	 * Whether the current user may run UpdateBee abilities.
	 *
	 * Matches the plugin settings screen: manage_network on multisite, manage_options on a single site.
	 *
	 * @param mixed $input Ability input. Permission does not depend on it.
	 * @return bool True when the user can manage UpdateBee settings.
	 */
	public static function can_execute( $input = null ): bool {
		unset( $input );

		return Functions::can_access_options();
	}

	/**
	 * Register one public ability.
	 *
	 * @param string               $name        Ability name without the updatebee/ prefix.
	 * @param string               $label       Human-readable label.
	 * @param string               $description Agent-facing description.
	 * @param callable             $execute     Execute callback.
	 * @param array<string, bool>  $annotations Behavior hints.
	 * @param array<string, mixed> $output      Output JSON schema.
	 * @param array<string, mixed> $input       Input JSON schema. Empty skips the schema.
	 */
	private static function register( string $name, string $label, string $description, callable $execute, array $annotations, array $output, array $input = array() ): void {
		$args = array(
			'label'               => $label,
			'description'         => $description,
			'category'            => 'updatebee',
			'output_schema'       => $output,
			'execute_callback'    => $execute,
			'permission_callback' => array( self::class, 'can_execute' ),
			'meta'                => array(
				'public'      => true,
				'annotations' => $annotations,
			),
		);

		if ( ! empty( $input ) ) {
			$args['input_schema'] = $input;
		}

		wp_register_ability( 'updatebee/' . $name, $args );
	}

	/**
	 * Behavior hints stored on an ability.
	 *
	 * @param bool $read_only   Whether the ability only reads data.
	 * @param bool $destructive Whether the ability can change the site destructively.
	 * @param bool $idempotent  Whether repeating the same call has no further effect.
	 * @return array<string, bool> Annotations.
	 */
	private static function annotations( bool $read_only, bool $destructive, bool $idempotent ): array {
		return array(
			'readonly'    => $read_only,
			'destructive' => $destructive,
			'idempotent'  => $idempotent,
		);
	}

	/**
	 * Object JSON schema.
	 *
	 * @param array<string, mixed> $properties Schema properties.
	 * @param array<int, string>   $required   Required property names.
	 * @param bool                 $strict     Whether to reject unknown properties.
	 * @return array<string, mixed> JSON schema.
	 */
	private static function object_schema( array $properties, array $required = array(), bool $strict = false ): array {
		$schema = array(
			'type'       => 'object',
			'properties' => $properties,
		);

		if ( ! empty( $required ) ) {
			$schema['required'] = $required;
		}

		if ( $strict ) {
			$schema['additionalProperties'] = false;
		}

		return $schema;
	}

	/**
	 * Boolean schema property.
	 *
	 * @param string $description Property description.
	 * @return array<string, string> Schema property.
	 */
	private static function bool_property( string $description ): array {
		return array(
			'type'        => 'boolean',
			'description' => $description,
		);
	}

	/**
	 * String schema property.
	 *
	 * @param string $description Property description.
	 * @return array<string, string> Schema property.
	 */
	private static function string_property( string $description ): array {
		return array(
			'type'        => 'string',
			'description' => $description,
		);
	}

	/**
	 * Schema for the site-wide policy payload.
	 *
	 * @return array<string, mixed> JSON schema.
	 */
	private static function policy_schema(): array {
		$properties = array();
		foreach ( Policy::writable_on_off_keys() as $key ) {
			$properties[ $key ] = array(
				'type' => 'string',
				'enum' => array( 'on', 'off' ),
			);
		}

		$properties['delay_updates']                   = array(
			'type'        => 'integer',
			'description' => __( 'Days to wait before an automatic update is eligible.', 'updatebee' ),
		);
		$properties['upgrade_notice_keywords']         = self::string_property( __( 'Newline-separated upgrade-notice keywords.', 'updatebee' ) );
		$properties['upgrade_notice_keywords_enabled'] = self::bool_property( __( 'Whether upgrade-notice keywords force an automatic update.', 'updatebee' ) );
		$properties['fatal_error_detection']           = self::bool_property( __( 'Whether post-update fatal detection is enabled.', 'updatebee' ) );
		$properties['enable_core_emails']              = self::bool_property( __( 'Whether WordPress core update emails are enabled.', 'updatebee' ) );
		$properties['slack_configured']                = self::bool_property( __( 'Whether a Slack webhook URL is saved. The URL itself is not returned.', 'updatebee' ) );

		return self::object_schema( $properties, array_keys( $properties ) );
	}

	/**
	 * Input schema for set-update-policy.
	 *
	 * @return array<string, mixed> JSON schema.
	 */
	private static function policy_input_schema(): array {
		$properties = array();
		foreach ( Policy::writable_on_off_keys() as $key ) {
			$properties[ $key ] = array(
				'type' => 'string',
				'enum' => array( 'on', 'off' ),
			);
		}

		$properties['delay_updates'] = array(
			'type'        => 'integer',
			'minimum'     => 0,
			'description' => __( 'Days to wait before an automatic update is eligible.', 'updatebee' ),
		);

		$schema                         = self::object_schema( $properties, array(), true );
		$schema['minProperties']        = 1;
		$schema['additionalProperties'] = false;

		return $schema;
	}

	/**
	 * Schema for one plugin row.
	 *
	 * @return array<string, mixed> JSON schema.
	 */
	private static function plugin_schema(): array {
		return self::object_schema(
			array(
				'file'             => self::string_property( __( 'Plugin basename, for example akismet/akismet.php.', 'updatebee' ) ),
				'name'             => self::string_property( __( 'Plugin name.', 'updatebee' ) ),
				'version'          => self::string_property( __( 'Installed version.', 'updatebee' ) ),
				'active'           => self::bool_property( __( 'Whether the plugin is active.', 'updatebee' ) ),
				'update_available' => self::bool_property( __( 'Whether WordPress offers an update.', 'updatebee' ) ),
				'auto_update'      => self::bool_property( __( 'Whether the plugin would auto-update under the current site policy.', 'updatebee' ) ),
				'blocked'          => self::bool_property( __( 'Whether updates for the plugin are blocked.', 'updatebee' ) ),
				'patches_only'     => self::bool_property( __( 'Whether automatic updates are limited to patches.', 'updatebee' ) ),
				'delay_days'       => array(
					'type'        => array( 'integer', 'null' ),
					'description' => __( 'Per-plugin delay in days, or null to inherit the site delay.', 'updatebee' ),
				),
				'allow_untested'   => self::bool_property( __( 'Whether the plugin may update when it is untested with this WordPress version.', 'updatebee' ) ),
				'notes'            => self::string_property( __( 'Operator notes.', 'updatebee' ) ),
			),
			array( 'file', 'name', 'version', 'active', 'update_available', 'auto_update', 'blocked', 'patches_only', 'delay_days', 'allow_untested', 'notes' )
		);
	}

	/**
	 * Schema for one theme row.
	 *
	 * @return array<string, mixed> JSON schema.
	 */
	private static function theme_schema(): array {
		return self::object_schema(
			array(
				'stylesheet'       => self::string_property( __( 'Theme stylesheet directory.', 'updatebee' ) ),
				'name'             => self::string_property( __( 'Theme name.', 'updatebee' ) ),
				'version'          => self::string_property( __( 'Installed version.', 'updatebee' ) ),
				'active'           => self::bool_property( __( 'Whether this is the active theme.', 'updatebee' ) ),
				'update_available' => self::bool_property( __( 'Whether WordPress offers an update.', 'updatebee' ) ),
				'auto_update'      => self::bool_property( __( 'Whether the theme would auto-update under the current site policy.', 'updatebee' ) ),
				'blocked'          => self::bool_property( __( 'Whether updates for the theme are blocked.', 'updatebee' ) ),
				'patches_only'     => self::bool_property( __( 'Whether automatic updates are limited to patches.', 'updatebee' ) ),
				'delay_days'       => array(
					'type'        => array( 'integer', 'null' ),
					'description' => __( 'Per-theme delay in days, or null to inherit the site delay.', 'updatebee' ),
				),
				'allow_untested'   => self::bool_property( __( 'Whether the theme may update when it is untested with this WordPress version.', 'updatebee' ) ),
				'notes'            => self::string_property( __( 'Operator notes.', 'updatebee' ) ),
			),
			array( 'stylesheet', 'name', 'version', 'active', 'update_available', 'auto_update', 'blocked', 'patches_only', 'delay_days', 'allow_untested', 'notes' )
		);
	}

	/**
	 * Schema for one translation row.
	 *
	 * @return array<string, mixed> JSON schema.
	 */
	private static function translation_schema(): array {
		return self::object_schema(
			array(
				'slug'        => self::string_property( __( 'Locale slug, for example da_DK.', 'updatebee' ) ),
				'language'    => self::string_property( __( 'Language name.', 'updatebee' ) ),
				'type'        => self::string_property( __( 'Translation type. Catalog rows use core.', 'updatebee' ) ),
				'auto_update' => self::bool_property( __( 'Whether the locale would auto-update under the current site policy.', 'updatebee' ) ),
				'blocked'     => self::bool_property( __( 'Whether updates for the locale are blocked.', 'updatebee' ) ),
			),
			array( 'slug', 'language', 'type', 'auto_update', 'blocked' )
		);
	}

	/**
	 * Input schema for plugin or theme policy changes.
	 *
	 * @param string $identifier  file or stylesheet.
	 * @param string $description Identifier description.
	 * @return array<string, mixed> JSON schema.
	 */
	private static function extension_input_schema( string $identifier, string $description ): array {
		$schema                  = self::object_schema(
			array(
				$identifier      => array(
					'type'        => 'string',
					'minLength'   => 1,
					'description' => $description,
				),
				'auto_update'    => self::bool_property( __( 'Whether to include this item in the auto-update list.', 'updatebee' ) ),
				'blocked'        => self::bool_property( __( 'Whether to block updates for this item.', 'updatebee' ) ),
				'patches_only'   => self::bool_property( __( 'Whether to limit automatic updates to patches.', 'updatebee' ) ),
				'delay_days'     => array(
					'description' => __( 'Days to wait, or null to inherit the site delay.', 'updatebee' ),
					'type'        => array( 'integer', 'null' ),
				),
				'allow_untested' => array(
					'description' => __( 'Whether to allow untested updates, or null to inherit the site setting.', 'updatebee' ),
					'type'        => array( 'boolean', 'null' ),
				),
				'notes'          => self::string_property( __( 'Operator notes. An empty string clears them.', 'updatebee' ) ),
			),
			array( $identifier ),
			true
		);
		$schema['minProperties'] = 2;

		return $schema;
	}

	/**
	 * Schema for pending updates.
	 *
	 * @return array<string, mixed> JSON schema.
	 */
	private static function available_updates_schema(): array {
		return self::object_schema(
			array(
				'core'         => array(
					'type'  => 'array',
					'items' => self::object_schema(
						array(
							'slug'         => self::string_property( __( 'Core identifier. Always wordpress.', 'updatebee' ) ),
							'from_version' => self::string_property( __( 'Installed WordPress version that was checked.', 'updatebee' ) ),
							'to_version'   => self::string_property( __( 'Offered WordPress version.', 'updatebee' ) ),
							'response'     => self::string_property( __( 'WordPress update response, such as upgrade.', 'updatebee' ) ),
							'locale'       => self::string_property( __( 'Locale for this offer, when WordPress provides one.', 'updatebee' ) ),
						),
						array( 'slug', 'from_version', 'to_version', 'response', 'locale' )
					),
				),
				'plugins'      => array(
					'type'  => 'array',
					'items' => self::object_schema(
						array(
							'file'         => self::string_property( __( 'Plugin basename.', 'updatebee' ) ),
							'slug'         => self::string_property( __( 'Plugin slug.', 'updatebee' ) ),
							'from_version' => self::string_property( __( 'Installed version.', 'updatebee' ) ),
							'to_version'   => self::string_property( __( 'Offered version.', 'updatebee' ) ),
						),
						array( 'file', 'slug', 'from_version', 'to_version' )
					),
				),
				'themes'       => array(
					'type'  => 'array',
					'items' => self::object_schema(
						array(
							'stylesheet'   => self::string_property( __( 'Theme stylesheet.', 'updatebee' ) ),
							'from_version' => self::string_property( __( 'Installed version.', 'updatebee' ) ),
							'to_version'   => self::string_property( __( 'Offered version.', 'updatebee' ) ),
						),
						array( 'stylesheet', 'from_version', 'to_version' )
					),
				),
				'translations' => array(
					'type'  => 'array',
					'items' => self::object_schema(
						array(
							'type'         => self::string_property( __( 'core, plugin, or theme.', 'updatebee' ) ),
							'slug'         => self::string_property( __( 'Project slug.', 'updatebee' ) ),
							'language'     => self::string_property( __( 'Locale.', 'updatebee' ) ),
							'from_version' => self::string_property( __( 'Previous translation version. Empty when WordPress does not provide one.', 'updatebee' ) ),
							'to_version'   => self::string_property( __( 'Offered translation version.', 'updatebee' ) ),
						),
						array( 'type', 'slug', 'language', 'from_version', 'to_version' )
					),
				),
			),
			array( 'core', 'plugins', 'themes', 'translations' )
		);
	}

	/**
	 * Input schema for get-logs.
	 *
	 * @return array<string, mixed> JSON schema.
	 */
	private static function log_query_schema(): array {
		$schema            = self::object_schema(
			array(
				'page'          => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => __( 'Page of results, starting at 1.', 'updatebee' ),
				),
				'per_page'      => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'maximum'     => 50,
					'description' => __( 'Rows per page. Maximum 50.', 'updatebee' ),
				),
				'search'        => self::string_property( __( 'Search name or slug.', 'updatebee' ) ),
				'update_offer'  => array(
					'type' => 'string',
					'enum' => array( 'automatic', 'manual' ),
				),
				'update_type'   => array(
					'type' => 'string',
					'enum' => array( 'core', 'plugin', 'theme', 'translation' ),
				),
				'update_status' => array(
					'type' => 'string',
					'enum' => array( 'success', 'failure', 'fatal', 'blocked', 'skipped', 'delayed' ),
				),
			),
			array(),
			true
		);
		$schema['default'] = array();

		return $schema;
	}

	/**
	 * Schema for a page of log summaries.
	 *
	 * @return array<string, mixed> JSON schema.
	 */
	private static function log_page_schema(): array {
		return self::object_schema(
			array(
				'logs'     => array(
					'type'  => 'array',
					'items' => self::log_summary_schema(),
				),
				'total'    => array(
					'type'        => 'integer',
					'description' => __( 'Total rows matching the filter.', 'updatebee' ),
				),
				'page'     => array(
					'type' => 'integer',
				),
				'per_page' => array(
					'type' => 'integer',
				),
			),
			array( 'logs', 'total', 'page', 'per_page' )
		);
	}

	/**
	 * Schema for one log summary.
	 *
	 * @return array<string, mixed> JSON schema.
	 */
	private static function log_summary_schema(): array {
		return self::object_schema(
			array(
				'id'            => array(
					'type' => 'integer',
				),
				'name'          => self::string_property( __( 'Updated item name.', 'updatebee' ) ),
				'slug'          => self::string_property( __( 'Updated item slug or file.', 'updatebee' ) ),
				'user_id'       => array(
					'type' => 'integer',
				),
				'from_version'  => self::string_property( __( 'Version before the attempt.', 'updatebee' ) ),
				'to_version'    => self::string_property( __( 'Version offered or installed.', 'updatebee' ) ),
				'update_offer'  => self::string_property( __( 'automatic or manual.', 'updatebee' ) ),
				'update_type'   => self::string_property( __( 'core, plugin, theme, or translation.', 'updatebee' ) ),
				'update_status' => self::string_property( __( 'success, failure, fatal, blocked, skipped, or delayed.', 'updatebee' ) ),
				'date'          => self::string_property( __( 'Log timestamp.', 'updatebee' ) ),
			),
			array( 'id', 'name', 'slug', 'user_id', 'from_version', 'to_version', 'update_offer', 'update_type', 'update_status', 'date' )
		);
	}

	/**
	 * Schema for one detailed log row.
	 *
	 * @return array<string, mixed> JSON schema.
	 */
	private static function log_detail_schema(): array {
		$schema                             = self::log_summary_schema();
		$schema['properties']['raw']        = self::string_property( __( 'Stored notes for this log row.', 'updatebee' ) );
		$schema['properties']['stacktrace'] = self::string_property( __( 'Stored stack trace.', 'updatebee' ) );
		$schema['required'][]               = 'raw';
		$schema['required'][]               = 'stacktrace';

		return $schema;
	}

	/**
	 * Schema for the update-check schedule.
	 *
	 * @return array<string, mixed> JSON schema.
	 */
	private static function schedule_schema(): array {
		return self::object_schema(
			array(
				'wp_cron_disabled' => self::bool_property( __( 'Whether UpdateBee is set to disable WP-Cron.', 'updatebee' ) ),
				'events'           => array(
					'type'  => 'array',
					'items' => self::object_schema(
						array(
							'hook'      => self::string_property( __( 'Cron hook.', 'updatebee' ) ),
							'scheduled' => self::bool_property( __( 'Whether the hook has a future event.', 'updatebee' ) ),
							'timestamp' => array(
								'type'        => array( 'integer', 'null' ),
								'description' => __( 'Unix timestamp of the next run, or null when unscheduled.', 'updatebee' ),
							),
							'schedule'  => array(
								'type'        => array( 'string', 'null' ),
								'description' => __( 'Recurrence slug, or null for a single event or an unscheduled hook.', 'updatebee' ),
							),
						),
						array( 'hook', 'scheduled', 'timestamp', 'schedule' )
					),
				),
			),
			array( 'wp_cron_disabled', 'events' )
		);
	}

	/**
	 * Schema for an operation that returns a message.
	 *
	 * @return array<string, mixed> JSON schema.
	 */
	private static function message_schema(): array {
		return self::object_schema(
			array(
				'message' => self::string_property( __( 'What the ability did.', 'updatebee' ) ),
			),
			array( 'message' )
		);
	}
}
