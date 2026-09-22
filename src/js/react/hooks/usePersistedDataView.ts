/**
 * DataViews view state with localStorage persistence.
 */
import type { View } from '@wordpress/dataviews';
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import type { Dispatch, SetStateAction } from 'react';
import {
	clearPersistedView,
	isViewSettingsModified,
	loadPersistedView,
	savePersistedView,
} from '../../utils/dataviews-view-storage';

type UsePersistedDataViewReturn = {
	view: View;
	onChangeView: Dispatch< SetStateAction< View > >;
	onReset: ( () => void ) | false;
};

/**
 * Manage a DataViews view with settings persisted in localStorage.
 *
 * @param storageKey    localStorage key.
 * @param defaultView   Default view when nothing is stored.
 * @param validFieldIds Column ids used to sanitize stored fields and sort.
 * @return View state, change handler, and DataViews onReset value.
 */
const usePersistedDataView = (
	storageKey: string,
	defaultView: View,
	validFieldIds: string[]
): UsePersistedDataViewReturn => {
	const [ view, setView ] = useState< View >( () => {
		return (
			loadPersistedView( storageKey, defaultView, validFieldIds ) ??
			defaultView
		);
	} );

	useEffect( () => {
		if ( ! isViewSettingsModified( view, defaultView ) ) {
			clearPersistedView( storageKey );
			return;
		}

		savePersistedView( storageKey, view );
	}, [ defaultView, storageKey, view ] );

	const onChangeView = useCallback( ( next: SetStateAction< View > ) => {
		setView( next );
	}, [] );

	const resetToDefault = useCallback( () => {
		clearPersistedView( storageKey );
		setView( defaultView );
	}, [ defaultView, storageKey ] );

	const isModified = useMemo(
		() => isViewSettingsModified( view, defaultView ),
		[ defaultView, view ]
	);

	const onReset = isModified ? resetToDefault : false;

	return {
		view,
		onChangeView,
		onReset,
	};
};

export default usePersistedDataView;
