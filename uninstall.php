<?php
/**
 * Uninstall handler — runs when the plugin is deleted from WordPress admin.
 *
 * Drops all custom tables, deletes options, and clears scheduled actions.
 *
 * @package FlavorLinkChecker
 * @since   1.0.0
 */

declare( strict_types=1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/vendor/autoload.php';

/*
 * Drop custom database tables.
 */
( new \FlavorLinkChecker\Database\Migrator() )->drop_tables();

/*
 * Delete plugin options.
 */
delete_option( 'slkc_db_version' );
delete_option( 'slkc_settings' );

/*
 * Unschedule all Action Scheduler actions.
 */
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( '', array(), 'sentinel-link-checker' );
}

/*
 * Clean up transients.
 */
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_slkc_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_slkc_' ) . '%'
	)
);
delete_option( 'slkc_last_scan_date' );
