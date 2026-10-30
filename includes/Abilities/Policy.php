<?php
/**
 * Policy-write ability callbacks.
 *
 * @package updatebee
 */

namespace UpdateBee\Abilities;

use UpdateBee\Functions;
use UpdateBee\Options;
use UpdateBee\Tables\Table_Plugins;
use UpdateBee\Tables\Table_Themes;

/**
 * Settings changes that do not install or delete software.
 */
class Policy {

	/**
	 * Dashboard and untested-update keys that accept `on` or `off`.
	 *
	 * @return array<int, string> Option keys.
	 */
	public static function writable_on_off_keys(): array {
		return array(
			'major_auto_updates',
			'minor_auto_updates',
			'plugin_auto_updates',
			'plugin_auto_updates_custom',
			'plugin_updates_disabled',
			'plugin_manual_updates_only',
			'plugin_auto_updates_patches_only',
			'theme_auto_updates',
			'theme_auto_updates_custom',
			'theme_updates_disabled',
			'theme_manual_updates_only',
			'theme_auto_updates_patches_only',
			'translation_auto_updates',
			'translation_auto_updates_custom',
			'translation_updates_disabled',
			'translation_manual_updates_only',
			'block_untested_updates',
		);
	}

	/**
	 * Option keys set-update-policy accepts.
	 *
	 * @return array<int, string> Option keys.
	 */
	public static function allowed_policy_keys(): array {
		return array_merge( self::writable_on_off_keys(), array( 'delay_updates' ) );
	}

	/**
	 * Partially update site-wide automatic-update policy.
	 *
	 * @param mixed $input Policy fields to change.
	 * @return array<string, mixed>|\WP_Error Updated policy, or an error.
	 */
	public static function set_update_policy( $input = null ) {
		if ( ! is_array( $input ) ) {
			return new \WP_Error( 'updatebee_invalid_policy', __( 'Update policy input must be an object.', 'updatebee' ) );
		}

		if ( array() === $input ) {
			return new \WP_Error( 'updatebee_empty_policy', __( 'Provide at least one update policy field to change.', 'updatebee' ) );
		}

		$unknown = self::reject_unknown_keys( $input, self::allowed_policy_keys() );
		if ( is_wp_error( $unknown ) ) {
			return $unknown;
		}

		$updates = array();
		foreach ( $input as $key => $value ) {
			if ( 'delay_updates' === $key ) {
				if ( ! is_int( $value ) ) {
					return new \WP_Error( 'updatebee_invalid_policy_value', __( 'Delay days must be an integer.', 'updatebee' ) );
				}

				$updates[ $key ] = max( 0, $value );
				continue;
			}

			if ( ! is_string( $value ) || ! in_array( $value, array( 'on', 'off' ), true ) ) {
				return new \WP_Error(
					'updatebee_invalid_policy_value',
					sprintf(
						/* translators: %s: option key. */
						__( 'Policy field %s must be "on" or "off".', 'updatebee' ),
						$key
					)
				);
			}

			$updates[ $key ] = $value;
		}

		$saved = Options::update_options( '', $updates );
		if ( null === $saved ) {
			return new \WP_Error( 'updatebee_policy_save_failed', __( 'Update policy could not be saved.', 'updatebee' ) );
		}

		return Read::get_update_policy();
	}

