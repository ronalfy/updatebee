import { readListFilter, writeListFilter } from './list-filter-param';

const ALLOWED = [ '', 'all', 'blocked', 'org-plugin' ] as const;

describe( 'readListFilter', () => {
	it( 'returns an allowed filter value', () => {
		expect(
			readListFilter(
				ALLOWED,
				'?page=updatebee&tab=plugins&filter=blocked'
			)
		).toBe( 'blocked' );
	} );

	it( 'returns an empty string when the parameter is missing', () => {
		expect( readListFilter( ALLOWED, '?page=updatebee&tab=plugins' ) ).toBe(
			''
		);
	} );

	it( 'returns an empty string for an unknown filter', () => {
		expect(
			readListFilter( ALLOWED, '?page=updatebee&tab=themes&filter=nope' )
		).toBe( '' );
	} );
} );

describe( 'writeListFilter', () => {
	const originalHref = window.location.href;

	afterEach( () => {
		window.history.replaceState( null, '', originalHref );
	} );

	it( 'sets the filter and keeps existing query arguments', () => {
		window.history.replaceState(
			null,
			'',
			'/tools.php?page=updatebee&tab=plugins'
		);

		writeListFilter( 'org-plugin' );

		const params = new URLSearchParams( window.location.search );
		expect( params.get( 'page' ) ).toBe( 'updatebee' );
		expect( params.get( 'tab' ) ).toBe( 'plugins' );
		expect( params.get( 'filter' ) ).toBe( 'org-plugin' );
	} );

	it( 'removes the filter when the value is empty', () => {
		window.history.replaceState(
			null,
			'',
			'/tools.php?page=updatebee&tab=themes&filter=blocked'
		);

		writeListFilter( '' );

		const params = new URLSearchParams( window.location.search );
		expect( params.get( 'page' ) ).toBe( 'updatebee' );
		expect( params.get( 'tab' ) ).toBe( 'themes' );
		expect( params.get( 'filter' ) ).toBeNull();
	} );
} );
