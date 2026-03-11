<?php
/**
 * Scan batch job for link extraction.
 *
 * @package FlavorLinkChecker
 * @since   1.0.0
 */

declare(strict_types=1);

namespace FlavorLinkChecker\Queue;

defined( 'ABSPATH' ) || exit;

use FlavorLinkChecker\Database\InstancesRepository;
use FlavorLinkChecker\Database\LinksRepository;
use FlavorLinkChecker\Models\Enums\LinkType;
use FlavorLinkChecker\Scanner\LinkExtractor;

/**
 * Processes a batch of posts: extracts links and stores them in the database.
 *
 * Called by Action Scheduler via the SCAN_BATCH_HOOK.
 *
 * @since 1.0.0
 */
class ScanJob {

	/**
	 * Timestamp when processing started.
	 *
	 * @since 1.0.0
	 * @var float
	 */
	private float $start_time;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param LinkExtractor       $extractor      Link extraction orchestrator.
	 * @param LinksRepository     $links_repo     Links CRUD repository.
	 * @param InstancesRepository $instances_repo Instances CRUD repository.
	 */
	public function __construct(
		private readonly LinkExtractor $extractor,
		private readonly LinksRepository $links_repo,
		private readonly InstancesRepository $instances_repo,
	) {}

	/**
	 * Processes a batch of posts.
	 *
	 * Reads post IDs from a transient, extracts links from each post,
	 * and persists the results. If resources run low, saves progress
	 * and re-enqueues itself for continuation.
	 *
	 * @since 1.0.0
	 *
	 * @param string $batch_id Batch identifier referencing a transient with post IDs.
	 */
	public function process_batch( string $batch_id ): void {
		$this->start_time = \microtime( true );

		if ( \defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			\error_log( "[FlavorLinkChecker] ScanJob::process_batch() started for batch {$batch_id}." );
		}

		$batch_data = \get_transient( 'flc_scan_batch_' . $batch_id );
		if ( false === $batch_data || ! \is_array( $batch_data ) ) {
			if ( \defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				\error_log( "[FlavorLinkChecker] ScanJob::process_batch() failed to load data for batch {$batch_id}." );
			}
			return;
		}

		$post_ids = $batch_data['post_ids'] ?? array();
		$offset   = $batch_data['offset'] ?? 0;
		$settings = \get_option( 'flc_settings', array() );

		// Shared-hosting optimizations.
		\wp_suspend_cache_addition( true );
		\wp_defer_term_counting( true );

		try {
			$count                 = \count( $post_ids );
			$processed_in_this_job = 0;

			for ( $i = $offset; $i < $count; $i++ ) {
				$post = \get_post( $post_ids[ $i ] );
				if ( ! $post instanceof \WP_Post ) {
					continue;
				}

				$this->process_post( $post, $settings );
				$processed_in_this_job++;

				// Check resources after each post.
				if ( ! $this->has_resources() && $i + 1 < $count ) {
					$this->update_progress( $processed_in_this_job );
					$processed_in_this_job = 0;

					// Save offset and re-enqueue for continuation.
					$batch_data['offset'] = $i + 1;
					\set_transient( 'flc_scan_batch_' . $batch_id, $batch_data, \HOUR_IN_SECONDS );
					SchedulerBootstrap::enqueue_scan_batch( $batch_id );
					return;
				}

				// Periodic progress update every 20 articles.
				if ( $processed_in_this_job >= 20 ) {
					$this->update_progress( $processed_in_this_job );
					$processed_in_this_job = 0;
				}
			}

			// Final progress update.
			if ( $processed_in_this_job > 0 ) {
				$this->update_progress( $processed_in_this_job );
			}

			// All posts in this batch are processed.
			if ( \defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				\error_log( "[FlavorLinkChecker] ScanJob batch {$batch_id}: completed, processed {$count} posts." );
			}
			\delete_transient( 'flc_scan_batch_' . $batch_id );

			/**
			 * Fires when a scan batch completes.
			 *
			 * @since 1.0.0
			 *
			 * @param string $batch_id The completed batch identifier.
			 */
			\do_action( 'flc/scan/batch_complete', $batch_id );
		} catch ( \Throwable $e ) {
			if ( \defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				\error_log( '[FlavorLinkChecker] ScanJob error in batch ' . $batch_id . ': ' . $e->getMessage() );
			}
		} finally {
			\wp_suspend_cache_addition( false );
			\wp_defer_term_counting( false );
		}
	}

