<?php
/**
 * HTTP link checker.
 *
 * @package FlavorLinkChecker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace FlavorLinkChecker\Scanner;

defined( 'ABSPATH' ) || exit;

use FlavorLinkChecker\Models\Enums\LinkStatus;

/**
 * Verifies the HTTP status of a URL using HEAD with GET fallback.
 *
 * @since 1.0.0
 */
class HttpChecker {

	/**
	 * HTTP status codes that trigger a GET fallback after HEAD.
	 *
	 * @since 1.0.0
	 * @var int[]
	 */
	private const GET_FALLBACK_CODES = array( 403, 405, 501 );

	/**
	 * Maximum response body size for GET requests (1 MB).
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private const MAX_RESPONSE_SIZE = 1048576;

	/**
	 * Request timeout in seconds.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private readonly int $timeout;

	/**
	 * User-Agent string for HTTP requests.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private readonly string $user_agent;

	/**
	 * Site URL for resolving relative URLs.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private readonly string $site_url;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $timeout  Request timeout in seconds.
	 * @param string $site_url Optional site URL for testability. Defaults to home_url().
	 */
	public function __construct(
		int $timeout = 15,
		string $site_url = '',
	) {
		$this->timeout    = $timeout;
		$this->site_url   = '' !== $site_url ? $site_url : home_url();
		$this->user_agent = 'Mozilla/5.0 (compatible; FlavorLinkChecker/' . FLC_VERSION . '; +' . $this->site_url . ')';
	}

