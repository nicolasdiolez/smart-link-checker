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
	 * @param LinksRepository $links_repo Links CRUD repository.
	 */
	public function __construct(
		private readonly LinksRepository $links_repo,
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

		// Update scan status with total links.
		$status = get_transient( 'flc_scan_status' );
		if ( is_array( $status ) ) {
			$status['total_links'] = count( $link_ids );
			set_transient( 'flc_scan_status', $status, HOUR_IN_SECONDS );
		}

		return $this->create_and_enqueue_check_batches( $link_ids );
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
			return $status;
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
				// Re-read transient to pick up total_links set by start_check().
				$status = get_transient( 'flc_scan_status' );
				if ( ! is_array( $status ) ) {
					return $this->get_idle_status();
				}
				$status['phase'] = 'checking';
				set_transient( 'flc_scan_status', $status, HOUR_IN_SECONDS );
			} else {
				$status['status'] = 'complete';
				set_transient( 'flc_scan_status', $status, HOUR_IN_SECONDS );
			}
		} elseif ( 'checking' === $phase && 0 === $pending_count ) {
			$status['status'] = 'complete';
			set_transient( 'flc_scan_status', $status, HOUR_IN_SECONDS );
		}

		return $status;
	}

	/**
	 * Returns the default idle status array.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	private function get_idle_status(): array {
		return array(
			'status'         => 'idle',
			'phase'          => null,
			'total_posts'    => 0,
			'scanned_posts'  => 0,
			'total_links'    => 0,
			'checked_links'  => 0,
			'broken_count'   => 0,
			'redirect_count' => 0,
			'started_at'     => null,
			'error_message'  => null,
		);
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

		// Delta scan: only posts modified since last scan.
		if ( 'delta' === $scan_type ) {
			$status = get_transient( 'flc_scan_status' );
			if ( is_array( $status ) && ! empty( $status['started_at'] ) ) {
				$query_args['date_query'] = array(
					array(
						'column' => 'post_modified_gmt',
						'after'  => $status['started_at'],
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

		$chunks      = array_chunk( $post_ids, $batch_size );
		$batch_count = 0;
		$fail_count  = 0;

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
			}
			++$batch_count;
		}

		if ( $fail_count > 0 && $fail_count === $batch_count ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( "[FlavorLinkChecker] All {$batch_count} scan batches failed to enqueue." );
			$status = get_transient( 'flc_scan_status' );
			if ( is_array( $status ) ) {
				$status['status']        = 'error';
				$status['error_message'] = __( 'Action Scheduler failed to enqueue scan batches. Check server error logs.', 'flavor-link-checker' );
				set_transient( 'flc_scan_status', $status, HOUR_IN_SECONDS );
			}
		}

		return $batch_count;
	}

	/**
	 * Splits link IDs into batches and enqueues check actions.
	 *
	 * @since 1.0.0
	 *
	 * @param int[] $link_ids Link IDs to check.
	 * @return int Number of batches created.
	 */
	private function create_and_enqueue_check_batches( array $link_ids ): int {
		if ( empty( $link_ids ) ) {
			return 0;
		}

		$chunks      = array_chunk( $link_ids, self::MAX_CHECK_BATCH_SIZE );
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
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( "[FlavorLinkChecker] All {$batch_count} check batches failed to enqueue." );
		}

		return $batch_count;
	}
}