	/**
	 * Processes a single post: extract, classify, persist.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post             $post     The post to process.
	 * @param array<string, mixed> $settings Plugin settings.
	 */
	private function process_post( \WP_Post $post, array $settings ): void {
		$extracted = $this->extractor->extract_from_post( $post, $settings );

		if ( empty( $extracted ) ) {
			// No links found: clean up any old instances.
			$this->instances_repo->sync_for_post( $post->ID, array() );
			return;
		}

		$instances_data = array();

		foreach ( $extracted as $url_data ) {
			// Insert or get the link record.
			$link_id = $this->links_repo->insert_or_get(
				$url_data['url'],
				$url_data['url_hash'],
				LinkType::External === $url_data['type'],
				$url_data['is_affiliate'],
				$url_data['affiliate_network']
			);

			if ( 0 === $link_id ) {
				continue;
			}

			// Build instance records for each occurrence.
			foreach ( $url_data['instances'] as $instance ) {
				$scan_result = $instance['scan_result'];
				$rel_flags   = $instance['rel_flags'];

				$instances_data[] = array(
					'link_id'       => $link_id,
					'post_id'       => $post->ID,
					'source_type'   => $scan_result->source_type,
					'anchor_text'   => $scan_result->anchor_text,
					'rel_nofollow'  => $rel_flags['rel_nofollow'],
					'rel_sponsored' => $rel_flags['rel_sponsored'],
					'rel_ugc'       => $rel_flags['rel_ugc'],
					'is_dofollow'   => $rel_flags['is_dofollow'],
					'link_position' => $scan_result->link_position,
					'block_name'    => $scan_result->block_name,
				);
			}
		}

		// Atomically replace all instances for this post.
		$this->instances_repo->sync_for_post( $post->ID, $instances_data );

		/**
		 * Fires after a post has been fully processed by the scanner.
		 *
		 * @since 1.0.0
		 *
		 * @param int $post_id The processed post ID.
		 */
		\do_action( 'flc/scan/post_processed', $post->ID );
	}

	/**
	 * Checks whether resources (memory, time) allow continuing.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if processing can continue.
	 */
	private function has_resources(): bool {
		$limit_bytes   = \wp_convert_hr_to_bytes( \WP_MEMORY_LIMIT );
		$memory_usage  = \memory_get_usage( true );
		$time_elapsed  = \microtime( true ) - $this->start_time;

		$has_resources = ( $memory_usage < $limit_bytes * 0.8 ) && ( $time_elapsed < 25 );

		if ( ! $has_resources ) {
			if ( \defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				\error_log( \sprintf(
					'[FlavorLinkChecker] Low resources detected! Memory: %.2f MB / %.2f MB, Time: %.2f s. Pausing batch.',
					$memory_usage / 1024 / 1024,
					$limit_bytes / 1024 / 1024,
					$time_elapsed
				) );
			}
		}

		return $has_resources;
	}

	/**
	 * Updates scan progress tracking.
	 *
	 * @since 1.0.0
	 *
	 * @param int $increment Number of posts processed to add to the total.
	 */
	private function update_progress( int $increment ): void {
		$status = \get_transient( 'flc_scan_status' );
		if ( \is_array( $status ) ) {
			$status['scanned_posts'] = ( $status['scanned_posts'] ?? 0 ) + $increment;
			\set_transient( 'flc_scan_status', $status, \HOUR_IN_SECONDS );
		}
	}
}
