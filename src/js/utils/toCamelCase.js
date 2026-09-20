/**
 * Convert an underscore_separated field to camelCase.
 *
 * @param {string} field Field to convert.
 * @return {string} camelCased field.
 */
export default function toCamelCase( field ) {
	const words = field
		.split( '_' )
		.map( ( word ) => word.charAt( 0 ).toUpperCase() + word.slice( 1 ) )
		.join( '' );

	return words.charAt( 0 ).toLowerCase() + words.slice( 1 );
}
