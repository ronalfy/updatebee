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
	dashboardSettingsDefaults,
	type DashboardSettingsForm,
} from './DashboardUpdateOptions';

type SnackbarType = 'success' | 'error' | 'warning' | 'info';

type SnackbarState = {
	isVisible: boolean;
	message: string;
	type: SnackbarType;
};

type DashboardOptionsPayload = {
	major_updates?: string;
	minor_updates?: string;
	plugin_updates?: string;
	theme_updates?: string;
	translation_updates?: string;
};

import { boolToOption, optionToBool } from '../../utils/bool';
/**
 * Map camelCase form values to option keys.
 *
 * @param values Form values.
 * @return Option payload.
 */
const formToOptions = (
	values: DashboardSettingsForm
): DashboardOptionsPayload => {
	return {
		major_updates: boolToOption( values.majorUpdates ),
		minor_updates: boolToOption( values.minorUpdates ),
		plugin_updates: boolToOption( values.pluginUpdates ),
		theme_updates: boolToOption( values.themeUpdates ),
		translation_updates: boolToOption( values.translationUpdates ),
	};
};

/**
 * Map option keys back to camelCase form values.
 *
 * @param data Option payload.
 * @return Form values.
 */
const optionsToForm = (
	data?: DashboardOptionsPayload
): DashboardSettingsForm => {
	if ( ! data ) {
		return dashboardSettingsDefaults;
	}

	return {
		majorUpdates: optionToBool( data.major_updates ),
		minorUpdates: optionToBool( data.minor_updates ),
		pluginUpdates: optionToBool( data.plugin_updates ),
		themeUpdates: optionToBool( data.theme_updates ),
		translationUpdates: optionToBool( data.translation_updates ),
	};
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
