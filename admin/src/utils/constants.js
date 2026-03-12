/**
 * Shared constants for the Smart Link Checker admin UI.
 *
 * @package
 * @since   1.0.0
 */

import { __ } from '@wordpress/i18n';

export const STORE_NAME = 'smart-link-checker';
export const API_NAMESPACE = '/smart-link-checker/v1';

export const STATUS_LABELS = {
	pending: __( 'Pending', 'smart-link-checker' ),
	ok: __( 'OK', 'smart-link-checker' ),
	redirect: __( 'Redirect', 'smart-link-checker' ),
	broken: __( 'Broken', 'smart-link-checker' ),
	error: __( 'Error', 'smart-link-checker' ),
	timeout: __( 'Timeout', 'smart-link-checker' ),
	skipped: __( 'Skipped', 'smart-link-checker' ),
};

export const STATUS_COLORS = {
	pending: '#757575',
	ok: '#00a32a',
	redirect: '#dba617',
	broken: '#d63638',
	error: '#d63638',
	timeout: '#996800',
	skipped: '#757575',
};

export const LINK_TYPE_OPTIONS = [
	{ value: 'external', label: __( 'External', 'smart-link-checker' ) },
	{ value: 'internal', label: __( 'Internal', 'smart-link-checker' ) },
];

export const REL_OPTIONS = [
	{ value: 'nofollow', label: __( 'Nofollow', 'smart-link-checker' ) },
	{ value: 'sponsored', label: __( 'Sponsored', 'smart-link-checker' ) },
	{ value: 'ugc', label: __( 'UGC', 'smart-link-checker' ) },
	{ value: 'dofollow', label: __( 'Dofollow', 'smart-link-checker' ) },
];

export const AFFILIATE_TYPE_OPTIONS = [
	{ value: 'all', label: __( 'All Affiliates', 'smart-link-checker' ) },
	{
		value: 'cloaked',
		label: __( 'Cloaked (Internal)', 'smart-link-checker' ),
	},
	{
		value: 'direct',
		label: __( 'Direct (External)', 'smart-link-checker' ),
	},
];
