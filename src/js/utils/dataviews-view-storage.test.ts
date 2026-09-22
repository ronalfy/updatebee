import type { View } from '@wordpress/dataviews';
import {
	areViewSettingsEqual,
	getPersistableView,
	isViewSettingsModified,
	loadPersistedView,
	mergeStoredView,
	savePersistedView,
} from './dataviews-view-storage';

const defaultView: View = {
	type: 'table',
	search: '',
	page: 1,
	perPage: 25,
	fields: [ 'Name', 'Date' ],
	sort: {
		field: 'Date',
		direction: 'desc',
	},
	layout: {
		density: 'balanced',
		styles: {
			Name: { width: '100px', maxWidth: '120px' },
		},
	},
};

const createMockStorage = (): Storage => {
	const store: Record< string, string > = {};

	return {
		get length() {
			return Object.keys( store ).length;
		},
		clear() {
			Object.keys( store ).forEach( ( key ) => {
				delete store[ key ];
			} );
		},
		getItem( key: string ) {
			return Object.prototype.hasOwnProperty.call( store, key )
				? store[ key ]
				: null;
		},
		key( index: number ) {
			return Object.keys( store )[ index ] ?? null;
		},
		removeItem( key: string ) {
			delete store[ key ];
		},
		setItem( key: string, value: string ) {
			store[ key ] = value;
		},
	};
};

describe( 'dataviews-view-storage', () => {
	it( 'omits search, page, and layout.styles from persistable view', () => {
		const view: View = {
			...defaultView,
			search: 'query',
			page: 3,
			perPage: 50,
		};

		expect( getPersistableView( view ) ).toEqual( {
			type: 'table',
			perPage: 50,
			fields: [ 'Name', 'Date' ],
			sort: {
				field: 'Date',
				direction: 'desc',
			},
			layout: {
				density: 'balanced',
			},
		} );
	} );

	it( 'treats page-only changes as unmodified settings', () => {
		const withPage: View = {
			...defaultView,
			page: 4,
		};

		expect( isViewSettingsModified( withPage, defaultView ) ).toBe( false );
		expect( areViewSettingsEqual( withPage, defaultView ) ).toBe( true );
	} );

	it( 'detects settings changes such as perPage', () => {
		const modified: View = {
			...defaultView,
			perPage: 50,
		};

		expect( isViewSettingsModified( modified, defaultView ) ).toBe( true );
	} );

	it( 'merges stored view with defaults and reapplies column styles', () => {
		const merged = mergeStoredView(
			{
				perPage: 50,
				search: 'keep-me-out',
				page: 9,
				layout: {
					density: 'compact',
				},
			},
			defaultView,
			defaultView.fields ?? []
		);

		expect( merged.search ).toBe( '' );
		expect( merged.page ).toBe( 1 );
		expect( merged.perPage ).toBe( 50 );
		expect( merged.layout ).toEqual( {
			density: 'compact',
			styles: defaultView.layout?.styles,
		} );
	} );

	it( 'falls back when stored fields are invalid', () => {
		const merged = mergeStoredView(
			{
				fields: [ 'Unknown' ],
				sort: { field: 'Unknown', direction: 'asc' },
			},
			defaultView,
			defaultView.fields ?? []
		);

		expect( merged.fields ).toEqual( defaultView.fields );
		expect( merged.sort ).toEqual( defaultView.sort );
	} );

	it( 'round-trips through mock localStorage', () => {
		const storage = createMockStorage();
		const key = 'updatebee:test';
		const modified: View = {
			...defaultView,
			perPage: 50,
			search: 'session',
			page: 2,
		};

		savePersistedView( key, modified, storage );
		const loaded = loadPersistedView(
			key,
			defaultView,
			defaultView.fields ?? [],
			storage
		);

		expect( loaded?.perPage ).toBe( 50 );
		expect( loaded?.search ).toBe( '' );
		expect( loaded?.page ).toBe( 1 );
		const loadedLayout = loaded?.layout as typeof defaultView.layout;
		expect( loadedLayout?.styles ).toEqual( defaultView.layout?.styles );
	} );

	it( 'returns null for corrupt JSON', () => {
		const storage = createMockStorage();
		storage.setItem( 'updatebee:bad', '{not-json' );

		expect(
			loadPersistedView(
				'updatebee:bad',
				defaultView,
				defaultView.fields ?? [],
				storage
			)
		).toBeNull();
	} );
} );