	/**
	 * Checks the HTTP status of a URL.
	 *
	 * Tries HEAD first, falls back to GET on 403/405/501.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url The URL to check.
	 * @return array{
	 *     http_status: int,
	 *     status_category: LinkStatus,
	 *     final_url: string|null,
	 *     response_time: int,
	 *     redirect_count: int,
	 *     redirect_chain: array<int, array{url: string, status: int}>|null,
	 *     is_redirect_loop: bool,
	 *     error: string|null,
	 * }
	 */
	public function check( string $url ): array {
		$url        = $this->ensure_absolute_url( $url );
		$start_time = microtime( true );

		// Try HEAD first.
		$response = $this->do_head( $url );

		// Handle WP_Error from HEAD.
		if ( is_wp_error( $response ) ) {
			return $this->build_error_result( $response, $start_time );
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		// Fallback to GET if HEAD is not supported.
		if ( $this->needs_get_fallback( $status_code ) ) {
			$response = $this->do_get( $url );

			if ( is_wp_error( $response ) ) {
				return $this->build_error_result( $response, $start_time );
			}

			$status_code = wp_remote_retrieve_response_code( $response );
		}

		$response_time  = (int) round( ( microtime( true ) - $start_time ) * 1000 );
		$final_url      = $this->detect_final_url( $response, $url );
		$redirect_count = $this->count_redirects( $response );
		$chain          = $this->extract_redirect_chain( $response );
		$is_loop        = null !== $chain && $this->detect_redirect_loop( $chain );

		return array(
			'http_status'      => $status_code,
			'status_category'  => LinkStatus::from_http_status( $status_code ),
			'final_url'        => $final_url !== $url ? $final_url : null,
			'response_time'    => $response_time,
			'redirect_count'   => $redirect_count,
			'redirect_chain'   => $chain,
			'is_redirect_loop' => $is_loop,
			'error'            => null,
		);
	}

	/**
	 * Performs a HEAD request.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url The URL to check.
	 * @return array|\WP_Error Response array or WP_Error on failure.
	 */
	private function do_head( string $url ): array|\WP_Error {
		return wp_remote_head( $url, $this->get_request_args() );
	}

	/**
	 * Performs a GET request (fallback).
	 *
	 * @since 1.0.0
	 *
	 * @param string $url The URL to check.
	 * @return array|\WP_Error Response array or WP_Error on failure.
	 */
	private function do_get( string $url ): array|\WP_Error {
		$args                        = $this->get_request_args();
		$args['limit_response_size'] = self::MAX_RESPONSE_SIZE;

		return wp_remote_get( $url, $args );
	}

	/**
	 * Determines if the HEAD response warrants a GET fallback.
	 *
	 * @since 1.0.0
	 *
	 * @param int $status_code HTTP status code from HEAD response.
	 * @return bool
	 */
	private function needs_get_fallback( int $status_code ): bool {
		return in_array( $status_code, self::GET_FALLBACK_CODES, true );
	}

	/**
	 * Resolves a relative URL to absolute using the site URL.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url The URL to resolve.
	 * @return string Absolute URL.
	 */
	private function ensure_absolute_url( string $url ): string {
		// Protocol-relative URLs.
		if ( str_starts_with( $url, '//' ) ) {
			$parsed = wp_parse_url( $this->site_url );
			$scheme = $parsed['scheme'] ?? 'https';
			return $scheme . ':' . $url;
		}

		// Relative URLs.
		if ( str_starts_with( $url, '/' ) ) {
			return rtrim( $this->site_url, '/' ) . $url;
		}

		return $url;
	}

	/**
	 * Builds the request arguments array.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	private function get_request_args(): array {
		return array(
			'timeout'     => $this->timeout,
			'redirection' => 5,
			'user-agent'  => $this->user_agent,
			'sslverify'   => true,
			'httpversion' => '1.1',
		);
	}

	/**
	 * Builds an error result from a WP_Error.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Error $error      The error object.
	 * @param float     $start_time Request start timestamp.
	 * @return array{http_status: int, status_category: LinkStatus, final_url: null, response_time: int, redirect_count: int, redirect_chain: null, is_redirect_loop: bool, error: string}
	 */
	private function build_error_result( \WP_Error $error, float $start_time ): array {
		$response_time = (int) round( ( microtime( true ) - $start_time ) * 1000 );
		$error_message = $error->get_error_message();
		$error_lower   = strtolower( $error_message );

		// Detect redirect loop errors (check before timeout since both may match).
		$is_loop = str_contains( $error_lower, 'too many redirects' )
			|| str_contains( $error_lower, 'redirect loop' );

		// Detect timeout errors.
		$is_timeout = ! $is_loop
			&& ( str_contains( $error_lower, 'timed out' )
				|| str_contains( $error_lower, 'timeout' )
				|| 'http_request_failed' === $error->get_error_code() );

		$final_error = $is_loop ? 'redirect_loop: ' . $error_message : $error_message;

		$status = match ( true ) {
			$is_timeout => LinkStatus::Timeout,
			default     => LinkStatus::Error,
		};

		return array(
			'http_status'      => 0,
			'status_category'  => $status,
			'final_url'        => null,
			'response_time'    => $response_time,
			'redirect_count'   => 0,
			'redirect_chain'   => null,
			'is_redirect_loop' => $is_loop,
			'error'            => $final_error,
		);
	}

	/**
	 * Extracts the full redirect chain from the response history.
	 *
	 * @since 1.1.0
	 *
	 * @param array $response The HTTP response array.
	 * @return array<int, array{url: string, status: int}>|null Null if no redirects.
	 */
	private function extract_redirect_chain( array $response ): ?array {
		$history = $response['http_response']?->get_response_object()->history ?? array();

		if ( ! is_countable( $history ) || 0 === count( $history ) ) {
			return null;
		}

		$chain = array();
		foreach ( $history as $entry ) {
			$chain[] = array(
				'url'    => $entry->url ?? '',
				'status' => (int) ( $entry->status_code ?? 0 ),
			);
		}

		return $chain;
	}

	/**
	 * Detects whether a redirect chain contains a loop (repeated URL).
	 *
	 * @since 1.1.0
	 *
	 * @param array<int, array{url: string, status: int}> $chain The redirect chain.
	 * @return bool True if a loop is detected.
	 */
	private function detect_redirect_loop( array $chain ): bool {
		$urls = array_column( $chain, 'url' );
		return count( $urls ) !== count( array_unique( $urls ) );
	}

	/**
	 * Detects the final URL after redirects.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $response The HTTP response array.
	 * @param string $original The original requested URL.
	 * @return string The final URL.
	 */
	private function detect_final_url( array $response, string $original ): string {
		// Check for redirect history in the response.
		$redirects = $response['http_response']?->get_response_object()->url ?? null;
		if ( null !== $redirects && $redirects !== $original ) {
			return $redirects;
		}

		return $original;
	}

	/**
	 * Counts the number of redirect hops.
	 *
	 * @since 1.0.0
	 *
	 * @param array $response The HTTP response array.
	 * @return int Number of redirects.
	 */
	private function count_redirects( array $response ): int {
		$history = $response['http_response']?->get_response_object()->history ?? array();
		return is_countable( $history ) ? count( $history ) : 0;
	}
}
