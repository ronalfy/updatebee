/**
 * Upcoming plugin and theme updates.
 */
import {
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
import { __ } from '@wordpress/i18n';
import { Button, Stack } from '@wordpress/ui';
import sendCommand from '../../../utils/SendCommand';
import { DATAVIEWS_STORAGE_KEYS } from '../../../utils/dataviews-view-storage';
import { ErrorFallback } from '../../components/ErrorBoundary';
import Loader from '../../components/Loader';
import Snackbar from '../../components/Snackbar';
import usePersistedDataView from '../../hooks/usePersistedDataView';
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
};

type UpcomingPayload = {
	items?: UpcomingItem[];
	next_run_label?: string;
};

type MessagePayload = {
	message?: string;
};

type SnackbarType = 'success' | 'error' | 'warning' | 'info';

type SnackbarState = {
	isVisible: boolean;
	message: string;
	type: SnackbarType;
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
 * Upcoming updates table.
 */
const UpcomingUpdates = () => {
	const [ items, setItems ] = useState< UpcomingItem[] >( [] );
	const [ nextRunLabel, setNextRunLabel ] = useState( '' );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ isError, setIsError ] = useState( false );
	const [ isBusy, setIsBusy ] = useState( false );
	const [ loadAttempt, setLoadAttempt ] = useState( 0 );
	const [ snackbar, setSnackbar ] = useState< SnackbarState >( {
		isVisible: false,
		message: '',
		type: 'success',
	} );
	const { view, onChangeView, onReset } = usePersistedDataView(
		DATAVIEWS_STORAGE_KEYS.upcoming,
		defaultView,
		fieldIds
	);

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

	const loadItems = useCallback( () => {
		setIsLoading( true );
		setIsError( false );

		return sendCommand< UpcomingPayload >(
			'updatebee_retrieve_upcoming_updates',
			{
				nonce: updatebeeDashboard.retrieveUpcomingNonce,
			}
		)
			.then( ( response ) => {
				const payload = response.data.data;
				setItems(
					Array.isArray( payload?.items ) ? payload.items : []
				);
				setNextRunLabel( payload?.next_run_label || '' );
			} )
			.catch( () => {
				setIsError( true );
			} )
			.finally( () => {
				setIsLoading( false );
			} );
	}, [] );

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
			sendCommand< MessagePayload >( action, payload )
				.then( ( response ) => {
					showSnackbar(
						response.data.data?.message ||
							__( 'Action completed.', 'updatebee' ),
						'success'
					);
					return loadItems();
				} )
				.catch( ( error: Error ) => {
					showSnackbar( error.message || fallback, 'error' );
				} )
				.finally( () => {
					setIsBusy( false );
				} );
		},
		[ isBusy, loadItems, showSnackbar ]
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
			} ),
		[ onBlock, onSkip, onUpdate ]
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
		sendCommand< MessagePayload >( 'updatebee_refresh_updates', {
			nonce: updatebeeDashboard.refreshNonce,
		} )
			.then( ( response ) => {
				showSnackbar(
					response.data.data?.message ||
						__( 'Updates have been force refreshed.', 'updatebee' ),
					'success'
				);
				return loadItems();
			} )
			.catch( ( error: Error ) => {
				showSnackbar(
					error.message ||
						__( 'Updates could not be refreshed.', 'updatebee' ),
					'error'
				);
			} )
			.finally( () => {
				setIsBusy( false );
			} );
	};

	if ( isLoading && 0 === items.length && ! isError ) {
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
						<Button
							variant="outline"
							onClick={ () => {
								window.location.assign(
									updatebeeDashboard.schedulesUrl
								);
							} }
						>
							{ __( 'Change', 'updatebee' ) }
						</Button>
					</div>
				</div>
				<div className="updatebee-upcoming-toolbar-actions">
					<Button
						variant="outline"
						disabled={ isBusy }
						onClick={ onCheckNow }
					>
						{ __( 'Check now', 'updatebee' ) }
					</Button>
				</div>
			</div>
			<DataViews
				data={ visibleItems }
				fields={ fields }
				view={ view }
				onChangeView={ onChangeView }
				onReset={ onReset }
				actions={ actions }
				paginationInfo={ paginationInfo }
				isLoading={ isBusy || isLoading }
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
			<Snackbar
				isVisible={ snackbar.isVisible }
				message={ snackbar.message }
				type={ snackbar.type }
				onClose={ () => {
					setSnackbar( ( current ) => ( {
						...current,
						isVisible: false,
					} ) );
				} }
			/>
		</div>
	);
};

export default UpcomingUpdates;
