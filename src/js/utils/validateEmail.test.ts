import validateEmail from './validateEmail';

describe( 'validateEmail', () => {
	it( 'accepts a standard address', () => {
		expect( validateEmail( 'user@example.com' ) ).toBe( true );
	} );

	it( 'rejects an invalid address', () => {
		expect( validateEmail( 'not-an-email' ) ).toBe( false );
	} );
} );
