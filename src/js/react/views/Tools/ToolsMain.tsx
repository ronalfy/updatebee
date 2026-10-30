/**
 * Tools maintenance actions (force updates, trigger cron, refresh updates).
 */
import { useCallback, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Card, useEnableWpCompatOverlaySlot } from '@wordpress/ui';
import sendCommand from '../../../utils/SendCommand';
import ToolsFooter, {
	type SnackbarState,
	type SnackbarType,
} from './ToolsFooter';
import ToolsHideImporterModal from './ToolsHideImporterModal';
import ToolsMigrateModal from './ToolsMigrateModal';

type ToolsAction =
	| 'forceUpdates'
	| 'triggerCron'
	| 'refreshUpdates'
	| 'migrateEum'
	| 'hideEum'
	| 'migrateCau'
	| 'hideCau';

type ImporterKey = 'eum' | 'cau';

type ToolsMessagePayload = {
	message: string;
};

const getMigrateDescriptions = (): Record< ImporterKey, string > => ( {
	eum: sprintf(
		/* translators: %s: Plugin name. */
		__(
			'This will overwrite overlapping %s settings with values from Easy Updates Manager.',
			'updatebee'
		),
		updatebeeTools.pluginName
	),
	cau: sprintf(
		/* translators: %s: Plugin name. */
		__(
			'This will overwrite overlapping %s settings with values from Companion Auto Update.',
			'updatebee'
		),
		updatebeeTools.pluginName
	),
} );

