/**
 * Logs screen: update history with DataViews filters.
 */
import { DataViews, type Field, type View } from '@wordpress/dataviews';
import {
	useCallback,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Stack, useEnableWpCompatOverlaySlot } from '@wordpress/ui';
import { Tooltip } from '@wordpress/components';
import sendCommand from '../../../utils/SendCommand';
import { DATAVIEWS_STORAGE_KEYS } from '../../../utils/dataviews-view-storage';
import { ErrorFallback } from '../../components/ErrorBoundary';
import usePersistedDataView from '../../hooks/usePersistedDataView';
import Loader from '../../components/Loader';
import LogDetailModal, { type LogDetailPayload } from './LogDetailModal';
import LogsClearModal from './LogsClearModal';
import LogsExportDrawer from './LogsExportDrawer';
import LogsSettingsDrawer from './LogsSettingsDrawer';
import { getLogActions } from './log-actions';
import {
	getLogUpdateStatusLabel,
	LOG_UPDATE_STATUS_OPTIONS,
} from './log-status-labels';
import LogsFooter, {
	type SnackbarState,
	type SnackbarType,
} from './LogsFooter';

export type LogItem = {
	Id: number;
	Name: string;
	Slug: string;
	UserId: number;
	User: string;
	FromVersion: string;
	ToVersion: string;
	UpdateOffer: string;
	UpdateType: string;
	UpdateStatus: string;
	Date: string;
	HasNotes: boolean;
	HasStacktrace: boolean;
};

type LogsPayload = {
	logs?: LogItem[];
	paginationInfo?: {
		totalItems: number;
		totalPages: number;
	};
};

type LogDetailResponse = {
	Notes?: string;
	Stacktrace?: string;
};

type LogsMessagePayload = {
	message?: string;
};

/**
 * Table column widths. DataViews stretches titleField/mediaField and ignores
 * styles for those ids, so this view uses regular fields only.
 */
const tableColumnStyles = {
	Name: {
		width: '240px',
		maxWidth: '280px',
	},
	User: {
		width: '120px',
		maxWidth: '140px',
	},
	FromVersion: {
		width: '120px',
		maxWidth: '140px',
	},
	ToVersion: {
		width: '120px',
		maxWidth: '140px',
	},
	UpdateOffer: {
		width: '140px',
		maxWidth: '160px',
	},
	UpdateType: {
		width: '140px',
		maxWidth: '160px',
	},
	UpdateStatus: {
		width: '120px',
		maxWidth: '140px',
	},
	Date: {
		width: '180px',
		maxWidth: '200px',
	},
};

const defaultView: View = {
	type: 'table',
	search: '',
	page: 1,
	perPage: 25,
	fields: [
		'Name',
		'User',
		'FromVersion',
		'ToVersion',
		'UpdateOffer',
		'UpdateType',
		'UpdateStatus',
		'Date',
	],
	sort: {
		field: 'Date',
		direction: 'desc',
	},
	layout: {
		density: 'balanced',
		styles: tableColumnStyles,
	},
};

const logFieldIds = defaultView.fields ?? [];

type LogsDataViewsProps = {
	searchLabel: string;
	snackbar: SnackbarState;
	onSnackbarClose: () => void;
};

/**
 * Compose DataViews so the footer can render in the admin slot.
 *
 * @param props Toolbar labels and footer snackbar state.
 */
const LogsDataViews = ( props: LogsDataViewsProps ) => {
	const { searchLabel, snackbar, onSnackbarClose } = props;

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
					<DataViews.FiltersToggle />
				</Stack>
				<Stack direction="row" gap="xs" style={ { flexShrink: 0 } }>
					<DataViews.LayoutSwitcher />
					<DataViews.ViewConfig />
				</Stack>
			</Stack>
			<DataViews.FiltersToggled className="dataviews-filters__container" />
			<DataViews.Layout />
			<LogsFooter
				snackbar={ snackbar }
				onSnackbarClose={ onSnackbarClose }
			/>
		</>
	);
};

