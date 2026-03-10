import '@testing-library/jest-dom';
import { render, screen } from '@testing-library/react';
import Dashboard from '../Dashboard';

jest.mock( '@wordpress/i18n', () => ( {
	__: ( str ) => str,
	sprintf: ( str, ...args ) => {
		let i = 0;
		return str.replace( /%[0-9]*\$?[ds%]/g, () => args[ i++ ] ?? '' );
	},
} ) );

// Track useSelect calls — select() must return an object with selector methods.
let mockSelectReturn = {};
jest.mock( '@wordpress/data', () => ( {
	useSelect: ( mapSelect ) => {
		const selectorObj = {};
		for ( const key of Object.keys( mockSelectReturn ) ) {
			selectorObj[ key ] = ( ...args ) => mockSelectReturn[ key ];
		}
		return mapSelect( () => selectorObj );
	},
	useDispatch: () => ( {} ),
} ) );

// Stub ScanPanel.
jest.mock( '../ScanPanel', () => () => <div data-testid="scan-panel" /> );

jest.mock( '@wordpress/components', () => ( {
	Button: ( { children, ...props } ) => (
		<button { ...props }>{ children }</button>
	),
	ButtonGroup: ( { children } ) => <div>{ children }</div>,
} ) );

jest.mock( '../../store', () => ( { STORE_NAME: 'flavor-link-checker' } ) );

describe( 'Dashboard', () => {
	beforeEach( () => {
		mockSelectReturn = {
			getStats: null,
		};
	} );

	it( 'renders dash values when scanStatus is null', () => {
		mockSelectReturn.getScanStatus = null;

		render( <Dashboard /> );

		const values = screen.getAllByText( '—' );
		expect( values.length ).toBe( 4 );
	} );

	it( 'renders scan status values', () => {
		mockSelectReturn.getScanStatus = {
			total_links: 500,
			broken_count: 12,
			redirect_count: 45,
			checked_links: 300,
		};

		render( <Dashboard /> );

		expect( screen.getByText( '500' ) ).toBeInTheDocument();
		expect( screen.getByText( '12' ) ).toBeInTheDocument();
		expect( screen.getByText( '45' ) ).toBeInTheDocument();
		expect( screen.getByText( '300' ) ).toBeInTheDocument();
	} );

	it( 'renders card labels', () => {
		mockSelectReturn.getScanStatus = null;

		render( <Dashboard /> );

		expect( screen.getByText( 'Total Links' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Broken' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Redirects' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Checked' ) ).toBeInTheDocument();
	} );

	it( 'includes ScanPanel', () => {
		mockSelectReturn.getScanStatus = null;

		render( <Dashboard /> );

		expect( screen.getByTestId( 'scan-panel' ) ).toBeInTheDocument();
	} );
} );
