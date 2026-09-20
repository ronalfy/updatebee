import { useEffect } from 'react';
import { Snackbar as WPSnackbar } from '@wordpress/components';
import clsx from 'clsx';

interface SnackbarProps {
	isVisible: boolean;
	message: string;
	type?: 'success' | 'error' | 'warning' | 'info';
	isPersistent?: boolean;
	onClose: () => void;
}

const Snackbar = ( props: SnackbarProps ) => {
	const {
		isVisible,
		message,
		type = 'success',
		isPersistent = false,
		onClose,
	} = props;

	useEffect( () => {
		if ( isVisible && ! isPersistent ) {
			const timer = setTimeout( () => {
				onClose();
			}, 5000 );
			return () => clearTimeout( timer );
		}
	}, [ isVisible, isPersistent, onClose ] );

	if ( ! isVisible ) {
		return null;
	}

	return (
		<WPSnackbar
			className={ clsx( 'updatebee-snackbar', `updatebee-snackbar-${ type }` ) }
			onDismiss={ onClose }
		>
			{ message }
		</WPSnackbar>
	);
};

export default Snackbar;
