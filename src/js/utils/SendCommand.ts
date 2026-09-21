/**
 * Post an admin-ajax request.
 */
import axios, { type AxiosResponse } from 'axios';
import qs from 'qs';

export type SendCommandData = Record< string, unknown >;

export interface WpAjaxSuccess< T > {
	success: true;
	data: T;
}

export interface WpAjaxError {
	success: false;
	data?: unknown;
}

export type WpAjaxResponse< T > = WpAjaxSuccess< T > | WpAjaxError;

/**
 * Send a WordPress admin-ajax command.
 *
 * Non-success WP JSON (`success: false`) rejects so callers can use `.catch`.
 *
 * @param action Admin-ajax action name.
 * @param data   POST body fields.
 * @return Axios response promise.
 */
export default function sendCommand< T = unknown >(
	action: string,
	data: SendCommandData = {}
): Promise< AxiosResponse< WpAjaxSuccess< T > > > {
	const payload: SendCommandData = {
		nonce: false,
		action,
		...data,
	};

	return axios< WpAjaxResponse< T > >( {
		method: 'post',
		url: ajaxurl,
		params: {
			action,
		},
		paramsSerializer( params: Record< string, string > ) {
			return qs.stringify( params, { arrayFormat: 'brackets' } );
		},
		data: qs.stringify( payload ),
	} ).then( ( response ) => {
		if ( ! response.data || true !== response.data.success ) {
			return Promise.reject( new Error( 'Request failed.' ) );
		}

		return response as AxiosResponse< WpAjaxSuccess< T > >;
	} );
}
