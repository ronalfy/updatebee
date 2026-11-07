<?php
/**
 * Pending plugin and theme updates for the Settings screen.
 *
 * @package updatebee
 */

namespace UpdateBee\Updates;

use UpdateBee\Emails\Available_Updates;
use UpdateBee\Functions;

/**
 * Build the upcoming-updates list.
 */
class Upcoming {

	/**
	 * Pending plugin and theme rows plus the next automatic install time.
	 *
	 * @return array<string, mixed> List payload.
	 */
	public static function get_list(): array {
		$next_run = self::next_install_timestamp();
		$rows     = array();

		foreach ( self::plugin_rows( $next_run ) as $row ) {
			$rows[] = $row;
		}

		foreach ( self::theme_rows( $next_run ) as $row ) {
			$rows[] = $row;
		}

		usort(
			$rows,
			static function ( $left, $right ): int {
				$left_name  = isset( $left['Name'] ) ? (string) $left['Name'] : '';
				$right_name = isset( $right['Name'] ) ? (string) $right['Name'] : '';

				return strcasecmp( $left_name, $right_name );
			}
		);

		return array(
			'items'          => $rows,
			'next_run_label' => self::next_install_label( $next_run ),
		);
	}

	/**
	 * Whether a manual update can be started for this row.
	 *
	 * @param bool $has_package    Offer includes an installable package.
	 * @param bool $blocked        This item is blocked.
	 * @param bool $fully_blocked  Updates of this type are disabled.
	 * @param bool $user_can       Current user can install this update.
	 * @return bool True when Update now should be offered.
	 */
	public static function allows_manual_update( bool $has_package, bool $blocked, bool $fully_blocked, bool $user_can ): bool {
		return $has_package && ! $blocked && ! $fully_blocked && $user_can;
	}

	/**
	 * Pending plugin rows.
	 *
	 * Blocked plugins are omitted. They cannot be installed, so listing them
	 * looks like an update is available.
	 *
	 * @param int|null $next_run Next automatic install timestamp.
	 * @return array<int, array<string, mixed>> Rows.
	 */
	private static function plugin_rows( ?int $next_run ): array {
		if ( Functions::plugin_updates_fully_blocked() ) {
			return array();
		}

		$installed = Functions::get_installed_plugin_files();
		$rows      = array();
		$user_can  = current_user_can( 'update_plugins' );

		foreach ( $installed as $file => $plugin ) {
			if ( ! is_string( $file ) || ! is_array( $plugin ) ) {
				continue;
			}

			if ( Functions::auto_updates_blocked( $file, 'plugin' ) ) {
				continue;
			}

			$offer = Available_Updates::plugin_offer( $file );
			if ( null === $offer ) {
				continue;
			}

			$name   = isset( $plugin['Name'] ) ? (string) $plugin['Name'] : $file;
			$from   = isset( $plugin['Version'] ) ? (string) $plugin['Version'] : '';
			$rows[] = self::row(
				'plugin',
				$file,
				$name,
				__( 'Plugin', 'updatebee' ),
				$from,
				$offer,
				false,
				false,
				$user_can,
				$next_run,
				'updatebee-plugin-skip-' . $file,
				'updatebee-plugin-block-' . $file
			);
		}

		return $rows;
	}

	/**
	 * Pending theme rows.
	 *
	 * @param int|null $next_run Next automatic install timestamp.
	 * @return array<int, array<string, mixed>> Rows.
	 */
	private static function theme_rows( ?int $next_run ): array {
		$rows     = array();
		$fully    = Functions::theme_updates_fully_blocked();
		$user_can = current_user_can( 'update_themes' );

		foreach ( Functions::get_installed_themes() as $stylesheet => $theme ) {
			if ( ! is_string( $stylesheet ) ) {
				continue;
			}

			$offer = Available_Updates::theme_offer( $stylesheet );
			if ( null === $offer ) {
				continue;
			}

			$name = $stylesheet;
			$from = '';
			if ( is_object( $theme ) && method_exists( $theme, 'get' ) ) {
				$name = (string) $theme->get( 'Name' );
				$from = (string) $theme->get( 'Version' );
			}

			$rows[] = self::row(
				'theme',
				$stylesheet,
				$name,
				__( 'Theme', 'updatebee' ),
				$from,
				$offer,
				Functions::auto_updates_blocked( $stylesheet, 'theme' ) || $fully,
				$fully,
				$user_can,
				$next_run,
				'updatebee-theme-skip-' . $stylesheet,
				'updatebee-theme-block-' . $stylesheet
			);
		}

		return $rows;
	}

