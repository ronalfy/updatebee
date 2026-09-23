/**
 * Advanced settings (delayed automatic updates).
 */
import { TextControl, Notice } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Card, useEnableWpCompatOverlaySlot } from '@wordpress/ui';
import sendCommand from '../../../utils/SendCommand';
import AdvancedFooter, {
	type SnackbarState,
	type SnackbarType,
} from './AdvancedFooter';

type AdvancedMessagePayload = {
	message?: string;
	delay_updates?: number;
};

/**
 * Normalize a delay-days value.
 *
 * @param value Incoming days value.
 * @return Non-negative integer days.
 */
const normalizeDays = ( value: unknown ): number => {
	const days = Number( value );
	if ( Number.isFinite( days ) && days >= 0 ) {
		return Math.floor( days );
	}

	return 0;
};

const AdvancedMain = () => {
	useEnableWpCompatOverlaySlot();
	const [ delayUpdates, setDelayUpdates ] = useState(
		normalizeDays( updatebeeAdvanced.delayUpdates )
	);
	const [ isSaving, setIsSaving ] = useState( false );
	const [ snackbar, setSnackbar ] = useState< SnackbarState >( {
		isVisible: false,
		message: '',
		type: 'success',
	} );

	const handleSnackbarClose = useCallback( () => {
		setSnackbar( ( current ) => ( {
			...current,
			isVisible: false,
		} ) );
	}, [] );

	/**
	 * Show a snackbar notice.
	 *
	 * @param message Notice text.
	 * @param type    Notice type.
	 */
	const showSnackbar = useCallback(
		( message: string, type: SnackbarType ) => {
			setSnackbar( {
				isVisible: true,
				message,
				type,
			} );
		},
		[]
	);

	/**
	 * Persist delayed automatic update days.
	 */
	const handleSave = useCallback( () => {
		if ( isSaving ) {
			return;
		}

		const nextDays = normalizeDays( delayUpdates );
		setIsSaving( true );

		sendCommand< AdvancedMessagePayload >(
			'updatebee_save_advanced_settings',
			{
				nonce: updatebeeAdvanced.saveNonce,
				delay_updates: nextDays,
			}
		)
			.then( ( response ) => {
				const payload = response.data.data;
				setDelayUpdates( normalizeDays( payload.delay_updates ) );
				showSnackbar(
					payload.message ||
						__( 'Update delay has been saved.', 'updatebee' ),
					'success'
				);
			} )
			.catch( () => {
				showSnackbar(
					__( 'Update delay could not be saved.', 'updatebee' ),
					'error'
				);
			} )
			.finally( () => {
				setIsSaving( false );
			} );
	}, [ delayUpdates, isSaving, showSnackbar ] );

	return (
		<div className="updatebee-advanced-main">
			<div className="updatebee-advanced-bento">
				<Card.Root className="updatebee-advanced-card">
					<Card.Header>
						<Card.Title>
							{ __(
								'Delay Automatic Updates on Plugins and Themes',
								'updatebee'
							) }
						</Card.Title>
					</Card.Header>
					<Card.Content className="updatebee-advanced-card-content">
						<p className="updatebee-advanced-card-description">
							{ __(
								'A plugin or theme release may be shortlived if a developer finds another issue with the code and has to put out another release.',
								'updatebee'
							) }
						</p>
						<Notice
							status="warning"
							isDismissible={ false }
						>
							{ __( 'Warning: If an update was fixing a security issue, then your site will be unfixed for longer than normal.',
							'updatebee'
							) }
						</Notice>
						<div className="updatebee-advanced-card-field">
							<TextControl
								label={ __( 'Delay automatic updates', 'updatebee' ) }
								type="number"
								min={ 0 }
								value={ String( delayUpdates ) }
								onChange={ ( value ) => {
									setDelayUpdates( normalizeDays( value ) );
								} }
								help={ __( 'Time in days for how long to delay an update for. Use 0 for no delay.', 'updatebee' ) }
								disabled={ isSaving }
							/>
						</div>
						<p className="updatebee-advanced-card-actions">
							<Button
								variant="solid"
								loading={ isSaving }
								loadingAnnouncement={ __(
									'Saving delay…',
									'updatebee'
								) }
								disabled={ isSaving }
								onClick={ handleSave }
							>
								{ __( 'Save delay', 'updatebee' ) }
							</Button>
						</p>
					</Card.Content>
				</Card.Root>
			</div>

			<AdvancedFooter
				snackbar={ snackbar }
				onSnackbarClose={ handleSnackbarClose }
			/>
		</div>
	);
};

export default AdvancedMain;
