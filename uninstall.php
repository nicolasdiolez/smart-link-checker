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
delete_option( 'flc_db_version' );
delete_option( 'flc_settings' );

/*
 * Unschedule all Action Scheduler actions.
 */
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( '', array(), 'flavor-link-checker' );
}

/*
 * Clean up transients.
 */
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_flc\_%' OR option_name LIKE '_transient_timeout_flc\_%'" );