	/**
	 * One upcoming row.
	 *
	 * @param string               $type         plugin or theme.
	 * @param string               $id           Plugin file or theme stylesheet.
	 * @param string               $name         Display name.
	 * @param string               $type_label   Translated type.
	 * @param string               $from_version Installed version.
	 * @param array<string, mixed> $offer        Offer from Available_Updates.
	 * @param bool                 $blocked      Item or type is blocked.
	 * @param bool                 $fully        Type updates are disabled.
	 * @param bool                 $user_can     User can install the update.
	 * @param int|null             $next_run     Next automatic install timestamp.
	 * @param string               $skip_action  Skip nonce action.
	 * @param string               $block_action Block nonce action.
	 * @return array<string, mixed> Row.
	 */
	private static function row( string $type, string $id, string $name, string $type_label, string $from_version, array $offer, bool $blocked, bool $fully, bool $user_can, ?int $next_run, string $skip_action, string $block_action ): array {
		$to_version  = isset( $offer['new_version'] ) ? (string) $offer['new_version'] : '';
		$has_package = ! empty( $offer['has_package'] );
		$skipped     = Skip_Version::is_skipped( $type, $id, $to_version );
		$schedule    = self::schedule_label( $type, $id, $blocked, $skipped, $next_run );

		if ( $blocked ) {
			$status = 'blocked';
		} elseif ( $skipped ) {
			$status = 'skipped';
		} else {
			$status = 'scheduled';
		}

		return array(
			'RowId'        => $type . ':' . $id,
			'Id'           => $id,
			'Type'         => $type,
			'Name'         => $name,
			'TypeLabel'    => $type_label,
			'FromVersion'  => $from_version,
			'ToVersion'    => $to_version,
			'Schedule'     => $schedule,
			'Status'       => $status,
			'HasPackage'   => $has_package,
			'FullyBlocked' => $fully,
			'Blocked'      => $blocked,
			'Skipped'      => $skipped,
			'CanUpdate'    => self::allows_manual_update( $has_package, $blocked, $fully, $user_can ),
			'SkipNonce'    => wp_create_nonce( $skip_action ),
			'BlockNonce'   => wp_create_nonce( $block_action ),
			'UpdateNonce'  => isset( $offer['nonce'] ) ? (string) $offer['nonce'] : '',
		);
	}

	/**
	 * Whether Composer or a checkout owns this item.
	 *
	 * @param string $type plugin or theme.
	 * @param string $id   Plugin file or theme stylesheet.
	 * @return bool True when automatic installs stay off.
	 */
	private static function is_version_controlled( string $type, string $id ): bool {
		if ( 'plugin' === $type ) {
			return Version_Controlled::is_plugin( $id );
		}

		if ( 'theme' === $type ) {
			return Version_Controlled::is_theme( $id );
		}

		return false;
	}

	/**
	 * Schedule column label.
	 *
	 * @param string   $type     plugin or theme.
	 * @param string   $id       Plugin file or theme stylesheet.
	 * @param bool     $blocked  Item is blocked.
	 * @param bool     $skipped  This version is skipped.
	 * @param int|null $next_run Next automatic install timestamp.
	 * @return string Label.
	 */
	private static function schedule_label( string $type, string $id, bool $blocked, bool $skipped, ?int $next_run ): string {
		if ( $blocked ) {
			return __( 'Blocked', 'updatebee' );
		}

		if ( $skipped ) {
			return __( 'Skipped', 'updatebee' );
		}

		if ( self::is_version_controlled( $type, $id ) ) {
			return __( 'Version controlled', 'updatebee' );
		}

		$run      = $next_run;
		$eligible = Delay_Updates::get_instance()->eligible_timestamp( $type, $id );
		if ( null !== $eligible && ( null === $run || $eligible > $run ) ) {
			$run = $eligible;
		}

		if ( null === $run ) {
			return __( 'Not scheduled', 'updatebee' );
		}

		return self::format_time( $run );
	}

	/**
	 * Next wp_version_check timestamp.
	 *
	 * @return int|null Timestamp, or null when the event is not scheduled.
	 */
	private static function next_install_timestamp(): ?int {
		$crons = Init::get_update_crons();
		$event = $crons['version_check'] ?? false;
		if ( ! is_object( $event ) || ! isset( $event->timestamp ) ) {
			return null;
		}

		return (int) $event->timestamp;
	}

	/**
	 * Header sentence for the next automatic install.
	 *
	 * @param int|null $timestamp Next install timestamp.
	 * @return string Label.
	 */
	private static function next_install_label( ?int $timestamp ): string {
		if ( null === $timestamp ) {
			return __( 'Automatic installs are not scheduled.', 'updatebee' );
		}

		return sprintf(
			/* translators: %s: date and time of the next automatic install. */
			__( 'Next automatic install: %s', 'updatebee' ),
			self::format_time( $timestamp )
		);
	}

	/**
	 * Format a timestamp in the site timezone.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return string Formatted date and time.
	 */
	private static function format_time( int $timestamp ): string {
		$format = trim( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ) );
		if ( '' === $format ) {
			$format = 'Y-m-d H:i';
		}

		return wp_date( $format, $timestamp );
	}
}
