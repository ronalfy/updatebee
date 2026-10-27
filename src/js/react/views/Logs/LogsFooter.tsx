/**
 * Logs footer snackbar and DataViews pagination bar.
 */
import { Fill } from '@wordpress/components';
import { DataViews } from '@wordpress/dataviews';
import Snackbar from '../../components/Snackbar';

export type SnackbarType = 'success' | 'error' | 'warning' | 'info';

export type SnackbarState = {
	isVisible: boolean;
	message: string;
	type: SnackbarType;
};

type LogsFooterProps = {
	snackbar: SnackbarState;
	onSnackbarClose: () => void;
};

const LogsFooter = ( props: LogsFooterProps ) => {
	const { snackbar, onSnackbarClose } = props;

	return (
		<Fill name="updatebeeFooter">
			<div className="updatebee-logs-footer">
				<Snackbar
					isVisible={ snackbar.isVisible }
					message={ snackbar.message }
					type={ snackbar.type }
					onClose={ onSnackbarClose }
				/>
				<DataViews.Footer />
			</div>
		</Fill>
	);
};

export default LogsFooter;
