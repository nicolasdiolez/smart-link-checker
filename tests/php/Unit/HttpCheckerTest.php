<?php
/**
 * Unit tests for HttpChecker.
 *
 * @package FlavorLinkChecker\Tests\Unit
 */

declare( strict_types=1 );

namespace FlavorLinkChecker\Tests\Unit;

use FlavorLinkChecker\Models\Enums\LinkStatus;
use FlavorLinkChecker\Scanner\HttpChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;
use WpHttpStub;

/**
 * Unit tests for HttpChecker.
 */
#[CoversClass(HttpChecker::class)]
class HttpCheckerTest extends TestCase {

	private HttpChecker $checker;

	protected function setUp(): void {
		parent::setUp();
		WpHttpStub::reset();
		$this->checker = new HttpChecker( timeout: 5, site_url: 'https://example.com' );
	}

	protected function tearDown(): void {
		WpHttpStub::reset();
		parent::tearDown();
	}

	public function test_check_returns_ok_for_200(): void {
		WpHttpStub::$next_response = $this->make_response( 200 );

		$result = $this->checker->check( 'https://target.com/page' );

		$this->assertSame( 200, $result['http_status'] );
		$this->assertSame( LinkStatus::Ok, $result['status_category'] );
		$this->assertNull( $result['error'] );
	}

	public function test_check_returns_redirect_for_301(): void {
		WpHttpStub::$next_response = $this->make_response( 301 );

		$result = $this->checker->check( 'https://target.com/old' );

		$this->assertSame( 301, $result['http_status'] );
		$this->assertSame( LinkStatus::Redirect, $result['status_category'] );
	}

	public function test_check_returns_redirect_for_302(): void {
		WpHttpStub::$next_response = $this->make_response( 302 );

		$result = $this->checker->check( 'https://target.com/temp' );

		$this->assertSame( 302, $result['http_status'] );
		$this->assertSame( LinkStatus::Redirect, $result['status_category'] );
	}

	public function test_check_returns_broken_for_404(): void {
		WpHttpStub::$next_response = $this->make_response( 404 );

		$result = $this->checker->check( 'https://target.com/missing' );

		$this->assertSame( 404, $result['http_status'] );
		$this->assertSame( LinkStatus::Broken, $result['status_category'] );
	}

	public function test_check_returns_broken_for_410(): void {
		WpHttpStub::$next_response = $this->make_response( 410 );

		$result = $this->checker->check( 'https://target.com/gone' );

		$this->assertSame( 410, $result['http_status'] );
		$this->assertSame( LinkStatus::Broken, $result['status_category'] );
	}

	public function test_check_returns_error_for_500(): void {
		WpHttpStub::$next_response = $this->make_response( 500 );

		$result = $this->checker->check( 'https://target.com/error' );

		$this->assertSame( 500, $result['http_status'] );
		$this->assertSame( LinkStatus::Error, $result['status_category'] );
	}

	public function test_check_returns_timeout_on_timeout_error(): void {
		WpHttpStub::$next_response = new \WP_Error( 'http_request_failed', 'Connection timed out' );

		$result = $this->checker->check( 'https://target.com/slow' );

		$this->assertSame( 0, $result['http_status'] );
		$this->assertSame( LinkStatus::Timeout, $result['status_category'] );
		$this->assertStringContainsString( 'timed out', $result['error'] );
	}

	public function test_check_returns_error_on_generic_wp_error(): void {
		WpHttpStub::$next_response = new \WP_Error( 'ssl_error', 'SSL certificate problem' );

		$result = $this->checker->check( 'https://target.com/bad-ssl' );

		$this->assertSame( 0, $result['http_status'] );
		$this->assertSame( LinkStatus::Error, $result['status_category'] );
		$this->assertStringContainsString( 'SSL', $result['error'] );
	}

	public function test_check_falls_back_to_get_on_405(): void {
		WpHttpStub::$next_response = $this->make_response( 405 );
		WpHttpStub::$get_response  = $this->make_response( 200 );

		$result = $this->checker->check( 'https://target.com/no-head' );

		$this->assertSame( 200, $result['http_status'] );
		$this->assertSame( LinkStatus::Ok, $result['status_category'] );

		// Verify both HEAD and GET were called.
		$methods = array_column( WpHttpStub::$request_log, 'method' );
		$this->assertContains( 'HEAD', $methods );
		$this->assertContains( 'GET', $methods );
	}

	public function test_check_falls_back_to_get_on_403(): void {
		WpHttpStub::$next_response = $this->make_response( 403 );
		WpHttpStub::$get_response  = $this->make_response( 200 );

		$result = $this->checker->check( 'https://target.com/forbidden-head' );

		$this->assertSame( 200, $result['http_status'] );
		$this->assertSame( LinkStatus::Ok, $result['status_category'] );
	}

	public function test_check_captures_response_time(): void {
		WpHttpStub::$next_response = $this->make_response( 200 );

		$result = $this->checker->check( 'https://target.com/page' );

		$this->assertIsInt( $result['response_time'] );
		$this->assertGreaterThanOrEqual( 0, $result['response_time'] );
	}

	public function test_check_resolves_relative_url(): void {
		WpHttpStub::$next_response = $this->make_response( 200 );

		$this->checker->check( '/internal-page' );

		// Verify the request was made with the absolute URL.
		$this->assertNotEmpty( WpHttpStub::$request_log );
		$this->assertStringStartsWith( 'https://example.com/', WpHttpStub::$request_log[0]['url'] );
	}

