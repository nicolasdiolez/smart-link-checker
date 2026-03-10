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

/**
 * Processes a batch of links: verifies their HTTP status.
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
	 * @param HttpChecker     $checker    HTTP verification engine.
	 * @param LinksRepository $links_repo Links CRUD repository.
	 */
	public function __construct(
		private readonly HttpChecker $checker,
		private readonly LinksRepository $links_repo,
	) {}

	/**
	 * Processes a batch of link IDs: checks their HTTP status.
	 *
	 * @since 1.0.0
	 *
	 * @param int[] $link_ids Array of link IDs to check.
	 */
	public function process_batch( array $link_ids ): void {
		$this->start_time = microtime( true );

		$settings            = \get_option( 'flc_settings', array() );
		$this->request_delay = (int) ( $settings['http_request_delay'] ?? 300 );

		$count = count( $link_ids );
		for ( $i = 0; $i < $count; $i++ ) {
			$link = $this->links_repo->find( $link_ids[ $i ] );
			if ( null === $link ) {
				continue;
			}

			$this->check_link( $link->id, $link->url );

			// Rate limiting between requests.
			if ( $i + 1 < $count ) {
				$this->rate_limit_pause();
			}

			// Check resources after each link.
			if ( ! $this->has_resources() && $i + 1 < $count ) {
				// Re-enqueue remaining links.
				$remaining = array_slice( $link_ids, $i + 1 );
				SchedulerBootstrap::enqueue_check_batch( $remaining );
				return;
			}
		}
	}

	/**
	 * Checks a single link and updates the database.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $link_id The link ID.
	 * @param string $url     The URL to check.
	 */
	private function check_link( int $link_id, string $url ): void {
		try {
			$result = $this->checker->check( $url );

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

			/**
			 * Fires after a link has been checked.
			 *
			 * @since 1.0.0
			 *
			 * @param int   $link_id The checked link ID.
			 * @param array $result  The check result.
			 */
			\do_action( 'flc/check/link_checked', $link_id, $result );

			// Update progress.
			$status = \get_transient( 'flc_scan_status' );
			if ( is_array( $status ) ) {
				$status['checked_links'] = ( $status['checked_links'] ?? 0 ) + 1;

				if ( LinkStatus::Broken === $result['status_category'] ) {
					$status['broken_count'] = ( $status['broken_count'] ?? 0 ) + 1;
				} elseif ( LinkStatus::Redirect === $result['status_category'] ) {
					$status['redirect_count'] = ( $status['redirect_count'] ?? 0 ) + 1;
				}

				\set_transient( 'flc_scan_status', $status, \HOUR_IN_SECONDS );
			}
		} catch ( \Throwable $e ) {
			// Isolate errors: one failing link should not stop the batch.
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
			usleep( $this->request_delay * 1000 );
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
		$memory_limit = wp_convert_hr_to_bytes( WP_MEMORY_LIMIT );
		$memory_usage = memory_get_usage( true );
		$time_elapsed = microtime( true ) - $this->start_time;

		return ( $memory_usage < $memory_limit * 0.8 ) && ( $time_elapsed < 25 );
	}
}
