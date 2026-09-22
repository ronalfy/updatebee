/**
 * Success or failure email report card.
 */
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Card } from '@wordpress/ui';
import sendCommand from '../../../utils/SendCommand';
import type { EmailReportKind, SendSchedule } from './email-types';
import { getReportAjaxConfig } from './email-report-config';
import {
	defaultEmailReportValues,
	getDeliveryBadgeLabel,
	mapPayloadToFormValues,
} from './email-form-utils';
import EmailReportForm, {
	EmailReportFormLoading,
} from './EmailReportForm';

type EmailReportCardProps = {
	kind: EmailReportKind;
	title: string;
	description: string;
	onNotify: ( message: string, type: 'success' | 'error' ) => void;
};

/**
 * Delivery badge text.
 *
 * @param sendSchedule Current mode.
 * @return Localized label.
 */
const deliveryBadgeText = ( sendSchedule: SendSchedule ): string => {
	const slug = getDeliveryBadgeLabel( sendSchedule );
	if ( 'immediate' === slug ) {
		return __( 'Immediate', 'updatebee' );
	}
	if ( 'scheduled' === slug ) {
		return __( 'Scheduled', 'updatebee' );
	}

	return __( 'Off', 'updatebee' );
};

/**
 * Card shell for a custom email report.
 *
 * @param props Report kind and copy.
 */
const EmailReportCard = ( props: EmailReportCardProps ) => {
	const { kind, title, description, onNotify } = props;
	const config = useMemo(
		() => getReportAjaxConfig( kind ),
		[ kind ]
	);

	const [ isLoading, setIsLoading ] = useState( true );
	const [ previewUrl, setPreviewUrl ] = useState( '' );
	const [ formValues, setFormValues ] = useState(
		defaultEmailReportValues()
	);
	const [ sendSchedule, setSendSchedule ] = useState< SendSchedule >( 'none' );

	const loadOptions = useCallback( async () => {
		setIsLoading( true );
		try {
			const response = await sendCommand< Record< string, unknown > >(
				config.retrieveAction,
				{
					nonce: config.retrieveNonce,
				}
			);
			const payload = response.data.data;
			const mapped = mapPayloadToFormValues(
				payload as Record< string, unknown >
			);
			setFormValues( mapped );
			setSendSchedule( mapped.sendSchedule );
			const preview =
				typeof payload.previewurl === 'string'
					? payload.previewurl
					: '';
			setPreviewUrl( preview );
		} catch {
			onNotify(
				__( 'Could not load email report settings.', 'updatebee' ),
				'error'
			);
		} finally {
			setIsLoading( false );
		}
	}, [ config, onNotify ] );

	useEffect( () => {
		void loadOptions();
	}, [ loadOptions ] );

	const badgeClass =
		'none' === sendSchedule
			? 'updatebee-emails-status-badge'
			: 'updatebee-emails-status-badge is-active';

	return (
		<Card.Root className="updatebee-emails-card">
			<Card.Header>
				<div className="updatebee-emails-card-header">
					<div>
						<Card.Title>{ title }</Card.Title>
						<p className="description">{ description }</p>
					</div>
					<span className={ badgeClass }>
						{ deliveryBadgeText( sendSchedule ) }
					</span>
				</div>
			</Card.Header>
			<Card.Content>
				<EmailReportFormLoading isLoading={ isLoading } />
				{ ! isLoading && (
					<EmailReportForm
						config={ config }
						defaultValues={ formValues }
						previewUrl={ previewUrl }
						onSaved={ setSendSchedule }
						onNotify={ onNotify }
					/>
				) }
			</Card.Content>
		</Card.Root>
	);
};

export default EmailReportCard;
