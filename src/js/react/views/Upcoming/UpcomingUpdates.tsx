/**
 * Upcoming plugin and theme updates.
 */
import {
	Notice,
	Tooltip,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalTruncate as Truncate,
} from '@wordpress/components';
import {
	DataViews,
	filterSortAndPaginate,
	type Field,
	type View,
} from '@wordpress/dataviews';
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Button, Stack, useEnableWpCompatOverlaySlot } from '@wordpress/ui';
import ChangelogModal from '../../components/ChangelogModal';
import FloatingNotice from '../../components/FloatingNotice';
import sendCommand from '../../../utils/SendCommand';
import { DATAVIEWS_STORAGE_KEYS } from '../../../utils/dataviews-view-storage';
import { ErrorFallback } from '../../components/ErrorBoundary';
import Loader from '../../components/Loader';
import usePersistedDataView from '../../hooks/usePersistedDataView';
import UpcomingDelayModal from './UpcomingDelayModal';
import { getUpcomingActions } from './upcoming-actions';

export type UpcomingItem = {
	RowId: string;
	Id: string;
	Type: 'plugin' | 'theme';
	Name: string;
	TypeLabel: string;
	FromVersion: string;
	ToVersion: string;
	Schedule: string;
	Status: 'blocked' | 'skipped' | 'scheduled';
	HasPackage: boolean;
	FullyBlocked: boolean;
	Blocked: boolean;
	Skipped: boolean;
	CanUpdate: boolean;
	SkipNonce: string;
	BlockNonce: string;
	UpdateNonce: string;
	HasChangelog?: boolean;
	ChangelogNonce?: string;
};

type UpcomingPayload = {
	items?: UpcomingItem[];
	next_run_label?: string;
	delay_updates?: number;
	message?: string;
};

type MessagePayload = {
	message?: string;
};

type NoticeIntent = 'success' | 'error';

type NoticeState = {
	message: string;
	intent: NoticeIntent;
};

const tableColumnStyles = {
	Name: {
		width: '240px',
		maxWidth: '320px',
	},
	Version: {
		width: '180px',
		maxWidth: '220px',
	},
	Schedule: {
		width: '220px',
		maxWidth: '280px',
	},
};

const defaultView: View = {
	type: 'table',
	search: '',
	page: 1,
	perPage: 20,
	fields: [ 'Name', 'Version', 'Schedule' ],
	sort: {
		field: 'Name',
		direction: 'asc',
	},
	layout: {
		density: 'balanced',
		styles: tableColumnStyles,
	},
};

const fieldIds = defaultView.fields ?? [];

const UpcomingDataViews = ( props: { searchLabel: string } ) => {
	const { searchLabel } = props;

	return (
		<>
			<Stack
				direction="row"
				align="top"
				justify="space-between"
				className="dataviews__view-actions"
				gap="xs"
			>
				<Stack
					direction="row"
					justify="start"
					gap="sm"
					className="dataviews__search"
				>
					<DataViews.Search label={ searchLabel } />
				</Stack>
			</Stack>
			<DataViews.Layout />
		</>
	);
};

/**
 * Format the installed and offered versions.
 *
 * @param item Upcoming row.
 */
const versionLabel = ( item: UpcomingItem ): string =>
	`${ item.FromVersion } → ${ item.ToVersion }`;

/**
 * Normalize a delay-days value.
 *
 * @param value Incoming days value.
 * @return Non-negative integer days.
 */
const normalizeDays = ( value: unknown ): number => {
	const days = Number( value );
	if ( Number.isFinite( days ) && days >= 0 ) {
		return Math.floor( days );
	}

	return 0;
};

/**
 * Warning copy for an active site-wide delay.
 *
 * @param days Delay in whole days. Greater than zero.
 */
const delayNotice = ( days: number ): string => {
	const wait = sprintf(
		/* translators: %d: number of days updates wait before they can install. */
		_n(
			'Plugin and theme updates wait %d day before they can install automatically.',
			'Plugin and theme updates wait %d days before they can install automatically.',
			days,
			'updatebee'
		),
		days
	);

	return `${ wait } ${ __(
		'If an update is fixing a security issue, then your site will be unfixed for the time of the delay.',
		'updatebee'
	) }`;
};