	/**
	 * Update policy for one installed plugin.
	 *
	 * @param mixed $input Plugin file and the fields to change.
	 * @return array<string, mixed>|\WP_Error Updated plugin row, or an error.
	 */
	public static function set_plugin_policy( $input = null ) {
		if ( ! is_array( $input ) ) {
			return new \WP_Error( 'updatebee_invalid_policy', __( 'Plugin policy input must be an object.', 'updatebee' ) );
		}

		$unknown = self::reject_unknown_keys(
			$input,
			array( 'file', 'auto_update', 'blocked', 'patches_only', 'delay_days', 'allow_untested', 'notes' )
		);
		if ( is_wp_error( $unknown ) ) {
			return $unknown;
		}

		$file = isset( $input['file'] ) && is_string( $input['file'] ) ? $input['file'] : '';
		if ( ! Functions::plugin_file_exists( $file ) ) {
			return new \WP_Error(
				'updatebee_plugin_not_found',
				sprintf(
					/* translators: %s: plugin basename. */
					__( 'Plugin file %s was not found.', 'updatebee' ),
					$file
				)
			);
		}

		$present = self::present_keys( $input, array( 'auto_update', 'blocked', 'patches_only', 'delay_days', 'allow_untested', 'notes' ) );
		if ( is_wp_error( $present ) ) {
			return $present;
		}

		if ( array_key_exists( 'auto_update', $input ) ) {
			$valid = self::assert_bool( $input['auto_update'], 'auto_update' );
			if ( is_wp_error( $valid ) ) {
				return $valid;
			}

			$saved = Functions::set_plugin_auto_update( $file, $input['auto_update'] );
			if ( null === $saved ) {
				return self::save_failed();
			}
		}

		if ( array_key_exists( 'blocked', $input ) ) {
			$valid = self::assert_bool( $input['blocked'], 'blocked' );
			if ( is_wp_error( $valid ) ) {
				return $valid;
			}

			$saved = Functions::set_plugin_blocked( $file, $input['blocked'] );
			if ( null === $saved ) {
				return self::save_failed();
			}
		}

		if ( array_key_exists( 'patches_only', $input ) ) {
			$valid = self::assert_bool( $input['patches_only'], 'patches_only' );
			if ( is_wp_error( $valid ) ) {
				return $valid;
			}

			$saved = Functions::set_plugin_patches_only( $file, $input['patches_only'] );
			if ( null === $saved ) {
				return self::save_failed();
			}
		}

		if ( array_key_exists( 'delay_days', $input ) ) {
			$days = self::delay_days_input( $input['delay_days'] );
			if ( is_wp_error( $days ) ) {
				return $days;
			}

			$saved = Table_Plugins::save_delay_settings( $file, $days );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
		}

		if ( array_key_exists( 'allow_untested', $input ) ) {
			$allow = self::nullable_bool( $input['allow_untested'], 'allow_untested' );
			if ( is_wp_error( $allow ) ) {
				return $allow;
			}

			$saved = Table_Plugins::save_untested_setting( $file, $allow );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
		}

		if ( array_key_exists( 'notes', $input ) ) {
			if ( ! is_string( $input['notes'] ) ) {
				return new \WP_Error( 'updatebee_invalid_policy_value', __( 'Notes must be a string.', 'updatebee' ) );
			}

			$saved = Table_Plugins::save_notes( $file, $input['notes'] );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
		}

		$plugin = Read::plugin_by_file( $file );
		if ( null === $plugin ) {
			return new \WP_Error( 'updatebee_plugin_not_found', __( 'Plugin file was not found.', 'updatebee' ) );
		}

		return $plugin;
	}

