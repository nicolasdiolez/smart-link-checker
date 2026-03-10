import '@testing-library/jest-dom';
import { render, screen, fireEvent } from '@testing-library/react';
import FilterBar from '../FilterBar';

// Mock @wordpress/i18n.
jest.mock( '@wordpress/i18n', () => ( {
	__: ( str ) => str,
	sprintf: ( str, ...args ) => {
		let i = 0;
		return str.replace( /%[0-9]*\$?[ds%]/g, () => args[ i++ ] ?? '' );
	},
} ) );

// Mock @wordpress/components to provide simple HTML elements.
jest.mock( '@wordpress/components', () => ( {
	Button: ( { children, onClick, variant, ...props } ) => (
		<button onClick={ onClick } data-variant={ variant } { ...props }>
			{ children }
		</button>
	),
	ButtonGroup: ( { children } ) => <div role="group">{ children }</div>,
} ) );

describe( 'FilterBar', () => {
	it( 'renders all 5 quick-filter buttons', () => {
		render( <FilterBar currentStatus="" onStatusChange={ jest.fn() } /> );

		expect( screen.getByText( 'All' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Broken' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Redirects' ) ).toBeInTheDocument();
		expect( screen.getByText( 'OK' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Pending' ) ).toBeInTheDocument();
	} );

	it( 'highlights the active filter as primary', () => {
		render(
			<FilterBar currentStatus="broken" onStatusChange={ jest.fn() } />
		);

		expect( screen.getByText( 'Broken' ) ).toHaveAttribute(
			'data-variant',
			'primary'
		);
		expect( screen.getByText( 'All' ) ).toHaveAttribute(
			'data-variant',
			'secondary'
		);
	} );

	it( 'calls onStatusChange with correct key on click', () => {
		const onChange = jest.fn();
		render( <FilterBar currentStatus="" onStatusChange={ onChange } /> );

		fireEvent.click( screen.getByText( 'Broken' ) );
		expect( onChange ).toHaveBeenCalledWith( 'broken' );

		fireEvent.click( screen.getByText( 'OK' ) );
		expect( onChange ).toHaveBeenCalledWith( 'ok' );
	} );

	it( 'calls onStatusChange with empty string for All', () => {
		const onChange = jest.fn();
		render(
			<FilterBar currentStatus="broken" onStatusChange={ onChange } />
		);

		fireEvent.click( screen.getByText( 'All' ) );
		expect( onChange ).toHaveBeenCalledWith( '' );
	} );
} );
