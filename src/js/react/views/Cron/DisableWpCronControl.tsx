/**
 * Network-wide control to disable WordPress visitor-triggered cron.
 */
import { ToggleControl, Notice } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import sendCommand from '../../../utils/SendCommand';
import type { SnackbarType } from '../Tools/ToolsFooter';

type DisableWpCronState = {
	enabled: boolean;
	constantDefined: boolean;
	constantValue: boolean | null;
};

type SavePayload = {
	message?: string;
	state?: DisableWpCronState;
};

type DisableWpCronControlProps = {
	onNotify: ( message: string, type: SnackbarType ) => void;
};

/**
 * Normalize localized disable-wp-cron state.
 *
 * @param state Incoming localized state.
 */
const normalizeState = (
	state: UpdateBeeDisableWpCronState
): DisableWpCronState => ( {
	enabled: Boolean( state?.enabled ),
	constantDefined: Boolean( state?.constantDefined ),
	constantValue:
		null === state?.constantValue || undefined === state?.constantValue
			? null
			: Boolean( state.constantValue ),
} );

/**
 * Toggle or status for DISABLE_WP_CRON under WordPress time.
 *
 * @param props Snackbar callback.
 */
const DisableWpCronControl = ( props: DisableWpCronControlProps ) => {
	const { onNotify } = props;
	const [ state, setState ] = useState< DisableWpCronState >( () =>
		normalizeState( updatebeeCron.disableWpCron )
	);
	const [ isSaving, setIsSaving ] = useState( false );

	if ( state.constantDefined ) {
		const isDisabledByConfig = true === state.constantValue;

		return (
			<div className="updatebee-cron-disable-wp-cron">
				<Notice
					status={ isDisabledByConfig ? 'warning' : 'info' }
					isDismissible={ false }
				>
					{ isDisabledByConfig
						? __(
								'WordPress native cron is already disabled via DISABLE_WP_CRON in wp-config.php.',
								'updatebee'
						  )
						: __(
								'wp-config.php sets DISABLE_WP_CRON to false, so native cron cannot be disabled from this setting.',
								'updatebee'
						  ) }
				</Notice>
			</div>
		);
	}

	const handleChange = async ( nextEnabled: boolean ) => {
		const previous = state;
		setState( {
			...state,
			enabled: nextEnabled,
		} );
		setIsSaving( true );

		try {
			const response = await sendCommand< SavePayload >(
				'updatebee_save_disable_wp_cron',
				{
					nonce: updatebeeCron.nonce,
					enabled: nextEnabled,
				}
			);
			const payload = response.data.data;
			if ( payload.state ) {
				setState( normalizeState( payload.state ) );
			}
			onNotify(
				typeof payload.message === 'string'
					? payload.message
					: __( 'Setting saved.', 'updatebee' ),
				'success'
			);
		} catch {
			setState( previous );
			onNotify(
				__( 'Could not save the native cron setting.', 'updatebee' ),
				'error'
			);
		} finally {
			setIsSaving( false );
		}
	};

	return (
		<div className="updatebee-cron-disable-wp-cron">
			<ToggleControl
				label={ __( 'Disable WordPress Cron', 'updatebee' ) }
				help={ __(
					'If disabled, you must use a cron tab to run the updates. You can find those under each schedule in the Advanced detail.',
					'updatebee'
				) }
				checked={ state.enabled }
				disabled={ isSaving }
				onChange={ ( nextChecked ) => {
					void handleChange( nextChecked );
				} }
			/>
		</div>
	);
};

export default DisableWpCronControl;