	/**
	 * Update policy for one installed theme.
	 *
	 * @param mixed $input Theme stylesheet and the fields to change.
	 * @return array<string, mixed>|\WP_Error Updated theme row, or an error.
	 */
	public static function set_theme_policy( $input = null ) {
		if ( ! is_array( $input ) ) {
			return new \WP_Error( 'updatebee_invalid_policy', __( 'Theme policy input must be an object.', 'updatebee' ) );
		}

		$unknown = self::reject_unknown_keys(
			$input,
			array( 'stylesheet', 'auto_update', 'blocked', 'patches_only', 'delay_days', 'allow_untested', 'notes' )
		);
		if ( is_wp_error( $unknown ) ) {
			return $unknown;
		}

		$stylesheet = isset( $input['stylesheet'] ) && is_string( $input['stylesheet'] ) ? $input['stylesheet'] : '';
		if ( ! Functions::theme_stylesheet_exists( $stylesheet ) ) {
			return new \WP_Error(
				'updatebee_theme_not_found',
				sprintf(
					/* translators: %s: theme stylesheet. */
					__( 'Theme stylesheet %s was not found.', 'updatebee' ),
					$stylesheet
				)
			);
		}

		$present = self::present_keys( $input, array( 'auto_update', 'blocked', 'patches_only', 'delay_days', 'allow_untested', 'notes' ) );
		if ( is_wp_error( $present ) ) {
			return $present;
		}

		if ( array_key_exists( 'auto_update', $input ) ) {
			$valid = self::assert_bool( $input['auto_update'], 'auto_update' );
			if ( is_wp_error( $valid ) ) {
				return $valid;
			}

			$saved = Functions::set_theme_auto_update( $stylesheet, $input['auto_update'] );
			if ( null === $saved ) {
				return self::save_failed();
			}
		}

		if ( array_key_exists( 'blocked', $input ) ) {
			$valid = self::assert_bool( $input['blocked'], 'blocked' );
			if ( is_wp_error( $valid ) ) {
				return $valid;
			}

			$saved = Functions::set_theme_blocked( $stylesheet, $input['blocked'] );
			if ( null === $saved ) {
				return self::save_failed();
			}
		}

		if ( array_key_exists( 'patches_only', $input ) ) {
			$valid = self::assert_bool( $input['patches_only'], 'patches_only' );
			if ( is_wp_error( $valid ) ) {
				return $valid;
			}

			$saved = Functions::set_theme_patches_only( $stylesheet, $input['patches_only'] );
			if ( null === $saved ) {
				return self::save_failed();
			}
		}

		if ( array_key_exists( 'delay_days', $input ) ) {
			$days = self::delay_days_input( $input['delay_days'] );
			if ( is_wp_error( $days ) ) {
				return $days;
			}

			$saved = Table_Themes::save_delay_settings( $stylesheet, $days );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
		}

		if ( array_key_exists( 'allow_untested', $input ) ) {
			$allow = self::nullable_bool( $input['allow_untested'], 'allow_untested' );
			if ( is_wp_error( $allow ) ) {
				return $allow;
			}

			$saved = Table_Themes::save_untested_setting( $stylesheet, $allow );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
		}

		if ( array_key_exists( 'notes', $input ) ) {
			if ( ! is_string( $input['notes'] ) ) {
				return new \WP_Error( 'updatebee_invalid_policy_value', __( 'Notes must be a string.', 'updatebee' ) );
			}

			$saved = Table_Themes::save_notes( $stylesheet, $input['notes'] );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
		}

		$theme = Read::theme_by_stylesheet( $stylesheet );
		if ( null === $theme ) {
			return new \WP_Error( 'updatebee_theme_not_found', __( 'Theme stylesheet was not found.', 'updatebee' ) );
		}

		return $theme;
	}

	/**
	 * Update auto-update or block settings for one translation locale.
	 *
	 * @param mixed $input Locale slug and the fields to change.
	 * @return array<string, mixed>|\WP_Error Updated translation row, or an error.
	 */
	public static function set_translation_policy( $input = null ) {
		if ( ! is_array( $input ) ) {
			return new \WP_Error( 'updatebee_invalid_policy', __( 'Translation policy input must be an object.', 'updatebee' ) );
		}

		$unknown = self::reject_unknown_keys( $input, array( 'slug', 'auto_update', 'blocked' ) );
		if ( is_wp_error( $unknown ) ) {
			return $unknown;
		}

		$slug = isset( $input['slug'] ) && is_string( $input['slug'] ) ? $input['slug'] : '';
		if ( ! Functions::translation_locale_exists( $slug ) ) {
			return new \WP_Error(
				'updatebee_translation_not_found',
				sprintf(
					/* translators: %s: locale slug. */
					__( 'Translation locale %s was not found.', 'updatebee' ),
					$slug
				)
			);
		}

		$present = self::present_keys( $input, array( 'auto_update', 'blocked' ) );
		if ( is_wp_error( $present ) ) {
			return $present;
		}

		if ( array_key_exists( 'auto_update', $input ) ) {
			$valid = self::assert_bool( $input['auto_update'], 'auto_update' );
			if ( is_wp_error( $valid ) ) {
				return $valid;
			}

			$saved = Functions::set_translation_auto_update( $slug, $input['auto_update'] );
			if ( null === $saved ) {
				return self::save_failed();
			}
		}

		if ( array_key_exists( 'blocked', $input ) ) {
			$valid = self::assert_bool( $input['blocked'], 'blocked' );
			if ( is_wp_error( $valid ) ) {
				return $valid;
			}

			$saved = Functions::set_translation_blocked( $slug, $input['blocked'] );
			if ( null === $saved ) {
				return self::save_failed();
			}
		}

		$translation = Read::translation_by_slug( $slug );
		if ( null === $translation ) {
			return new \WP_Error( 'updatebee_translation_not_found', __( 'Translation locale was not found.', 'updatebee' ) );
		}

		return $translation;
	}

