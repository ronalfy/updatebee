/**
 * Dashboard footer save bar and snackbar.
 */
import { Fill } from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useFormContext } from 'react-hook-form';
import sendCommand from '../../../utils/SendCommand';
import SaveBar from '../../components/SaveBar';
import Snackbar from '../../components/Snackbar';
import {
	formToOptions,
	optionsToForm,
	type DashboardOptionsPayload,
	type DashboardSettingsForm,
} from './DashboardUpdateOptions';

type SnackbarType = 'success' | 'error' | 'warning' | 'info';

type SnackbarState = {
	isVisible: boolean;
	message: string;
	type: SnackbarType;
};

const DashboardFooter = () => {
	const {
		handleSubmit,
		reset,
		formState: { isDirty, errors },
	} = useFormContext< DashboardSettingsForm >();
	const [ saving, setSaving ] = useState( false );
	const [ resetting, setResetting ] = useState( false );
	const [ snackbar, setSnackbar ] = useState< SnackbarState >( {
		isVisible: false,
		message: '',
		type: 'success',
	} );

	const hasErrors = 0 < Object.keys( errors ).length;

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
	const showSnackbar = ( message: string, type: SnackbarType ) => {
		setSnackbar( {
			isVisible: true,
			message,
			type,
		} );
	};

	/**
	 * Restore the last saved form values without an Ajax request.
	 */
	const handleDiscard = () => {
		reset();
	};

	/**
	 * Persist the current form values.
	 */
	const handleSave = handleSubmit( ( values ) => {
		setSaving( true );

		sendCommand< DashboardOptionsPayload >(
			'updatebee_save_dashboard_settings',
			{
				nonce: updatebeeDashboard.saveNonce,
				...formToOptions( values ),
			}
		)
			.then( ( response ) => {
				if ( ! response.data.success ) {
					throw new Error( 'save failed' );
				}

				reset( optionsToForm( response.data.data ) );
				showSnackbar( __( 'Settings saved.', 'updatebee' ), 'success' );
			} )
			.catch( () => {
				showSnackbar(
					__( 'Settings could not be saved.', 'updatebee' ),
					'error'
				);
			} )
			.finally( () => {
				setSaving( false );
			} );
	} );

	/**
	 * Persist dashboard defaults and reset the form.
	 */
	const handleReset = () => {
		setResetting( true );

		sendCommand< DashboardOptionsPayload >(
			'updatebee_reset_dashboard_settings',
			{
				nonce: updatebeeDashboard.resetNonce,
			}
		)
			.then( ( response ) => {
				if ( ! response.data.success ) {
					throw new Error( 'reset failed' );
				}

				reset( optionsToForm( response.data.data ) );
				showSnackbar(
					__( 'Settings have been reset to defaults.', 'updatebee' ),
					'success'
				);
			} )
			.catch( () => {
				showSnackbar(
					__( 'Settings could not be reset.', 'updatebee' ),
					'error'
				);
			} )
			.finally( () => {
				setResetting( false );
			} );
	};

	return (
		<Fill name="updatebeeFooter">
			<Snackbar
				isVisible={ snackbar.isVisible }
				message={ snackbar.message }
				type={ snackbar.type }
				onClose={ handleSnackbarClose }
			/>
			<SaveBar
				onSave={ handleSave }
				onReset={ handleReset }
				onDiscardChanges={ handleDiscard }
				isSaving={ saving }
				isResetting={ resetting }
				isDirtyFields={ isDirty }
				hasErrors={ hasErrors }
			/>
		</Fill>
	);
};

export default DashboardFooter;