	public function test_check_resolves_protocol_relative_url(): void {
		WpHttpStub::$next_response = $this->make_response( 200 );

		$this->checker->check( '//cdn.example.com/resource' );

		$this->assertStringStartsWith( 'https://', WpHttpStub::$request_log[0]['url'] );
	}

	// --- Phase 5: Redirect chain and loop detection ---

	public function test_check_returns_redirect_chain_keys(): void {
		WpHttpStub::$next_response = $this->make_response( 200 );

		$result = $this->checker->check( 'https://target.com/page' );

		$this->assertArrayHasKey( 'redirect_chain', $result );
		$this->assertArrayHasKey( 'is_redirect_loop', $result );
	}

	public function test_check_null_chain_for_direct_200(): void {
		WpHttpStub::$next_response = $this->make_response( 200 );

		$result = $this->checker->check( 'https://target.com/page' );

		$this->assertNull( $result['redirect_chain'] );
		$this->assertFalse( $result['is_redirect_loop'] );
	}

	public function test_check_extracts_redirect_chain(): void {
		WpHttpStub::$next_response = $this->make_response_with_history( 200, [
			(object) [ 'url' => 'https://a.com/', 'status_code' => 301 ],
			(object) [ 'url' => 'https://b.com/', 'status_code' => 302 ],
		] );

		$result = $this->checker->check( 'https://a.com/' );

		$this->assertIsArray( $result['redirect_chain'] );
		$this->assertCount( 2, $result['redirect_chain'] );
		$this->assertSame( 'https://a.com/', $result['redirect_chain'][0]['url'] );
		$this->assertSame( 301, $result['redirect_chain'][0]['status'] );
		$this->assertFalse( $result['is_redirect_loop'] );
	}

	public function test_check_detects_redirect_loop_in_chain(): void {
		WpHttpStub::$next_response = $this->make_response_with_history( 200, [
			(object) [ 'url' => 'https://a.com/', 'status_code' => 301 ],
			(object) [ 'url' => 'https://b.com/', 'status_code' => 302 ],
			(object) [ 'url' => 'https://a.com/', 'status_code' => 301 ],
		] );

		$result = $this->checker->check( 'https://a.com/' );

		$this->assertTrue( $result['is_redirect_loop'] );
	}

	public function test_check_detects_redirect_loop_from_wp_error(): void {
		WpHttpStub::$next_response = new \WP_Error( 'http_request_failed', 'Too many redirects' );

		$result = $this->checker->check( 'https://target.com/loop' );

		$this->assertTrue( $result['is_redirect_loop'] );
		$this->assertStringStartsWith( 'redirect_loop:', $result['error'] );
		$this->assertSame( LinkStatus::Error, $result['status_category'] );
	}

	// --- SSRF Prevention ---

	public function test_check_blocks_loopback_ip(): void {
		WpHttpStub::$next_response = $this->make_response( 200 );

		$result = $this->checker->check( 'http://127.0.0.1/admin' );

		$this->assertSame( 0, $result['http_status'] );
		$this->assertSame( LinkStatus::Skipped, $result['status_category'] );
		$this->assertSame( 'ssrf_blocked', $result['error'] );
	}

	public function test_check_blocks_private_ip(): void {
		WpHttpStub::$next_response = $this->make_response( 200 );

		$result = $this->checker->check( 'http://192.168.1.1/admin' );

		$this->assertSame( 0, $result['http_status'] );
		$this->assertSame( LinkStatus::Skipped, $result['status_category'] );
		$this->assertSame( 'ssrf_blocked', $result['error'] );
	}

	public function test_check_blocks_cloud_metadata_ip(): void {
		WpHttpStub::$next_response = $this->make_response( 200 );

		$result = $this->checker->check( 'http://169.254.169.254/latest/meta-data/' );

		$this->assertSame( 0, $result['http_status'] );
		$this->assertSame( LinkStatus::Skipped, $result['status_category'] );
		$this->assertSame( 'ssrf_blocked', $result['error'] );
	}

	public function test_check_allows_public_url(): void {
		WpHttpStub::$next_response = $this->make_response( 200 );

		$result = $this->checker->check( 'https://google.com/page' );

		$this->assertSame( 200, $result['http_status'] );
		$this->assertSame( LinkStatus::Ok, $result['status_category'] );
	}

	/**
	 * Helper to create a mock HTTP response.
	 *
	 * @param int   $code    HTTP status code.
	 * @param array $headers Response headers.
	 * @return array
	 */
	private function make_response( int $code, array $headers = [] ): array {
		return [
			'response'      => [ 'code' => $code ],
			'headers'       => $headers,
			'http_response' => null,
		];
	}

	/**
	 * Helper to create a mock HTTP response with redirect history.
	 *
	 * @param int   $code    HTTP status code.
	 * @param array $history Array of history entry objects with url and status_code.
	 * @return array
	 */
	private function make_response_with_history( int $code, array $history ): array {
		$response_object          = new \stdClass();
		$response_object->url     = end( $history )->url ?? '';
		$response_object->history = $history;

		$http_response = new class( $response_object ) {
			public function __construct( private readonly \stdClass $obj ) {}
			public function get_response_object(): \stdClass {
				return $this->obj;
			}
		};

		return [
			'response'      => [ 'code' => $code ],
			'headers'       => [],
			'http_response' => $http_response,
		];
	}
}
