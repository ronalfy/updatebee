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

/**
 * Send a WordPress admin-ajax command.
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

	return axios( {
		method: 'post',
		url: ajaxurl,
		params: {
			action,
		},
		paramsSerializer( params: Record< string, string > ) {
			return qs.stringify( params, { arrayFormat: 'brackets' } );
		},
		data: qs.stringify( payload ),
	} );
}
