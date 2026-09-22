/**
 * Translations footer snackbar.
 */
import { Fill } from '@wordpress/components';
import Snackbar from '../../components/Snackbar';

export type SnackbarType = 'success' | 'error' | 'warning' | 'info';

export type SnackbarState = {
	isVisible: boolean;
	message: string;
	type: SnackbarType;
};

type TranslationsFooterProps = {
	snackbar: SnackbarState;
	onSnackbarClose: () => void;
};

const TranslationsFooter = ( props: TranslationsFooterProps ) => {
	const { snackbar, onSnackbarClose } = props;

	return (
		<Fill name="updatebeeFooter">
			<div className="updatebee-translations-footer">
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

export default TranslationsFooter;
