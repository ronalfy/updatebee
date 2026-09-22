/**
 * localStorage helpers for DataViews view settings.
 */
import type { View } from '@wordpress/dataviews';

export const DATAVIEWS_STORAGE_KEYS = {
	logs: 'updatebee:dataviews:v1:logs',
	translations: 'updatebee:dataviews:v1:translations',
	cronEvents: 'updatebee:dataviews:v1:cron-events',
} as const;

type PersistableView = Omit< View, 'search' | 'page' > & {
	layout?: Omit< NonNullable< View[ 'layout' ] >, 'styles' >;
};

/**
 * Return the view slice that should be saved to localStorage.
 *
 * @param view Current view.
 * @return Settings without search, page, or layout column styles.
 */
export const getPersistableView = ( view: View ): PersistableView => {
	const { search: _search, page: _page, layout, ...rest } = view;

	if ( ! layout || 'object' !== typeof layout ) {
		return rest as PersistableView;
	}

	const { styles: _styles, ...layoutRest } = layout as Record<
		string,
		unknown
	>;

	return {
		...rest,
		layout: layoutRest,
	} as PersistableView;
};

/**
 * Compare persistable view settings for equality.
 *
 * @param left  First view.
 * @param right Second view.
 * @return True when persistable slices match.
 */
export const areViewSettingsEqual = ( left: View, right: View ): boolean => {
	return (
		JSON.stringify( getPersistableView( left ) ) ===
		JSON.stringify( getPersistableView( right ) )
	);
};

/**
 * True when the current view settings differ from the default.
 *
 * @param view        Current view.
 * @param defaultView Screen default.
 * @return Whether settings were modified.
 */
export const isViewSettingsModified = (
	view: View,
	defaultView: View
): boolean => {
	return ! areViewSettingsEqual( view, defaultView );
};

/**
 * Sanitize and merge a stored view with defaults.
 *
 * @param stored        Parsed storage payload.
 * @param defaultView   Screen default view.
 * @param validFieldIds Allowed field column ids.
 * @return Merged view for React state.
 */
export const mergeStoredView = (
	stored: Partial< View >,
	defaultView: View,
	validFieldIds: string[]
): View => {
	const filteredFields = ( stored.fields ?? [] ).filter( ( fieldId ) =>
		validFieldIds.includes( fieldId )
	);
	const fields =
		filteredFields.length > 0 ? filteredFields : defaultView.fields;

	let sort = stored.sort ?? defaultView.sort;
	if ( sort?.field && ! validFieldIds.includes( sort.field ) ) {
		sort = defaultView.sort;
	}

	const defaultLayout = defaultView.layout ?? {};
	const storedLayout = stored.layout ?? {};

	return {
		...defaultView,
		...stored,
		search: '',
		page: 1,
		fields,
		sort,
		layout: {
			...defaultLayout,
			...storedLayout,
			...( defaultView.layout &&
			'styles' in defaultView.layout &&
			defaultView.layout.styles
				? { styles: defaultView.layout.styles }
				: {} ),
		},
	} as View;
};

/**
 * Read and normalize a persisted view from localStorage.
 *
 * @param storageKey    Storage key.
 * @param defaultView   Screen default view.
 * @param validFieldIds Allowed field column ids.
 * @param storage       Storage backend (for tests).
 * @return Merged view or null when nothing valid is stored.
 */
export const loadPersistedView = (
	storageKey: string,
	defaultView: View,
	validFieldIds: string[],
	storage: Storage = window.localStorage
): View | null => {
	try {
		const raw = storage.getItem( storageKey );
		if ( ! raw ) {
			return null;
		}

		const parsed = JSON.parse( raw ) as Partial< View >;
		if ( ! parsed || 'object' !== typeof parsed ) {
			return null;
		}

		return mergeStoredView( parsed, defaultView, validFieldIds );
	} catch {
		return null;
	}
};

/**
 * Write persistable view settings to localStorage.
 *
 * @param storageKey Storage key.
 * @param view       Current view.
 * @param storage    Storage backend (for tests).
 */
export const savePersistedView = (
	storageKey: string,
	view: View,
	storage: Storage = window.localStorage
): void => {
	try {
		storage.setItem(
			storageKey,
			JSON.stringify( getPersistableView( view ) )
		);
	} catch {
		// Private browsing or quota; ignore.
	}
};

/**
 * Remove persisted view settings from localStorage.
 *
 * @param storageKey Storage key.
 * @param storage    Storage backend (for tests).
 */
export const clearPersistedView = (
	storageKey: string,
	storage: Storage = window.localStorage
): void => {
	try {
		storage.removeItem( storageKey );
	} catch {
		// Ignore storage errors.
	}
};
