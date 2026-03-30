<?php
/**
 * HTTP check batch job.
 *
 * @package FlavorLinkChecker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace FlavorLinkChecker\Queue;

defined( 'ABSPATH' ) || exit;

use FlavorLinkChecker\Database\LinksRepository;
use FlavorLinkChecker\Models\Enums\LinkStatus;
use FlavorLinkChecker\Scanner\HttpChecker;
use FlavorLinkChecker\Scanner\InternalLinkChecker;

/**
 * Processes a batch of links: verifies their HTTP status in parallel.
 *
 * Called by Action Scheduler via the CHECK_BATCH_HOOK.
 *
 * @since 1.0.0
 */
class CheckJob {

	/**
	 * Timestamp when processing started.
	 *
	 * @since 1.0.0
	 * @var float
	 */
	private float $start_time;

	/**
	 * Delay between HTTP requests in milliseconds.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private int $request_delay;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param HttpChecker          $checker          HTTP verification engine.
	 * @param InternalLinkChecker  $internal_checker Internal link verification engine.
	 * @param LinksRepository      $links_repo       Links CRUD repository.
	 */
	public function __construct(
		private readonly HttpChecker $checker,
		private readonly InternalLinkChecker $internal_checker,
		private readonly LinksRepository $links_repo,
	) {}

	/**
	 * Processes a batch of link IDs: checks their HTTP status in parallel.
	 *
	 * @since 1.0.0
	 *
	 * @param int[] $link_ids Array of link IDs to check.
	 */
	public function process_batch( array $link_ids ): void {
		$this->start_time = \microtime( true );

		// Suspend object cache additions during batch processing.
		\wp_suspend_cache_addition( true );

		$settings            = \get_option( 'slkc_settings', array() );
		$this->request_delay = (int) ( $settings['http_request_delay'] ?? 300 );
		$parallel_size       = 5; // Default parallel cluster size.

		$count    = \count( $link_ids );
		$chunks   = \array_chunk( $link_ids, $parallel_size );
		$offset   = 0;

		foreach ( $chunks as $chunk_ids ) {
			// Resolve URLs for the chunk in a single query.
			$links        = $this->links_repo->find_by_ids( $chunk_ids );
			$external_map = array(); // link_id => url (external links — HTTP check).
			$internal_map = array(); // link_id => url (internal links — local check).

			foreach ( $chunk_ids as $id ) {
				if ( isset( $links[ $id ] ) ) {
					if ( $links[ $id ]->is_external ) {
						$external_map[ $id ] = $links[ $id ]->url;
					} else {
						$internal_map[ $id ] = $links[ $id ]->url;
					}
				}
			}

			// Check internal links locally (no HTTP request).
			if ( ! empty( $internal_map ) ) {
				$internal_results = $this->internal_checker->check_batch( \array_values( $internal_map ) );
				foreach ( $internal_map as $id => $url ) {
					$result = $internal_results[ $url ] ?? null;
					if ( $result ) {
						$this->persist_result( $id, $result );
					}
				}
			}

			// Check external links via HTTP.
			if ( ! empty( $external_map ) ) {
				$external_results = $this->checker->check_batch( \array_values( $external_map ) );
				foreach ( $external_map as $id => $url ) {
					$result = $external_results[ $url ] ?? null;
					if ( $result ) {
						$this->persist_result( $id, $result );
					}
				}
			}

			$offset += \count( $chunk_ids );

			// Rate limiting between parallel chunks.
			if ( $offset < $count ) {
				$this->rate_limit_pause();
			}

			// Resource check.
			if ( ! $this->has_resources() && $offset < $count ) {
				$remaining = \array_slice( $link_ids, $offset );

				\wp_suspend_cache_addition( false );
				\do_action( 'slkc/check/batch_split', $link_ids, $remaining );
				SchedulerBootstrap::enqueue_check_batch( $remaining );
				return;
			}
		}

		\wp_suspend_cache_addition( false );
		\do_action( 'slkc/check/batch_complete', $link_ids );
	}

	/**
	 * Persists a check result to the database and updates progress.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $link_id The link ID.
	 * @param array $result  Check result data.
	 */
	private function persist_result( int $link_id, array $result ): void {
		try {
			$chain_json = null !== $result['redirect_chain']
				? \wp_json_encode( $result['redirect_chain'] )
				: null;

			$error = $result['error'];
			if ( $result['is_redirect_loop'] && null === $error ) {
				$error = 'redirect_loop';
			}

			$this->links_repo->update_check_result(
				$link_id,
				$result['http_status'],
				$result['status_category'],
				$result['final_url'],
				$result['response_time'],
				$result['redirect_count'],
				$chain_json,
				$error
			);

			\do_action( 'slkc/check/link_checked', $link_id, $result );

			// Update progress.
			$status = \get_transient( 'slkc_scan_status' );
			if ( \is_array( $status ) ) {
				$status['checked_links']  = ( $status['checked_links'] ?? 0 ) + 1;
				$category                 = $result['status_category'];

				if ( LinkStatus::Broken === $category ) {
					$status['broken_count'] = ( $status['broken_count'] ?? 0 ) + 1;
				} elseif ( LinkStatus::Redirect === $category ) {
					$status['redirect_count'] = ( $status['redirect_count'] ?? 0 ) + 1;
				}

				\set_transient( 'slkc_scan_status', $status, \HOUR_IN_SECONDS );
			}
		} catch ( \Throwable $e ) {
			$this->links_repo->update_check_result(
				$link_id,
				0,
				LinkStatus::Error,
				null,
				0,
				0,
				null,
				$e->getMessage()
			);
		}
	}

	/**
	 * Pauses between HTTP requests for rate limiting.
	 *
	 * @since 1.0.0
	 */
	private function rate_limit_pause(): void {
		if ( $this->request_delay > 0 ) {
			\usleep( $this->request_delay * 1000 );
		}
	}

	/**
	 * Checks whether resources (memory, time) allow continuing.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if processing can continue.
	 */
	private function has_resources(): bool {
		$memory_limit = \wp_convert_hr_to_bytes( \WP_MEMORY_LIMIT );
		$memory_usage = \memory_get_usage( true );
		$time_elapsed = \microtime( true ) - $this->start_time;

		return ( $memory_usage < $memory_limit * 0.8 ) && ( $time_elapsed < 25 );
	}
}
