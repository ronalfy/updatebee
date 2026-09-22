/**
 * Translations screen: locale catalog with DataViews actions.
 */
import { Notice } from '@wordpress/components';
import {
	DataViews,
	filterSortAndPaginate,
	type Field,
	type View,
} from '@wordpress/dataviews';
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Stack } from '@wordpress/ui';
import sendCommand from '../../../utils/SendCommand';
import { ErrorFallback } from '../../components/ErrorBoundary';
import Loader from '../../components/Loader';
import TranslationsFooter, {
	type SnackbarState,
	type SnackbarType,
} from './TranslationsFooter';
import {
	getTranslationActionMessage,
	getTranslationActions,
	type TranslationActionId,
} from './translation-actions';

export type TranslationItem = {
	Locale: string;
	EnglishName: string;
	NativeName: string;
	Autoupdates: boolean;
	Blocked: boolean;
	EnableNonce: string;
	BlockNonce: string;
};

export type AutoUpdatesStatus = 'enabled' | 'custom' | 'disabled' | 'blocked';

type TranslationsPayload = {
	translations?: TranslationItem[];
	auto_updates_enabled?: AutoUpdatesStatus;
};

/**
 * Table column widths. DataViews stretches titleField/mediaField and ignores
 * styles for those ids, so this view uses regular fields only.
 */
const tableColumnStyles = {
	EnglishName: {
		width: '260px',
		maxWidth: '280px',
	},
	Locale: {
		width: '140px',
		maxWidth: '160px',
	},
	NativeName: {
		width: '220px',
		maxWidth: '280px',
	},
};

const defaultView: View = {
	type: 'table',
	search: '',
	page: 1,
	perPage: 25,
	fields: ['EnglishName', 'Locale', 'NativeName', 'status'],
	sort: {
		field: 'EnglishName',
		direction: 'asc',
	},
	layout: {
		density: 'balanced',
		styles: tableColumnStyles,
	},
};

const getStatusNotice = (
	status: AutoUpdatesStatus
): { message: string; status: 'success' | 'warning' | 'error' | 'info' } => {
	if ('enabled' === status) {
		return {
			status: 'success',
			message: __(
				'Automatic updates are enabled for all translations.',
				'updatebee'
			),
		};
	}

	if ('custom' === status) {
		return {
			status: 'info',
			message: __(
				'Automatic updates are enabled, but only for the translations that are manually enabled.',
				'updatebee'
			),
		};
	}

	if ('blocked' === status) {
		return {
			status: 'error',
			message: __(
				'All updates are blocked. You will no longer receive any translation updates and may be a security risk.',
				'updatebee'
			),
		};
	}

	return {
		status: 'warning',
		message: __(
			'Automatic updates are disabled for all translations.',
			'updatebee'
		),
	};
};

type TranslationsDataViewsProps = {
	searchLabel: string;
	snackbar: SnackbarState;
	onSnackbarClose: () => void;
};

/**
 * Compose DataViews so the footer can render in the admin slot.
 *
 * @param props Toolbar labels and footer snackbar state.
 */
const TranslationsDataViews = (props: TranslationsDataViewsProps) => {
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
					<DataViews.Search label={searchLabel} />
				</Stack>
				<Stack direction="row" gap="xs" style={{ flexShrink: 0 }}>
					<DataViews.LayoutSwitcher />
					<DataViews.ViewConfig />
				</Stack>
			</Stack>
			<DataViews.FiltersToggled className="dataviews-filters__container" />
			<DataViews.Layout />
			<TranslationsFooter
				snackbar={snackbar}
				onSnackbarClose={onSnackbarClose}
			/>
		</>
	);
};

const TranslationIcon = () => {
	return (
		<svg
			className="updatebee-icon updatebee-translation-icon"
			aria-hidden="true"
			focusable="false"
		>
			<use href="#icon-language" />
		</svg>
	);
};

