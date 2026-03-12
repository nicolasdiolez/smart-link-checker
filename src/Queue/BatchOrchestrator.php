<?php
/**
 * Batch orchestrator for scan and check workflows.
 *
 * @package FlavorLinkChecker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace FlavorLinkChecker\Queue;

defined( 'ABSPATH' ) || exit;

use FlavorLinkChecker\Database\InstancesRepository;
use FlavorLinkChecker\Database\LinksRepository;

/**
 * Orchestrates the creation and management of scan and check batches.
 *
 * Splits work into batches, stores them as transients, and enqueues
 * Action Scheduler actions for each batch.
 *
 * @since 1.0.0
 */
class BatchOrchestrator {

	/**
	 * Maximum number of links per check batch.
	 *
	 * Kept small because each check involves an HTTP request with delay.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private const MAX_CHECK_BATCH_SIZE = 20;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param LinksRepository     $links_repo     Links CRUD repository.
	 * @param InstancesRepository $instances_repo Instances CRUD repository.
	 */
	public function __construct(
		private readonly LinksRepository $links_repo,
		private readonly InstancesRepository $instances_repo,
	) {}

	/**
	 * Starts a scan: queries all scannable posts and creates scan batches.
	 *
	 * @since 1.0.0
	 *
	 * @param string $scan_type 'full' or 'delta' (delta = only modified since last scan).
	 * @return int Number of batches created.
	 */
	public function start_scan( string $scan_type = 'full' ): int {
		$post_ids   = $this->get_scannable_post_ids( $scan_type );
		$batch_size = $this->calculate_batch_size();

		/**
		 * Filters the number of posts per scan batch.
		 *
		 * @since 1.0.0
		 *
		 * @param int $batch_size Calculated batch size.
		 */
		$batch_size = (int) apply_filters( 'flc/scanner/batch_size', $batch_size );

		// Initialize scan status.
		set_transient(
			'flc_scan_status',
			array(
				'status'         => 'running',
				'phase'          => 'scanning',
				'scan_type'      => $scan_type,
				'total_posts'    => count( $post_ids ),
				'scanned_posts'  => 0,
				'total_links'    => 0,
				'checked_links'  => 0,
				'broken_count'   => 0,
				'redirect_count' => 0,
				'started_at'     => gmdate( 'c' ),
				'scan_batches'   => array(),
				'check_batches'  => array(),
			),
			HOUR_IN_SECONDS
		);

		$batch_count = $this->create_and_enqueue_scan_batches( $post_ids, $batch_size );

		return $batch_count;
	}

	/**
	 * Creates check batches for all links with status 'pending'.
	 *
	 * @since 1.0.0
	 *
	 * @return int Number of batches created.
	 */
	public function start_check(): int {
		$links    = $this->links_repo->find_pending_or_stale( PHP_INT_MAX, 0 );
		$link_ids = array_map( fn( $link ) => $link->id, $links );
		$chunks   = array_chunk( $link_ids, self::MAX_CHECK_BATCH_SIZE );

		// Update scan status with total links and batches.
		$status = get_transient( 'flc_scan_status' );
		if ( is_array( $status ) ) {
			$status['total_links']   = count( $link_ids );
			$status['check_batches'] = $chunks;
			set_transient( 'flc_scan_status', $status, HOUR_IN_SECONDS );
		}

		return $this->enqueue_check_batches( $chunks );
	}

	/**
	 * Handles the daily recheck of stale links.
	 *
	 * Called by the RECHECK_DAILY_HOOK recurring action.
	 *
	 * @since 1.0.0
	 */
	public function recheck_stale_links(): void {
		$settings     = get_option( 'flc_settings', array() );
		$recheck_days = (int) ( $settings['recheck_interval'] ?? 7 );
		$batch_size   = (int) ( $settings['batch_size'] ?? 50 );

		$links    = $this->links_repo->find_pending_or_stale( $batch_size * 5, $recheck_days );
		$link_ids = array_map( fn( $link ) => $link->id, $links );

		if ( ! empty( $link_ids ) ) {
			$this->create_and_enqueue_check_batches( $link_ids );
		}
	}

