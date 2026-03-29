/**
 * App component — Root of the Sentinel Link Checker admin interface.
 *
 * @package
 * @since   1.0.0
 */

import { useState, useCallback } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { TabPanel, SnackbarList } from '@wordpress/components';
import { store as noticesStore } from '@wordpress/notices';
import { __ } from '@wordpress/i18n';
import Dashboard from './components/Dashboard';
import LinkTable from './components/LinkTable';
import LinkEditModal from './components/LinkEditModal';
import SettingsPanel from './components/SettingsPanel';
import ErrorBoundary from './components/ErrorBoundary';
import { STORE_NAME } from './store';

const TABS = [
	{ name: 'dashboard', title: __( 'Dashboard', 'sentinel-link-checker' ) },
	{ name: 'links', title: __( 'Links', 'sentinel-link-checker' ) },
	{ name: 'settings', title: __( 'Settings', 'sentinel-link-checker' ) },
];

const App = () => {
	const [ editLinkId, setEditLinkId ] = useState( null );
	const { fetchLinks } = useDispatch( STORE_NAME );

	const notices = useSelect(
		( select ) => select( noticesStore ).getNotices(),
		[]
	);
	const { removeNotice } = useDispatch( noticesStore );
	const snackbarNotices = notices.filter( ( n ) => n.type === 'snackbar' );

	const handleEditLink = useCallback( ( id ) => {
		setEditLinkId( id );
	}, [] );

	const handleCloseModal = useCallback(
		( shouldRefresh ) => {
			setEditLinkId( null );
			if ( shouldRefresh ) {
				fetchLinks();
			}
		},
		[ fetchLinks ]
	);

	return (
		<ErrorBoundary>
			<div className="flc-app">
				<h1>{ __( 'Sentinel Link Checker', 'sentinel-link-checker' ) }</h1>

				<TabPanel tabs={ TABS }>
					{ ( tab ) => {
						if ( tab.name === 'dashboard' ) {
							return <Dashboard />;
						}
						if ( tab.name === 'links' ) {
							return <LinkTable onEditLink={ handleEditLink } />;
						}
						if ( tab.name === 'settings' ) {
							return <SettingsPanel />;
						}
						return null;
					} }
				</TabPanel>

				{ editLinkId && (
					<LinkEditModal
						linkId={ editLinkId }
						onClose={ handleCloseModal }
					/>
				) }

				<SnackbarList
					notices={ snackbarNotices }
					onRemove={ removeNotice }
					className="flc-snackbar-list"
				/>
			</div>
		</ErrorBoundary>
	);
};

export default App;