/**
 * Upcoming updates table.
 */
const UpcomingUpdates = () => {
	useEnableWpCompatOverlaySlot();
	const [ items, setItems ] = useState< UpcomingItem[] >( [] );
	const [ nextRunLabel, setNextRunLabel ] = useState( '' );
	const [ delayUpdates, setDelayUpdates ] = useState( 0 );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ isError, setIsError ] = useState( false );
	const [ isBusy, setIsBusy ] = useState( false );
	const [ isChecking, setIsChecking ] = useState( false );
	const [ isSavingDelay, setIsSavingDelay ] = useState( false );
	const [ isDelayModalOpen, setIsDelayModalOpen ] = useState( false );
	const [ delayError, setDelayError ] = useState( '' );
	const [ loadAttempt, setLoadAttempt ] = useState( 0 );
	const [ notice, setNotice ] = useState< NoticeState | null >( null );
	const [ changelogItem, setChangelogItem ] = useState< UpcomingItem | null >(
		null
	);
	const { view, onChangeView, onReset } = usePersistedDataView(
		DATAVIEWS_STORAGE_KEYS.upcoming,
		defaultView,
		fieldIds
	);

	const showNotice = useCallback(
		( message: string, intent: NoticeIntent ) => {
			setNotice( { message, intent } );
		},
		[]
	);

	const applyPayload = useCallback(
		( payload: UpcomingPayload | undefined ) => {
			setItems( Array.isArray( payload?.items ) ? payload.items : [] );
			setNextRunLabel( payload?.next_run_label || '' );
			setDelayUpdates( normalizeDays( payload?.delay_updates ) );
		},
		[]
	);

	const loadItems = useCallback( () => {
		setIsLoading( true );
		setIsError( false );

		return sendCommand< UpcomingPayload >(
			'updatebee_retrieve_upcoming_updates',
			{
				nonce: updatebeeUpcoming.retrieveUpcomingNonce,
			}
		)
			.then( ( response ) => {
				applyPayload( response.data.data );
			} )
			.catch( () => {
				setIsError( true );
			} )
			.finally( () => {
				setIsLoading( false );
			} );
	}, [ applyPayload ] );

	const openDelayModal = useCallback( () => {
		setDelayError( '' );
		setIsDelayModalOpen( true );
	}, [] );

	const closeDelayModal = useCallback( () => {
		if ( isSavingDelay ) {
			return;
		}

		setIsDelayModalOpen( false );
		setDelayError( '' );
	}, [ isSavingDelay ] );

	const onSaveDelay = useCallback(
		( days: number ) => {
			if ( isBusy || isSavingDelay ) {
				return;
			}

			setIsSavingDelay( true );
			setDelayError( '' );
			setNotice( null );

			sendCommand< UpcomingPayload >( 'updatebee_save_upcoming_delay', {
				nonce: updatebeeUpcoming.saveDelayNonce,
				delay_updates: days,
			} )
				.then( ( response ) => {
					const payload = response.data.data;
					applyPayload( payload );
					setIsDelayModalOpen( false );
					showNotice(
						payload?.message ||
							__( 'Update delay has been saved.', 'updatebee' ),
						'success'
					);
				} )
				.catch( ( error: Error ) => {
					setDelayError(
						error.message ||
							__(
								'Update delay could not be saved.',
								'updatebee'
							)
					);
				} )
				.finally( () => {
					setIsSavingDelay( false );
				} );
		},
		[ applyPayload, isBusy, isSavingDelay, showNotice ]
	);

	useEffect( () => {
		void loadItems();
	}, [ loadAttempt, loadItems ] );

	const runAction = useCallback(
		(
			action: string,
			payload: Record< string, unknown >,
			fallback: string
		) => {
			if ( isBusy ) {
				return;
			}

			setIsBusy( true );
			setNotice( null );
			sendCommand< MessagePayload >( action, payload )
				.then( ( response ) => {
					showNotice(
						response.data.data?.message ||
							__( 'Action completed.', 'updatebee' ),
						'success'
					);
					return loadItems();
				} )
				.catch( ( error: Error ) => {
					showNotice( error.message || fallback, 'error' );
				} )
				.finally( () => {
					setIsBusy( false );
				} );
		},
		[ isBusy, loadItems, showNotice ]
	);

	const onSkip = useCallback(
		( item: UpcomingItem ) => {
			const action =
				'plugin' === item.Type
					? 'updatebee_skip_plugin_version'
					: 'updatebee_skip_theme_version';
			const assetKey = 'plugin' === item.Type ? 'file' : 'stylesheet';

			runAction(
				action,
				{
					nonce: item.SkipNonce,
					[ assetKey ]: item.Id,
					version: item.ToVersion,
					enabled: ! item.Skipped,
				},
				__( 'The version could not be skipped.', 'updatebee' )
			);
		},
		[ runAction ]
	);

	const onBlock = useCallback(
		( item: UpcomingItem ) => {
			const action =
				'plugin' === item.Type
					? 'updatebee_toggle_plugin_block_updates'
					: 'updatebee_toggle_theme_block_updates';
			const assetKey = 'plugin' === item.Type ? 'file' : 'stylesheet';

			runAction(
				action,
				{
					nonce: item.BlockNonce,
					[ assetKey ]: item.Id,
					enabled: ! item.Blocked,
				},
				__( 'Updates could not be blocked.', 'updatebee' )
			);
		},
		[ runAction ]
	);

	const onChangelog = useCallback( ( item: UpcomingItem ) => {
		setChangelogItem( item );
	}, [] );

	const onUpdate = useCallback(
		( item: UpcomingItem ) => {
			const action =
				'plugin' === item.Type
					? 'updatebee_update_plugin'
					: 'updatebee_update_theme';
			const assetKey = 'plugin' === item.Type ? 'file' : 'stylesheet';

			runAction(
				action,
				{
					nonce: item.UpdateNonce,
					[ assetKey ]: item.Id,
				},
				'plugin' === item.Type
					? __( 'Plugin could not be updated.', 'updatebee' )
					: __( 'Theme could not be updated.', 'updatebee' )
			);
		},
		[ runAction ]
	);

	const actions = useMemo(
		() =>
			getUpcomingActions( {
				onSkip,
				onBlock,
				onUpdate,
				onChangelog,
			} ),
		[ onBlock, onChangelog, onSkip, onUpdate ]
	);

	const fields = useMemo< Field< UpcomingItem >[] >(
		() => [
			{
				id: 'Name',
				type: 'text',
				label: __( 'Update offer', 'updatebee' ),
				enableGlobalSearch: true,
				enableHiding: false,
				enableSorting: true,
				getValue: ( { item } ) =>
					`${ item.Name } ${ item.TypeLabel }`.trim(),
				render: ( { item } ) => (
					<Tooltip text={ item.Name }>
						<Truncate ellipsizeMode="tail" limit={ 35 }>
							{ item.Name }
						</Truncate>
					</Tooltip>
				),
			},
			{
				id: 'Version',
				type: 'text',
				label: __( 'Version', 'updatebee' ),
				enableGlobalSearch: true,
				enableSorting: true,
				getValue: ( { item } ) => versionLabel( item ),
				render: ( { item } ) => versionLabel( item ),
			},
			{
				id: 'Schedule',
				type: 'text',
				label: __( 'Schedule', 'updatebee' ),
				enableGlobalSearch: false,
				enableSorting: true,
			},
		],
		[]
	);

	const { data: visibleItems, paginationInfo } = useMemo(
		() => filterSortAndPaginate( items, view, fields ),
		[ fields, items, view ]
	);

	const onCheckNow = () => {
		if ( isBusy ) {
			return;
		}

		setIsBusy( true );
		setIsChecking( true );
		setNotice( null );
		sendCommand< MessagePayload >( 'updatebee_refresh_updates', {
			nonce: updatebeeUpcoming.refreshNonce,
		} )
			.then( ( response ) => {
				showNotice(
					response.data.data?.message ||
						__( 'Updates have been force refreshed.', 'updatebee' ),
					'success'
				);
				return loadItems();
			} )
			.catch( ( error: Error ) => {
				showNotice(
					error.message ||
						__( 'Updates could not be refreshed.', 'updatebee' ),
					'error'
				);
			} )
			.finally( () => {
				setIsBusy( false );
				setIsChecking( false );
			} );
	};

	if ( isLoading && ! isChecking && 0 === items.length && ! isError ) {
		return <Loader />;
	}

	if ( isError && 0 === items.length ) {
		return (
			<ErrorFallback
				heading={ __( 'Upcoming updates', 'updatebee' ) }
				message={ __(
					'Upcoming updates could not be loaded. Try again or reload the page.',
					'updatebee'
				) }
				onRetry={ () => {
					setLoadAttempt( ( attempt ) => attempt + 1 );
				} }
			/>
		);
	}

	return (
		<div className="updatebee-upcoming-tab">
			<div className="updatebee-upcoming-toolbar">
				<div>
					<h1>{ __( 'Upcoming updates', 'updatebee' ) }</h1>
					<p className="description">
						{ __(
							'Manage updates before they are installed automatically.',
							'updatebee'
						) }
					</p>
					<div className="updatebee-upcoming-next-run">
						<p className="description">{ nextRunLabel }</p>
						{ updatebeeUpcoming.showSchedules && (
							<Button
								variant="outline"
								onClick={ () => {
									window.location.assign(
										updatebeeUpcoming.schedulesUrl
									);
								} }
							>
								{ __( 'Change', 'updatebee' ) }
							</Button>
						) }
					</div>
				</div>
				<div className="updatebee-upcoming-toolbar-actions">
					<Button
						variant="outline"
						disabled={ isBusy }
						loading={ isChecking }
						loadingAnnouncement={ __(
							'Checking for updates',
							'updatebee'
						) }
						onClick={ onCheckNow }
					>
						{ __( 'Check now', 'updatebee' ) }
					</Button>
				</div>
			</div>
			{ 0 < delayUpdates && (
				<div className="updatebee-upcoming-delay">
					<Notice status="warning" isDismissible={ false }>
						<span className="updatebee-upcoming-delay-copy">
							{ delayNotice( delayUpdates ) }
						</span>
						<Button
							variant="outline"
							disabled={ isBusy || isSavingDelay }
							onClick={ openDelayModal }
						>
							{ __( 'Change delay', 'updatebee' ) }
						</Button>
					</Notice>
				</div>
			) }
			<FloatingNotice
				isVisible={ null !== notice }
				message={ notice?.message ?? '' }
				type={ notice?.intent }
				onClose={ () => {
					setNotice( null );
				} }
			/>
			<DataViews
				data={ visibleItems }
				fields={ fields }
				view={ view }
				onChangeView={ onChangeView }
				onReset={ onReset }
				actions={ actions }
				paginationInfo={ paginationInfo }
				isLoading={ ! isChecking && ( isBusy || isLoading ) }
				getItemId={ ( item ) => item.RowId }
				defaultLayouts={ {
					table: {
						layout: {
							density: 'balanced',
							styles: tableColumnStyles,
						},
					},
				} }
				empty={
					<p>
						{ 0 === items.length
							? __(
									'No plugin or theme updates are available.',
									'updatebee'
								)
							: __(
									'No updates match the current search.',
									'updatebee'
								) }
					</p>
				}
			>
				<UpcomingDataViews
					searchLabel={ __( 'Search updates', 'updatebee' ) }
				/>
			</DataViews>
			<UpcomingDelayModal
				isOpen={ isDelayModalOpen }
				delayDays={ delayUpdates }
				isSaving={ isSavingDelay }
				errorMessage={ delayError }
				onClose={ closeDelayModal }
				onSave={ onSaveDelay }
			/>
			<ChangelogModal
				target={
					changelogItem && changelogItem.ChangelogNonce
						? {
								kind: changelogItem.Type,
								id: changelogItem.Id,
								name: changelogItem.Name,
								nonce: changelogItem.ChangelogNonce,
							}
						: null
				}
				onClose={ () => {
					setChangelogItem( null );
				} }
			/>
		</div>
	);
};

export default UpcomingUpdates;