	/**
	 * Cancels the current scan/check.
	 *
	 * @since 1.0.0
	 */
	public function cancel(): void {
		SchedulerBootstrap::cancel_all();

		$status = get_transient( 'flc_scan_status' );
		if ( is_array( $status ) ) {
			$status['status'] = 'cancelled';
			set_transient( 'flc_scan_status', $status, HOUR_IN_SECONDS );
		}
	}

	/**
	 * Resets all scan data: cancels jobs and truncates tables.
	 *
	 * @since 1.0.0
	 */
	public function reset(): void {
		$this->cancel();

		$this->instances_repo->truncate();
		$this->links_repo->truncate();

		delete_transient( 'flc_scan_status' );
		delete_option( 'flc_last_scan_date' );
	}

	/**
	 * Resumes a cancelled or interrupted scan.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if scan was resumed.
	 */
	public function resume(): bool {
		$status = get_transient( 'flc_scan_status' );
		if ( ! is_array( $status ) || 'cancelled' !== $status['status'] ) {
			return false;
		}

		$status['status'] = 'running';
		set_transient( 'flc_scan_status', $status, HOUR_IN_SECONDS );

		if ( 'scanning' === $status['phase'] ) {
			foreach ( $status['scan_batches'] as $batch_id ) {
				SchedulerBootstrap::enqueue_scan_batch( $batch_id );
			}
		} elseif ( 'checking' === $status['phase'] ) {
			$this->enqueue_check_batches( $status['check_batches'] );
		}

		return true;
	}

	/**
	 * Removes a scan batch from the tracking list.
	 *
	 * @since 1.0.0
	 *
	 * @param string $batch_id Batch identifier.
	 */
	public function remove_scan_batch( string $batch_id ): void {
		$status = get_transient( 'flc_scan_status' );
		if ( is_array( $status ) && isset( $status['scan_batches'] ) ) {
			$status['scan_batches'] = array_values( array_diff( $status['scan_batches'], array( $batch_id ) ) );
			set_transient( 'flc_scan_status', $status, HOUR_IN_SECONDS );
		}
	}

	/**
	 * Removes a check batch from the tracking list.
	 *
	 * @since 1.0.0
	 *
	 * @param int[] $link_ids Link IDs that were in the batch.
	 */
	public function remove_check_batch( array $link_ids ): void {
		$status = get_transient( 'flc_scan_status' );
		if ( is_array( $status ) && isset( $status['check_batches'] ) ) {
			foreach ( $status['check_batches'] as $index => $chunk ) {
				// We compare the arrays to find the batch that just finished.
				if ( $chunk === $link_ids ) {
					unset( $status['check_batches'][ $index ] );
					$status['check_batches'] = array_values( $status['check_batches'] );
					break;
				}
			}
			set_transient( 'flc_scan_status', $status, HOUR_IN_SECONDS );
		}
	}

	/**
	 * Handles a check batch being split into a smaller one due to resource limits.
	 *
	 * @since 1.0.0
	 *
	 * @param int[] $old_link_ids Original batch link IDs.
	 * @param int[] $new_link_ids Remaining link IDs.
	 */
	public function handle_check_batch_split( array $old_link_ids, array $new_link_ids ): void {
		$status = get_transient( 'flc_scan_status' );
		if ( is_array( $status ) && isset( $status['check_batches'] ) ) {
			foreach ( $status['check_batches'] as $index => $chunk ) {
				if ( $chunk === $old_link_ids ) {
					$status['check_batches'][ $index ] = $new_link_ids;
					break;
				}
			}
			set_transient( 'flc_scan_status', $status, HOUR_IN_SECONDS );
		}
	}

	/**
	 * Adds a check batch to the tracking list (used during re-enqueueing).
	 *
	 * @since 1.0.0
	 *
	 * @param int[] $link_ids Link IDs in the new batch.
	 */
	public function add_check_batch( array $link_ids ): void {
		$status = get_transient( 'flc_scan_status' );
		if ( is_array( $status ) && isset( $status['check_batches'] ) ) {
			$status['check_batches'][] = $link_ids;
			set_transient( 'flc_scan_status', $status, HOUR_IN_SECONDS );
		}
	}

