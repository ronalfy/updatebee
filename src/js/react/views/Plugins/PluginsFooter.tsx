/**
 * Plugins footer bulk bar and snackbar.
 */
import { Fill } from '@wordpress/components';
import type {
	Control,
	UseFormGetValues,
	UseFormSetValue,
} from 'react-hook-form';
import Snackbar from '../../components/Snackbar';
import BulkActions, { type Action } from './bulk-actions';
import type { PluginItem, PluginsFormValues } from './PluginsMain';

export type SnackbarType = 'success' | 'error' | 'warning' | 'info';

export type SnackbarState = {
	isVisible: boolean;
	message: string;
	type: SnackbarType;
};

type PluginsFooterProps = {
	plugins: PluginItem[];
	actions: Action< PluginItem >[];
	snackbar: SnackbarState;
	onSnackbarClose: () => void;
	isBusy: boolean;
	control: Control< PluginsFormValues >;
	getValues: UseFormGetValues< PluginsFormValues >;
	setValue: UseFormSetValue< PluginsFormValues >;
};

const PluginsFooter = ( props: PluginsFooterProps ) => {
	const {
		plugins,
		actions,
		snackbar,
		onSnackbarClose,
		isBusy,
		control,
		getValues,
		setValue,
	} = props;

	return (
		<Fill name="updatebeeFooter">
			<div className="updatebee-plugins-footer">
				<Snackbar
					isVisible={ snackbar.isVisible }
					message={ snackbar.message }
					type={ snackbar.type }
					onClose={ onSnackbarClose }
				/>
				<BulkActions
					plugins={ plugins }
					actions={ actions }
					isBusy={ isBusy }
					control={ control }
					getValues={ getValues }
					setValue={ setValue }
				/>
			</div>
		</Fill>
	);
};

export default PluginsFooter;
