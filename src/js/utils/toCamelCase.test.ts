import toCamelCase from './toCamelCase';

describe( 'toCamelCase', () => {
	it( 'converts underscore fields to camelCase', () => {
		expect( toCamelCase( 'major_updates' ) ).toBe( 'majorUpdates' );
	} );

	it( 'leaves a single word lowercase', () => {
		expect( toCamelCase( 'dashboard' ) ).toBe( 'dashboard' );
	} );

	it( 'handles multiple separators', () => {
		expect( toCamelCase( 'auto_update_plugins' ) ).toBe(
			'autoUpdatePlugins'
		);
	} );
} );