const getHideDescriptions = (): Record< ImporterKey, string > => ( {
	eum: sprintf(
		/* translators: %s: Plugin name. */
		__(
			"After you hide this, Easy Updates Manager settings can only be imported again if you reset %s's import options.",
			'updatebee'
		),
		updatebeeTools.pluginName
	),
	cau: sprintf(
		/* translators: %s: Plugin name. */
		__(
			"After you hide this, Companion Auto Update settings can only be imported again if you reset %s's import options.",
			'updatebee'
		),
		updatebeeTools.pluginName
	),
} );

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
	const [ migrateTarget, setMigrateTarget ] = useState< ImporterKey | null >(
		null
	);
	const [ hideTarget, setHideTarget ] = useState< ImporterKey | null >(
		null
	);
	const [ showEumImporter, setShowEumImporter ] = useState(
		updatebeeTools.eumDetected
	);
	const [ showCauImporter, setShowCauImporter ] = useState(
		updatebeeTools.cauDetected
	);

	const isBusy = null !== activeAction;
	const isMigrating =
		'migrateEum' === activeAction || 'migrateCau' === activeAction;
	const isHiding = 'hideEum' === activeAction || 'hideCau' === activeAction;

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
		if ( isMigrating ) {
			return;
		}

		setMigrateTarget( null );
	}, [ isMigrating ] );

	/**
	 * Close the hide-importer confirmation modal.
	 */
	const handleCloseHideImporterModal = useCallback( () => {
		if ( isHiding ) {
			return;
		}

		setHideTarget( null );
	}, [ isHiding ] );

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
	 * Open the migration confirmation modal for one importer.
	 *
	 * @param importer Importer card that was clicked.
	 */
	const handleMigrate = ( importer: ImporterKey ) => {
		if ( isBusy ) {
			return;
		}

		setMigrateTarget( importer );
	};

	/**
	 * Import settings after confirmation.
	 *
	 * @return True when the request succeeds.
	 */
	const handleConfirmMigrate = useCallback( (): Promise< boolean > => {
		if ( 'cau' === migrateTarget ) {
			return runToolAction(
				'updatebee_migrate_cau',
				updatebeeTools.migrateCauNonce,
				'migrateCau',
				__( 'Migration could not be completed.', 'updatebee' )
			);
		}

		return runToolAction(
			'updatebee_migrate_eum',
			updatebeeTools.migrateEumNonce,
			'migrateEum',
			__( 'Migration could not be completed.', 'updatebee' )
		);
	}, [ migrateTarget, runToolAction ] );

	/**
	 * Open the hide-importer confirmation modal.
	 *
	 * @param importer Importer card that was clicked.
	 */
	const handleHideImporter = ( importer: ImporterKey ) => {
		if ( isBusy ) {
			return;
		}

		setHideTarget( importer );
	};

	/**
	 * Persist the hide-importer flag after confirmation.
	 *
	 * @return True when the request succeeds.
	 */
	const handleConfirmHideImporter = useCallback( (): Promise< boolean > => {
		if ( 'cau' === hideTarget ) {
			return runToolAction(
				'updatebee_hide_cau_importer',
				updatebeeTools.hideCauNonce,
				'hideCau',
				__( 'The importer could not be hidden.', 'updatebee' )
			).then( ( didSucceed ) => {
				if ( didSucceed ) {
					setShowCauImporter( false );
				}

				return didSucceed;
			} );
		}

		return runToolAction(
			'updatebee_hide_eum_importer',
			updatebeeTools.hideEumNonce,
			'hideEum',
			__( 'The importer could not be hidden.', 'updatebee' )
		).then( ( didSucceed ) => {
			if ( didSucceed ) {
				setShowEumImporter( false );
			}

			return didSucceed;
		} );
	}, [ hideTarget, runToolAction ] );

	const migrateCopy =
		null === migrateTarget
			? ''
			: getMigrateDescriptions()[ migrateTarget ];
	const hideCopy =
		null === hideTarget ? '' : getHideDescriptions()[ hideTarget ];

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

				{ showEumImporter && (
					<Card.Root className="updatebee-tools-card">
						<Card.Header>
							<Card.Title>
								{ __(
									'Migrate from Easy Updates Manager',
									'updatebee'
								) }
							</Card.Title>
						</Card.Header>
						<Card.Content className="updatebee-tools-card-content">
							<p className="updatebee-tools-card-description">
								{ sprintf(
									/* translators: %s: Plugin name. */
									__(
										'Import core, plugin, theme, and translation settings from Easy Updates Manager. Overlapping %s settings will be replaced.',
										'updatebee'
									),
									updatebeeTools.pluginName
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
									onClick={ () => handleMigrate( 'eum' ) }
								>
									{ __( 'Run Migration', 'updatebee' ) }
								</Button>
								<Button
									variant="outline"
									loading={ 'hideEum' === activeAction }
									loadingAnnouncement={ __(
										'Hiding importer…',
										'updatebee'
									) }
									disabled={ isBusy }
									onClick={ () => handleHideImporter( 'eum' ) }
								>
									{ __( 'Hide importer', 'updatebee' ) }
								</Button>
							</p>
						</Card.Content>
					</Card.Root>
				) }

				{ showCauImporter && (
					<Card.Root className="updatebee-tools-card">
						<Card.Header>
							<Card.Title>
								{ __(
									'Migrate from Companion Auto Update',
									'updatebee'
								) }
							</Card.Title>
						</Card.Header>
						<Card.Content className="updatebee-tools-card-content">
							<p className="updatebee-tools-card-description">
								{ sprintf(
									/* translators: %s: Plugin name. */
									__(
										'Import core, plugin, theme, and translation settings from Companion Auto Update. Overlapping %1$s settings will be replaced. Deactivate Companion Auto Update afterward so its update filters do not override %1$s.',
										'updatebee'
									),
									updatebeeTools.pluginName
								) }
							</p>
							<p className="updatebee-tools-card-actions">
								<Button
									variant="solid"
									loading={ 'migrateCau' === activeAction }
									loadingAnnouncement={ __(
										'Migrating settings…',
										'updatebee'
									) }
									disabled={ isBusy }
									onClick={ () => handleMigrate( 'cau' ) }
								>
									{ __( 'Run Migration', 'updatebee' ) }
								</Button>
								<Button
									variant="outline"
									loading={ 'hideCau' === activeAction }
									loadingAnnouncement={ __(
										'Hiding importer…',
										'updatebee'
									) }
									disabled={ isBusy }
									onClick={ () => handleHideImporter( 'cau' ) }
								>
									{ __( 'Hide importer', 'updatebee' ) }
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
				isOpen={ null !== migrateTarget }
				isMigrating={ isMigrating }
				title={ __( 'Run migration', 'updatebee' ) }
				description={ migrateCopy }
				onClose={ handleCloseMigrateModal }
				onMigrate={ handleConfirmMigrate }
			/>
			<ToolsHideImporterModal
				isOpen={ null !== hideTarget }
				isHiding={ isHiding }
				description={ hideCopy }
				onClose={ handleCloseHideImporterModal }
				onHide={ handleConfirmHideImporter }
			/>
		</div>
	);
};

export default ToolsMain;
