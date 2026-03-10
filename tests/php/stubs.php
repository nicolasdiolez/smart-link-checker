<?php
/**
 * WordPress function stubs for unit testing without loading WordPress.
 *
 * These stubs provide minimal implementations of WordPress functions
 * used by the plugin classes under test.
 *
 * @package FlavorLinkChecker\Tests
 */

// Translation functions.
if ( ! function_exists( '__' ) ) {
	/**
	 * Stub for WordPress translation function.
	 *
	 * @param string $text   Text to translate.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

// URL functions.
if ( ! function_exists( 'home_url' ) ) {
	/**
	 * Stub for WordPress home_url().
	 *
	 * @param string $path Optional path to append.
	 * @return string
	 */
	function home_url( string $path = '' ): string {
		return 'https://example.com' . $path;
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	/**
	 * Stub for WordPress wp_parse_url().
	 *
	 * @param string $url       The URL to parse.
	 * @param int    $component Optional component to retrieve.
	 * @return mixed
	 */
	function wp_parse_url( string $url, int $component = -1 ): mixed {
		return parse_url( $url, $component );
	}
}

// Filter functions.
if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Stub for WordPress apply_filters().
	 *
	 * Returns the value unchanged (no filters applied in tests).
	 *
	 * @param string $hook_name Filter name.
	 * @param mixed  $value     Value to filter.
	 * @param mixed  ...$args   Additional arguments.
	 * @return mixed
	 */
	function apply_filters( string $hook_name, mixed $value, mixed ...$args ): mixed {
		return $value;
	}
}

// HTTP functions for HttpChecker tests.
if ( ! function_exists( 'wp_remote_head' ) ) {
	/**
	 * Stub for WordPress wp_remote_head().
	 *
	 * Configure via WpHttpStub::$next_response before calling.
	 *
	 * @param string $url  The URL.
	 * @param array  $args Request arguments.
	 * @return array|\WP_Error
	 */
	function wp_remote_head( string $url, array $args = [] ): array|\WP_Error {
		return WpHttpStub::get_response( 'HEAD', $url );
	}
}

if ( ! function_exists( 'wp_remote_get' ) ) {
	/**
	 * Stub for WordPress wp_remote_get().
	 *
	 * Configure via WpHttpStub::$next_response before calling.
	 *
	 * @param string $url  The URL.
	 * @param array  $args Request arguments.
	 * @return array|\WP_Error
	 */
	function wp_remote_get( string $url, array $args = [] ): array|\WP_Error {
		return WpHttpStub::get_response( 'GET', $url );
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	/**
	 * Stub for WordPress wp_remote_retrieve_response_code().
	 *
	 * @param array|\WP_Error $response HTTP response.
	 * @return int
	 */
	function wp_remote_retrieve_response_code( array|\WP_Error $response ): int {
		if ( is_wp_error( $response ) ) {
			return 0;
		}
		return (int) ( $response['response']['code'] ?? 0 );
	}
}

if ( ! function_exists( 'wp_remote_retrieve_header' ) ) {
	/**
	 * Stub for WordPress wp_remote_retrieve_header().
	 *
	 * @param array|\WP_Error $response HTTP response.
	 * @param string          $header   Header name.
	 * @return string
	 */
	function wp_remote_retrieve_header( array|\WP_Error $response, string $header ): string {
		if ( is_wp_error( $response ) ) {
			return '';
		}
		return $response['headers'][ strtolower( $header ) ] ?? '';
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * Stub for WordPress is_wp_error().
	 *
	 * @param mixed $thing Value to check.
	 * @return bool
	 */
	function is_wp_error( mixed $thing ): bool {
		return $thing instanceof \WP_Error;
	}
}

// Database constants.
if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}
if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

// wpdb stub class.
if ( ! class_exists( 'wpdb' ) ) {
	/**
	 * Minimal wpdb stub for testing database-dependent classes.
	 */
	class wpdb {
		/** @var string */
		public string $prefix = 'wp_';

		/** @var int */
		public int $insert_id = 0;

		public function prepare( $query, ...$args ): string {
			return (string) $query;
		}

		public function get_var( $query = null, $x = 0, $y = 0 ): mixed {
			return null;
		}

		public function get_row( $query = null, $output = OBJECT, $y = 0 ): ?object {
			return null;
		}

		public function get_results( $query = null, $output = OBJECT ): array {
			return [];
		}

		public function query( $query ): int|bool {
			return 0;
		}

		public function esc_like( $text ): string {
			return addcslashes( (string) $text, '_%\\' );
		}
	}
}

// WP_Error stub class.
if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Minimal WP_Error stub for testing.
	 */
	class WP_Error {
		private string $code;
		private string $message;

		public function __construct( string $code = '', string $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}

		public function get_error_code(): string {
			return $this->code;
		}

		public function get_error_message(): string {
			return $this->message;
		}
	}
}

/**
 * Configurable HTTP stub for testing HttpChecker.
 *
 * Usage:
 *   WpHttpStub::$next_response = [ 'response' => [ 'code' => 200 ], 'headers' => [] ];
 *   WpHttpStub::$next_response = new WP_Error( 'timeout', 'Connection timed out' );
 */
class WpHttpStub {

	/**
	 * Next response to return from wp_remote_head() or wp_remote_get().
	 *
	 * @var array|\WP_Error|null
	 */
	public static array|\WP_Error|null $next_response = null;

	/**
	 * Separate GET response (for testing HEAD->GET fallback).
	 *
	 * @var array|\WP_Error|null
	 */
	public static array|\WP_Error|null $get_response = null;

	/**
	 * Log of requests made.
	 *
	 * @var array<int, array{method: string, url: string}>
	 */
	public static array $request_log = [];

	/**
	 * Returns the configured response.
	 *
	 * @param string $method HTTP method (HEAD or GET).
	 * @param string $url    The requested URL.
	 * @return array|\WP_Error
	 */
	public static function get_response( string $method, string $url ): array|\WP_Error {
		self::$request_log[] = [ 'method' => $method, 'url' => $url ];

		if ( 'GET' === $method && null !== self::$get_response ) {
			return self::$get_response;
		}

		return self::$next_response ?? [
			'response' => [ 'code' => 200 ],
			'headers'  => [],
			'http_response' => null,
		];
	}

	/**
	 * Resets the stub state.
	 */
	public static function reset(): void {
		self::$next_response = null;
		self::$get_response  = null;
		self::$request_log   = [];
	}
}
