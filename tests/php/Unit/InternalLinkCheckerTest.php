<?php
/**
 * Unit tests for InternalLinkChecker.
 *
 * @package FlavorLinkChecker\Tests\Unit
 */

declare( strict_types=1 );

namespace FlavorLinkChecker\Tests\Unit;

use FlavorLinkChecker\Models\Enums\LinkStatus;
use FlavorLinkChecker\Scanner\InternalLinkChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;
use UrlToPostIdStub;

/**
 * Unit tests for InternalLinkChecker.
 */
#[CoversClass(InternalLinkChecker::class)]
class InternalLinkCheckerTest extends TestCase {

	private InternalLinkChecker $checker;

	protected function setUp(): void {
		parent::setUp();
		UrlToPostIdStub::reset();

		// Create the upload directory for media tests.
		if ( ! is_dir( '/tmp/wp-uploads/2024/01' ) ) {
			mkdir( '/tmp/wp-uploads/2024/01', 0777, true );
		}

		$this->checker = new InternalLinkChecker(
			site_url: 'https://example.com',
			upload_basedir: '/tmp/wp-uploads',
			upload_baseurl: 'https://example.com/wp-content/uploads',
		);
	}

	protected function tearDown(): void {
		UrlToPostIdStub::reset();

		// Clean up test files.
		if ( file_exists( '/tmp/wp-uploads/2024/01/photo.jpg' ) ) {
			unlink( '/tmp/wp-uploads/2024/01/photo.jpg' );
		}

		parent::tearDown();
	}

	public function test_check_published_page_returns_ok(): void {
		UrlToPostIdStub::$next_id = 42;

		// get_post stub in stubs.php returns an object with post_status = 'publish'.
		UrlToPostIdStub::$posts[42] = (object) array(
			'ID'          => 42,
			'post_status' => 'publish',
		);

		$result = $this->checker->check( '/about/' );

		$this->assertSame( 200, $result['http_status'] );
		$this->assertSame( LinkStatus::Ok, $result['status_category'] );
		$this->assertNull( $result['error'] );
	}

	public function test_check_nonexistent_page_returns_assumed_ok(): void {
		UrlToPostIdStub::$next_id = 0;

		$result = $this->checker->check( '/unknown-page/' );

		// Unresolvable URLs are assumed OK (to avoid false positives).
		$this->assertSame( 200, $result['http_status'] );
		$this->assertSame( LinkStatus::Ok, $result['status_category'] );
		$this->assertSame( 'unresolvable_assumed_ok', $result['error'] );
	}

	public function test_check_draft_page_returns_broken(): void {
		UrlToPostIdStub::$next_id = 99;

		UrlToPostIdStub::$posts[99] = (object) array(
			'ID'          => 99,
			'post_status' => 'draft',
		);

		$result = $this->checker->check( '/draft-page/' );

		$this->assertSame( 404, $result['http_status'] );
		$this->assertSame( LinkStatus::Broken, $result['status_category'] );
		$this->assertSame( 'post_not_published', $result['error'] );
	}

	public function test_check_media_file_exists_returns_ok(): void {
		// Create a test file.
		file_put_contents( '/tmp/wp-uploads/2024/01/photo.jpg', 'fake image data' );

		$result = $this->checker->check( 'https://example.com/wp-content/uploads/2024/01/photo.jpg' );

		$this->assertSame( 200, $result['http_status'] );
		$this->assertSame( LinkStatus::Ok, $result['status_category'] );
	}

	public function test_check_media_file_missing_returns_broken(): void {
		$result = $this->checker->check( 'https://example.com/wp-content/uploads/2024/01/missing.jpg' );

		$this->assertSame( 404, $result['http_status'] );
		$this->assertSame( LinkStatus::Broken, $result['status_category'] );
	}

	public function test_check_returns_correct_result_format(): void {
		UrlToPostIdStub::$next_id = 0;

		$result = $this->checker->check( '/any-page/' );

		$this->assertArrayHasKey( 'http_status', $result );
		$this->assertArrayHasKey( 'status_category', $result );
		$this->assertArrayHasKey( 'final_url', $result );
		$this->assertArrayHasKey( 'response_time', $result );
		$this->assertArrayHasKey( 'redirect_count', $result );
		$this->assertArrayHasKey( 'redirect_chain', $result );
		$this->assertArrayHasKey( 'is_redirect_loop', $result );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertSame( 0, $result['redirect_count'] );
		$this->assertNull( $result['redirect_chain'] );
		$this->assertFalse( $result['is_redirect_loop'] );
	}

	public function test_check_batch_returns_keyed_results(): void {
		UrlToPostIdStub::$next_id = 0;

		$urls    = array( '/page-a/', '/page-b/' );
		$results = $this->checker->check_batch( $urls );

		$this->assertCount( 2, $results );
		$this->assertArrayHasKey( '/page-a/', $results );
		$this->assertArrayHasKey( '/page-b/', $results );
	}

	public function test_check_resolves_relative_url(): void {
		UrlToPostIdStub::$next_id = 0;

		// Relative URL should be resolved against site_url.
		$result = $this->checker->check( '/contact/' );

		// Should succeed (unresolvable = assumed OK).
		$this->assertSame( 200, $result['http_status'] );
	}
}
