/**
 * Branding footer snackbar.
 */
import { Fill } from '@wordpress/components';
import Snackbar from '../../components/Snackbar';

export type SnackbarType = 'success' | 'error' | 'warning' | 'info';

export type SnackbarState = {
	isVisible: boolean;
	message: string;
	type: SnackbarType;
};

type BrandingFooterProps = {
	snackbar: SnackbarState;
	onSnackbarClose: () => void;
};

const BrandingFooter = ( props: BrandingFooterProps ) => {
	const { snackbar, onSnackbarClose } = props;

	return (
		<Fill name="updatebeeFooter">
			<div className="updatebee-branding-footer">
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

export default BrandingFooter;
