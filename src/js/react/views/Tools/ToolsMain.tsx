/**
 * Tools maintenance actions (force updates, trigger cron, refresh updates).
 */
import { useCallback, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Card, useEnableWpCompatOverlaySlot } from '@wordpress/ui';
import sendCommand from '../../../utils/SendCommand';
import ToolsFooter, {
	type SnackbarState,
	type SnackbarType,
} from './ToolsFooter';
import ToolsMigrateModal from './ToolsMigrateModal';

type ToolsAction = 'forceUpdates' | 'triggerCron' | 'refreshUpdates' | 'migrateEum';

type ToolsMessagePayload = {
	message: string;
};

const ToolsMain = () => {
	useEnableWpCompatOverlaySlot();
	const [ activeAction, setActiveAction ] = useState< ToolsAction | null >(
		null
	);
	const [ snackbar, setSnackbar ] = useState< SnackbarState >( {
		isVisible: false,
		message: '',
		type: 'success',
	} );
	const [ isMigrateOpen, setIsMigrateOpen ] = useState( false );

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
	 * @param action       Admin-ajax action name.
	 * @param nonce        Request nonce.
	 * @param actionKey    Local loading state key.
	 * @param errorMessage Fallback error notice.
	 * @return True when the request succeeds.
	 */
	const runToolAction = useCallback(
		(
			action: string,
			nonce: string,
			actionKey: ToolsAction,
			errorMessage: string
		): Promise< boolean > => {
			if ( isBusy ) {
				return Promise.resolve( false );
			}

			setActiveAction( actionKey );

			return sendCommand< ToolsMessagePayload >( action, { nonce } )
				.then( ( response ) => {
					const message =
						response.data.data?.message ||
						__( 'Action completed.', 'updatebee' );
					showSnackbar( message, 'success' );
					return true;
				} )
				.catch( () => {
					showSnackbar( errorMessage, 'error' );
					return false;
				} )
				.finally( () => {
					setActiveAction( null );
				} );
		},
		[ isBusy, showSnackbar ]
	);

	/**
	 * Close the migration confirmation modal.
	 */
	const handleCloseMigrateModal = useCallback( () => {
		if ( 'migrateEum' === activeAction ) {
			return;
		}

		setIsMigrateOpen( false );
	}, [ activeAction ] );

	const handleForceUpdates = () => {
		runToolAction(
			'updatebee_force_updates',
			updatebeeTools.forceUpdatesNonce,
			'forceUpdates',
			__( 'Force updates could not be completed.', 'updatebee' )
		);
	};

	const handleTriggerCron = () => {
		runToolAction(
			'updatebee_trigger_update_cron',
			updatebeeTools.triggerCronNonce,
			'triggerCron',
			__( 'Update cron could not be triggered.', 'updatebee' )
		);
	};

	const handleRefreshUpdates = () => {
		runToolAction(
			'updatebee_refresh_updates',
			updatebeeTools.refreshUpdatesNonce,
			'refreshUpdates',
			__( 'Updates could not be refreshed.', 'updatebee' )
		);
	};

	/**
	 * Open the Easy Updates Manager migration confirmation modal.
	 */
	const handleMigrateEum = () => {
		if ( isBusy ) {
			return;
		}

		setIsMigrateOpen( true );
	};

	/**
	 * Import Easy Updates Manager settings after confirmation.
	 *
	 * @return True when the request succeeds.
	 */
	const handleConfirmMigrate = useCallback( (): Promise< boolean > => {
		return runToolAction(
			'updatebee_migrate_eum',
			updatebeeTools.migrateEumNonce,
			'migrateEum',
			__( 'Migration could not be completed.', 'updatebee' )
		);
	}, [ runToolAction ] );

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

				<Card.Root className="updatebee-tools-card">
					<Card.Header>
						<Card.Title>
							{ __( 'Force Refresh Updates', 'updatebee' ) }
						</Card.Title>
					</Card.Header>
					<Card.Content className="updatebee-tools-card-content">
						<p className="updatebee-tools-card-description">
							{ __(
								'Clears the WordPress update cache and checks WordPress.org again for core, plugin, and theme updates. This runs immediately and may take a moment on large sites.',
								'updatebee'
							) }
						</p>
						<p className="updatebee-tools-card-actions">
							<Button
								variant="solid"
								loading={ 'refreshUpdates' === activeAction }
								loadingAnnouncement={ __(
									'Refreshing updates…',
									'updatebee'
								) }
								disabled={ isBusy }
								onClick={ handleRefreshUpdates }
							>
								{ __( 'Refresh Updates', 'updatebee' ) }
							</Button>
						</p>
					</Card.Content>
				</Card.Root>

				{ updatebeeTools.eumDetected && (
					<Card.Root className="updatebee-tools-card">
						<Card.Header>
							<Card.Title>
								{ __( 'Migrate from Easy Updates Manager', 'updatebee' ) }
							</Card.Title>
						</Card.Header>
						<Card.Content className="updatebee-tools-card-content">
							<p className="updatebee-tools-card-description">
								{ __(
									'Import core, plugin, theme, and translation settings from Easy Updates Manager. Overlapping UpdateBee settings will be replaced.',
									'updatebee'
								) }
							</p>
							<p className="updatebee-tools-card-actions">
								<Button
									variant="solid"
									loading={ 'migrateEum' === activeAction }
									loadingAnnouncement={ __(
										'Migrating settings…',
										'updatebee'
									) }
									disabled={ isBusy }
									onClick={ handleMigrateEum }
								>
									{ __( 'Run Migration', 'updatebee' ) }
								</Button>
							</p>
						</Card.Content>
					</Card.Root>
				) }
			</div>

			<ToolsFooter
				snackbar={ snackbar }
				onSnackbarClose={ handleSnackbarClose }
			/>
			<ToolsMigrateModal
				isOpen={ isMigrateOpen }
				isMigrating={ 'migrateEum' === activeAction }
				onClose={ handleCloseMigrateModal }
				onMigrate={ handleConfirmMigrate }
			/>
		</div>
	);
};

export default ToolsMain;
