<?php
/**
 * Monitor scan progress and resource usage.
 *
 * Usage: php tests/monitor-scan.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	$current_dir = __DIR__;
	while ( ! file_exists( $current_dir . '/wp-load.php' ) && '/' !== $current_dir ) {
		$current_dir = dirname( $current_dir );
	}
	require_once $current_dir . '/wp-load.php';
}

require_once wp_normalize_path( WP_PLUGIN_DIR . '/flavor-link-checker/vendor/autoload.php' );

$links_repo   = new \FlavorLinkChecker\Database\LinksRepository( $GLOBALS['wpdb'] );
$orchestrator = new \FlavorLinkChecker\Queue\BatchOrchestrator( $links_repo );

echo "Monitoring scan status...\n";

// Force reset for a fresh load test run.
$should_reset = ( isset( $argv[1] ) && 'reset' === $argv[1] ) || ( isset( $args[0] ) && 'reset' === $args[0] );

if ( $should_reset ) {
	echo "Resetting scan status and clearing existing data...\n";
	delete_transient( 'flc_scan_status' );
	delete_transient( 'flc_transition_lock' );

	// Ensure redirect_chain column exists.
	$has_column = $GLOBALS['wpdb']->get_results( "SHOW COLUMNS FROM {$GLOBALS['wpdb']->prefix}flc_links LIKE 'redirect_chain'" );
	if ( empty( $has_column ) ) {
		echo "Fixing schema: adding redirect_chain column...\n";
		$GLOBALS['wpdb']->query( "ALTER TABLE {$GLOBALS['wpdb']->prefix}flc_links ADD COLUMN redirect_chain text DEFAULT NULL AFTER redirect_count" );
	}

	$GLOBALS['wpdb']->query( "TRUNCATE TABLE {$GLOBALS['wpdb']->prefix}flc_instances" );
	$GLOBALS['wpdb']->query( "TRUNCATE TABLE {$GLOBALS['wpdb']->prefix}flc_links" );
	// Clear all AS actions for this plugin.
	if ( class_exists( 'ActionScheduler_DBStore' ) ) {
		\ActionScheduler_DBStore::instance()->cancel_actions_by_group( 'flavor-link-checker' );
	}
}

$settings = get_option( 'flc_settings', array() );
echo "Current Settings: " . json_encode( $settings ) . "\n";

$limit_const = defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : 'Not Defined';
$limit_bytes = wp_convert_hr_to_bytes( $limit_const );
$threshold   = $limit_bytes * 0.8 / 1024 / 1024;
echo "Memory Limit (WP_MEMORY_LIMIT): $limit_const\n";
echo "Resource Safety Threshold (80%): " . round( $threshold, 2 ) . " MB\n";

$status = $orchestrator->get_status();
if ( 'idle' === $status['status'] ) {
	echo "Starting full scan...\n";
	$orchestrator->start_scan( 'full' );
	$status = $orchestrator->get_status(); // Refresh status.
}

echo "Detected Posts to Scan: " . $status['total_posts'] . "\n";
echo "Action Scheduler Diagnostics: " . json_encode( \FlavorLinkChecker\Queue\SchedulerBootstrap::get_diagnostics() ) . "\n";
echo str_repeat( "-", 80 ) . "\n";
printf( "%-20s | %-10s | %-10s | %-12s | %-12s | %-8s | %-10s\n", "Time", "Status", "Phase", "Posts", "Links", "Pending", "Memory" );
echo str_repeat( "-", 80 ) . "\n";

while ( true ) {
	$status  = $orchestrator->get_status();
	$diag    = \FlavorLinkChecker\Queue\SchedulerBootstrap::get_diagnostics();
	$pending = $diag['pending'] ?? 0;
	
	// Also check for 'running' actions.
	$running = count( as_get_scheduled_actions( [
		'group'    => 'flavor-link-checker',
		'status'   => 'in-progress',
		'per_page' => 0,
	], 'ids' ) );

	$mem     = memory_get_usage( true ) / 1024 / 1024;

	printf(
		"%-20s | %-10s | %-10s | %-12s | %-12s | P:%-2d R:%-2d | %.2f MB\n",
		date( 'H:i:s' ),
		$status['status'],
		$status['phase'] ?? 'N/A',
		$status['scanned_posts'] . '/' . $status['total_posts'],
		$status['checked_links'] . '/' . $status['total_links'],
		$pending,
		$running,
		$mem
	);

	if ( in_array( $status['status'], [ 'complete', 'error', 'cancelled' ], true ) ) {
		echo "\nScan finished with status: " . $status['status'] . "\n";
		break;
	}

	// Nudge Action Scheduler if it's stuck.
	if ( defined( 'AS_VERSION' ) ) {
		\ActionScheduler_QueueRunner::instance()->run();
	}

	sleep( 5 );
}
