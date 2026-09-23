/**
 * Themes screen: load and populate theme auto-update rows.
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
import ItemSettingsDrawer from '../../components/ItemSettingsDrawer';
import Loader from '../../components/Loader';
import { type Action } from './bulk-actions';
import ThemeNote from './ThemeNote';
import { getThemeFieldKey } from './theme-fields';
import ThemesFooter, {
	type SnackbarState,
	type SnackbarType,
} from './ThemesFooter';

export { getThemeFieldKey };

export type ThemeItem = {
	Stylesheet: string;
	Template: string;
	Slug: string;
	Name: string;
	Description?: string;
	Version?: string;
	AuthorName?: string;
	AuthorURI?: string;
	ThemeURI?: string;
	url?: string;
	id?: string;
	Screenshot?: string;
	ParentName?: string;
	Active: boolean;
	NetworkEnabled: boolean;
	Autoupdates: boolean;
	Blocked: boolean;
	PatchesOnly: boolean;
	LastUpdated: string;
	EnableNonce: string;
	BlockNonce: string;
	PatchNonce: string;
	NoteNonce: string;
	Notes: string;
	DelayDays: number | null;
	SettingsNonce: string;
};

type AutoUpdatesStatus = 'enabled' | 'custom' | 'disabled' | 'blocked';

type ThemesPayload = {
	themes?: Record< string, ThemeItem >;
	auto_updates_enabled?: AutoUpdatesStatus;
	delay_updates?: number;
};

export type ThemesFormValues = Record< string, boolean >;

type ThemeToggleSnapshot = {
	auto_update: boolean;
	patches_only: boolean;
	block_updates: boolean;
};

type BulkActionId = 'enable' | 'disable' | 'block' | 'unblock' | 'patches';

const dirty = {
	shouldDirty: true,
};

const isDotOrgTheme = ( theme: ThemeItem ): boolean => {
	if ( theme.id && /w\.org/i.test( theme.id ) ) {
		return true;
	}

	if ( theme.url && /wordpress\.org\/themes/i.test( theme.url ) ) {
		return true;
	}

	return false;
};

const getStatusNotice = (
	status: AutoUpdatesStatus
): { message: string; status: 'success' | 'warning' | 'error' | 'info' } => {
	if ( 'enabled' === status ) {
		return {
			status: 'success',
			message: __(
				'Automatic updates are enabled for all themes.',
				'updatebee'
			),
		};
	}

	if ( 'custom' === status ) {
		return {
			status: 'info',
			message: __(
				'Automatic updates are enabled, but only for the themes that are manually enabled.',
				'updatebee'
			),
		};
	}

	if ( 'blocked' === status ) {
		return {
			status: 'error',
			message: __(
				'All updates are blocked. You will no longer receive any theme updates and may be a security risk.',
				'updatebee'
			),
		};
	}

	return {
		status: 'warning',
		message: __(
			'Automatic updates are disabled for all themes.',
			'updatebee'
		),
	};
};

/**
 * Success copy after a bulk action.
 *
 * @param actionId Bulk action id.
 * @param count    Eligible theme count.
 */
const getBulkSuccessMessage = (
	actionId: BulkActionId,
	count: number
): string => {
	switch ( actionId ) {
		case 'enable':
			return sprintf(
				/* translators: %d: Number of themes. */
				_n(
					'Automatic updates enabled for %d theme.',
					'Automatic updates enabled for %d themes.',
					count,
					'updatebee'
				),
				count
			);
		case 'disable':
			return sprintf(
				/* translators: %d: Number of themes. */
				_n(
					'Automatic updates disabled for %d theme.',
					'Automatic updates disabled for %d themes.',
					count,
					'updatebee'
				),
				count
			);
		case 'block':
			return sprintf(
				/* translators: %d: Number of themes. */
				_n(
					'Updates blocked for %d theme.',
					'Updates blocked for %d themes.',
					count,
					'updatebee'
				),
				count
			);
		case 'unblock':
			return sprintf(
				/* translators: %d: Number of themes. */
				_n(
					'Updates unblocked for %d theme.',
					'Updates unblocked for %d themes.',
					count,
					'updatebee'
				),
				count
			);
		case 'patches':
			return sprintf(
				/* translators: %d: Number of themes. */
				_n(
					'Patches only enabled for %d theme.',
					'Patches only enabled for %d themes.',
					count,
					'updatebee'
				),
				count
			);
		default:
			return __( 'Theme updates have been updated.', 'updatebee' );
	}
};

