/**
 * Store resolvers — auto-fetch on first selector access.
 *
 * @package
 * @since   1.0.0
 */

import {
	fetchStatsApi,
	fetchScanStatusApi,
	fetchSettingsApi,
} from '../utils/api';

export function getScanStatus() {
	return async ( { dispatch } ) => {
		try {
			const status = await fetchScanStatusApi();
			dispatch( { type: 'SET_SCAN_STATUS', status } );
		} catch {
			// Silently ignore — will retry on next access.
		}
	};
}

export function getStats() {
	return async ( { dispatch } ) => {
		try {
			const stats = await fetchStatsApi();
			dispatch( { type: 'SET_STATS', stats } );
		} catch {
			// Silently ignore — will retry on next access.
		}
	};
}

export function getSettings() {
	return async ( { dispatch } ) => {
		dispatch( { type: 'SET_LOADING', key: 'settings', value: true } );
		try {
			const settings = await fetchSettingsApi();
			dispatch( { type: 'SET_SETTINGS', settings } );
		} finally {
			dispatch( { type: 'SET_LOADING', key: 'settings', value: false } );
		}
	};
}
