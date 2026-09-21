<?php

/**
 * Plugin options helper.
 *
 * @package updatebee
 */

namespace UpdateBee;

/**
 * Class Options.
 */
class Options {


	/**
	 * A static cached version of the main options for the plugin.
	 *
	 * @var array<string, mixed>
	 */
	private static $options = array();

	/**
	 * Options key.
	 *
	 * @var string
	 */
	private static $options_key = 'updatebee_options';

	/**
	 * Retrieve default options for the plugin.
	 *
	 * @return array<string, mixed>
	 */
	private static function get_defaults(): array {
		$defaults = array(
			'major_updates'                   => 'off',
			'minor_updates'                   => 'on',
			'plugin_updates'                  => 'off',
			'plugin_custom'                   => 'off', /* choose per plugin */
			'plugin_manual_updates_only'      => 'off',
			'plugin_patches_only'             => 'off',
			'plugin_updates_disabled'         => 'off', /* all plugin updates disabled */
			'theme_updates'                   => 'off',
			'theme_custom'                    => 'off', /* choose per theme */
			'theme_manual_updates_only'       => 'off',
			'theme_patches_only'              => 'off',
			'theme_updates_disabled'          => 'off', /* all theme updates disabled */
			'translation_updates'             => 'on',
			'translation_custom'              => 'off', /* choose per translation */
			'translation_manual_updates_only' => 'off',
			'auto_update_plugins'             => array(),
			'blocked_plugins'                 => array(),
			'auto_update_themes'              => array(),
			'blocked_themes'                  => array(),
			'auto_update_translations'        => array(),
			'blocked_translations'            => array(),
			'enable_core_emails'              => true,
		);

		/**
		 * Filter for option defaults.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $defaults Option defaults.
		 */
		return (array) apply_filters( 'updatebee_options_defaults', $defaults );
	}

	/**
	 * Reset the in-memory options cache.
	 */
	public static function flush_cache(): void {
		self::$options = array();
	}

	/**
	 * Retrieve the plugin's options.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key          Array key to retrieve (if left empty, returns all options).
	 * @param bool   $force_reload Whether to retrieve cached options or forcefully retrieve from the database.
	 * @return mixed All options for key, value for $option, empty array on failure.
	 */
	public static function get_options( string $key = '', bool $force_reload = false ) {
		$options = self::$options;

		if ( empty( $options ) || true === $force_reload ) {
			$options = get_site_option( self::$options_key, array() );
			if ( ! is_array( $options ) ) {
				$options = array();
			}
		}

		$options       = wp_parse_args( $options, self::get_defaults() );
		self::$options = $options;

		if ( ! empty( $key ) && array_key_exists( $key, $options ) ) {
			return $options[ $key ];
		}

		if ( ! empty( $key ) ) {
			return array();
		}

		return $options;
	}

	/**
	 * Save options for the plugin.
	 *
	 * @param string                      $key     Key to update.
	 * @param array<string, mixed>|string $options Options to save.
	 * @param bool                        $force   True to forcefully retrieve fresh options.
	 * @return array<string, mixed>|null Options.
	 */
	public static function update_options( string $key = '', array|string $options = array(), bool $force = false ) {
		$cached_options = self::get_options( '', $force );

		if ( ! is_array( $cached_options ) ) {
			return null;
		}

		if ( array_key_exists( $key, $cached_options ) && is_array( $options ) && array_key_exists( $key, $options ) ) {
			$cached_options[ $key ] = $options[ $key ];
			/**
			 * Filter for saving options.
			 *
			 * @since 1.0.0
			 *
			 * @param array<string, mixed> $cached_options Options.
			 * @param string               $key            Option key.
			 * @param mixed                $options        Incoming options.
			 * @param bool                 $force          Force reload.
			 */
			$cached_options = apply_filters( 'updatebee_options_save_pre', $cached_options, $key, $options, $force );
			$cached_options = Functions::parse_args_r( $cached_options, self::get_defaults() );
			update_site_option( self::$options_key, $cached_options );
		} elseif ( is_array( $options ) && empty( $key ) ) {
			$cached_options = Functions::parse_args_r( $options, $cached_options );
			$cached_options = apply_filters( 'updatebee_options_save_pre', $cached_options, $key, $options, $force );
			$cached_options = Functions::parse_args_r( $cached_options, self::get_defaults() );
			update_site_option( self::$options_key, $cached_options );
		} elseif ( is_array( $options ) && array_key_exists( $key, $cached_options ) ) {
			$cached_options[ $key ] = $options;
			$cached_options         = apply_filters( 'updatebee_options_save_pre', $cached_options, $key, $options, $force );
			$cached_options         = Functions::parse_args_r( $cached_options, self::get_defaults() );
			update_site_option( self::$options_key, $cached_options );
		} else {
			return null;
		}

		self::$options = $cached_options;

		return self::$options;
	}

	/**
	 * Clear/Reset all options to defaults.
	 */
	public static function reset_options(): void {
		self::update_options( '', self::get_defaults() );
	}
}