/**
 * Return the sprite symbol for a log update type.
 *
 * @param updateType Stored update type.
 * @return Sprite symbol id.
 */
const getLogIconId = ( updateType: string ): string => {
	if ( 'theme' === updateType ) {
		return 'icon-roller-brush';
	}

	if ( 'core' === updateType ) {
		return 'icon-wordpress';
	}

	if ( 'translation' === updateType ) {
		return 'icon-language';
	}

	return 'icon-plug';
};

type LogNameCellProps = {
	name: string;
	slug: string;
	updateType: string;
};

/**
 * Render the type icon beside the log name and slug.
 *
 * @param props Cell props.
 */
const LogNameCell = ( props: LogNameCellProps ) => {
	const { name, slug, updateType } = props;

	const displayName = name.length > 30 ? name.slice( 0, 30 ) + '…' : name;

	return (
		<span className="updatebee-log-name">
			<svg
				className="updatebee-icon updatebee-log-icon"
				aria-hidden="true"
				focusable="false"
			>
				<use href={ `#${ getLogIconId( updateType ) }` } />
			</svg>
			<span className="updatebee-log-name-text">
				<Tooltip text={ name }>
					<span className="updatebee-log-name-title">
						{ displayName }
					</span>
				</Tooltip>
				{ slug && (
					<p className="description updatebee-log-name-slug">
						{ slug }
					</p>
				) }
			</span>
		</span>
	);
};

