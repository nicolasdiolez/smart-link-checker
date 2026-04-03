/**
 * Store action creators — plain actions and thunks.
 *
 * @package
 * @since   1.0.0
 */

import { __, sprintf } from '@wordpress/i18n';
import {
	fetchLinksFromApi,
	fetchLinkFromApi,
	updateLinkApi,
	deleteLinkApi,
	recheckLinkApi,
	bulkActionApi,
	fetchStatsApi,
	fetchScanStatusApi,
	startScanApi,
	cancelScanApi,
	resumeScanApi,
	resetScanApi,
	fetchSettingsApi,
	updateSettingsApi,
} from '../utils/api';

/* ── Plain actions ──────────────────────────────────────────────── */

export function setLinks(items, total, totalPages) {
	return { type: 'SET_LINKS', items, total, totalPages };
}

export function setScanStatus(status) {
	return { type: 'SET_SCAN_STATUS', status };
}

export function setSettings(settings) {
	return { type: 'SET_SETTINGS', settings };
}

export function setStats(stats) {
	return { type: 'SET_STATS', stats };
}

export function setCurrentLink(link) {
	return { type: 'SET_CURRENT_LINK', link };
}

export function setLoading(key, value) {
	return { type: 'SET_LOADING', key, value };
}

/* ── Thunk actions ──────────────────────────────────────────────── */

export function fetchLinks(params = {}) {
	return async ({ dispatch }) => {
		dispatch(setLoading('links', true));
		try {
			const { items, total, totalPages } =
				await fetchLinksFromApi(params);
			dispatch(setLinks(items, total, totalPages));
		} catch {
			dispatch(setLinks([], 0, 0));
		} finally {
			dispatch(setLoading('links', false));
		}
	};
}

export function fetchLink(id) {
	return async ({ dispatch }) => {
		dispatch(setLoading('currentLink', true));
		try {
			const link = await fetchLinkFromApi(id);
			dispatch(setCurrentLink(link));
		} finally {
			dispatch(setLoading('currentLink', false));
		}
	};
}

export function fetchScanStatus() {
	return async ({ dispatch }) => {
		try {
			const status = await fetchScanStatusApi();
			dispatch(setScanStatus(status));
		} catch {
			// Silently ignore — scan status is non-critical.
		}
	};
}

export function startScan(scanType = 'full') {
	return async ({ dispatch, registry }) => {
		dispatch(setLoading('scan', true));
		try {
			const result = await startScanApi(scanType);
			dispatch(setScanStatus(result.status || result));
			registry
				.dispatch('core/notices')
				.createSuccessNotice(
					__('Scan started.', 'muri-link-tracker'),
					{ type: 'snackbar' }
				);
		} catch (error) {
			registry
				.dispatch('core/notices')
				.createErrorNotice(
					error.message ||
					__('Failed to start scan.', 'muri-link-tracker'),
					{ type: 'snackbar' }
				);
		} finally {
			dispatch(setLoading('scan', false));
		}
	};
}

export function cancelScan() {
	return async ({ dispatch, registry }) => {
		try {
			const status = await cancelScanApi();
			dispatch(setScanStatus(status));
			registry
				.dispatch('core/notices')
				.createSuccessNotice(
					__('Scan cancelled.', 'muri-link-tracker'),
					{ type: 'snackbar' }
				);
		} catch {
			// Silently ignore.
		}
	};
}

export function resumeScan() {
	return async ({ dispatch, registry }) => {
		dispatch(setLoading('scan', true));
		try {
			const result = await resumeScanApi();
			const status = result.status || result;
			dispatch(setScanStatus(status));
			registry
				.dispatch('core/notices')
				.createSuccessNotice(
					__('Scan resumed.', 'muri-link-tracker'),
					{ type: 'snackbar' }
				);
		} catch (error) {
			registry
				.dispatch('core/notices')
				.createErrorNotice(
					error.message ||
					__('Failed to resume scan.', 'muri-link-tracker'),
					{ type: 'snackbar' }
				);
		} finally {
			dispatch(setLoading('scan', false));
		}
	};
}

