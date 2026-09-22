/**
 * Emails admin tab.
 */
import { useCallback, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useEnableWpCompatOverlaySlot } from '@wordpress/ui';
import EmailReportCard from './EmailReportCard';
import EmailsFooter from './EmailsFooter';
import WordPressEmailsCard from './WordPressEmailsCard';
import type { SnackbarState, SnackbarType } from '../Tools/ToolsFooter';

const EmailsMain = () => {
	useEnableWpCompatOverlaySlot();

	const [ snackbar, setSnackbar ] = useState< SnackbarState >( {
		isVisible: false,
		message: '',
		type: 'success',
	} );

	const handleSnackbarClose = useCallback( () => {
		setSnackbar( ( current ) => ( {
			...current,
			isVisible: false,
		} ) );
	}, [] );

	const showSnackbar = useCallback(
		( message: string, type: SnackbarType ) => {
			setSnackbar( {
				isVisible: true,
				message,
				type,
			} );
		},
		[]
	);

	return (
		<div className="updatebee-emails-main">
			<p className="updatebee-emails-intro">
				{ __(
					'Control WordPress built-in update emails and configure UpdateBee success and failure reports.',
					'updatebee'
				) }
			</p>
			<WordPressEmailsCard onNotify={ showSnackbar } />
			<EmailReportCard
				kind="success"
				title={ __( 'Success reports', 'updatebee' ) }
				description={ __(
					'Email a digest when automatic updates complete successfully.',
					'updatebee'
				) }
				onNotify={ showSnackbar }
			/>
			<EmailReportCard
				kind="failure"
				title={ __( 'Failure reports', 'updatebee' ) }
				description={ __(
					'Email a digest when automatic updates fail.',
					'updatebee'
				) }
				onNotify={ showSnackbar }
			/>
			<EmailsFooter
				snackbar={ snackbar }
				onSnackbarClose={ handleSnackbarClose }
			/>
		</div>
	);
};

export default EmailsMain;
