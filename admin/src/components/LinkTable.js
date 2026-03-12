/**
 * LinkTable — DataViews-powered links table with server-side pagination.
 *
 * @package
 * @since   1.0.0
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { DataViews } from '@wordpress/dataviews';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import { STORE_NAME } from '../store';
import {
	API_NAMESPACE,
	STATUS_LABELS,
	STATUS_COLORS,
} from '../utils/constants';
import { getLinkActions } from './BulkActions';
import FilterBar from './FilterBar';

const ORDERBY_MAP = {
	url: 'url',
	httpStatus: 'http_status',
	lastChecked: 'last_checked',
	createdAt: 'created_at',
};

const DEFAULT_VIEW = {
	type: 'table',
	search: '',
	filters: [],
	sort: { field: 'createdAt', direction: 'desc' },
	page: 1,
	perPage: 25,
	layout: {
		density: 'balanced',
	},
	fields: [
		'url',
		'httpStatus',
		'statusCategory',
		'isExternal',
		'lastChecked',
	],
};

/**
 * Converts DataViews view state to REST API query parameters.
 *
 * @param {Object} view   DataViews view state.
 * @param {string} status Quick-filter status (from FilterBar).
 * @return {Object} API query parameters.
 */
function viewToApiParams( view, status ) {
	const params = {
		page: view.page,
		perPage: view.perPage,
	};

	if ( view.search ) {
		params.search = view.search;
	}

	if ( view.sort ) {
		params.orderby = ORDERBY_MAP[ view.sort.field ] || 'created_at';
		params.order = view.sort.direction;
	}

	// Quick-filter status takes precedence.
	if ( status ) {
		params.status = status;
	}

	// DataViews inline filters.
	for ( const filter of view.filters || [] ) {
		if ( filter.field === 'statusCategory' && filter.value && ! status ) {
			params.status = filter.value;
		}
		if ( filter.field === 'isExternal' && filter.value !== undefined ) {
			params.linkType = filter.value === 'true' ? 'external' : 'internal';
		}
	}

	return params;
}

