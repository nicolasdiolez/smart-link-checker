/**
 * Shared constants for the Smart Link Checker admin UI.
 *
 * @package
 * @since   1.0.0
 */

import { __ } from '@wordpress/i18n';

export const STORE_NAME = 'flavor-link-checker';
export const API_NAMESPACE = '/flavor-link-checker/v1';

export const STATUS_LABELS = {
	pending: __( 'Pending', 'flavor-link-checker' ),
	ok: __( 'OK', 'flavor-link-checker' ),
	redirect: __( 'Redirect', 'flavor-link-checker' ),
	broken: __( 'Broken', 'flavor-link-checker' ),
	error: __( 'Error', 'flavor-link-checker' ),
	timeout: __( 'Timeout', 'flavor-link-checker' ),
	skipped: __( 'Skipped', 'flavor-link-checker' ),
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
	{ value: 'external', label: __( 'External', 'flavor-link-checker' ) },
	{ value: 'internal', label: __( 'Internal', 'flavor-link-checker' ) },
];

export const REL_OPTIONS = [
	{ value: 'nofollow', label: __( 'Nofollow', 'flavor-link-checker' ) },
	{ value: 'sponsored', label: __( 'Sponsored', 'flavor-link-checker' ) },
	{ value: 'ugc', label: __( 'UGC', 'flavor-link-checker' ) },
	{ value: 'dofollow', label: __( 'Dofollow', 'flavor-link-checker' ) },
];

export const AFFILIATE_TYPE_OPTIONS = [
	{ value: 'all', label: __( 'All Affiliates', 'flavor-link-checker' ) },
	{
		value: 'cloaked',
		label: __( 'Cloaked (Internal)', 'flavor-link-checker' ),
	},
	{
		value: 'direct',
		label: __( 'Direct (External)', 'flavor-link-checker' ),
	},
];
