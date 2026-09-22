/**
 * Cron tab footer snackbar.
 */
import { Fill } from '@wordpress/components';
import Snackbar from '../../components/Snackbar';
import type { SnackbarState } from '../Tools/ToolsFooter';

type CronFooterProps = {
	snackbar: SnackbarState;
	onSnackbarClose: () => void;
};

const CronFooter = ( props: CronFooterProps ) => {
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
			</div>
		</Fill>
	);
};

export default CronFooter;
