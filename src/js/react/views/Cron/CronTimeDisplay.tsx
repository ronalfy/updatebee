/**
 * Universal and local WordPress time for the Cron tab.
 */
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import sendCommand from '../../../utils/SendCommand';
import Loader from '../../components/Loader';

type TimePayload = {
	universal_time?: string;
	local_time?: string | false;
};

/**
 * Fetch and display site time strings.
 */
const CronTimeDisplay = () => {
	const [ isLoading, setIsLoading ] = useState( true );
	const [ universalTime, setUniversalTime ] = useState( '' );
	const [ localTime, setLocalTime ] = useState( '' );

	const loadTime = useCallback( async () => {
		setIsLoading( true );
		try {
			const response = await sendCommand< TimePayload >(
				'updatebee_get_cron_time',
				{
					nonce: updatebeeCron.nonce,
				}
			);
			const data = response.data.data;
			setUniversalTime(
				typeof data.universal_time === 'string'
					? data.universal_time
					: ''
			);
			setLocalTime(
				typeof data.local_time === 'string' ? data.local_time : ''
			);
		} catch {
			setUniversalTime( '' );
			setLocalTime( '' );
		} finally {
			setIsLoading( false );
		}
	}, [] );

	useEffect( () => {
		void loadTime();
	}, [ loadTime ] );

	if ( isLoading ) {
		return <Loader />;
	}

	return (
		<div className="updatebee-cron-time">
			{ universalTime && (
				<p>
					{ __( 'Universal time is', 'updatebee' ) }{ ' ' }
					<code>{ universalTime }</code>
				</p>
			) }
			{ localTime && (
				<p>
					{ __( 'Local time is', 'updatebee' ) }{ ' ' }
					<code>{ localTime }</code>
				</p>
			) }
		</div>
	);
};

export default CronTimeDisplay;