export function resetScan() {
	return async ({ dispatch, registry }) => {
		dispatch(setLoading('scan', true));
		try {
			const status = await resetScanApi();
			dispatch(setScanStatus(status));
			dispatch(setLinks([], 0, 0)); // Clear links from store.
			dispatch(fetchStats()); // Refresh stats (should be all 0).
			registry
				.dispatch('core/notices')
				.createSuccessNotice(
					__('Scan data reset.', 'muri-link-tracker'),
					{ type: 'snackbar' }
				);
		} catch (error) {
			registry
				.dispatch('core/notices')
				.createErrorNotice(
					error.message ||
					__('Failed to reset scan.', 'muri-link-tracker'),
					{ type: 'snackbar' }
				);
		} finally {
			dispatch(setLoading('scan', false));
		}
	};
}

export function fetchStats() {
	return async ({ dispatch }) => {
		try {
			const stats = await fetchStatsApi();
			dispatch(setStats(stats));
		} catch {
			// Silently ignore — stats are non-critical.
		}
	};
}

export function fetchSettings() {
	return async ({ dispatch }) => {
		dispatch(setLoading('settings', true));
		try {
			const settings = await fetchSettingsApi();
			dispatch(setSettings(settings));
		} finally {
			dispatch(setLoading('settings', false));
		}
	};
}

export function updateSettings(data) {
	return async ({ dispatch, registry }) => {
		dispatch(setLoading('settings', true));
		try {
			const settings = await updateSettingsApi(data);
			dispatch(setSettings(settings));
			registry
				.dispatch('core/notices')
				.createSuccessNotice(
					__('Settings saved.', 'muri-link-tracker'),
					{ type: 'snackbar' }
				);
		} catch (error) {
			registry
				.dispatch('core/notices')
				.createErrorNotice(
					error.message ||
					__('Failed to save settings.', 'muri-link-tracker'),
					{ type: 'snackbar' }
				);
		} finally {
			dispatch(setLoading('settings', false));
		}
	};
}

export function updateLink(id, data) {
	return async ({ registry }) => {
		try {
			const result = await updateLinkApi(id, data);
			registry
				.dispatch('core/notices')
				.createSuccessNotice(
					__('Link updated.', 'muri-link-tracker'),
					{ type: 'snackbar' }
				);
			return result;
		} catch (error) {
			registry
				.dispatch('core/notices')
				.createErrorNotice(
					error.message ||
					__('Failed to update link.', 'muri-link-tracker'),
					{ type: 'snackbar' }
				);
			return null;
		}
	};
}

export function deleteLink(id) {
	return async ({ registry }) => {
		try {
			await deleteLinkApi(id);
			registry
				.dispatch('core/notices')
				.createSuccessNotice(
					__('Link deleted.', 'muri-link-tracker'),
					{ type: 'snackbar' }
				);
			return true;
		} catch (error) {
			registry
				.dispatch('core/notices')
				.createErrorNotice(
					error.message ||
					__('Failed to delete link.', 'muri-link-tracker'),
					{ type: 'snackbar' }
				);
			return false;
		}
	};
}

export function recheckLink(id) {
	return async ({ registry }) => {
		try {
			await recheckLinkApi(id);
			registry
				.dispatch('core/notices')
				.createSuccessNotice(
					__('Recheck scheduled.', 'muri-link-tracker'),
					{ type: 'snackbar' }
				);
			return true;
		} catch (error) {
			registry
				.dispatch('core/notices')
				.createErrorNotice(
					error.message ||
					__(
						'Failed to schedule recheck.',
						'muri-link-tracker'
					),
					{ type: 'snackbar' }
				);
			return false;
		}
	};
}

export function bulkAction(action, ids) {
	return async ({ registry }) => {
		try {
			const result = await bulkActionApi(action, ids);
			registry.dispatch('core/notices').createSuccessNotice(
				sprintf(
					/* translators: %d: number of links processed. */
					__('%d link(s) processed.', 'muri-link-tracker'),
					result.success
				),
				{ type: 'snackbar' }
			);
			return result;
		} catch (error) {
			registry
				.dispatch('core/notices')
				.createErrorNotice(
					error.message ||
					__('Bulk action failed.', 'muri-link-tracker'),
					{ type: 'snackbar' }
				);
			return null;
		}
	};
}

export function refreshData() {
	return async ({ dispatch }) => {
		dispatch(fetchStats());
		dispatch(fetchScanStatus());
	};
}
