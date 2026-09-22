/**
 * Shared form field keys for theme rows.
 */

export type ThemeField =
	| 'auto_update'
	| 'patches_only'
	| 'block_updates'
	| 'selected';

type ThemeStylesheetRow = {
	Stylesheet: string;
};

/**
 * Flatten a theme stylesheet so react-hook-form does not treat it as a nested path.
 *
 * @param stylesheet Theme stylesheet slug.
 */
const flattenThemeStylesheet = ( stylesheet: string ): string =>
	stylesheet.replace( /[.[\]'"]/g, '_' );

/**
 * Form key used for a theme row field.
 *
 * @param theme Theme row.
 * @param field Field suffix.
 */
export const getThemeFieldKey = (
	theme: ThemeStylesheetRow,
	field: ThemeField
): string => `${ flattenThemeStylesheet( theme.Stylesheet ) }_${ field }`;
