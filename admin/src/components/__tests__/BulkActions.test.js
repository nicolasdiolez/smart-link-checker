import { getLinkActions } from '../BulkActions';

jest.mock( '@wordpress/i18n', () => ( {
	__: ( str ) => str,
} ) );

describe( 'BulkActions / getLinkActions', () => {
	const handlers = {
		onRecheck: jest.fn(),
		onDelete: jest.fn(),
		onEdit: jest.fn(),
	};

	let actions;

	beforeEach( () => {
		jest.clearAllMocks();
		actions = getLinkActions( handlers );
	} );

	it( 'returns 3 actions', () => {
		expect( actions ).toHaveLength( 3 );
	} );

	it( 'edit action calls onEdit with item id', () => {
		const editAction = actions.find( ( a ) => a.id === 'edit' );
		expect( editAction.supportsBulk ).toBe( false );
		expect( editAction.isPrimary ).toBe( true );

		editAction.callback( [ { id: 42 } ] );
		expect( handlers.onEdit ).toHaveBeenCalledWith( 42 );
	} );

	it( 'edit action does nothing with multiple items', () => {
		const editAction = actions.find( ( a ) => a.id === 'edit' );
		editAction.callback( [ { id: 1 }, { id: 2 } ] );
		expect( handlers.onEdit ).not.toHaveBeenCalled();
	} );

	it( 'recheck action supports bulk and maps ids', () => {
		const recheckAction = actions.find( ( a ) => a.id === 'recheck' );
		expect( recheckAction.supportsBulk ).toBe( true );

		recheckAction.callback( [ { id: 1 }, { id: 2 }, { id: 3 } ] );
		expect( handlers.onRecheck ).toHaveBeenCalledWith( [ 1, 2, 3 ] );
	} );

	it( 'delete action supports bulk and is destructive', () => {
		const deleteAction = actions.find( ( a ) => a.id === 'delete' );
		expect( deleteAction.supportsBulk ).toBe( true );
		expect( deleteAction.isDestructive ).toBe( true );

		deleteAction.callback( [ { id: 10 } ] );
		expect( handlers.onDelete ).toHaveBeenCalledWith( [ 10 ] );
	} );
} );