const ThemesList = ( props: {
	themes: ThemeItem[];
	autoUpdatesStatus: AutoUpdatesStatus;
	globalDelayDays: number;
	onDelayChange: ( stylesheet: string, delayDays: number | null ) => void;
	onNotesChange: ( stylesheet: string, notes: string ) => void;
} ) => {
	const {
		themes,
		autoUpdatesStatus,
		globalDelayDays,
		onDelayChange,
		onNotesChange,
	} = props;
	const { control, getValues, setValue } =
		useFormContext< ThemesFormValues >();
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
		| 'org-theme'
	>( '' );
	const [ busyThemes, setBusyThemes ] = useState< Record< string, boolean > >(
		{}
	);
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
	 * Mark a theme row as saving.
	 *
	 * @param stylesheet Theme stylesheet.
	 * @param busy       Whether the row is busy.
	 */
	const setThemeBusy = ( stylesheet: string, busy: boolean ) => {
		setBusyThemes( ( current ) => {
			if ( busy ) {
				return {
					...current,
					[ stylesheet ]: true,
				};
			}

			const next = { ...current };
			delete next[ stylesheet ];
			return next;
		} );
	};

	/**
	 * Current toggle values for a theme row.
	 *
	 * @param theme Theme row.
	 */
	const snapshotThemeFields = useCallback(
		( theme: ThemeItem ): ThemeToggleSnapshot => ( {
			auto_update: Boolean(
				getValues( getThemeFieldKey( theme, 'auto_update' ) )
			),
			patches_only: Boolean(
				getValues( getThemeFieldKey( theme, 'patches_only' ) )
			),
			block_updates: Boolean(
				getValues( getThemeFieldKey( theme, 'block_updates' ) )
			),
		} ),
		[ getValues ]
	);

	/**
	 * Restore toggle values after a failed save.
	 *
	 * @param theme    Theme row.
	 * @param snapshot Values to restore.
	 */
	const restoreThemeFields = useCallback(
		( theme: ThemeItem, snapshot: ThemeToggleSnapshot ) => {
			setValue(
				getThemeFieldKey( theme, 'auto_update' ),
				snapshot.auto_update,
				dirty
			);
			setValue(
				getThemeFieldKey( theme, 'patches_only' ),
				snapshot.patches_only,
				dirty
			);
			setValue(
				getThemeFieldKey( theme, 'block_updates' ),
				snapshot.block_updates,
				dirty
			);
		},
		[ setValue ]
	);

	const visibleThemes = themes.filter( ( theme ) => {
		if ( filter && 'all' !== filter ) {
			const autoUpdate = Boolean(
				getValues( getThemeFieldKey( theme, 'auto_update' ) )
			);
			const blocked = Boolean(
				getValues( getThemeFieldKey( theme, 'block_updates' ) )
			);
			const patchesOnly = Boolean(
				getValues( getThemeFieldKey( theme, 'patches_only' ) )
			);

			switch ( filter ) {
				case 'activated':
					if ( updatebeeThemes.isNetworkAdmin ) {
						if ( ! theme.NetworkEnabled ) {
							return false;
						}
					} else if ( ! theme.Active ) {
						return false;
					}
					break;
				case 'deactivated':
					if ( updatebeeThemes.isNetworkAdmin ) {
						if ( theme.NetworkEnabled ) {
							return false;
						}
					} else if ( theme.Active ) {
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
					if ( isDotOrgTheme( theme ) ) {
						return false;
					}
					break;
				case 'org-theme':
					if ( ! isDotOrgTheme( theme ) ) {
						return false;
					}
					break;
				default:
					return false;
			}
		}

		if (
			search &&
			! theme.Name.toLowerCase().includes( search.toLowerCase() )
		) {
			return false;
		}

		return true;
	} );

	/**
	 * Persist a bulk action and revert on failure.
	 *
	 * @param actionId       Bulk action id.
	 * @param selectedThemes Eligible themes.
	 * @param snapshots      Values from before the local update.
	 */
	const persistBulkAction = useCallback(
		(
			actionId: BulkActionId,
			selectedThemes: ThemeItem[],
			snapshots: Array< {
				theme: ThemeItem;
				values: ThemeToggleSnapshot;
			} >
		) => {
			setBulkBusy( true );

			sendCommand( 'updatebee_bulk_theme_action', {
				nonce: updatebeeThemes.bulkNonce,
				bulk_action: actionId,
				stylesheets: selectedThemes.map(
					( theme ) => theme.Stylesheet
				),
			} )
				.then( () => {
					selectedThemes.forEach( ( theme ) => {
						setValue(
							getThemeFieldKey( theme, 'selected' ),
							false
						);
					} );
					showSnackbar(
						getBulkSuccessMessage(
							actionId,
							selectedThemes.length
						),
						'success'
					);
				} )
				.catch( () => {
					snapshots.forEach( ( { theme, values } ) => {
						restoreThemeFields( theme, values );
					} );
					showSnackbar(
						__(
							'Theme updates could not be updated.',
							'updatebee'
						),
						'error'
					);
				} )
				.finally( () => {
					setBulkBusy( false );
				} );
		},
		[ restoreThemeFields, setValue, showSnackbar ]
	);

	const actions = useMemo< Action< ThemeItem >[] >( () => {
		const isBlocked = ( theme: ThemeItem ): boolean =>
			Boolean( getValues( getThemeFieldKey( theme, 'block_updates' ) ) );
		const hasAutoUpdates = ( theme: ThemeItem ): boolean =>
			Boolean( getValues( getThemeFieldKey( theme, 'auto_update' ) ) );
		const hasPatchesOnly = ( theme: ThemeItem ): boolean =>
			Boolean( getValues( getThemeFieldKey( theme, 'patches_only' ) ) );

		const getCountLabel = (
			label: string,
			selectedThemes: ThemeItem[]
		): string =>
			sprintf(
				/* translators: 1: Action label. 2: Number of eligible themes. */
				__( '%1$s (%2$d)', 'updatebee' ),
				label,
				selectedThemes.length
			);

		const canEnable = ( theme: ThemeItem ): boolean =>
			autoUpdatesAvailable &&
			! isBlocked( theme ) &&
			! hasAutoUpdates( theme );
		const canDisable = ( theme: ThemeItem ): boolean =>
			autoUpdatesAvailable &&
			! isBlocked( theme ) &&
			hasAutoUpdates( theme );
		const canBlock = ( theme: ThemeItem ): boolean =>
			blockActionsAvailable && ! isBlocked( theme );
		const canUnblock = ( theme: ThemeItem ): boolean =>
			blockActionsAvailable && isBlocked( theme );
		const canEnablePatches = ( theme: ThemeItem ): boolean =>
			autoUpdatesAvailable &&
			! isBlocked( theme ) &&
			! hasPatchesOnly( theme );

		return [
			{
				id: 'enable',
				label: __( 'Enable Automatic Updates', 'updatebee' ),
				icon: UpdateIcon,
				isDestructive: false,
				isEligible: canEnable,
				getLabel: ( selectedThemes ) =>
					getCountLabel(
						__( 'Enable Automatic Updates', 'updatebee' ),
						selectedThemes
					),
				callback: ( selectedThemes ) => {
					const snapshots = selectedThemes.map( ( theme ) => ( {
						theme,
						values: snapshotThemeFields( theme ),
					} ) );
					selectedThemes.forEach( ( theme ) => {
						setValue(
							getThemeFieldKey( theme, 'auto_update' ),
							true,
							dirty
						);
					} );
					persistBulkAction( 'enable', selectedThemes, snapshots );
				},
			},
			{
				id: 'disable',
				label: __( 'Disable Automatic Updates', 'updatebee' ),
				icon: CancelCircleFilledIcon,
				isDestructive: false,
				isEligible: canDisable,
				getLabel: ( selectedThemes ) =>
					getCountLabel(
						__( 'Disable Automatic Updates', 'updatebee' ),
						selectedThemes
					),
				callback: ( selectedThemes ) => {
					const snapshots = selectedThemes.map( ( theme ) => ( {
						theme,
						values: snapshotThemeFields( theme ),
					} ) );
					selectedThemes.forEach( ( theme ) => {
						setValue(
							getThemeFieldKey( theme, 'auto_update' ),
							false,
							dirty
						);
						setValue(
							getThemeFieldKey( theme, 'patches_only' ),
							false,
							dirty
						);
					} );
					persistBulkAction( 'disable', selectedThemes, snapshots );
				},
			},
			{
				id: 'block',
				label: __( 'Block Selected Themes', 'updatebee' ),
				icon: LockIcon,
				isDestructive: true,
				isEligible: canBlock,
				getLabel: ( selectedThemes ) =>
					getCountLabel(
						__( 'Block Selected Themes', 'updatebee' ),
						selectedThemes
					),
				callback: ( selectedThemes ) => {
					const snapshots = selectedThemes.map( ( theme ) => ( {
						theme,
						values: snapshotThemeFields( theme ),
					} ) );
					selectedThemes.forEach( ( theme ) => {
						setValue(
							getThemeFieldKey( theme, 'block_updates' ),
							true,
							dirty
						);
					} );
					persistBulkAction( 'block', selectedThemes, snapshots );
				},
			},
			{
				id: 'unblock',
				label: __( 'Unblock Selected Themes', 'updatebee' ),
				icon: UnlockIcon,
				isDestructive: false,
				isEligible: canUnblock,
				getLabel: ( selectedThemes ) =>
					getCountLabel(
						__( 'Unblock Selected Themes', 'updatebee' ),
						selectedThemes
					),
				callback: ( selectedThemes ) => {
					const snapshots = selectedThemes.map( ( theme ) => ( {
						theme,
						values: snapshotThemeFields( theme ),
					} ) );
					selectedThemes.forEach( ( theme ) => {
						setValue(
							getThemeFieldKey( theme, 'block_updates' ),
							false,
							dirty
						);
					} );
					persistBulkAction( 'unblock', selectedThemes, snapshots );
				},
			},
			{
				id: 'patches',
				label: __( 'Enable Patches Only', 'updatebee' ),
				icon: BackupIcon,
				isDestructive: false,
				isEligible: canEnablePatches,
				getLabel: ( selectedThemes ) =>
					getCountLabel(
						__( 'Enable Patches Only', 'updatebee' ),
						selectedThemes
					),
				callback: ( selectedThemes ) => {
					const snapshots = selectedThemes.map( ( theme ) => ( {
						theme,
						values: snapshotThemeFields( theme ),
					} ) );
					selectedThemes.forEach( ( theme ) => {
						setValue(
							getThemeFieldKey( theme, 'auto_update' ),
							true,
							dirty
						);
						setValue(
							getThemeFieldKey( theme, 'patches_only' ),
							true,
							dirty
						);
					} );
					persistBulkAction( 'patches', selectedThemes, snapshots );
				},
			},
		];
	}, [
		autoUpdatesAvailable,
		blockActionsAvailable,
		getValues,
		persistBulkAction,
		setValue,
		snapshotThemeFields,
	] );

	/**
	 * Get activation, child, network, and notes badges for a theme.
	 *
	 * @param theme The theme.
	 * @return The theme badges.
	 */
	const getThemeBadges = ( theme: ThemeItem ): React.ReactNode => {
		const hasNotes = '' !== ( theme.Notes || '' ).trim();
		const isChild = '' !== ( theme.ParentName || '' ).trim();
		let activationBadge: React.ReactNode = null;

		let intent: React.ComponentProps< typeof Badge >[ 'intent' ] = 'stable';
		let label: string = __( 'Activated', 'updatebee' );
		if ( ! updatebeeThemes.isNetworkAdmin && theme.Active ) {
			activationBadge = <Badge intent={ intent }>{ label }</Badge>;
		} else if ( ! updatebeeThemes.isNetworkAdmin && ! theme.Active ) {
			if ( ! theme.Active ) {
				intent = 'high';
				label = __( 'Deactivated', 'updatebee' );
				activationBadge = <Badge intent={ intent }>{ label }</Badge>;
			}
		} else if ( updatebeeThemes.isNetworkAdmin && ! theme.NetworkEnabled ) {
			intent = 'high';
			label = __( 'Network Disabled', 'updatebee' );
			activationBadge = <Badge intent={ intent }>{ label }</Badge>;
		}

		const networkEnabledBadge =
			updatebeeThemes.isNetworkAdmin && theme.NetworkEnabled ? (
				<Badge intent="informational">
					{ __( 'Network Enabled', 'updatebee' ) }
				</Badge>
			) : null;

		const childBadge = isChild ? (
			<Badge intent="informational">{ __( 'Child', 'updatebee' ) }</Badge>
		) : null;

		if (
			! activationBadge &&
			! hasNotes &&
			! networkEnabledBadge &&
			! childBadge
		) {
			return null;
		}

		return (
			<div className="updatebee-theme-badge">
				{ activationBadge }
				{ networkEnabledBadge }
				{ childBadge }
				{ hasNotes && (
					<Badge intent="low">{ __( 'Note', 'updatebee' ) }</Badge>
				) }
			</div>
		);
	};

	const themeFilterOptions = useMemo(
		() => [
			{
				label: __( 'Select a Filter', 'updatebee' ),
				value: '',
			},
			{
				label: __( 'All', 'updatebee' ),
				value: 'all',
			},
			{
				label: updatebeeThemes.isNetworkAdmin
					? __( 'Network Enabled', 'updatebee' )
					: __( 'Activated', 'updatebee' ),
				value: 'activated',
			},
			{
				label: updatebeeThemes.isNetworkAdmin
					? __( 'Network Disabled', 'updatebee' )
					: __( 'Deactivated', 'updatebee' ),
				value: 'deactivated',
			},
			{
				label: __( 'Automatic Updates Enabled', 'updatebee' ),
				value: 'auto-updates-enabled',
			},
			{
				label: __( 'Automatic Updates Disabled', 'updatebee' ),
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
				label: __( 'Third-party Themes', 'updatebee' ),
				value: 'third-party',
			},
			{
				label: __( 'WordPress.org Themes', 'updatebee' ),
				value: 'org-theme',
			},
		],
		[]
	);

	return (
		<>
			<div className="updatebee-themes-main">
				<h1>{ __( 'Themes', 'updatebee' ) }</h1>
				<Notice
					className="updatebee-theme-notice"
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
							options={ themeFilterOptions }
							onChange={ ( value ) => {
								setFilter(
									value as
										| 'all'
										| 'activated'
										| 'deactivated'
										| 'auto-updates-enabled'
										| 'auto-updates-disabled'
										| 'blocked'
										| 'unblocked'
										| 'patches-only'
										| 'third-party'
										| 'org-theme'
								);
							} }
						/>
					</div>
					<div className="updatebee-theme-list-search">
						<TextControl
							label={ __( 'Search Themes', 'updatebee' ) }
							value={ search }
							onChange={ ( value ) => {
								setSearch( value );
							} }
						/>
					</div>
				</div>
				<div className="updatebee-theme-list-wrapper">
					{ visibleThemes.length === 0 && (
						<div className="updatebee-theme-list-empty">
							{ __(
								'No themes found matching your filters or search.',
								'updatebee'
							) }
						</div>
					) }
					{ visibleThemes.map( ( theme ) => {
						const screenshot = theme.Screenshot || '';
						const hasScreenshot = '' !== screenshot;
						const isDotOrg = isDotOrgTheme( theme );
						const autoKey = getThemeFieldKey(
							theme,
							'auto_update'
						);
						const patchesKey = getThemeFieldKey(
							theme,
							'patches_only'
						);
						const blockKey = getThemeFieldKey(
							theme,
							'block_updates'
						);
						const selectedKey = getThemeFieldKey(
							theme,
							'selected'
						);
						const isBlocked = Boolean( getValues( blockKey ) );
						const autoEnabled = Boolean( getValues( autoKey ) );
						const patchesOnly = Boolean( getValues( patchesKey ) );
						const isBusy =
							bulkBusy ||
							Boolean( busyThemes[ theme.Stylesheet ] );

						return (
							<div
								key={ theme.Stylesheet }
								className={ [
									'updatebee-theme-item',
									hasScreenshot
										? 'updatebee-theme-has-screenshot'
										: 'updatebee-theme-has-svg-icon',
								].join( ' ' ) }
							>
								<ItemSettingsDrawer
									kind="theme"
									assetName={ theme.Name }
									assetId={ theme.Stylesheet }
									delayDays={ theme.DelayDays }
									settingsNonce={ theme.SettingsNonce }
									globalDelayDays={ globalDelayDays }
									disabled={ isBusy }
									onDelayChange={ onDelayChange }
									showSnackbar={ showSnackbar }
								/>
								<div className="updatebee-theme-column updatebee-theme-column-checkbox">
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
								<div className="updatebee-theme-column updatebee-theme-column-main">
									<h2 className="updatebee-theme-title">
										{ theme.Name }
									</h2>
									<h3 className="updatebee-theme-slug">
										{ theme.Slug }
									</h3>
									{ hasScreenshot && (
										<img
											className="updatebee-theme-screenshot"
											src={ screenshot }
											alt={ theme.Name }
										/>
									) }
									{ ! hasScreenshot && (
										<svg
											className="updatebee-theme-screenshot updatebee-icon-svg"
											aria-hidden="true"
										>
											<use href="#icon-roller-brush" />
										</svg>
									) }
									{ theme.Description && (
										<p className="updatebee-theme-description">
											{ theme.Description }
										</p>
									) }
									<table className="form-table">
										<tbody>
											{ theme.AuthorName && (
												<tr>
													<th scope="row">
														{ __(
															'By:',
															'updatebee'
														) }
													</th>
													<td>
														{ theme.AuthorURI ? (
															<a
																href={
																	theme.AuthorURI
																}
																target="_blank"
																rel="noopener noreferrer"
															>
																{
																	theme.AuthorName
																}
															</a>
														) : (
															<span>
																{
																	theme.AuthorName
																}
															</span>
														) }
														{ theme.ThemeURI && (
															<>
																<span> | </span>
																<a
																	href={
																		theme.ThemeURI
																	}
																	target="_blank"
																	rel="noopener noreferrer"
																>
																	{ __(
																		'Theme Homepage',
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
												<td>{ theme.LastUpdated }</td>
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
													{ isDotOrg && theme.url ? (
														<a
															href={ theme.url }
															target="_blank"
															rel="noopener noreferrer"
														>
															{ __(
																'WordPress Theme Directory',
																'updatebee'
															) }
														</a>
													) : (
														__(
															'Third-party Theme',
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
												<td>{ theme.Version }</td>
											</tr>
											{ theme.ParentName && (
												<tr>
													<th scope="row">
														{ __(
															'Parent:',
															'updatebee'
														) }
													</th>
													<td>
														{ theme.ParentName }
													</td>
												</tr>
											) }
										</tbody>
									</table>
								</div>
								<div></div>
								<div className="updatebee-theme-column updatebee-theme-column-info">
									{ getThemeBadges( theme ) }
									<div className="updatebee-theme-actions">
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
															snapshotThemeFields(
																theme
															);
														onChange( next );
														if ( ! next ) {
															setValue(
																patchesKey,
																false,
																dirty
															);
														}
														setThemeBusy(
															theme.Stylesheet,
															true
														);
														sendCommand(
															'updatebee_toggle_theme_auto_updates',
															{
																stylesheet:
																	theme.Stylesheet,
																nonce: theme.EnableNonce,
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
																restoreThemeFields(
																	theme,
																	snapshot
																);
																showSnackbar(
																	__(
																		'Theme updates could not be updated.',
																		'updatebee'
																	),
																	'error'
																);
															} )
															.finally( () => {
																setThemeBusy(
																	theme.Stylesheet,
																	false
																);
															} );
													} }
													help={ __(
														'Enable automatic updates for this theme.',
														'updatebee'
													) }
													disabled={
														isBlocked ||
														! autoUpdatesAvailable ||
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
															snapshotThemeFields(
																theme
															);
														onChange( next );
														if ( next ) {
															setValue(
																autoKey,
																true,
																dirty
															);
														}
														setThemeBusy(
															theme.Stylesheet,
															true
														);
														sendCommand(
															'updatebee_toggle_theme_patches',
															{
																stylesheet:
																	theme.Stylesheet,
																nonce: theme.PatchNonce,
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
																restoreThemeFields(
																	theme,
																	snapshot
																);
																showSnackbar(
																	__(
																		'Theme updates could not be updated.',
																		'updatebee'
																	),
																	'error'
																);
															} )
															.finally( () => {
																setThemeBusy(
																	theme.Stylesheet,
																	false
																);
															} );
													} }
													help={ __(
														'Limit automatic updates for this theme to patch releases.',
														'updatebee'
													) }
													disabled={
														isBlocked ||
														! autoUpdatesAvailable ||
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
																	snapshotThemeFields(
																		theme
																	);
																onChange(
																	next
																);
																setThemeBusy(
																	theme.Stylesheet,
																	true
																);
																sendCommand(
																	'updatebee_toggle_theme_block_updates',
																	{
																		stylesheet:
																			theme.Stylesheet,
																		nonce: theme.BlockNonce,
																		enabled:
																			next,
																	}
																)
																	.then(
																		() => {
																			showSnackbar(
																				next
																					? __(
																							'Theme updates blocked.',
																							'updatebee'
																					  )
																					: __(
																							'Theme updates unblocked.',
																							'updatebee'
																					  ),
																				'success'
																			);
																		}
																	)
																	.catch(
																		() => {
																			restoreThemeFields(
																				theme,
																				snapshot
																			);
																			showSnackbar(
																				__(
																					'Theme updates could not be updated.',
																					'updatebee'
																				),
																				'error'
																			);
																		}
																	)
																	.finally(
																		() => {
																			setThemeBusy(
																				theme.Stylesheet,
																				false
																			);
																		}
																	);
															} }
															help={ __(
																'Blocking themes helps prevent the theme from being manually or automatically updated.',
																'updatebee'
															) }
															disabled={ isBusy }
														/>
													) }
												/>
											</>
										) }
									</div>
									<ThemeNote
										theme={ theme }
										disabled={ isBusy }
										onNotesChange={ onNotesChange }
										showSnackbar={ showSnackbar }
									/>
								</div>

								<div
									className="updatebee-theme-column updatebee-theme-column-separator"
									aria-hidden="true"
								></div>
							</div>
						);
					} ) }
				</div>
			</div>
			<ThemesFooter
				themes={ visibleThemes }
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

const ThemesMain = () => {
	const methods = useForm< ThemesFormValues >( {
		defaultValues: {},
	} );
	const { reset } = methods;
	const [ loading, setLoading ] = useState( true );
	const [ loadError, setLoadError ] = useState( false );
	const [ loadAttempt, setLoadAttempt ] = useState( 0 );
	const [ themes, setThemes ] = useState< ThemeItem[] >( [] );
	const [ autoUpdatesStatus, setAutoUpdatesStatus ] =
		useState< AutoUpdatesStatus >( 'disabled' );
	const [ globalDelayDays, setGlobalDelayDays ] = useState( 0 );

	/**
	 * Update a theme's delay in local list state.
	 *
	 * @param stylesheet Theme stylesheet.
	 * @param delayDays  Saved delay override.
	 */
	const handleDelayChange = useCallback(
		( stylesheet: string, delayDays: number | null ) => {
			setThemes( ( current ) =>
				current.map( ( theme ) =>
					theme.Stylesheet === stylesheet
						? { ...theme, DelayDays: delayDays }
						: theme
				)
			);
		},
		[]
	);

	/**
	 * Update a theme's notes in local list state.
	 *
	 * @param stylesheet Theme stylesheet.
	 * @param notes      Saved notes.
	 */
	const handleNotesChange = useCallback(
		( stylesheet: string, notes: string ) => {
			setThemes( ( current ) =>
				current.map( ( theme ) =>
					theme.Stylesheet === stylesheet
						? { ...theme, Notes: notes }
						: theme
				)
			);
		},
		[]
	);

	useEffect( () => {
		let cancelled = false;

		setLoading( true );
		setLoadError( false );

		sendCommand< ThemesPayload >( 'updatebee_retrieve_themes', {
			nonce: updatebeeThemes.retrieveNonce,
		} )
			.then( ( response ) => {
				const data = response.data?.data;
				if ( ! data?.themes ) {
					throw new Error( 'Invalid response.' );
				}

				if ( cancelled ) {
					return;
				}

				const themeItems = Object.values( data.themes ).map(
					( theme ) => ( {
						...theme,
						Notes: theme.Notes || '',
						NoteNonce: theme.NoteNonce || '',
						ParentName: theme.ParentName || '',
						Screenshot: theme.Screenshot || '',
					} )
				);
				const values: ThemesFormValues = {};
				themeItems.forEach( ( theme ) => {
					values[ getThemeFieldKey( theme, 'auto_update' ) ] =
						Boolean( theme.Autoupdates );
					values[ getThemeFieldKey( theme, 'patches_only' ) ] =
						Boolean( theme.PatchesOnly );
					values[ getThemeFieldKey( theme, 'block_updates' ) ] =
						Boolean( theme.Blocked );
					values[ getThemeFieldKey( theme, 'selected' ) ] = false;
				} );

				setThemes( themeItems );
				setAutoUpdatesStatus( data.auto_updates_enabled || 'disabled' );
				setGlobalDelayDays( data.delay_updates || 0 );
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
				heading={ __( 'Themes', 'updatebee' ) }
				message={ __(
					'Themes could not be loaded. Try again or reload the page.',
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
			<ThemesList
				themes={ themes }
				autoUpdatesStatus={ autoUpdatesStatus }
				globalDelayDays={ globalDelayDays }
				onDelayChange={ handleDelayChange }
				onNotesChange={ handleNotesChange }
			/>
		</FormProvider>
	);
};

export default ThemesMain;