const LinkTable = ( { onEditLink } ) => {
	const [ view, setView ] = useState( DEFAULT_VIEW );
	const [ quickStatus, setQuickStatus ] = useState( '' );

	const { links, total, totalPages, loading, settings } = useSelect(
		( select ) => ( {
			links: select( STORE_NAME ).getLinks(),
			total: select( STORE_NAME ).getTotal(),
			totalPages: select( STORE_NAME ).getTotalPages(),
			loading: select( STORE_NAME ).isLoading( 'links' ),
			settings: select( STORE_NAME ).getSettings(),
		} ),
		[]
	);

	const { fetchLinks, deleteLink, recheckLink, bulkAction, updateSettings } =
		useDispatch( STORE_NAME );

	// Fetch links whenever view or quick-filter changes.
	useEffect( () => {
		fetchLinks( viewToApiParams( view, quickStatus ) );
	}, [ view, quickStatus, fetchLinks ] );

	// Sync local density with server settings and persist changes.
	useEffect( () => {
		const serverDensity = settings?.density;
		const localDensity = view.layout?.density;

		if ( ! serverDensity || ! localDensity ) {
			return;
		}

		// Update local state if server has a different value (initial load or external change).
		if ( serverDensity !== localDensity ) {
			// We only want to update local state from server if the server change 
			// isn't a result of our own recent update.
			// DataViews state updates are often asynchronous.
			setView( ( prev ) => ( {
				...prev,
				layout: { ...prev.layout, density: serverDensity },
			} ) );
		}
	}, [ settings?.density ] );

	// Persist changes only when DataViews triggers it.
	const handleViewChange = useCallback( ( newView ) => {
		const oldDensity = view.layout?.density;
		const newDensity = newView.layout?.density;

		setView( newView );

		if ( newDensity && newDensity !== oldDensity ) {
			updateSettings( { density: newDensity } );
		}
	}, [ view.layout?.density, updateSettings ] );

	const handleStatusChange = useCallback( ( status ) => {
		setQuickStatus( status );
		setView( ( prev ) => ( { ...prev, page: 1 } ) );
	}, [] );

	const handleRecheck = useCallback(
		async ( ids ) => {
			if ( ids.length === 1 ) {
				await recheckLink( ids[ 0 ] );
			} else {
				await bulkAction( 'recheck', ids );
			}
			fetchLinks( viewToApiParams( view, quickStatus ) );
		},
		[ recheckLink, bulkAction, fetchLinks, view, quickStatus ]
	);

	const handleDelete = useCallback(
		async ( ids ) => {
			if ( ids.length === 1 ) {
				await deleteLink( ids[ 0 ] );
			} else {
				await bulkAction( 'delete', ids );
			}
			fetchLinks( viewToApiParams( view, quickStatus ) );
		},
		[ deleteLink, bulkAction, fetchLinks, view, quickStatus ]
	);

	const handleExport = useCallback( () => {
		const apiParams = viewToApiParams( view, quickStatus );
		const query = {};
		if ( apiParams.status ) {
			query.status = apiParams.status;
		}
		if ( apiParams.search ) {
			query.search = apiParams.search;
		}
		if ( apiParams.orderby ) {
			query.orderby = apiParams.orderby;
		}
		if ( apiParams.order ) {
			query.order = apiParams.order;
		}
		if ( apiParams.linkType ) {
			query.link_type = apiParams.linkType;
		}
		query._wpnonce = window.wpApiSettings?.nonce || '';
		const url = addQueryArgs(
			`${
				window.wpApiSettings?.root || '/wp-json/'
			}${ API_NAMESPACE.replace( /^\//, '' ) }/links/export`,
			query
		);
		window.open( url, '_blank' );
	}, [ view, quickStatus ] );

	const fields = [
		{
			id: 'url',
			label: __( 'URL', 'smart-link-checker' ),
			enableSorting: true,
			enableGlobalSearch: true,
			render: ( { item } ) => (
				<a
					href={ item.url }
					target="_blank"
					rel="noopener noreferrer"
					className="flc-link-url"
					title={ item.url }
				>
					{ item.url.length > 60
						? item.url.substring( 0, 60 ) + '…'
						: item.url }
				</a>
			),
		},
		{
			id: 'httpStatus',
			label: __( 'HTTP', 'smart-link-checker' ),
			enableSorting: true,
			render: ( { item } ) => (
				<span
					className={ `flc-http-status flc-http-status--${ item.statusCategory }` }
				>
					{ item.httpStatus || '—' }
				</span>
			),
		},
		{
			id: 'statusCategory',
			label: __( 'Status', 'smart-link-checker' ),
			enableSorting: false,
			elements: Object.entries( STATUS_LABELS ).map(
				( [ value, label ] ) => ( { value, label } )
			),
			filterBy: { operators: [ 'is' ] },
			render: ( { item } ) => (
				<span
					className={ `flc-badge flc-badge--${ item.statusCategory }` }
					style={ { color: STATUS_COLORS[ item.statusCategory ] } }
				>
					{ STATUS_LABELS[ item.statusCategory ] ||
						item.statusCategory }
				</span>
			),
		},
		{
			id: 'isExternal',
			label: __( 'Type', 'smart-link-checker' ),
			elements: [
				{
					value: 'true',
					label: __( 'External', 'smart-link-checker' ),
				},
				{
					value: 'false',
					label: __( 'Internal', 'smart-link-checker' ),
				},
			],
			filterBy: { operators: [ 'is' ] },
			render: ( { item } ) => (
				<span className="flc-link-type">
					{ item.isExternal
						? __( 'External', 'smart-link-checker' )
						: __( 'Internal', 'smart-link-checker' ) }
				</span>
			),
		},
		{
			id: 'lastChecked',
			label: __( 'Last Checked', 'smart-link-checker' ),
			enableSorting: true,
			render: ( { item } ) =>
				item.lastChecked
					? new Date( item.lastChecked ).toLocaleDateString()
					: '—',
		},
	];

	const actions = getLinkActions( {
		onRecheck: handleRecheck,
		onDelete: handleDelete,
		onEdit: onEditLink,
	} );

	return (
		<div className={ `flc-link-table is-density-${ view.layout?.density || 'balanced' }` }>
			<div className="flc-link-table__toolbar">
				<FilterBar
					currentStatus={ quickStatus }
					onStatusChange={ handleStatusChange }
				/>
				<Button variant="secondary" onClick={ handleExport }>
					{ __( 'Export CSV', 'smart-link-checker' ) }
				</Button>
			</div>

			<DataViews
				data={ links }
				fields={ fields }
				view={ view }
				onChangeView={ handleViewChange }
				paginationInfo={ { totalItems: total, totalPages } }
				actions={ actions }
				getItemId={ ( item ) => item.id }
				isLoading={ loading }
				defaultLayouts={ { table: {} } }
			/>
		</div>
	);
};

export default LinkTable;