const LogsMain = () => {
	useEnableWpCompatOverlaySlot();
	const [ isLoaded, setIsLoaded ] = useState( false );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ isError, setIsError ] = useState( false );
	const [ logs, setLogs ] = useState< LogItem[] >( [] );
	const [ paginationInfo, setPaginationInfo ] = useState( {
		totalItems: 0,
		totalPages: 0,
	} );
	const { view, onChangeView, onReset } = usePersistedDataView(
		DATAVIEWS_STORAGE_KEYS.logs,
		defaultView,
		logFieldIds
	);
	const [ snackbar, setSnackbar ] = useState< SnackbarState >( {
		isVisible: false,
		message: '',
		type: 'success',
	} );
	const [ isClearOpen, setIsClearOpen ] = useState( false );
	const [ isClearing, setIsClearing ] = useState( false );
	const [ detailLog, setDetailLog ] = useState< LogItem | null >( null );
	const [ detailPayload, setDetailPayload ] =
		useState< LogDetailPayload | null >( null );
	const [ detailLoading, setDetailLoading ] = useState( false );
	const [ detailError, setDetailError ] = useState( '' );
	const detailCacheRef = useRef< Map< number, LogDetailPayload > >(
		new Map()
	);

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

	const handleCloseDetailModal = useCallback( () => {
		setDetailLog( null );
		setDetailPayload( null );
		setDetailLoading( false );
		setDetailError( '' );
	}, [] );

	/**
	 * Close the clear confirmation modal.
	 */
	const handleCloseClearModal = useCallback( () => {
		if ( isClearing ) {
			return;
		}

		setIsClearOpen( false );
	}, [ isClearing ] );

	/**
	 * Clear all logs and refresh the table.
	 *
	 * @return True when the request succeeds.
	 */
	const handleClearLogs = useCallback( (): Promise< boolean > => {
		if ( isClearing ) {
			return Promise.resolve( false );
		}

		setIsClearing( true );

		return sendCommand< LogsMessagePayload >( 'updatebee_clear_logs', {
			nonce: updatebeeLogs.clearNonce,
		} )
			.then( ( response ) => {
				setLogs( [] );
				detailCacheRef.current.clear();
				handleCloseDetailModal();
				onChangeView( ( current: View ) => ( {
					...current,
					page: 1,
				} ) );
				showSnackbar(
					response.data.data?.message ||
						__( 'Logs have been cleared.', 'updatebee' ),
					'success'
				);
				return true;
			} )
			.catch( () => {
				showSnackbar(
					__( 'Logs could not be cleared.', 'updatebee' ),
					'error'
				);
				return false;
			} )
			.finally( () => {
				setIsClearing( false );
			} );
	}, [ handleCloseDetailModal, isClearing, onChangeView, showSnackbar ] );

	const handleViewDetails = useCallback( ( item: LogItem ) => {
		setDetailLog( item );
		setDetailError( '' );

		const cached = detailCacheRef.current.get( item.Id );
		if ( cached ) {
			setDetailPayload( cached );
			setDetailLoading( false );
			return;
		}

		setDetailPayload( null );
		setDetailLoading( true );

		sendCommand< LogDetailResponse >( 'updatebee_retrieve_log_detail', {
			nonce: updatebeeLogs.detailNonce,
			log_id: item.Id,
		} )
			.then( ( ajaxResponse ) => {
				const payload = ajaxResponse.data.data;
				const nextDetail: LogDetailPayload = {
					notes: payload.Notes || '',
					stacktrace: payload.Stacktrace || '',
				};

				detailCacheRef.current.set( item.Id, nextDetail );
				setDetailPayload( nextDetail );
			} )
			.catch( () => {
				setDetailError(
					__( 'Could not load log details. Try again.', 'updatebee' )
				);
			} )
			.finally( () => {
				setDetailLoading( false );
			} );
	}, [] );

	const actions = useMemo(
		() =>
			getLogActions( {
				onViewDetails: handleViewDetails,
			} ),
		[ handleViewDetails ]
	);

	const fields = useMemo< Field< LogItem >[] >(
		() => [
			{
				id: 'Name',
				type: 'text',
				label: __( 'Name', 'updatebee' ),
				enableGlobalSearch: true,
				enableHiding: false,
				getValue: ( { item } ) =>
					`${ item.Name } ${ item.Slug }`.trim(),
				render: ( { item } ) => (
					<LogNameCell
						name={ item.Name }
						slug={ item.Slug }
						updateType={ item.UpdateType }
					/>
				),
			},
			{
				id: 'User',
				type: 'text',
				label: __( 'User', 'updatebee' ),
				enableGlobalSearch: true,
			},
			{
				id: 'FromVersion',
				type: 'text',
				label: __( 'Version From', 'updatebee' ),
				enableGlobalSearch: false,
				render: ( { item } ) => item.FromVersion || ' - ',
			},
			{
				id: 'ToVersion',
				type: 'text',
				label: __( 'Version To', 'updatebee' ),
				enableGlobalSearch: false,
			},
			{
				id: 'UpdateOffer',
				type: 'text',
				label: __( 'Update Offer', 'updatebee' ),
				enableGlobalSearch: false,
				filterBy: {
					operators: [ 'is' ],
					isPrimary: true,
				},
				elements: [
					{
						value: 'automatic',
						label: __( 'Automatic', 'updatebee' ),
					},
					{
						value: 'manual',
						label: __( 'Manual', 'updatebee' ),
					},
				],
			},
			{
				id: 'UpdateType',
				type: 'text',
				label: __( 'Update Types', 'updatebee' ),
				enableGlobalSearch: false,
				filterBy: {
					operators: [ 'is' ],
					isPrimary: true,
				},
				elements: [
					{
						value: 'core',
						label: __( 'Core', 'updatebee' ),
					},
					{
						value: 'plugin',
						label: __( 'Plugin', 'updatebee' ),
					},
					{
						value: 'theme',
						label: __( 'Theme', 'updatebee' ),
					},
					{
						value: 'translation',
						label: __( 'Translation', 'updatebee' ),
					},
				],
			},
			{
				id: 'UpdateStatus',
				type: 'text',
				label: __( 'Status', 'updatebee' ),
				enableGlobalSearch: false,
				filterBy: {
					operators: [ 'is' ],
				},
				elements: LOG_UPDATE_STATUS_OPTIONS,
				render: ( { item } ) =>
					getLogUpdateStatusLabel( item.UpdateStatus ),
			},
			{
				id: 'Date',
				type: 'datetime',
				label: __( 'Date', 'updatebee' ),
				enableSorting: true,
				enableGlobalSearch: false,
			},
		],
		[]
	);

	const visibleLogs = logs;

	useEffect( () => {
		setIsLoading( true );

		sendCommand< LogsPayload >( 'updatebee_retrieve_logs', {
			nonce: updatebeeLogs.retrieveNonce,
			page: view.page,
			per_page: view.perPage,
			sort_field: view.sort?.field ?? 'Date',
			sort_direction: view.sort?.direction ?? 'desc',
			search: view.search ?? '',
			filters: view.filters ?? [],
		} )
			.then( ( ajaxResponse ) => {
				const payload = ajaxResponse.data.data;
				const nextLogs = Array.isArray( payload.logs )
					? payload.logs
					: [];

				setLogs( nextLogs );
				setPaginationInfo(
					payload.paginationInfo ?? { totalItems: 0, totalPages: 0 }
				);
				setIsError( false );
				setIsLoaded( true );
			} )
			.catch( () => {
				setIsError( true );
				setIsLoaded( true );
			} )
			.finally( () => {
				setIsLoading( false );
			} );
	}, [ view ] );

	if ( ! isLoaded ) {
		return <Loader />;
	}

	if ( isError ) {
		return (
			<ErrorFallback
				heading={ __( 'Logs could not be loaded.', 'updatebee' ) }
				message={ __(
					'An unexpected error occurred while loading logs. Try again or reload the page.',
					'updatebee'
				) }
			/>
		);
	}

	const hasLogs = 0 < logs.length;
	const actionsDisabled = ! hasLogs || isClearing;

	return (
		<>
			<div className="updatebee-logs-main">
				<h1>{ __( 'Logs', 'updatebee' ) }</h1>
				<Stack
					direction="row"
					justify="end"
					gap="xs"
					className="updatebee-logs-actions"
				>
					<LogsExportDrawer disabled={ actionsDisabled } />
					<Button
						variant="solid"
						className="updatebee-button-danger"
						disabled={ actionsDisabled }
						onClick={ () => {
							setIsClearOpen( true );
						} }
					>
						{ __( 'Clear logs', 'updatebee' ) }
					</Button>
					<LogsSettingsDrawer onSnackbar={ showSnackbar } />
				</Stack>
				<DataViews
					data={ visibleLogs }
					fields={ fields }
					view={ view }
					onChangeView={ onChangeView }
					onReset={ onReset }
					actions={ actions }
					paginationInfo={ paginationInfo }
					isLoading={ isLoading }
					defaultLayouts={ {
						table: {
							layout: {
								density: 'balanced',
								styles: tableColumnStyles,
							},
						},
					} }
					getItemId={ ( item ) => String( item.Id ) }
					empty={
						<p>
							{ 0 === logs.length
								? __(
										'No logs are currently available.',
										'updatebee'
								  )
								: __(
										'No logs match the current filters.',
										'updatebee'
								  ) }
						</p>
					}
				>
					<LogsDataViews
						searchLabel={ __( 'Search logs', 'updatebee' ) }
						snackbar={ snackbar }
						onSnackbarClose={ handleSnackbarClose }
					/>
				</DataViews>
			</div>
			<LogDetailModal
				log={ detailLog }
				isOpen={ null !== detailLog }
				isLoading={ detailLoading }
				error={ detailError }
				detail={ detailPayload }
				onClose={ handleCloseDetailModal }
			/>
			<LogsClearModal
				isOpen={ isClearOpen }
				isClearing={ isClearing }
				onClose={ handleCloseClearModal }
				onClear={ handleClearLogs }
			/>
		</>
	);
};

export default LogsMain;
