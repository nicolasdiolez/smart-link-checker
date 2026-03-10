import {
	getLinks,
	getTotal,
	getTotalPages,
	getScanStatus,
	getSettings,
	getCurrentLink,
	isLoading,
} from '../selectors';

const STATE = {
	links: [ { id: 1, url: 'https://example.com' } ],
	total: 42,
	totalPages: 2,
	scanStatus: { status: 'running', scanned_posts: 10 },
	settings: { check_timeout: 15 },
	currentLink: { id: 5, url: 'https://test.com' },
	isLoading: { links: true, scan: false },
};

describe( 'store/selectors', () => {
	it( 'getLinks returns links array', () => {
		expect( getLinks( STATE ) ).toBe( STATE.links );
	} );

	it( 'getTotal returns total count', () => {
		expect( getTotal( STATE ) ).toBe( 42 );
	} );

	it( 'getTotalPages returns total pages', () => {
		expect( getTotalPages( STATE ) ).toBe( 2 );
	} );

	it( 'getScanStatus returns scan status object', () => {
		expect( getScanStatus( STATE ) ).toBe( STATE.scanStatus );
	} );

	it( 'getSettings returns settings object', () => {
		expect( getSettings( STATE ) ).toBe( STATE.settings );
	} );

	it( 'getCurrentLink returns current link', () => {
		expect( getCurrentLink( STATE ) ).toBe( STATE.currentLink );
	} );

	it( 'isLoading returns true for loading key', () => {
		expect( isLoading( STATE, 'links' ) ).toBe( true );
	} );

	it( 'isLoading returns false for non-loading key', () => {
		expect( isLoading( STATE, 'scan' ) ).toBe( false );
	} );

	it( 'isLoading returns false for unknown key', () => {
		expect( isLoading( STATE, 'unknown' ) ).toBe( false );
	} );
} );
