/**
 * LinkChecker admin entry point.
 *
 * @package
 * @since   1.0.0
 */

import { createRoot } from '@wordpress/element';
import './store';
import './index.scss';
import App from './App';

const container = document.getElementById( 'flc-root' );
if ( container ) {
	const root = createRoot( container );
	root.render( <App /> );
}