	/**
	 * Reject keys that are not in the allow list.
	 *
	 * @param array<string, mixed> $input   Incoming fields.
	 * @param array<int, string>   $allowed Allowed keys.
	 * @return true|\WP_Error True when every key is allowed.
	 */
	private static function reject_unknown_keys( array $input, array $allowed ) {
		$unknown = array_diff( array_keys( $input ), $allowed );
		if ( array() === $unknown ) {
			return true;
		}

		return new \WP_Error(
			'updatebee_unknown_policy_key',
			sprintf(
				/* translators: %s: comma-separated option keys. */
				__( 'Unknown update policy keys: %s', 'updatebee' ),
				implode( ', ', $unknown )
			)
		);
	}

	/**
	 * Require at least one field that changes policy.
	 *
	 * @param array<string, mixed> $input Incoming fields.
	 * @param array<int, string>   $keys  Mutation keys.
	 * @return true|\WP_Error True when a mutation key is present.
	 */
	private static function present_keys( array $input, array $keys ) {
		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				return true;
			}
		}

		return new \WP_Error( 'updatebee_empty_policy', __( 'Provide at least one policy field to change.', 'updatebee' ) );
	}

	/**
	 * Require a boolean.
	 *
	 * @param mixed  $value Raw value.
	 * @param string $field Field name.
	 * @return true|\WP_Error True when the value is a boolean.
	 */
	private static function assert_bool( $value, string $field ) {
		if ( is_bool( $value ) ) {
			return true;
		}

		return new \WP_Error(
			'updatebee_invalid_policy_value',
			sprintf(
				/* translators: %s: field name. */
				__( 'Policy field %s must be a boolean.', 'updatebee' ),
				$field
			)
		);
	}

	/**
	 * Require a boolean or null.
	 *
	 * @param mixed  $value Raw value.
	 * @param string $field Field name.
	 * @return bool|null|\WP_Error Parsed value, or an error.
	 */
	private static function nullable_bool( $value, string $field ) {
		if ( null === $value || is_bool( $value ) ) {
			return $value;
		}

		return new \WP_Error(
			'updatebee_invalid_policy_value',
			sprintf(
				/* translators: %s: field name. */
				__( 'Policy field %s must be a boolean or null.', 'updatebee' ),
				$field
			)
		);
	}

	/**
	 * Require an integer delay or null.
	 *
	 * @param mixed $value Raw delay.
	 * @return int|null|\WP_Error Days, null to inherit, or an error.
	 */
	private static function delay_days_input( $value ) {
		if ( null === $value ) {
			return null;
		}

		if ( ! is_int( $value ) ) {
			return new \WP_Error( 'updatebee_invalid_policy_value', __( 'Delay days must be an integer or null.', 'updatebee' ) );
		}

		return max( 0, $value );
	}

	/**
	 * Error used when an option list cannot be saved.
	 *
	 * @return \WP_Error Save error.
	 */
	private static function save_failed(): \WP_Error {
		return new \WP_Error( 'updatebee_policy_save_failed', __( 'Update policy could not be saved.', 'updatebee' ) );
	}
}
