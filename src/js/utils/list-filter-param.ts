/**
 * Read and write the plugins/themes list filter query parameter.
 */

const FILTER_PARAM = 'filter';

/**
 * Read a list filter from a query string.
 *
 * @param allowed Allowed filter values for the current screen.
 * @param search  Query string to read. Defaults to the current location.
 * @return The filter when it is allowed, otherwise an empty string.
 */
export const readListFilter = < T extends string >(
	allowed: readonly T[],
	search: string = window.location.search
): T | '' => {
	const value = new URLSearchParams( search ).get( FILTER_PARAM ) ?? '';

	return ( allowed as readonly string[] ).includes( value )
		? ( value as T )
		: '';
};

/**
 * Store a list filter on the current URL.
 *
 * An empty value removes the parameter. Other query arguments are left in place.
 *
 * @param value Filter value to store.
 */
export const writeListFilter = ( value: string ): void => {
	const url = new URL( window.location.href );

	if ( value ) {
		url.searchParams.set( FILTER_PARAM, value );
	} else {
		url.searchParams.delete( FILTER_PARAM );
	}

	window.history.replaceState( null, '', url.toString() );
};
