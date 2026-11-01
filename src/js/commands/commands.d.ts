/**
 * Types for the admin command palette.
 *
 * WordPress provides this package at runtime as `wp-commands`.
 */
declare module '@wordpress/commands' {
	export type CommandCallback = ( args: { close: () => void } ) => void;

	export interface CommandConfig {
		name: string;
		label: string;
		searchLabel?: string;
		context?: string;
		category?: string;
		keywords?: string[];
		disabled?: boolean;
		callback: CommandCallback;
	}

	export function useCommand( command: CommandConfig ): void;
}
