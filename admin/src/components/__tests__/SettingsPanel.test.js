import '@testing-library/jest-dom';
import { render, screen, fireEvent } from '@testing-library/react';
import SettingsPanel from '../SettingsPanel';

jest.mock( '@wordpress/i18n', () => ( {
	__: ( str ) => str,
} ) );

const mockUpdateSettings = jest.fn();
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
		updateSettings: mockUpdateSettings,
	} ),
} ) );

jest.mock( '@wordpress/components', () => ( {
	Button: ( { children, onClick, disabled } ) => (
		<button onClick={ onClick } disabled={ disabled }>
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
	TextareaControl: ( { label, value, onChange } ) => (
		<div>
			<label htmlFor={ `mock-${ label }` }>{ label }</label>
			<textarea
				id={ `mock-${ label }` }
				value={ value }
				onChange={ ( e ) => onChange( e.target.value ) }
			/>
		</div>
	),
	ToggleControl: ( { label, checked, onChange } ) => (
		<div>
			<label htmlFor={ `mock-${ label }` }>{ label }</label>
			<input
				id={ `mock-${ label }` }
				type="checkbox"
				checked={ checked }
				onChange={ ( e ) => onChange( e.target.checked ) }
			/>
		</div>
	),
	Spinner: () => <div data-testid="spinner" />,
	Panel: ( { children } ) => <div>{ children }</div>,
	PanelBody: ( { title, children } ) => (
		<fieldset>
			<legend>{ title }</legend>
			{ children }
		</fieldset>
	),
	PanelRow: ( { children } ) => <div>{ children }</div>,
} ) );

jest.mock( '../../store', () => ( { STORE_NAME: 'flavor-link-checker' } ) );

const DEFAULT_SETTINGS = {
	scan_post_types: [ 'post', 'page' ],
	batch_size: 50,
	scan_custom_fields: false,
	check_timeout: 15,
	http_request_delay: 300,
	recheck_interval: 7,
	excluded_urls: [ 'https://ignore.com' ],
};

describe( 'SettingsPanel', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		mockSelectValues = {};
	} );

	it( 'shows spinner when settings are not loaded', () => {
		mockSelectValues.getSettings = null;
		mockSelectValues.isLoading = true;

		render( <SettingsPanel /> );

		expect( screen.getByTestId( 'spinner' ) ).toBeInTheDocument();
	} );

	it( 'renders all settings fields when loaded', () => {
		mockSelectValues.getSettings = DEFAULT_SETTINGS;
		mockSelectValues.isLoading = false;

		render( <SettingsPanel /> );

		expect( screen.getByLabelText( 'Post types to scan' ) ).toHaveValue(
			'post, page'
		);
		expect( screen.getByLabelText( 'Batch size' ) ).toHaveValue( '50' );
		expect( screen.getByLabelText( 'Timeout (seconds)' ) ).toHaveValue(
			'15'
		);
		expect(
			screen.getByLabelText( 'Delay between requests (ms)' )
		).toHaveValue( '300' );
		expect(
			screen.getByLabelText( 'Recheck interval (days)' )
		).toHaveValue( '7' );
	} );

	it( 'renders panel section titles', () => {
		mockSelectValues.getSettings = DEFAULT_SETTINGS;
		mockSelectValues.isLoading = false;

		render( <SettingsPanel /> );

		expect( screen.getByText( 'Scanning' ) ).toBeInTheDocument();
		expect( screen.getByText( 'HTTP Checks' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Exclusions' ) ).toBeInTheDocument();
	} );

	it( 'calls updateSettings on Save', () => {
		mockSelectValues.getSettings = DEFAULT_SETTINGS;
		mockSelectValues.isLoading = false;

		render( <SettingsPanel /> );

		fireEvent.click( screen.getByText( 'Save Settings' ) );

		expect( mockUpdateSettings ).toHaveBeenCalledWith(
			expect.objectContaining( {
				batch_size: 50,
				check_timeout: 15,
			} )
		);
	} );

	it( 'updates batch_size field locally on change', () => {
		mockSelectValues.getSettings = DEFAULT_SETTINGS;
		mockSelectValues.isLoading = false;

		render( <SettingsPanel /> );

		const input = screen.getByLabelText( 'Batch size' );
		fireEvent.change( input, { target: { value: '100' } } );

		expect( input ).toHaveValue( '100' );
	} );
} );
