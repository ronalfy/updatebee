/**
 * Card for one WordPress update cron context.
 */
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Card } from '@wordpress/ui';
import sendCommand from '../../../utils/SendCommand';
import { getCronAjaxConfig } from './cron-config';
import type { CronContextSlug, CronSchedulePayload } from './cron-types';
import {
	defaultCronScheduleValues,
	mapPayloadToFormValues,
} from './cron-form-utils';
import CronScheduleForm, {
	CronScheduleFormLoading,
} from './CronScheduleForm';

type CronScheduleCardProps = {
	slug: CronContextSlug;
	title: string;
	description: string;
	onNotify: ( message: string, type: 'success' | 'error' ) => void;
};

/**
 * Single-context cron schedule card.
 *
 * @param props Slug, copy, and notifications.
 */
const CronScheduleCard = ( props: CronScheduleCardProps ) => {
	const { slug, title, description, onNotify } = props;
	const config = useMemo( () => getCronAjaxConfig( slug ), [ slug ] );

	const [ isLoading, setIsLoading ] = useState( true );
	const [ formValues, setFormValues ] = useState(
		defaultCronScheduleValues()
	);

	const loadSchedule = useCallback( async () => {
		setIsLoading( true );
		try {
			const response = await sendCommand< CronSchedulePayload >(
				config.retrieveAction,
				{
					nonce: updatebeeCron.nonce,
				}
			);
			setFormValues( mapPayloadToFormValues( response.data.data ) );
		} catch {
			onNotify(
				__( 'Could not load cron schedule.', 'updatebee' ),
				'error'
			);
		} finally {
			setIsLoading( false );
		}
	}, [ config.retrieveAction, onNotify ] );

	useEffect( () => {
		void loadSchedule();
	}, [ loadSchedule ] );

	return (
		<Card.Root className="updatebee-cron-card">
			<Card.Header>
				<Card.Title>{ title }</Card.Title>
				<p className="description">{ description }</p>
			</Card.Header>
			<Card.Content>
				<CronScheduleFormLoading isLoading={ isLoading } />
				{ ! isLoading && (
					<CronScheduleForm
						key={ slug }
						config={ config }
						fieldIdPrefix={ slug }
						initialValues={ formValues }
						onNotify={ onNotify }
					/>
				) }
			</Card.Content>
		</Card.Root>
	);
};

export default CronScheduleCard;
