/**
 * Plugins screen: load and populate plugin auto-update rows.
 */
import {
	CheckboxControl,
	Notice,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { Badge } from '@wordpress/ui';
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { _n, __, sprintf } from '@wordpress/i18n';
import {
	backup as BackupIcon,
	lock as LockIcon,
	unlock as UnlockIcon,
	update as UpdateIcon,
	cancelCircleFilled as CancelCircleFilledIcon,
} from '@wordpress/icons';
import {
	Controller,
	FormProvider,
	useForm,
	useFormContext,
	useWatch,
} from 'react-hook-form';
import sendCommand from '../../../utils/SendCommand';
import { ErrorFallback } from '../../components/ErrorBoundary';
import Loader from '../../components/Loader';
import { type Action } from './bulk-actions';
import PluginNote from './PluginNote';
import { getPluginFieldKey } from './plugin-fields';
import PluginsFooter, {
	type SnackbarState,
	type SnackbarType,
} from './PluginsFooter';

export { getPluginFieldKey };

type PluginIcons = {
	'1x'?: string;
	'2x'?: string;
};

export type PluginItem = {
	File: string;
	Slug: string;
	Name: string;
	Description?: string;
	Version?: string;
	AuthorName?: string;
	AuthorURI?: string;
	PluginURI?: string;
	Details?: string;
	url?: string;
	id?: string;
	icons?: PluginIcons;
	Active: boolean;
	Autoupdates: boolean;
	Blocked: boolean;
	PatchesOnly: boolean;
	LastUpdated: string;
	EnableNonce: string;
	BlockNonce: string;
	PatchNonce: string;
	NoteNonce: string;
	Notes: string;
};

type AutoUpdatesStatus = 'enabled' | 'custom' | 'disabled' | 'blocked';

type PluginsPayload = {
	plugins?: Record< string, PluginItem >;
	auto_updates_enabled?: AutoUpdatesStatus;
};

export type PluginsFormValues = Record< string, boolean >;

type PluginToggleSnapshot = {
	auto_update: boolean;
	patches_only: boolean;
	block_updates: boolean;
};

type BulkActionId = 'enable' | 'disable' | 'block' | 'unblock' | 'patches';

const dirty = {
	shouldDirty: true,
};

const isDotOrgPlugin = ( plugin: PluginItem ): boolean => {
	if ( ! plugin.id ) {
		return false;
	}

	return /w\.org/i.test( plugin.id );
};

const getPluginIconPath = ( plugin: PluginItem ): string => {
	if ( ! plugin.icons ) {
		return '';
	}

	return plugin.icons[ '2x' ] || plugin.icons[ '1x' ] || '';
};

const getStatusNotice = (
	status: AutoUpdatesStatus
): { message: string; status: 'success' | 'warning' | 'error' | 'info' } => {
	if ( 'enabled' === status ) {
		return {
			status: 'success',
			message: __(
				'Automatic updates are enabled for all plugins.',
				'updatebee'
			),
		};
	}

	if ( 'custom' === status ) {
		return {
			status: 'info',
			message: __(
				'Automatic updates are enabled, but only for the plugins that are manually enabled.',
				'updatebee'
			),
		};
	}

	if ( 'blocked' === status ) {
		return {
			status: 'error',
			message: __(
				'All updates are blocked. You will no longer receive any plugin updates and may be a security risk.',
				'updatebee'
			),
		};
	}

	return {
		status: 'warning',
		message: __(
			'Automatic updates are disabled for all plugins.',
			'updatebee'
		),
	};
};

/**
 * Success copy after a bulk action.
 *
 * @param actionId Bulk action id.
 * @param count    Eligible plugin count.
 */
const getBulkSuccessMessage = (
	actionId: BulkActionId,
	count: number
): string => {
	switch ( actionId ) {
		case 'enable':
			return sprintf(
				/* translators: %d: Number of plugins. */
				_n(
					'Automatic updates enabled for %d plugin.',
					'Automatic updates enabled for %d plugins.',
					count,
					'updatebee'
				),
				count
			);
		case 'disable':
			return sprintf(
				/* translators: %d: Number of plugins. */
				_n(
					'Automatic updates disabled for %d plugin.',
					'Automatic updates disabled for %d plugins.',
					count,
					'updatebee'
				),
				count
			);
		case 'block':
			return sprintf(
				/* translators: %d: Number of plugins. */
				_n(
					'Updates blocked for %d plugin.',
					'Updates blocked for %d plugins.',
					count,
					'updatebee'
				),
				count
			);
		case 'unblock':
			return sprintf(
				/* translators: %d: Number of plugins. */
				_n(
					'Updates unblocked for %d plugin.',
					'Updates unblocked for %d plugins.',
					count,
					'updatebee'
				),
				count
			);
		case 'patches':
			return sprintf(
				/* translators: %d: Number of plugins. */
				_n(
					'Patches only enabled for %d plugin.',
					'Patches only enabled for %d plugins.',
					count,
					'updatebee'
				),
				count
			);
		default:
			return __( 'Plugin updates have been updated.', 'updatebee' );
	}
};

const PluginsList = ( props: {
	plugins: PluginItem[];
	autoUpdatesStatus: AutoUpdatesStatus;
	onNotesChange: ( file: string, notes: string ) => void;
} ) => {
	const { plugins, autoUpdatesStatus, onNotesChange } = props;
	const { control, getValues, setValue } =
		useFormContext< PluginsFormValues >();
	// Subscribe so filters and row copy recompute after setValue, including from the footer Slot.
	useWatch( { control } );
	const [ search, setSearch ] = useState( '' );
	const [ filter, setFilter ] = useState<
		| ''
		| 'all'
		| 'activated'
		| 'deactivated'
		| 'auto-updates-enabled'
		| 'auto-updates-disabled'
		| 'blocked'
		| 'unblocked'
		| 'patches-only'
		| 'third-party'
		| 'org-plugin'
	>( '' );
	const [ busyPlugins, setBusyPlugins ] = useState<
		Record< string, boolean >
	>( {} );
	const [ bulkBusy, setBulkBusy ] = useState( false );
	const [ snackbar, setSnackbar ] = useState< SnackbarState >( {
		isVisible: false,
		message: '',
		type: 'success',
	} );
	const notice = getStatusNotice( autoUpdatesStatus );
	const autoUpdatesAvailable =
		'disabled' !== autoUpdatesStatus && 'blocked' !== autoUpdatesStatus;
	const blockActionsAvailable = 'blocked' !== autoUpdatesStatus;

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
	 * Mark a plugin row as saving.
	 *
	 * @param file Plugin file.
	 * @param busy Whether the row is busy.
	 */
	const setPluginBusy = ( file: string, busy: boolean ) => {
		setBusyPlugins( ( current ) => {
			if ( busy ) {
				return {
					...current,
					[ file ]: true,
				};
			}

			const next = { ...current };
			delete next[ file ];
			return next;
		} );
	};

	/**
	 * Current toggle values for a plugin row.
	 *
	 * @param plugin Plugin row.
	 */
	const snapshotPluginFields = useCallback(
		( plugin: PluginItem ): PluginToggleSnapshot => ( {
			auto_update: Boolean(
				getValues( getPluginFieldKey( plugin, 'auto_update' ) )
			),
			patches_only: Boolean(
				getValues( getPluginFieldKey( plugin, 'patches_only' ) )
			),
			block_updates: Boolean(
				getValues( getPluginFieldKey( plugin, 'block_updates' ) )
			),
		} ),
		[ getValues ]
	);

	/**
	 * Restore toggle values after a failed save.
	 *
	 * @param plugin   Plugin row.
	 * @param snapshot Values to restore.
	 */
	const restorePluginFields = useCallback(
		( plugin: PluginItem, snapshot: PluginToggleSnapshot ) => {
			setValue(
				getPluginFieldKey( plugin, 'auto_update' ),
				snapshot.auto_update,
				dirty
			);
			setValue(
				getPluginFieldKey( plugin, 'patches_only' ),
				snapshot.patches_only,
				dirty
			);
			setValue(
				getPluginFieldKey( plugin, 'block_updates' ),
				snapshot.block_updates,
				dirty
			);
		},
		[ setValue ]
	);

	const visiblePlugins = plugins.filter( ( plugin ) => {
		if ( filter && 'all' !== filter ) {
			const autoUpdate = Boolean(
				getValues( getPluginFieldKey( plugin, 'auto_update' ) )
			);
			const blocked = Boolean(
				getValues( getPluginFieldKey( plugin, 'block_updates' ) )
			);
			const patchesOnly = Boolean(
				getValues( getPluginFieldKey( plugin, 'patches_only' ) )
			);

			switch ( filter ) {
				case 'activated':
					if ( ! plugin.Active ) {
						return false;
					}
					break;
				case 'deactivated':
					if ( plugin.Active ) {
						return false;
					}
					break;
				case 'auto-updates-enabled':
					if ( ! autoUpdate ) {
						return false;
					}
					break;
				case 'auto-updates-disabled':
					if ( autoUpdate ) {
						return false;
					}
					break;
				case 'blocked':
					if ( ! blocked ) {
						return false;
					}
					break;
				case 'unblocked':
					if ( blocked ) {
						return false;
					}
					break;
				case 'patches-only':
					if ( ! patchesOnly ) {
						return false;
					}
					break;
				case 'third-party':
					if ( isDotOrgPlugin( plugin ) ) {
						return false;
					}
					break;
				case 'org-plugin':
					if ( ! isDotOrgPlugin( plugin ) ) {
						return false;
					}
					break;
				default:
					return false;
			}
		}

		if (
			search &&
			! plugin.Name.toLowerCase().includes( search.toLowerCase() )
		) {
			return false;
		}

		return true;
	} );

	/**
	 * Persist a bulk action and revert on failure.
	 *
	 * @param actionId        Bulk action id.
	 * @param selectedPlugins Eligible plugins.
	 * @param snapshots       Values from before the local update.
	 */
	const persistBulkAction = useCallback(
		(
			actionId: BulkActionId,
			selectedPlugins: PluginItem[],
			snapshots: Array< {
				plugin: PluginItem;
				values: PluginToggleSnapshot;
			} >
		) => {
			setBulkBusy( true );

			sendCommand( 'updatebee_bulk_plugin_action', {
				nonce: updatebeePlugins.bulkNonce,
				bulk_action: actionId,
				files: selectedPlugins.map( ( plugin ) => plugin.File ),
			} )
				.then( () => {
					selectedPlugins.forEach( ( plugin ) => {
						setValue(
							getPluginFieldKey( plugin, 'selected' ),
							false
						);
					} );
					showSnackbar(
						getBulkSuccessMessage(
							actionId,
							selectedPlugins.length
						),
						'success'
					);
				} )
				.catch( () => {
					snapshots.forEach( ( { plugin, values } ) => {
						restorePluginFields( plugin, values );
					} );
					showSnackbar(
						__(
							'Plugin updates could not be updated.',
							'updatebee'
						),
						'error'
					);
				} )
				.finally( () => {
					setBulkBusy( false );
				} );
		},
		[ restorePluginFields, setValue, showSnackbar ]
	);

	const actions = useMemo< Action< PluginItem >[] >( () => {
		const isBlocked = ( plugin: PluginItem ): boolean =>
			Boolean(
				getValues( getPluginFieldKey( plugin, 'block_updates' ) )
			);
		const hasAutoUpdates = ( plugin: PluginItem ): boolean =>
			Boolean( getValues( getPluginFieldKey( plugin, 'auto_update' ) ) );
		const hasPatchesOnly = ( plugin: PluginItem ): boolean =>
			Boolean( getValues( getPluginFieldKey( plugin, 'patches_only' ) ) );

		const getCountLabel = (
			label: string,
			selectedPlugins: PluginItem[]
		): string =>
			sprintf(
				/* translators: 1: Action label. 2: Number of eligible plugins. */
				__( '%1$s (%2$d)', 'updatebee' ),
				label,
				selectedPlugins.length
			);

		const canEnable = ( plugin: PluginItem ): boolean =>
			autoUpdatesAvailable &&
			! isBlocked( plugin ) &&
			! hasAutoUpdates( plugin );
		const canDisable = ( plugin: PluginItem ): boolean =>
			autoUpdatesAvailable &&
			! isBlocked( plugin ) &&
			hasAutoUpdates( plugin );
		const canBlock = ( plugin: PluginItem ): boolean =>
			blockActionsAvailable && ! isBlocked( plugin );
		const canUnblock = ( plugin: PluginItem ): boolean =>
			blockActionsAvailable && isBlocked( plugin );
		const canEnablePatches = ( plugin: PluginItem ): boolean =>
			autoUpdatesAvailable &&
			! isBlocked( plugin ) &&
			! hasPatchesOnly( plugin );

		return [
			{
				id: 'enable',
				label: __( 'Enable Automatic Updates', 'updatebee' ),
				icon: UpdateIcon,
				isDestructive: false,
				isEligible: canEnable,
				getLabel: ( selectedPlugins ) =>
					getCountLabel(
						__( 'Enable Automatic Updates', 'updatebee' ),
						selectedPlugins
					),
				callback: ( selectedPlugins ) => {
					const snapshots = selectedPlugins.map( ( plugin ) => ( {
						plugin,
						values: snapshotPluginFields( plugin ),
					} ) );
					selectedPlugins.forEach( ( plugin ) => {
						setValue(
							getPluginFieldKey( plugin, 'auto_update' ),
							true,
							dirty
						);
					} );
					persistBulkAction( 'enable', selectedPlugins, snapshots );
				},
			},
			{
				id: 'disable',
				label: __( 'Disable Automatic Updates', 'updatebee' ),
				icon: CancelCircleFilledIcon,
				isDestructive: false,
				isEligible: canDisable,
				getLabel: ( selectedPlugins ) =>
					getCountLabel(
						__( 'Disable Automatic Updates', 'updatebee' ),
						selectedPlugins
					),
				callback: ( selectedPlugins ) => {
					const snapshots = selectedPlugins.map( ( plugin ) => ( {
						plugin,
						values: snapshotPluginFields( plugin ),
					} ) );
					selectedPlugins.forEach( ( plugin ) => {
						setValue(
							getPluginFieldKey( plugin, 'auto_update' ),
							false,
							dirty
						);
						setValue(
							getPluginFieldKey( plugin, 'patches_only' ),
							false,
							dirty
						);
					} );
					persistBulkAction( 'disable', selectedPlugins, snapshots );
				},
			},
			{
				id: 'block',
				label: __( 'Block Selected Plugins', 'updatebee' ),
				icon: LockIcon,
				isDestructive: true,
				isEligible: canBlock,
				getLabel: ( selectedPlugins ) =>
					getCountLabel(
						__( 'Block Selected Plugins', 'updatebee' ),
						selectedPlugins
					),
				callback: ( selectedPlugins ) => {
					const snapshots = selectedPlugins.map( ( plugin ) => ( {
						plugin,
						values: snapshotPluginFields( plugin ),
					} ) );
					selectedPlugins.forEach( ( plugin ) => {
						setValue(
							getPluginFieldKey( plugin, 'block_updates' ),
							true,
							dirty
						);
					} );
					persistBulkAction( 'block', selectedPlugins, snapshots );
				},
			},
			{
				id: 'unblock',
				label: __( 'Unblock Selected Plugins', 'updatebee' ),
				icon: UnlockIcon,
				isDestructive: false,
				isEligible: canUnblock,
				getLabel: ( selectedPlugins ) =>
					getCountLabel(
						__( 'Unblock Selected Plugins', 'updatebee' ),
						selectedPlugins
					),
				callback: ( selectedPlugins ) => {
					const snapshots = selectedPlugins.map( ( plugin ) => ( {
						plugin,
						values: snapshotPluginFields( plugin ),
					} ) );
					selectedPlugins.forEach( ( plugin ) => {
						setValue(
							getPluginFieldKey( plugin, 'block_updates' ),
							false,
							dirty
						);
					} );
					persistBulkAction( 'unblock', selectedPlugins, snapshots );
				},
			},
			{
				id: 'patches',
				label: __( 'Enable Patches Only', 'updatebee' ),
				icon: BackupIcon,
				isDestructive: false,
				isEligible: canEnablePatches,
				getLabel: ( selectedPlugins ) =>
					getCountLabel(
						__( 'Enable Patches Only', 'updatebee' ),
						selectedPlugins
					),
				callback: ( selectedPlugins ) => {
					const snapshots = selectedPlugins.map( ( plugin ) => ( {
						plugin,
						values: snapshotPluginFields( plugin ),
					} ) );
					selectedPlugins.forEach( ( plugin ) => {
						setValue(
							getPluginFieldKey( plugin, 'auto_update' ),
							true,
							dirty
						);
						setValue(
							getPluginFieldKey( plugin, 'patches_only' ),
							true,
							dirty
						);
					} );
					persistBulkAction( 'patches', selectedPlugins, snapshots );
				},
			},
		];
	}, [
		autoUpdatesAvailable,
		blockActionsAvailable,
		getValues,
		persistBulkAction,
		setValue,
		snapshotPluginFields,
	] );

	/**
	 * Get activation and notes badges for a plugin.
	 *
	 * @param plugin The plugin.
	 * @return The plugin badges.
	 */
	const getPluginBadges = ( plugin: PluginItem ): React.ReactNode => {
		const hasNotes = '' !== ( plugin.Notes || '' ).trim();
		let activationBadge: React.ReactNode = null;

		if ( ! updatebeePlugins.isNetworkAdmin || plugin.Active ) {
			let intent: React.ComponentProps< typeof Badge >[ 'intent' ] =
				'stable';
			let label: string = __( 'Activated', 'updatebee' );
			if ( ! plugin.Active ) {
				intent = 'high';
				label = __( 'Deactivated', 'updatebee' );
			} else if ( updatebeePlugins.isNetworkAdmin && plugin.Active ) {
				intent = 'stable';
				label = __( 'Network Activated', 'updatebee' );
			}
			activationBadge = <Badge intent={ intent }>{ label }</Badge>;
		}

		if ( ! activationBadge && ! hasNotes ) {
			return null;
		}

		return (
			<div className="updatebee-plugin-badge">
				{ activationBadge }
				{ hasNotes && (
					<Badge intent="low">
						{ __( 'Note', 'updatebee' ) }
					</Badge>
				) }
			</div>
		);
	};

	return (
		<>
			<div className="updatebee-plugins-main">
				<h1>{ __( 'Plugins', 'updatebee' ) }</h1>
				<Notice
					className="updatebee-plugin-notice"
					status={ notice.status }
					isDismissible={ false }
				>
					{ notice.message }
				</Notice>
				<div className="updatebee-bulk-row">
					<div className="updatebee-filter-actions">
						<SelectControl
							label={ __( 'Filter', 'updatebee' ) }
							value={ filter }
							options={ [
								{
									label: __( 'Select a Filter', 'updatebee' ),
									value: '',
								},
								{
									label: __( 'All', 'updatebee' ),
									value: 'all',
								},
								{
									label: __( 'Activated', 'updatebee' ),
									value: 'activated',
								},
								{
									label: __( 'Deactivated', 'updatebee' ),
									value: 'deactivated',
								},
								{
									label: __(
										'Automatic Updates Enabled',
										'updatebee'
									),
									value: 'auto-updates-enabled',
								},
								{
									label: __(
										'Automatic Updates Disabled',
										'updatebee'
									),
									value: 'auto-updates-disabled',
								},
								{
									label: __( 'Blocked', 'updatebee' ),
									value: 'blocked',
								},
								{
									label: __( 'Unblocked', 'updatebee' ),
									value: 'unblocked',
								},
								{
									label: __( 'Patches Only', 'updatebee' ),
									value: 'patches-only',
								},
								{
									label: __(
										'Third-party Plugins',
										'updatebee'
									),
									value: 'third-party',
								},
								{
									label: __(
										'WordPress.org Plugins',
										'updatebee'
									),
									value: 'org-plugin',
								},
							] }
							onChange={ ( value ) => {
								setFilter( value );
							} }
						/>
					</div>
					<div className="updatebee-plugin-list-search">
						<TextControl
							label={ __( 'Search Plugins', 'updatebee' ) }
							value={ search }
							onChange={ ( value ) => {
								setSearch( value );
							} }
						/>
					</div>
				</div>
				<div className="updatebee-plugin-list-wrapper">
					{ visiblePlugins.length === 0 &&
						<div className="updatebee-plugin-list-empty">
							{ __( 'No plugins found matching your filters or search.', 'updatebee' ) }
						</div>
					}
					{ visiblePlugins.map( ( plugin ) => {
						const iconPath = getPluginIconPath( plugin );
						const hasIcon = '' !== iconPath;
						const isDotOrg = isDotOrgPlugin( plugin );
						const autoKey = getPluginFieldKey(
							plugin,
							'auto_update'
						);
						const patchesKey = getPluginFieldKey(
							plugin,
							'patches_only'
						);
						const blockKey = getPluginFieldKey(
							plugin,
							'block_updates'
						);
						const selectedKey = getPluginFieldKey(
							plugin,
							'selected'
						);
						const isBlocked = Boolean( getValues( blockKey ) );
						const autoEnabled = Boolean( getValues( autoKey ) );
						const patchesOnly = Boolean( getValues( patchesKey ) );
						const isBusy =
							bulkBusy || Boolean( busyPlugins[ plugin.File ] );

						return (
							<div
								key={ plugin.File }
								className={ [
									'updatebee-plugin-item',
									hasIcon
										? 'updatebee-plugin-has-icon'
										: 'updatebee-plugin-has-svg-icon',
								].join( ' ' ) }
							>
								<div className="updatebee-plugin-column updatebee-plugin-column-checkbox">
									<Controller
										name={ selectedKey }
										control={ control }
										render={ ( {
											field: { value, onChange },
										} ) => (
											<CheckboxControl
												checked={ Boolean( value ) }
												onChange={ onChange }
												label=""
												disabled={ isBusy }
											/>
										) }
									/>
								</div>
								<div className="updatebee-plugin-column updatebee-plugin-column-icon">
									{ hasIcon && (
										<img
											className="updatebee-plugin-icon"
											src={ iconPath }
											alt={ plugin.Name }
											width={ 128 }
											height={ 128 }
										/>
									) }
									{ ! hasIcon && (
										<svg
											className="updatebee-plugin-icon updatebee-icon-svg"
											aria-hidden="true"
										>
											<use href="#icon-plug" />
										</svg>
									) }
								</div>
								<div className="updatebee-plugin-column updatebee-plugin-column-main">
									<h2 className="updatebee-plugin-title">
										{ plugin.Name }
									</h2>
									<h3 className="updatebee-plugin-slug">
										{ plugin.Slug }
									</h3>
									{ plugin.Description && (
										<p className="updatebee-plugin-description">
											{ plugin.Description }
										</p>
									) }
									<table className="form-table">
										<tbody>
											{ plugin.AuthorName && (
												<tr>
													<th scope="row">
														{ __(
															'By:',
															'updatebee'
														) }
													</th>
													<td>
														{ plugin.AuthorURI ? (
															<a
																href={
																	plugin.AuthorURI
																}
																target="_blank"
																rel="noopener noreferrer"
															>
																{
																	plugin.AuthorName
																}
															</a>
														) : (
															<span>
																{
																	plugin.AuthorName
																}
															</span>
														) }
														{ isDotOrg &&
															plugin.Details && (
																<>
																	<span>
																		{ ' ' }
																		|{ ' ' }
																	</span>
																	<a
																		href={
																			plugin.Details
																		}
																		className="thickbox open-plugin-details-modal"
																		data-title={
																			plugin.Name
																		}
																		aria-label={ sprintf(
																			/* translators: %s: Plugin name. */
																			__(
																				'More information about %s',
																				'updatebee'
																			),
																			plugin.Name
																		) }
																	>
																		{ __(
																			'Plugin Details',
																			'updatebee'
																		) }
																	</a>
																</>
															) }
														{ plugin.PluginURI && (
															<>
																<span> | </span>
																<a
																	href={
																		plugin.PluginURI
																	}
																	target="_blank"
																	rel="noopener noreferrer"
																>
																	{ __(
																		'Plugin Homepage',
																		'updatebee'
																	) }
																</a>
															</>
														) }
													</td>
												</tr>
											) }
											<tr>
												<th scope="row">
													{ __(
														'Last Updated:',
														'updatebee'
													) }
												</th>
												<td>{ plugin.LastUpdated }</td>
											</tr>
											<tr>
												<th scope="row">
													{ __(
														'Update Method:',
														'updatebee'
													) }
												</th>
												<td>
													{ autoEnabled &&
														patchesOnly &&
														! isBlocked &&
														__(
															'Patches only',
															'updatebee'
														) }
													{ autoEnabled &&
														! patchesOnly &&
														! isBlocked &&
														__(
															'Automatic',
															'updatebee'
														) }
													{ ! autoEnabled &&
														! isBlocked &&
														__(
															'Manual',
															'updatebee'
														) }
													{ isBlocked &&
														__(
															'Blocked',
															'updatebee'
														) }
												</td>
											</tr>
											<tr>
												<th scope="row">
													{ __(
														'Hosted:',
														'updatebee'
													) }
												</th>
												<td>
													{ isDotOrg && plugin.url ? (
														<a
															href={ plugin.url }
															target="_blank"
															rel="noopener noreferrer"
														>
															{ __(
																'WordPress Plugin Directory',
																'updatebee'
															) }
														</a>
													) : (
														__(
															'Third-party Plugin',
															'updatebee'
														)
													) }
												</td>
											</tr>
											<tr>
												<th scope="row">
													{ __(
														'Version:',
														'updatebee'
													) }
												</th>
												<td>{ plugin.Version }</td>
											</tr>
										</tbody>
									</table>
								</div>
								<div></div>
								<div className="updatebee-plugin-column updatebee-plugin-column-info">
									{ getPluginBadges( plugin ) }
									<div className="updatebee-plugin-actions">
										<Controller
											name={ autoKey }
											control={ control }
											render={ ( {
												field: { value, onChange },
											} ) => (
												<ToggleControl
													label={ __(
														'Enable Automatic Updates',
														'updatebee'
													) }
													checked={ Boolean( value ) }
													onChange={ ( next ) => {
														const snapshot =
															snapshotPluginFields(
																plugin
															);
														onChange( next );
														if ( ! next ) {
															setValue(
																patchesKey,
																false,
																dirty
															);
														}
														setPluginBusy(
															plugin.File,
															true
														);
														sendCommand(
															'updatebee_toggle_plugin_auto_updates',
															{
																file: plugin.File,
																nonce: plugin.EnableNonce,
																enabled: next,
															}
														)
															.then( () => {
																showSnackbar(
																	next
																		? __(
																				'Automatic updates enabled.',
																				'updatebee'
																		  )
																		: __(
																				'Automatic updates disabled.',
																				'updatebee'
																		  ),
																	'success'
																);
															} )
															.catch( () => {
																restorePluginFields(
																	plugin,
																	snapshot
																);
																showSnackbar(
																	__(
																		'Plugin updates could not be updated.',
																		'updatebee'
																	),
																	'error'
																);
															} )
															.finally( () => {
																setPluginBusy(
																	plugin.File,
																	false
																);
															} );
													} }
													help={ __(
														'Enable automatic updates for this plugin.',
														'updatebee'
													) }
													disabled={
														isBlocked ||
														! autoEnabled ||
														isBusy
													}
												/>
											) }
										/>
										<Controller
											name={ patchesKey }
											control={ control }
											render={ ( {
												field: { value, onChange },
											} ) => (
												<ToggleControl
													label={ __(
														'Patches Only',
														'updatebee'
													) }
													checked={ Boolean( value ) }
													onChange={ ( next ) => {
														const snapshot =
															snapshotPluginFields(
																plugin
															);
														onChange( next );
														setPluginBusy(
															plugin.File,
															true
														);
														sendCommand(
															'updatebee_toggle_plugin_patches',
															{
																file: plugin.File,
																nonce: plugin.PatchNonce,
																enabled: next,
															}
														)
															.then( () => {
																showSnackbar(
																	next
																		? __(
																				'Patches only enabled.',
																				'updatebee'
																		  )
																		: __(
																				'Patches only disabled.',
																				'updatebee'
																		  ),
																	'success'
																);
															} )
															.catch( () => {
																restorePluginFields(
																	plugin,
																	snapshot
																);
																showSnackbar(
																	__(
																		'Plugin updates could not be updated.',
																		'updatebee'
																	),
																	'error'
																);
															} )
															.finally( () => {
																setPluginBusy(
																	plugin.File,
																	false
																);
															} );
													} }
													help={ __(
														'Limit automatic updates for this plugin to patch releases.',
														'updatebee'
													) }
													disabled={
														isBlocked ||
														! autoEnabled ||
														isBusy
													}
												/>
											) }
										/>
										{ 'blocked' !== autoUpdatesStatus && (
											<>
												<Controller
													name={ blockKey }
													control={ control }
													render={ ( {
														field: {
															value,
															onChange,
														},
													} ) => (
														<ToggleControl
															label={ __(
																'Block All Updates',
																'updatebee'
															) }
															checked={ Boolean(
																value
															) }
															onChange={ (
																next
															) => {
																const snapshot =
																	snapshotPluginFields(
																		plugin
																	);
																onChange(
																	next
																);
																setPluginBusy(
																	plugin.File,
																	true
																);
																sendCommand(
																	'updatebee_toggle_plugin_block_updates',
																	{
																		file: plugin.File,
																		nonce: plugin.BlockNonce,
																		enabled:
																			next,
																	}
																)
																	.then(
																		() => {
																			showSnackbar(
																				next
																					? __(
																							'Plugin updates blocked.',
																							'updatebee'
																					  )
																					: __(
																							'Plugin updates unblocked.',
																							'updatebee'
																					  ),
																				'success'
																			);
																		}
																	)
																	.catch(
																		() => {
																			restorePluginFields(
																				plugin,
																				snapshot
																			);
																			showSnackbar(
																				__(
																					'Plugin updates could not be updated.',
																					'updatebee'
																				),
																				'error'
																			);
																		}
																	)
																	.finally(
																		() => {
																			setPluginBusy(
																				plugin.File,
																				false
																			);
																		}
																	);
															} }
															help={ __(
																'Blocking plugins helps prevent the plugin from being manually or automatically updated.',
																'updatebee'
															) }
															disabled={ isBusy }
														/>
													) }
												/>
											</>
										) }
									</div>
									<PluginNote
										plugin={ plugin }
										disabled={ isBusy }
										onNotesChange={ onNotesChange }
										showSnackbar={ showSnackbar }
									/>
								</div>

								<div
									className="updatebee-plugin-column updatebee-plugin-column-separator"
									aria-hidden="true"
								></div>
							</div>
						);
					} ) }
				</div>
			</div>
			<PluginsFooter
				plugins={ visiblePlugins }
				actions={ actions }
				snackbar={ snackbar }
				onSnackbarClose={ handleSnackbarClose }
				isBusy={ bulkBusy }
				control={ control }
				getValues={ getValues }
				setValue={ setValue }
			/>
		</>
	);
};

const PluginsMain = () => {
	const methods = useForm< PluginsFormValues >( {
		defaultValues: {},
	} );
	const { reset } = methods;
	const [ loading, setLoading ] = useState( true );
	const [ loadError, setLoadError ] = useState( false );
	const [ loadAttempt, setLoadAttempt ] = useState( 0 );
	const [ plugins, setPlugins ] = useState< PluginItem[] >( [] );
	const [ autoUpdatesStatus, setAutoUpdatesStatus ] =
		useState< AutoUpdatesStatus >( 'disabled' );

	/**
	 * Update a plugin's notes in local list state.
	 *
	 * @param file  Plugin file.
	 * @param notes Saved notes.
	 */
	const handleNotesChange = useCallback( ( file: string, notes: string ) => {
		setPlugins( ( current ) =>
			current.map( ( plugin ) =>
				plugin.File === file ? { ...plugin, Notes: notes } : plugin
			)
		);
	}, [] );

	useEffect( () => {
		let cancelled = false;

		setLoading( true );
		setLoadError( false );

		sendCommand< PluginsPayload >( 'updatebee_retrieve_plugins', {
			nonce: updatebeePlugins.retrieveNonce,
		} )
			.then( ( response ) => {
				const data = response.data?.data;
				if ( ! data?.plugins ) {
					throw new Error( 'Invalid response.' );
				}

				if ( cancelled ) {
					return;
				}

				const pluginItems = Object.values( data.plugins ).map(
					( plugin ) => ( {
						...plugin,
						Notes: plugin.Notes || '',
						NoteNonce: plugin.NoteNonce || '',
					} )
				);
				const values: PluginsFormValues = {};
				pluginItems.forEach( ( plugin ) => {
					values[ getPluginFieldKey( plugin, 'auto_update' ) ] =
						Boolean( plugin.Autoupdates );
					values[ getPluginFieldKey( plugin, 'patches_only' ) ] =
						Boolean( plugin.PatchesOnly );
					values[ getPluginFieldKey( plugin, 'block_updates' ) ] =
						Boolean( plugin.Blocked );
					values[ getPluginFieldKey( plugin, 'selected' ) ] = false;
				} );

				setPlugins( pluginItems );
				setAutoUpdatesStatus( data.auto_updates_enabled || 'disabled' );
				reset( values );
			} )
			.catch( () => {
				if ( ! cancelled ) {
					setLoadError( true );
				}
			} )
			.finally( () => {
				if ( ! cancelled ) {
					setLoading( false );
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [ loadAttempt, reset ] );

	if ( loading ) {
		return <Loader />;
	}

	if ( loadError ) {
		return (
			<ErrorFallback
				heading={ __( 'Plugins', 'updatebee' ) }
				message={ __(
					'Plugins could not be loaded. Try again or reload the page.',
					'updatebee'
				) }
				onRetry={ () => {
					setLoadAttempt( ( attempt ) => attempt + 1 );
				} }
			/>
		);
	}

	return (
		<FormProvider { ...methods }>
			<PluginsList
				plugins={ plugins }
				autoUpdatesStatus={ autoUpdatesStatus }
				onNotesChange={ handleNotesChange }
			/>
		</FormProvider>
	);
};

export default PluginsMain;
