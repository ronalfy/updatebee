/**
 * Shared form field keys for plugin rows.
 */

export type PluginField =
	| 'auto_update'
	| 'patches_only'
	| 'block_updates'
	| 'selected';

type PluginFileRow = {
	File: string;
};

/**
 * Flatten a plugin file so react-hook-form does not treat it as a nested path.
 *
 * @param file Plugin basename path.
 */
const flattenPluginFile = ( file: string ): string =>
	file.replace( /[.[\]'"]/g, '_' );

/**
 * Form key used for a plugin row field.
 *
 * @param plugin Plugin row.
 * @param field  Field suffix.
 */
export const getPluginFieldKey = (
	plugin: PluginFileRow,
	field: PluginField
): string => `${ flattenPluginFile( plugin.File ) }_${ field }`;