	/**
	 * Returns the current scan status.
	 *
	 * @since 1.0.0
	 *
	 * @return array{status: string, total_posts: int, scanned_posts: int, total_links: int, checked_links: int, broken_count: int, redirect_count: int, started_at: string|null}
	 */
	public function get_status(): array {
		$status = get_transient( 'flc_scan_status' );

		if ( ! is_array( $status ) ) {
			return $this->get_idle_status();
		}

		if ( 'running' !== $status['status'] ) {
			$stats = $this->get_cached_stats();
			return array_merge( $status, array(
				'total_links'    => $stats['total'],
				'ok_count'       => $stats['ok_count'],
				'broken_count'   => $stats['broken_count'],
				'redirect_count' => $stats['single_redirect_count'] + $stats['chain_redirect_count'],
			) );
		}

		// Nudge the AS queue runner on every status poll.
		SchedulerBootstrap::maybe_run_queue();

		$phase         = $status['phase'] ?? 'scanning';
		$pending_count = SchedulerBootstrap::get_pending_count();

		if ( 'scanning' === $phase && 0 === $pending_count ) {
			// Prevent concurrent polls from triggering the transition twice.
			if ( false !== get_transient( 'flc_transition_lock' ) ) {
				return $status;
			}
			set_transient( 'flc_transition_lock', 1, 30 );

			// All scan batches done — transition to check phase.
			$check_batches = $this->start_check();
			delete_transient( 'flc_transition_lock' );

			if ( $check_batches > 0 ) {
				// Re-read transient to pick up total_links and check_batches set by start_check().
				$status = get_transient( 'flc_scan_status' );
				if ( ! is_array( $status ) ) {
					return $this->get_idle_status();
				}
				$status['phase'] = 'checking';
				set_transient( 'flc_scan_status', $status, HOUR_IN_SECONDS );
			} else {
				$status['status'] = 'complete';
				update_option( 'flc_last_scan_date', $status['started_at'] );
				set_transient( 'flc_scan_status', $status, HOUR_IN_SECONDS );
				do_action( 'flc/scan/complete' );
			}
		} elseif ( 'checking' === $phase && 0 === $pending_count ) {
			$status['status'] = 'complete';
			update_option( 'flc_last_scan_date', $status['started_at'] );
			set_transient( 'flc_scan_status', $status, HOUR_IN_SECONDS );
			do_action( 'flc/scan/complete' );
		}

		$stats = $this->get_cached_stats();
		return array_merge( $status, array(
			'total_links'     => $stats['total'],
			'ok_count'        => $stats['ok_count'],
			'broken_count'    => $stats['broken_count'],
			'redirect_count'  => $stats['redirect_count'],
			'error_count'     => $stats['error_count'],
			'timeout_count'   => $stats['timeout_count'],
			'skipped_count'   => $stats['skipped_count'],
			'pending_count'   => $stats['pending_count'],
			'checked_links'   => $stats['total'] - $stats['pending_count'],
		) );
	}

