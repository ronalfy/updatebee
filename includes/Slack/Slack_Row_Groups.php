<?php
/**
 * Shared helpers for grouping update rows in Slack messages.
 *
 * @package updatebee
 */

namespace UpdateBee\Slack;

/**
 * Group update rows by type for Slack sections.
 */
class Slack_Row_Groups {

	/**
	 * Maximum rows listed in one Slack message.
	 */
	public const ITEM_LIMIT = 40;

	/**
	 * Group rows by update type in a stable order.
	 *
	 * @param array<int, array<string, string>> $rows Normalized rows.
	 * @return array<string, array<int, array<string, string>>>
	 */
	public static function group_rows( array $rows ): array {
		$order  = array( 'core', 'plugin', 'theme', 'translation' );
		$groups = array();
		foreach ( $rows as $row ) {
			$type = '' !== $row['update_type'] ? $row['update_type'] : 'other';
			if ( ! isset( $groups[ $type ] ) ) {
				$groups[ $type ] = array();
			}
			$groups[ $type ][] = $row;
		}

		$sorted = array();
		foreach ( $order as $type ) {
			if ( isset( $groups[ $type ] ) ) {
				$sorted[ $type ] = $groups[ $type ];
				unset( $groups[ $type ] );
			}
		}

		foreach ( $groups as $type => $type_rows ) {
			$sorted[ $type ] = $type_rows;
		}

		return $sorted;
	}

	/**
	 * Section heading for an update type.
	 *
	 * @param string $update_type plugin|theme|core|translation.
	 * @return string
	 */
	public static function type_heading( string $update_type ): string {
		switch ( $update_type ) {
			case 'plugin':
				return __( 'Plugins', 'updatebee' );
			case 'theme':
				return __( 'Themes', 'updatebee' );
			case 'translation':
				return __( 'Translations', 'updatebee' );
			case 'core':
				return __( 'WordPress core', 'updatebee' );
			default:
				return ucfirst( $update_type );
		}
	}
}
