/**
 * Pending update row under a plugin or theme card.
 */
import { RawHTML, useCallback, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button } from '@wordpress/ui';
import sendCommand from '../../utils/SendCommand';

export type AssetUpdate = {
	new_version: string;
	upgrade_notice: string;
	has_package: boolean;
	nonce: string;
	skipped: boolean;
	skip_nonce: string;
};

type SnackbarType = 'success' | 'error' | 'warning' | 'info';

type UpdatePayload = {
	version?: string;
	message?: string;
};

type AssetUpdatePanelProps = {
	kind: 'plugin' | 'theme';
	assetId: string;
	update: AssetUpdate;
	blocked: boolean;
	updatesFullyBlocked: boolean;
	disabled?: boolean;
	onUpdated: ( version: string ) => void;
	onBusy: ( busy: boolean ) => void;
	showSnackbar: ( message: string, type: SnackbarType ) => void;
};

/**
 * Version, optional upgrade notice, and manual update button.
 *
 * @param props Component props.
 */
const AssetUpdatePanel = ( props: AssetUpdatePanelProps ) => {
	const {
		kind,
		assetId,
		update,
		blocked,
		updatesFullyBlocked,
		disabled = false,
		onUpdated,
		onBusy,
		showSnackbar,
	} = props;
	const [ isUpdating, setIsUpdating ] = useState( false );
	const [ isSkipping, setIsSkipping ] = useState( false );
	const [ skipped, setSkipped ] = useState( Boolean( update.skipped ) );
	const updatesBlocked = blocked || updatesFullyBlocked;
	const canUpdate =
		update.has_package && ! updatesBlocked && ! disabled && ! isUpdating;

	let reason = '';
	if ( updatesFullyBlocked ) {
		reason =
			'plugin' === kind
				? __( 'Plugin updates are blocked.', 'updatebee' )
				: __( 'Theme updates are blocked.', 'updatebee' );
	} else if ( blocked ) {
		reason =
			'plugin' === kind
				? __(
						'Unblock updates before updating this plugin.',
						'updatebee'
				  )
				: __(
						'Unblock updates before updating this theme.',
						'updatebee'
				  );
	} else if ( ! update.has_package ) {
		reason = __( 'This update has no installable package.', 'updatebee' );
	}

	useEffect( () => {
		setSkipped( Boolean( update.skipped ) );
	}, [ update.skipped ] );

	/**
	 * Skip or restore this offered version.
	 */
	const handleSkip = useCallback( () => {
		if (
			disabled ||
			isSkipping ||
			isUpdating ||
			'' === update.skip_nonce
		) {
			return;
		}

		const action =
			'plugin' === kind
				? 'updatebee_skip_plugin_version'
				: 'updatebee_skip_theme_version';
		const assetKey = 'plugin' === kind ? 'file' : 'stylesheet';
		const nextSkipped = ! skipped;

		setIsSkipping( true );
		onBusy( true );

		sendCommand< UpdatePayload >( action, {
			nonce: update.skip_nonce,
			[ assetKey ]: assetId,
			version: update.new_version,
			enabled: nextSkipped,
		} )
			.then( ( response ) => {
				setSkipped( nextSkipped );
				showSnackbar(
					response.data?.data?.message ||
						( nextSkipped
							? __( 'This version will be skipped.', 'updatebee' )
							: __(
									'This version is scheduled again.',
									'updatebee'
							  ) ),
					'success'
				);
			} )
			.catch( ( error: Error ) => {
				showSnackbar(
					error.message ||
						__( 'The version could not be skipped.', 'updatebee' ),
					'error'
				);
			} )
			.finally( () => {
				setIsSkipping( false );
				onBusy( false );
			} );
	}, [
		assetId,
		disabled,
		isSkipping,
		isUpdating,
		kind,
		onBusy,
		showSnackbar,
		skipped,
		update.new_version,
		update.skip_nonce,
	] );

	/**
	 * Install this asset's pending update.
	 */
	const handleUpdate = useCallback( () => {
		if (
			! update.has_package ||
			blocked ||
			updatesFullyBlocked ||
			disabled ||
			isUpdating
		) {
			return;
		}

		const action =
			'plugin' === kind
				? 'updatebee_update_plugin'
				: 'updatebee_update_theme';
		const assetKey = 'plugin' === kind ? 'file' : 'stylesheet';

		setIsUpdating( true );
		onBusy( true );

		sendCommand< UpdatePayload >( action, {
			nonce: update.nonce,
			[ assetKey ]: assetId,
		} )
			.then( ( response ) => {
				const version =
					response.data?.data?.version || update.new_version;
				const message =
					response.data?.data?.message ||
					( 'plugin' === kind
						? __( 'Plugin updated.', 'updatebee' )
						: __( 'Theme updated.', 'updatebee' ) );
				onUpdated( version );
				showSnackbar( message, 'success' );
			} )
			.catch( ( error: Error ) => {
				showSnackbar(
					error.message ||
						( 'plugin' === kind
							? __( 'Plugin could not be updated.', 'updatebee' )
							: __(
									'Theme could not be updated.',
									'updatebee'
							  ) ),
					'error'
				);
			} )
			.finally( () => {
				setIsUpdating( false );
				onBusy( false );
			} );
	}, [
		assetId,
		blocked,
		disabled,
		isUpdating,
		kind,
		onBusy,
		onUpdated,
		showSnackbar,
		update.has_package,
		update.new_version,
		update.nonce,
		updatesFullyBlocked,
	] );

	return (
		<div className="updatebee-asset-update">
			<p className="updatebee-asset-update-version">
				{ sprintf(
					/* translators: %s: version number. */
					__( 'Version %s is available.', 'updatebee' ),
					update.new_version
				) }
				{ skipped && (
					<span className="updatebee-asset-update-skipped">
						{ __( 'This version will be skipped.', 'updatebee' ) }
					</span>
				) }
			</p>
			{ '' !== update.upgrade_notice && (
				<div className="updatebee-asset-update-notice">
					<RawHTML>{ update.upgrade_notice }</RawHTML>
				</div>
			) }
			<Button
				variant="outline"
				loading={ isSkipping }
				loadingAnnouncement={
					skipped
						? __( 'Scheduling this version…', 'updatebee' )
						: __( 'Skipping this version…', 'updatebee' )
				}
				disabled={ disabled || isUpdating || isSkipping }
				onClick={ handleSkip }
			>
				{ skipped
					? __( 'Schedule update', 'updatebee' )
					: __( 'Skip this version', 'updatebee' ) }
			</Button>
			<Button
				variant="solid"
				loading={ isUpdating }
				loadingAnnouncement={
					'plugin' === kind
						? __( 'Updating plugin…', 'updatebee' )
						: __( 'Updating theme…', 'updatebee' )
				}
				disabled={ ! canUpdate }
				onClick={ handleUpdate }
			>
				{ __( 'Update now', 'updatebee' ) }
			</Button>
			{ '' !== reason && (
				<p className="updatebee-asset-update-reason">{ reason }</p>
			) }
		</div>
	);
};

export default AssetUpdatePanel;
