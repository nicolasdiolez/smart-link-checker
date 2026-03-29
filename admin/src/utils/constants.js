/**
 * Shared constants for the Sentinel Link Checker admin UI.
 *
 * @package
 * @since   1.0.0
 */

import { __ } from '@wordpress/i18n';

export const STORE_NAME = 'sentinel-link-checker';
export const API_NAMESPACE = '/sentinel-link-checker/v1';

export const STATUS_LABELS = {
	pending: __( 'Pending', 'sentinel-link-checker' ),
	ok: __( 'OK', 'sentinel-link-checker' ),
	redirect: __( 'Redirect', 'sentinel-link-checker' ),
	broken: __( 'Broken', 'sentinel-link-checker' ),
	error: __( 'Error', 'sentinel-link-checker' ),
	timeout: __( 'Timeout', 'sentinel-link-checker' ),
	skipped: __( 'Skipped', 'sentinel-link-checker' ),
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
	{ value: 'external', label: __( 'External', 'sentinel-link-checker' ) },
	{ value: 'internal', label: __( 'Internal', 'sentinel-link-checker' ) },
];

export const REL_OPTIONS = [
	{ value: 'nofollow', label: __( 'Nofollow', 'sentinel-link-checker' ) },
	{ value: 'sponsored', label: __( 'Sponsored', 'sentinel-link-checker' ) },
	{ value: 'ugc', label: __( 'UGC', 'sentinel-link-checker' ) },
	{ value: 'dofollow', label: __( 'Dofollow', 'sentinel-link-checker' ) },
];

export const AFFILIATE_TYPE_OPTIONS = [
	{ value: 'all', label: __( 'All Affiliates', 'sentinel-link-checker' ) },
	{
		value: 'cloaked',
		label: __( 'Cloaked (Internal)', 'sentinel-link-checker' ),
	},
	{
		value: 'direct',
		label: __( 'Direct (External)', 'sentinel-link-checker' ),
	},
];
