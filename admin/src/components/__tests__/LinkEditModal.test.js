import '@testing-library/jest-dom';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import LinkEditModal from '../LinkEditModal';

jest.mock( '@wordpress/i18n', () => ( {
	__: ( str ) => str,
} ) );

const mockFetchLink = jest.fn();
const mockUpdateLink = jest.fn();
const mockSetCurrentLink = jest.fn();

let mockSelectValues = {};

jest.mock( '@wordpress/data', () => ( {
	useSelect: ( mapSelect ) => {
		const selectorObj = {};
		for ( const key of Object.keys( mockSelectValues ) ) {
			selectorObj[ key ] = () => mockSelectValues[ key ];
		}
		return mapSelect( () => selectorObj );
	},
	useDispatch: () => ( {
		fetchLink: mockFetchLink,
		updateLink: mockUpdateLink,
		setCurrentLink: mockSetCurrentLink,
	} ),
} ) );

jest.mock( '@wordpress/components', () => ( {
	Modal: ( { title, onRequestClose, children } ) => (
		<div data-testid="modal" role="dialog" aria-label={ title }>
			<h2>{ title }</h2>
			<button onClick={ onRequestClose } data-testid="close-modal">
				Close
			</button>
			{ children }
		</div>
	),
	Button: ( { children, onClick, disabled, variant } ) => (
		<button
			onClick={ onClick }
			disabled={ disabled }
			data-variant={ variant }
		>
			{ children }
		</button>
	),
	TextControl: ( { label, value, onChange, help } ) => (
		<div>
			<label htmlFor={ `mock-${ label }` }>{ label }</label>
			<input
				id={ `mock-${ label }` }
				value={ value }
				onChange={ ( e ) => onChange( e.target.value ) }
			/>
			{ help && <span>{ help }</span> }
		</div>
	),
	Spinner: () => <div data-testid="spinner" />,
} ) );

jest.mock( '../../store', () => ( { STORE_NAME: 'sentinel-link-checker' } ) );

const MOCK_LINK = {
	id: 42,
	url: 'https://example.com/old',
	instances: [
		{
			id: 1,
			postTitle: 'My Post',
			postEditUrl: '/wp-admin/post.php?post=10&action=edit',
			anchorText: 'Click here',
			relNofollow: true,
			relSponsored: false,
			relUgc: false,
		},
	],
};

describe( 'LinkEditModal', () => {
	const onClose = jest.fn();

	beforeEach( () => {
		jest.clearAllMocks();
		mockSelectValues = {};
	} );

	it( 'calls fetchLink on mount', () => {
		mockSelectValues.getCurrentLink = null;
		mockSelectValues.isLoading = true;

		render( <LinkEditModal linkId={ 42 } onClose={ onClose } /> );

		expect( mockFetchLink ).toHaveBeenCalledWith( 42 );
	} );

	it( 'shows spinner while loading', () => {
		mockSelectValues.getCurrentLink = null;
		mockSelectValues.isLoading = true;

		render( <LinkEditModal linkId={ 42 } onClose={ onClose } /> );

		expect( screen.getByTestId( 'spinner' ) ).toBeInTheDocument();
	} );

	it( 'renders form when link is loaded', () => {
		mockSelectValues.getCurrentLink = MOCK_LINK;
		mockSelectValues.isLoading = false;

		render( <LinkEditModal linkId={ 42 } onClose={ onClose } /> );

		expect( screen.getByLabelText( 'URL' ) ).toHaveValue(
			'https://example.com/old'
		);
		expect( screen.getByLabelText( 'Rel attribute' ) ).toHaveValue(
			'nofollow'
		);
	} );

	it( 'displays instances with post titles', () => {
		mockSelectValues.getCurrentLink = MOCK_LINK;
		mockSelectValues.isLoading = false;

		render( <LinkEditModal linkId={ 42 } onClose={ onClose } /> );

		expect( screen.getByText( 'My Post' ) ).toBeInTheDocument();
		expect( screen.getByText( /Click here/ ) ).toBeInTheDocument();
	} );

	it( 'calls onClose(false) when Cancel is clicked', () => {
		mockSelectValues.getCurrentLink = MOCK_LINK;
		mockSelectValues.isLoading = false;

		render( <LinkEditModal linkId={ 42 } onClose={ onClose } /> );

		fireEvent.click( screen.getByText( 'Cancel' ) );
		expect( onClose ).toHaveBeenCalledWith( false );
	} );

	it( 'sends rel even when URL unchanged on Save', async () => {
		mockSelectValues.getCurrentLink = MOCK_LINK;
		mockSelectValues.isLoading = false;
		mockUpdateLink.mockResolvedValue( { id: 42 } );

		render( <LinkEditModal linkId={ 42 } onClose={ onClose } /> );

		fireEvent.click( screen.getByText( 'Save' ) );

		await waitFor( () => {
			expect( mockUpdateLink ).toHaveBeenCalledWith(
				42,
				expect.objectContaining( { rel: 'nofollow' } )
			);
		} );
	} );

	it( 'calls updateLink when URL is changed and Save is clicked', async () => {
		mockSelectValues.getCurrentLink = MOCK_LINK;
		mockSelectValues.isLoading = false;
		mockUpdateLink.mockResolvedValue( { id: 42 } );

		render( <LinkEditModal linkId={ 42 } onClose={ onClose } /> );

		fireEvent.change( screen.getByLabelText( 'URL' ), {
			target: { value: 'https://example.com/new' },
		} );

		fireEvent.click( screen.getByText( 'Save' ) );

		await waitFor( () => {
			expect( mockUpdateLink ).toHaveBeenCalledWith(
				42,
				expect.objectContaining( {
					url: 'https://example.com/new',
				} )
			);
		} );
	} );

	it( 'calls onClose(true) after successful save', async () => {
		mockSelectValues.getCurrentLink = MOCK_LINK;
		mockSelectValues.isLoading = false;
		mockUpdateLink.mockResolvedValue( { id: 42 } );

		render( <LinkEditModal linkId={ 42 } onClose={ onClose } /> );

		fireEvent.change( screen.getByLabelText( 'URL' ), {
			target: { value: 'https://example.com/new' },
		} );

		fireEvent.click( screen.getByText( 'Save' ) );

		await waitFor( () => {
			expect( onClose ).toHaveBeenCalledWith( true );
		} );
	} );
} );