	/**
	 * Returns the default idle status array.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	private function get_idle_status(): array {
		$stats = $this->get_cached_stats();
		return array(
			'status'         => 'idle',
			'phase'          => null,
			'total_posts'    => 0,
			'scanned_posts'  => 0,
			'total_links'    => $stats['total'],
			'checked_links'  => $stats['total'] - $stats['pending_count'],
			'ok_count'       => $stats['ok_count'],
			'broken_count'   => $stats['broken_count'],
			'redirect_count' => $stats['single_redirect_count'] + $stats['chain_redirect_count'],
			'started_at'     => null,
			'error_message'  => null,
		);
	}

	/**
	 * Returns category stats with a short-lived transient cache.
	 *
	 * Avoids running the heavy 16-SUM aggregation query on every
	 * status poll (every 5 seconds).
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, int>
	 */
	private function get_cached_stats(): array {
		$cached = get_transient( 'flc_stats_cache' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$stats = $this->links_repo->get_category_stats();
		set_transient( 'flc_stats_cache', $stats, 30 );

		return $stats;
	}

	/**
	 * Calculates optimal batch size based on available memory.
	 *
	 * @since 1.0.0
	 *
	 * @return int Batch size (between 10 and 200).
	 */
	private function calculate_batch_size(): int {
		$memory_available   = wp_convert_hr_to_bytes( WP_MEMORY_LIMIT ) - memory_get_usage( true );
		$estimated_per_item = 50 * 1024; // ~50KB per post.

		return max( 10, min( 200, (int) floor( $memory_available * 0.5 / $estimated_per_item ) ) );
	}

	/**
	 * Queries post IDs to scan based on settings and scan type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $scan_type 'full' or 'delta'.
	 * @return int[] Post IDs.
	 */
	private function get_scannable_post_ids( string $scan_type ): array {
		$settings = get_option( 'flc_settings', array() );

		$query_args = array(
			'post_type'      => $settings['scan_post_types'] ?? array( 'post', 'page' ),
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
		);

		// Delta scan: only posts modified since last successful scan.
		if ( 'delta' === $scan_type ) {
			$last_scan = get_option( 'flc_last_scan_date' );
			if ( $last_scan ) {
				$query_args['date_query'] = array(
					array(
						'column' => 'post_modified_gmt',
						'after'  => $last_scan,
					),
				);
			}
		}

		$query = new \WP_Query( $query_args );

		return $query->posts;
	}

	/**
	 * Splits post IDs into batches, stores as transients, and enqueues scan actions.
	 *
	 * @since 1.0.0
	 *
	 * @param int[] $post_ids   Post IDs to scan.
	 * @param int   $batch_size Number of posts per batch.
	 * @return int Number of batches created.
	 */
	private function create_and_enqueue_scan_batches( array $post_ids, int $batch_size ): int {
		if ( empty( $post_ids ) ) {
			return 0;
		}

		$chunks       = array_chunk( $post_ids, $batch_size );
		$batch_count  = 0;
		$fail_count   = 0;
		$scan_batches = array();

		foreach ( $chunks as $chunk ) {
			$batch_id = wp_unique_id( 'flc_batch_' );

			set_transient(
				'flc_scan_batch_' . $batch_id,
				array(
					'post_ids' => $chunk,
					'offset'   => 0,
				),
				HOUR_IN_SECONDS
			);

			$action_id = SchedulerBootstrap::enqueue_scan_batch( $batch_id );
			if ( 0 === $action_id ) {
				++$fail_count;
			} else {
				$scan_batches[] = $batch_id;
			}
			++$batch_count;
		}

		// Update status with tracked batches.
		$status = get_transient( 'flc_scan_status' );
		if ( is_array( $status ) ) {
			$status['scan_batches'] = $scan_batches;
			set_transient( 'flc_scan_status', $status, HOUR_IN_SECONDS );
		}

		if ( $fail_count > 0 && $fail_count === $batch_count ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( "[FlavorLinkChecker] All {$batch_count} scan batches failed to enqueue." );
			}
			$status = get_transient( 'flc_scan_status' );
			if ( is_array( $status ) ) {
				$status['status']        = 'error';
				$status['error_message'] = __( 'Action Scheduler failed to enqueue scan batches. Check server error logs.', 'smart-link-checker' );
				set_transient( 'flc_scan_status', $status, HOUR_IN_SECONDS );
			}
		}

		return $batch_count;
	}

	/**
	 * Enqueues check batches from chunks.
	 *
	 * @since 1.0.0
	 *
	 * @param array $chunks Array of link ID chunks.
	 * @return int Number of batches enqueued.
	 */
	private function enqueue_check_batches( array $chunks ): int {
		$batch_count = 0;
		$fail_count  = 0;

		foreach ( $chunks as $chunk ) {
			$action_id = SchedulerBootstrap::enqueue_check_batch( $chunk );
			if ( 0 === $action_id ) {
				++$fail_count;
			}
			++$batch_count;
		}

		if ( $fail_count > 0 && $fail_count === $batch_count ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( "[FlavorLinkChecker] All {$batch_count} check batches failed to enqueue." );
			}
		}

		return $batch_count;
	}
}
