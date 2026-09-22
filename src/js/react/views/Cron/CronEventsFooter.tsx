/**
 * Cron Events footer snackbar and DataViews pagination bar.
 */
import { Fill } from '@wordpress/components';
import { DataViews } from '@wordpress/dataviews';
import Snackbar from '../../components/Snackbar';
import type { SnackbarState } from '../Tools/ToolsFooter';

type CronEventsFooterProps = {
	snackbar: SnackbarState;
	onSnackbarClose: () => void;
};

const CronEventsFooter = ( props: CronEventsFooterProps ) => {
	const { snackbar, onSnackbarClose } = props;

	return (
		<Fill name="updatebeeFooter">
			<div className="updatebee-cron-footer">
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

export default CronEventsFooter;
