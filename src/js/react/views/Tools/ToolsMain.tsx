/**
 * Tools maintenance actions (force updates, trigger cron).
 */
import { useCallback, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Card } from '@wordpress/ui';
import sendCommand from '../../../utils/SendCommand';
import ToolsFooter, {
	type SnackbarState,
	type SnackbarType,
} from './ToolsFooter';

type ToolsAction = 'forceUpdates' | 'triggerCron';

type ToolsMessagePayload = {
	message: string;
};

const ToolsMain = () => {
	const [ activeAction, setActiveAction ] = useState< ToolsAction | null >(
		null
	);
	const [ snackbar, setSnackbar ] = useState< SnackbarState >( {
		isVisible: false,
		message: '',
		type: 'success',
	} );

	const isBusy = null !== activeAction;

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

	/**
	 * Run a tools Ajax action.
	 *
	 * @param action      Admin-ajax action name.
	 * @param nonce       Request nonce.
	 * @param actionKey   Local loading state key.
	 * @param errorMessage Fallback error notice.
	 */
	const runToolAction = useCallback(
		(
			action: string,
			nonce: string,
			actionKey: ToolsAction,
			errorMessage: string
		) => {
			if ( isBusy ) {
				return;
			}

			setActiveAction( actionKey );

			sendCommand< ToolsMessagePayload >( action, { nonce } )
				.then( ( response ) => {
					const message =
						response.data.data?.message ||
						__( 'Action completed.', 'updatebee' );
					showSnackbar( message, 'success' );
				} )
				.catch( () => {
					showSnackbar( errorMessage, 'error' );
				} )
				.finally( () => {
					setActiveAction( null );
				} );
		},
		[ isBusy, showSnackbar ]
	);

	const handleForceUpdates = () => {
		runToolAction(
			'updatebee_force_updates',
			updatebeeTools.forceUpdatesNonce,
			'forceUpdates',
			__(
				'Force updates could not be completed.',
				'updatebee'
			)
		);
	};

	const handleTriggerCron = () => {
		runToolAction(
			'updatebee_trigger_update_cron',
			updatebeeTools.triggerCronNonce,
			'triggerCron',
			__(
				'Update cron could not be triggered.',
				'updatebee'
			)
		);
	};

	return (
		<div className="updatebee-tools-main">
			<div className="updatebee-tools-bento">
				<Card.Root className="updatebee-tools-card">
					<Card.Header>
						<Card.Title>
							{ __( 'Force Updates', 'updatebee' ) }
						</Card.Title>
					</Card.Header>
					<Card.Content className="updatebee-tools-card-content">
						<p className="updatebee-tools-card-description">
							{ __(
								'Force automatic updates for all assets with automatic update enabled. For sites with a lot of updatable plugins, this may take a while.',
								'updatebee'
							) }
						</p>
						<p className="updatebee-tools-card-actions">
							<Button
								variant="solid"
								loading={ 'forceUpdates' === activeAction }
								loadingAnnouncement={ __(
									'Forcing updates. This could take a while.',
									'updatebee'
								) }
								disabled={ isBusy }
								onClick={ handleForceUpdates }
							>
								{ __( 'Force Updates', 'updatebee' ) }
							</Button>
						</p>
					</Card.Content>
				</Card.Root>

				<Card.Root className="updatebee-tools-card">
					<Card.Header>
						<Card.Title>
							{ __( 'Trigger the Update Cron', 'updatebee' ) }
						</Card.Title>
					</Card.Header>
					<Card.Content className="updatebee-tools-card-content">
						<p className="updatebee-tools-card-description">
							{ __(
								'The update cron is difficult to trigger. This tool makes it easy to trigger the cron processes.',
								'updatebee'
							) }
						</p>
						<p className="updatebee-tools-card-actions">
							<Button
								variant="solid"
								loading={ 'triggerCron' === activeAction }
								loadingAnnouncement={ __(
									'Triggering cron…',
									'updatebee'
								) }
								disabled={ isBusy }
								onClick={ handleTriggerCron }
							>
								{ __( 'Trigger Update Cron', 'updatebee' ) }
							</Button>
						</p>
					</Card.Content>
				</Card.Root>
			</div>

			<ToolsFooter
				snackbar={ snackbar }
				onSnackbarClose={ handleSnackbarClose }
			/>
		</div>
	);
};

export default ToolsMain;
