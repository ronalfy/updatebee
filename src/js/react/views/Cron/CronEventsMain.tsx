/**
 * Cron Events sub-tab: DataViews table of scheduled WordPress cron events.
 */
import {
	DataViews,
	filterSortAndPaginate,
	type Field,
	type View,
} from '@wordpress/dataviews';
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Card, Stack } from '@wordpress/ui';
import sendCommand from '../../../utils/SendCommand';
import { DATAVIEWS_STORAGE_KEYS } from '../../../utils/dataviews-view-storage';
import Loader from '../../components/Loader';
import usePersistedDataView from '../../hooks/usePersistedDataView';
import type { SnackbarState, SnackbarType } from '../Tools/ToolsFooter';
import CronEventsFooter from './CronEventsFooter';
import { getCronEventActions } from './cron-events-actions';
import type { CronEventRow, CronEventsListPayload } from './cron-events-types';

type CronEventsMainProps = {
	onNotify: ( message: string, type: SnackbarType ) => void;
	snackbar: SnackbarState;
	onSnackbarClose: () => void;
};

const tableColumnStyles = {
	hook: {
		width: '220px',
		maxWidth: '280px',
	},
	nextRunLocal: {
		width: '180px',
		maxWidth: '200px',
	},
	scheduleLabel: {
		width: '160px',
		maxWidth: '180px',
	},
	argsDisplay: {
		width: '240px',
		maxWidth: '320px',
	},
};

const defaultView: View = {
	type: 'table',
	search: '',
	page: 1,
	perPage: 25,
	titleField: 'hook',
	fields: [ 'hook', 'nextRunLocal', 'scheduleLabel', 'argsDisplay' ],
	sort: {
		field: 'nextRunLocal',
		direction: 'asc',
	},
	layout: {
		density: 'balanced',
		styles: tableColumnStyles,
	},
};

const cronEventFieldIds = defaultView.fields ?? [];

type CronEventsDataViewsProps = {
	searchLabel: string;
	snackbar: SnackbarState;
	onSnackbarClose: () => void;
};

const CronEventsDataViews = ( props: CronEventsDataViewsProps ) => {
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
			<CronEventsFooter
				snackbar={ snackbar }
				onSnackbarClose={ onSnackbarClose }
			/>
		</>
	);
};

/**
 * Scheduled cron events list with run/delete actions.
 *
 * @param props Snackbar callback.
 */