type TranslationNameCellProps = {
	name: string;
};

/**
 * Render the language icon beside the English locale name.
 *
 * @param props Cell props.
 */
const TranslationNameCell = (props: TranslationNameCellProps) => {
	const { name } = props;

	return (
		<span className="updatebee-translation-name">
			<TranslationIcon />
			{name}
		</span>
	);
};

const TranslationsMain = () => {
	const [isLoaded, setIsLoaded] = useState(false);
	const [isError, setIsError] = useState(false);
	const [isBusy, setIsBusy] = useState(false);
	const [translations, setTranslations] = useState<TranslationItem[]>(
		[]
	);
	const [autoUpdatesStatus, setAutoUpdatesStatus] =
		useState<AutoUpdatesStatus>('disabled');
	const [view, setView] = useState<View>(defaultView);
	const [snackbar, setSnackbar] = useState<SnackbarState>({
		isVisible: false,
		message: '',
		type: 'success',
	});

	const handleSnackbarClose = useCallback(() => {
		setSnackbar((current) => ({
			...current,
			isVisible: false,
		}));
	}, []);

	/**
	 * Show a snackbar notice.
	 *
	 * @param message Notice text.
	 * @param type    Notice type.
	 */
	const showSnackbar = useCallback(
		(message: string, type: SnackbarType) => {
			setSnackbar({
				isVisible: true,
				message,
				type,
			});
		},
		[]
	);

	/**
	 * Apply a row or bulk action and refresh local state.
	 *
	 * @param actionId Action id.
	 * @param items    Selected locales.
	 */
	const persistAction = useCallback(
		async (
			actionId: TranslationActionId,
			items: TranslationItem[]
		) => {
			if (!items.length || isBusy) {
				return;
			}

			setIsBusy(true);

			const applyLocalState = () => {
				setTranslations((current) =>
					current.map((translation) => {
						const selected = items.some(
							(item) => item.Locale === translation.Locale
						);
						if (!selected) {
							return translation;
						}

						if ('enable' === actionId) {
							return {
								...translation,
								Autoupdates: true,
							};
						}

						if ('disable' === actionId) {
							return {
								...translation,
								Autoupdates: false,
							};
						}

						if ('block' === actionId) {
							return {
								...translation,
								Blocked: true,
							};
						}

						return {
							...translation,
							Blocked: false,
						};
					})
				);
			};

			try {
				if (1 === items.length) {
					const item = items[0];
					if ('enable' === actionId || 'disable' === actionId) {
						await sendCommand(
							'updatebee_toggle_translation_auto_updates',
							{
								locale: item.Locale,
								nonce: item.EnableNonce,
								enabled: 'enable' === actionId,
							}
						);
					} else {
						await sendCommand(
							'updatebee_toggle_translation_block_updates',
							{
								locale: item.Locale,
								nonce: item.BlockNonce,
								enabled: 'block' === actionId,
							}
						);
					}
				} else {
					await sendCommand('updatebee_bulk_translation_action', {
						nonce: updatebeeTranslations.bulkNonce,
						bulk_action: actionId,
						locales: items.map((item) => item.Locale),
					});
				}

				applyLocalState();
				showSnackbar(
					getTranslationActionMessage(actionId, items),
					'success'
				);
			} catch (error) {
				showSnackbar(
					__(
						'The translation update could not be saved. Please try again.',
						'updatebee'
					),
					'error'
				);
			} finally {
				setIsBusy(false);
			}
		},
		[isBusy, showSnackbar]
	);

	const fields = useMemo<Field<TranslationItem>[]>(
		() => [
			{
				id: 'EnglishName',
				type: 'text',
				label: __('Translation name', 'updatebee'),
				enableGlobalSearch: true,
				enableHiding: false,
				render: ({ item }) => (
					<TranslationNameCell name={item.EnglishName} />
				),
			},
			{
				id: 'Locale',
				type: 'text',
				label: __('Locale', 'updatebee'),
				enableGlobalSearch: true,
			},
			{
				id: 'NativeName',
				type: 'text',
				label: __('Localized name', 'updatebee'),
				enableGlobalSearch: true,
			},
			{
				id: 'status',
				type: 'text',
				label: __('Status', 'updatebee'),
				enableSorting: true,
				enableHiding: true,
				enableGlobalSearch: false,
				filterBy: {
					operators: ['is'],
					isPrimary: true,
				},
				elements: [
					{
						value: 'enabled',
						label: __(
							'Auto-updates enabled',
							'updatebee'
						),
					},
					{
						value: 'disabled',
						label: __(
							'Auto-updates disabled',
							'updatebee'
						),
					},
					{
						value: 'blocked',
						label: __('Blocked', 'updatebee'),
					},
				],
				getValue: ({ item }) => {
					if (item.Blocked) {
						return 'blocked';
					}

					if ('enabled' === autoUpdatesStatus) {
						return 'enabled';
					}

					if ('custom' === autoUpdatesStatus && item.Autoupdates) {
						return 'enabled';
					}

					return 'disabled';
				},
			},
		],
		[autoUpdatesStatus]
	);

	const actions = useMemo(
		() =>
			getTranslationActions({
				autoUpdatesStatus,
				isBusy,
				onAction: persistAction,
			}),
		[autoUpdatesStatus, isBusy, persistAction]
	);

	const { data: visibleTranslations, paginationInfo } = useMemo(
		() => filterSortAndPaginate(translations, view, fields),
		[fields, translations, view]
	);

	useEffect(() => {
		sendCommand<TranslationsPayload>(
			'updatebee_retrieve_translations',
			{
				nonce: updatebeeTranslations.retrieveNonce,
			}
		)
			.then((ajaxResponse) => {
				const payload = ajaxResponse.data.data;
				const nextTranslations = Array.isArray(payload.translations)
					? payload.translations
					: [];

				setTranslations(nextTranslations);

				if (
					'enabled' === payload.auto_updates_enabled ||
					'custom' === payload.auto_updates_enabled ||
					'disabled' === payload.auto_updates_enabled ||
					'blocked' === payload.auto_updates_enabled
				) {
					setAutoUpdatesStatus(payload.auto_updates_enabled);
				}

				setIsLoaded(true);
			})
			.catch(() => {
				setIsError(true);
				setIsLoaded(true);
			});
	}, []);

	if (!isLoaded) {
		return <Loader />;
	}

	if (isError) {
		return (
			<ErrorFallback
				heading={__('Translations could not be loaded.', 'updatebee')}
				message={__(
					'An unexpected error occurred while loading translations. Try again or reload the page.',
					'updatebee'
				)}
			/>
		);
	}

	const notice = getStatusNotice(autoUpdatesStatus);

	return (
		<>
			<div className="updatebee-translations-main">
				<h1>{__('Translations', 'updatebee')}</h1>
				<Notice
					className="updatebee-translation-notice"
					status={notice.status}
					isDismissible={false}
				>
					{notice.message}
				</Notice>
				<DataViews
					data={visibleTranslations}
					fields={fields}
					view={view}
					onChangeView={setView}
					actions={actions}
					paginationInfo={paginationInfo}
					defaultLayouts={{
						table: {
							layout: {
								density: 'balanced',
								styles: tableColumnStyles,
							},
						},
					}}
					getItemId={(item) => item.Locale}
					isLoading={isBusy}
					empty={
						<p>
							{__(
								'No translations match the current filters.',
								'updatebee'
							)}
						</p>
					}
				>
					<TranslationsDataViews
						searchLabel={__('Search translations', 'updatebee')}
						snackbar={snackbar}
						onSnackbarClose={handleSnackbarClose}
					/>
				</DataViews>
			</div>
		</>
	);
};

export default TranslationsMain;
