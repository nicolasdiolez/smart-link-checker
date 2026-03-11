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
	 * Hosts and IP patterns blocked for SSRF prevention.
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	private const BLOCKED_HOSTS = array(
		'metadata.google.internal',
		'metadata.google.internal.',
	);

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
		$this->site_url   = '' !== $site_url ? $site_url : \home_url();
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
		$results = $this->check_batch( array( $url ) );
		return $results[$url];
	}

	/**
	 * Checks a batch of URLs in parallel.
	 *
	 * @since 1.0.0
	 *
	 * @param string[] $urls Array of URLs to check.
	 * @return array<string, array{
	 *     http_status: int,
	 *     status_category: LinkStatus,
	 *     final_url: string|null,
	 *     response_time: int,
	 *     redirect_count: int,
	 *     redirect_chain: array<int, array{url: string, status: int}>|null,
	 *     is_redirect_loop: bool,
	 *     error: string|null,
	 * }> Keyed by the original URL.
	 */
	public function check_batch( array $urls ): array {
		$results    = array();
		$start_time = \microtime( true );

		// Prepare requests, filtering out SSRF-unsafe URLs.
		$requests = array();
		foreach ( $urls as $url ) {
			$abs_url = $this->ensure_absolute_url( $url );

			if ( $this->is_unsafe_url( $abs_url ) ) {
				$results[ $url ] = array(
					'http_status'      => 0,
					'status_category'  => LinkStatus::Skipped,
					'final_url'        => null,
					'response_time'    => 0,
					'redirect_count'   => 0,
					'redirect_chain'   => null,
					'is_redirect_loop' => false,
					'error'            => 'ssrf_blocked',
				);
				continue;
			}

			$requests[$url] = array(
				'url'     => $abs_url,
				'options' => \array_merge( $this->get_request_args(), array( 'type' => 'HEAD' ) ),
			);
		}

		// Perform parallel HEAD requests.
		$responses = \WpOrg\Requests\Requests::request_multiple( $requests );

		// Process first pass (HEAD).
		$fallback_urls = array();
		foreach ( $urls as $url ) {
			// Skip URLs already handled (e.g. SSRF-blocked).
			if ( isset( $results[ $url ] ) ) {
				continue;
			}

			$response = $responses[$url] ?? null;

			if ( null === $response || $response instanceof \WpOrg\Requests\Exception || $response instanceof \WP_Error ) {
				// Convert to result.
				if ( $response instanceof \WpOrg\Requests\Exception ) {
					$response = new \WP_Error( 'http_check_failed', $response->getMessage() );
				} elseif ( null === $response ) {
					$response = new \WP_Error( 'http_check_failed', 'Empty response' );
				}
				$results[$url] = $this->build_error_result( $response, $start_time );
				continue;
			}

			$status_code = (int) ( $response->status_code ?? 0 );

			if ( $this->needs_get_fallback( $status_code ) ) {
				$fallback_urls[] = $url;
				continue;
			}

			$results[$url] = $this->build_success_result( $response, $url, $start_time );
		}

		// Second pass (GET fallback).
		if ( ! empty( $fallback_urls ) ) {
			$fallback_requests = array();
			foreach ( $fallback_urls as $url ) {
				$abs_url = $this->ensure_absolute_url( $url );
				$fallback_requests[$url] = array(
					'url'     => $abs_url,
					'options' => \array_merge( 
						$this->get_request_args(), 
						array( 
							'type'                 => 'GET',
							'limit_response_size' => self::MAX_RESPONSE_SIZE,
						) 
					),
				);
			}

			$fallback_responses = \WpOrg\Requests\Requests::request_multiple( $fallback_requests );

			foreach ( $fallback_urls as $url ) {
				$response = $fallback_responses[$url] ?? null;

				if ( null === $response || $response instanceof \WpOrg\Requests\Exception || $response instanceof \WP_Error ) {
					if ( $response instanceof \WpOrg\Requests\Exception ) {
						$response = new \WP_Error( 'http_request_failed', $response->getMessage() );
					} elseif ( null === $response ) {
						$response = new \WP_Error( 'http_request_failed', 'Empty response' );
					}
					$results[$url] = $this->build_error_result( $response, $start_time );
					continue;
				}

				$results[$url] = $this->build_success_result( $response, $url, $start_time );
			}
		}

		return $results;
	}

	/**
	 * Builds a success result from a Requests response.
	 *
	 * @since 1.0.0
	 *
	 * @param \WpOrg\Requests\Response $response   The response object.
	 * @param string                   $url        Original URL.
	 * @param float                    $start_time Start time.
	 * @return array
	 */
	private function build_success_result( $response, string $url, float $start_time ): array {
		$status_code    = (int) ( $response->status_code ?? 0 );
		$response_time  = (int) \round( ( \microtime( true ) - $start_time ) * 1000 );
		
		// In Requests library, final URL is available directly if was handled.
		$final_url      = $response->url ?? $url;
		
		// Redirect history.
		$history        = $response->history ?? array();
		$redirect_count = \count( $history );
		
		$chainAttrs = array();
		foreach ( $history as $entry ) {
			$chainAttrs[] = array(
				'url'    => $entry->url ?? '',
				'status' => (int) ( $entry->status_code ?? 0 ),
			);
		}
		$chain   = ! empty( $chainAttrs ) ? $chainAttrs : null;
		$is_loop = null !== $chain && $this->detect_redirect_loop( $chain );

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
	 * Determines if the HEAD response warrants a GET fallback.
	 *
	 * @since 1.0.0
	 *
	 * @param int $status_code HTTP status code from HEAD response.
	 * @return bool
	 */
	private function needs_get_fallback( int $status_code ): bool {
		return \in_array( $status_code, self::GET_FALLBACK_CODES, true );
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
		if ( \str_starts_with( $url, '//' ) ) {
			$parsed = \wp_parse_url( $this->site_url );
			$scheme = $parsed['scheme'] ?? 'https';
			return $scheme . ':' . $url;
		}

		// Relative URLs.
		if ( \str_starts_with( $url, '/' ) ) {
			return \rtrim( $this->site_url, '/' ) . $url;
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
		$response_time = (int) \round( ( \microtime( true ) - $start_time ) * 1000 );
		$error_message = $error->get_error_message();
		$error_lower   = \strtolower( $error_message );

		// Detect redirect loop errors.
		$is_loop = \str_contains( $error_lower, 'too many redirects' )
			|| \str_contains( $error_lower, 'redirect loop' );

		// Detect timeout errors.
		$is_timeout = ! $is_loop
			&& ( \str_contains( $error_lower, 'timed out' )
				|| \str_contains( $error_lower, 'timeout' )
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
	 * Detects whether a redirect chain contains a loop (repeated URL).
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array{url: string, status: int}> $chain The redirect chain.
	 * @return bool True if a loop is detected.
	 */
	private function detect_redirect_loop( array $chain ): bool {
		$urls = \array_column( $chain, 'url' );
		return \count( $urls ) !== \count( \array_unique( $urls ) );
	}

	/**
	 * Checks whether a URL targets a private/internal IP or cloud metadata endpoint.
	 *
	 * Prevents SSRF attacks by blocking requests to:
	 * - Private IPv4 ranges (10.x, 172.16-31.x, 192.168.x)
	 * - Loopback (127.x, ::1)
	 * - Link-local (169.254.x)
	 * - Cloud metadata (169.254.169.254, metadata.google.internal)
	 *
	 * @since 1.0.0
	 *
	 * @param string $url The absolute URL to validate.
	 * @return bool True if the URL is unsafe and should be blocked.
	 */
	private function is_unsafe_url( string $url ): bool {
		$parsed = \wp_parse_url( $url );
		$host   = \strtolower( $parsed['host'] ?? '' );

		if ( '' === $host ) {
			return false;
		}

		// Block known metadata hostnames.
		if ( \in_array( $host, self::BLOCKED_HOSTS, true ) ) {
			return true;
		}

		// Resolve hostname to IP for range checks.
		$ip = $host;
		if ( ! \filter_var( $host, FILTER_VALIDATE_IP ) ) {
			$resolved = \gethostbyname( $host );
			// gethostbyname returns the hostname if resolution fails.
			if ( $resolved === $host ) {
				return false;
			}
			$ip = $resolved;
		}

		// Block private and reserved IP ranges.
		return ! \filter_var(
			$ip,
			FILTER_VALIDATE_IP,
			FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
		);
	}
}