const CronEventsMain = ( props: CronEventsMainProps ) => {
	const { onNotify, snackbar, onSnackbarClose } = props;

	const [ isLoading, setIsLoading ] = useState( true );
	const [ events, setEvents ] = useState< CronEventRow[] >( [] );
	const [ isBusy, setIsBusy ] = useState( false );
	const { view, onChangeView, onReset } = usePersistedDataView(
		DATAVIEWS_STORAGE_KEYS.cronEvents,
		defaultView,
		cronEventFieldIds
	);

	const loadEvents = useCallback( async () => {
		setIsLoading( true );
		try {
			const response = await sendCommand< CronEventsListPayload >(
				'updatebee_list_cron_events',
				{
					nonce: updatebeeCron.eventsNonce,
				}
			);
			setEvents( response.data.data.events ?? [] );
		} catch {
			onNotify(
				__( 'Could not load cron events.', 'updatebee' ),
				'error'
			);
		} finally {
			setIsLoading( false );
		}
	}, [ onNotify ] );

	useEffect( () => {
		void loadEvents();
	}, [ loadEvents ] );

	const handleRun = useCallback(
		async ( item: CronEventRow ) => {
			if ( isBusy ) {
				return;
			}
			setIsBusy( true );
			try {
				const response = await sendCommand<
					CronEventsListPayload & {
						message?: string;
					}
				>( 'updatebee_run_cron_event', {
					nonce: updatebeeCron.eventsNonce,
					hook: item.hook,
					sig: item.sig,
				} );
				setEvents( response.data.data.events ?? [] );
				onNotify(
					response.data.data.message ??
						__( 'Cron event started.', 'updatebee' ),
					'success'
				);
			} catch {
				onNotify(
					__( 'Could not run cron event.', 'updatebee' ),
					'error'
				);
			} finally {
				setIsBusy( false );
			}
		},
		[ isBusy, onNotify ]
	);

	const handleDelete = useCallback(
		async ( item: CronEventRow ) => {
			if ( isBusy ) {
				return;
			}

			const confirmed = window.confirm(
				__(
					'Delete this cron event? This cannot be undone.',
					'updatebee'
				)
			);
			if ( ! confirmed ) {
				return;
			}

			setIsBusy( true );
			try {
				const response = await sendCommand<
					CronEventsListPayload & {
						message?: string;
					}
				>( 'updatebee_delete_cron_event', {
					nonce: updatebeeCron.eventsNonce,
					hook: item.hook,
					sig: item.sig,
					timestamp: item.timestamp,
				} );
				setEvents( response.data.data.events ?? [] );
				onNotify(
					response.data.data.message ??
						__( 'Cron event deleted.', 'updatebee' ),
					'success'
				);
			} catch {
				onNotify(
					__( 'Could not delete cron event.', 'updatebee' ),
					'error'
				);
			} finally {
				setIsBusy( false );
			}
		},
		[ isBusy, onNotify ]
	);

	const actions = useMemo(
		() =>
			getCronEventActions( {
				onRun: ( item ) => {
					void handleRun( item );
				},
				onDelete: ( item ) => {
					void handleDelete( item );
				},
			} ),
		[ handleDelete, handleRun ]
	);

	const fields: Field< CronEventRow >[] = useMemo(
		() => [
			{
				id: 'hook',
				type: 'text',
				label: __( 'Hook', 'updatebee' ),
				enableGlobalSearch: true,
				enableSorting: true,
			},
			{
				id: 'nextRunLocal',
				type: 'text',
				label: __( 'Next run (local time)', 'updatebee' ),
				enableGlobalSearch: false,
				enableSorting: true,
			},
			{
				id: 'scheduleLabel',
				type: 'text',
				label: __( 'Recurrence', 'updatebee' ),
				enableGlobalSearch: false,
				enableSorting: true,
			},
			{
				id: 'argsDisplay',
				type: 'text',
				label: __( 'Arguments', 'updatebee' ),
				enableGlobalSearch: false,
				render: ( { item } ) => item.argsDisplay || '—',
			},
		],
		[]
	);

	const searchLabel = __( 'Search cron hooks', 'updatebee' );

	const { data: visibleEvents, paginationInfo } = useMemo(
		() => filterSortAndPaginate( events, view, fields ),
		[ events, view, fields ]
	);

	return (
		<div className="updatebee-cron-events-tab">
			<Card.Root className="updatebee-cron-card">
				<Card.Header>
					<Card.Title>
						{ __( 'Cron events', 'updatebee' ) }
					</Card.Title>
					<p className="description">
						{ __(
							'All scheduled WordPress cron events on this site. Running or deleting events takes effect immediately. Persistent core events cannot be deleted.',
							'updatebee'
						) }
					</p>
				</Card.Header>
				<Card.Content>
					{ isLoading ? (
						<Loader />
					) : (
						<DataViews
							data={ visibleEvents }
							fields={ fields }
							view={ view }
							onChangeView={ onChangeView }
							onReset={ onReset }
							actions={ actions }
							isLoading={ isBusy }
							paginationInfo={ paginationInfo }
							getItemId={ ( item ) => item.id }
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
									{ 0 === events.length
										? __(
												'No cron events are currently scheduled.',
												'updatebee'
										  )
										: __(
												'No cron events match the current filters.',
												'updatebee'
										  ) }
								</p>
							}
						>
							<CronEventsDataViews
								searchLabel={ searchLabel }
								snackbar={ snackbar }
								onSnackbarClose={ onSnackbarClose }
							/>
						</DataViews>
					) }
				</Card.Content>
			</Card.Root>
		</div>
	);
};

export default CronEventsMain;
