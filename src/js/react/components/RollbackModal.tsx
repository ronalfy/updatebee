/**
 * Choose an older WordPress.org release for a plugin or theme.
 */
import { Button, Modal, Notice, RadioControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import sendCommand from '../../utils/SendCommand';
import type { AssetUpdate } from './AssetUpdatePanel';

export type RollbackKind = 'plugin' | 'theme';

export type RollbackTarget = {
	kind: RollbackKind;
	id: string;
	name: string;
	nonce: string;
};

type VersionPayload = {
	versions?: string[];
};

type RollbackPayload = {
	version?: string;
	update?: AssetUpdate | null;
	message?: string;
};

type RollbackModalProps = {
	target: RollbackTarget | null;
	onClose: () => void;
	onBusy: ( busy: boolean ) => void;
	onRolledBack: (
		version: string,
		update: AssetUpdate | null,
		message: string
	) => void;
};

/**
 * Modal listing older directory releases and installing the selected one.
 *
 * @param props Modal state and callbacks.
 */
const RollbackModal = ( props: RollbackModalProps ) => {
	const { target, onClose, onBusy, onRolledBack } = props;
	const [ versions, setVersions ] = useState< string[] >( [] );
	const [ selected, setSelected ] = useState( '' );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ isInstalling, setIsInstalling ] = useState( false );
	const [ error, setError ] = useState( '' );

	const targetKind = target?.kind;
	const targetId = target?.id;
	const targetNonce = target?.nonce;

	useEffect( () => {
		if ( ! targetKind || ! targetId || ! targetNonce ) {
			return undefined;
		}

		let cancelled = false;
		const versionsAction =
			'plugin' === targetKind
				? 'updatebee_plugin_rollback_versions'
				: 'updatebee_theme_rollback_versions';
		const idField = 'plugin' === targetKind ? 'file' : 'stylesheet';

		setIsLoading( true );
		setError( '' );
		setVersions( [] );
		setSelected( '' );

		sendCommand< VersionPayload >( versionsAction, {
			nonce: targetNonce,
			[ idField ]: targetId,
		} )
			.then( ( response ) => {
				if ( cancelled ) {
					return;
				}
				const next = response.data.data.versions;
				setVersions(
					Array.isArray( next )
						? next.filter(
								( version ) => 'string' === typeof version
						  )
						: []
				);
			} )
			.catch( ( loadError: Error ) => {
				if ( cancelled ) {
					return;
				}
				setError(
					loadError.message ||
						__( 'WordPress.org could not be reached.', 'updatebee' )
				);
			} )
			.finally( () => {
				if ( ! cancelled ) {
					setIsLoading( false );
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [ targetKind, targetId, targetNonce ] );

	if ( ! target ) {
		return null;
	}

	const staysActive =
		'plugin' === target.kind
			? __(
					'This replaces the installed copy with an older WordPress.org release. The plugin stays active.',
					'updatebee'
			  )
			: __(
					'This replaces the installed copy with an older WordPress.org release. The theme stays active.',
					'updatebee'
			  );

	const requestClose = () => {
		if ( isInstalling ) {
			return;
		}
		onClose();
	};

	const install = () => {
		if ( '' === selected || isInstalling ) {
			return;
		}

		const installAction =
			'plugin' === target.kind
				? 'updatebee_rollback_plugin'
				: 'updatebee_rollback_theme';
		const idField = 'plugin' === target.kind ? 'file' : 'stylesheet';
		const fallback =
			'plugin' === target.kind
				? __( 'Plugin could not be rolled back.', 'updatebee' )
				: __( 'Theme could not be rolled back.', 'updatebee' );

		setIsInstalling( true );
		setError( '' );
		onBusy( true );

		sendCommand< RollbackPayload >( installAction, {
			nonce: target.nonce,
			version: selected,
			[ idField ]: target.id,
		} )
			.then( ( response ) => {
				const version = response.data.data.version;
				if ( 'string' !== typeof version || '' === version ) {
					throw new Error( fallback );
				}
				const message =
					response.data.data.message ||
					( 'plugin' === target.kind
						? __( 'Plugin rolled back.', 'updatebee' )
						: __( 'Theme rolled back.', 'updatebee' ) );
				setIsInstalling( false );
				onBusy( false );
				onRolledBack(
					version,
					response.data.data.update ?? null,
					message
				);
			} )
			.catch( ( installError: Error ) => {
				setError( installError.message || fallback );
				setIsInstalling( false );
				onBusy( false );
			} );
	};

	let body = null;
	if ( isLoading ) {
		body = (
			<p className="updatebee-rollback-loading">
				{ __( 'Loading previous versions…', 'updatebee' ) }
			</p>
		);
	} else if ( 0 === versions.length && '' === error ) {
		body = (
			<p>
				{ __(
					'No older versions are available from WordPress.org.',
					'updatebee'
				) }
			</p>
		);
	} else if ( versions.length > 0 ) {
		body = (
			<>
				<p>{ staysActive }</p>
				<div className="updatebee-rollback-versions">
					<RadioControl
						label={ __( 'Previous versions', 'updatebee' ) }
						selected={ selected }
						options={ versions.map( ( version ) => ( {
							label: version,
							value: version,
						} ) ) }
						onChange={ ( value ) => {
							setSelected( value );
						} }
					/>
				</div>
			</>
		);
	}

	return (
		<Modal
			className="updatebee-rollback-modal"
			title={ sprintf(
				/* translators: %s: Plugin or theme name. */
				__( 'Rollback %s', 'updatebee' ),
				target.name
			) }
			onRequestClose={ requestClose }
			shouldCloseOnClickOutside={ ! isInstalling }
			shouldCloseOnEsc={ ! isInstalling }
			isDismissible={ ! isInstalling }
		>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ body }
			<div className="updatebee-rollback-actions">
				<Button
					variant="secondary"
					onClick={ requestClose }
					disabled={ isInstalling }
				>
					{ __( 'Cancel', 'updatebee' ) }
				</Button>
				<Button
					variant="primary"
					onClick={ install }
					disabled={
						'' === selected ||
						isInstalling ||
						isLoading ||
						0 === versions.length
					}
					isBusy={ isInstalling }
				>
					{ __( 'Install this version', 'updatebee' ) }
				</Button>
			</div>
		</Modal>
	);
};

export default RollbackModal;
