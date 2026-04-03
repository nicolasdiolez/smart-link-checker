<?php
/**
 * Unit tests for LinkHtmlEditor.
 *
 * @package MuriLinkTracker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace MuriLinkTracker\Tests\Unit;

use MuriLinkTracker\Scanner\LinkHtmlEditor;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the LinkHtmlEditor class which handles DOM-based link modifications.
 *
 * @since 1.0.0
 * @covers \MuriLinkTracker\Scanner\LinkHtmlEditor
 */
class LinkHtmlEditorTest extends TestCase {

	/**
	 * LinkHtmlEditor instance with a mock wpdb.
	 *
	 * @since 1.0.0
	 * @var LinkHtmlEditor
	 */
	private LinkHtmlEditor $editor;

	/**
	 * Sets up the test instance.
	 *
	 * @since 1.0.0
	 */
	protected function setUp(): void {
		parent::setUp();
		// wpdb is stubbed in tests/php/stubs.php (loaded by PHPUnit bootstrap).
		$this->editor = new LinkHtmlEditor( new \wpdb() );
	}

	// -------------------------------------------------------------------------
	// replace_link_in_html — URL replacement
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * @since 1.0.0
	 */
	public function replace_link_in_html_replaces_url(): void {
		$html     = '<p>Visit <a href="https://old.com">Old</a> now.</p>';
		$result   = $this->editor->replace_link_in_html( $html, 'https://old.com', 'https://new.com', null );

		$this->assertStringContainsString( 'href="https://new.com"', $result );
		$this->assertStringNotContainsString( 'href="https://old.com"', $result );
	}

	/**
	 * @test
	 * @since 1.0.0
	 */
	public function replace_link_in_html_replaces_rel(): void {
		$html   = '<p><a href="https://example.com">Link</a></p>';
		$result = $this->editor->replace_link_in_html( $html, 'https://example.com', null, 'nofollow' );

		$this->assertStringContainsString( 'rel="nofollow"', $result );
	}

	/**
	 * @test
	 * @since 1.0.0
	 */
	public function replace_link_in_html_removes_rel_when_empty_string(): void {
		$html   = '<p><a href="https://example.com" rel="nofollow">Link</a></p>';
		$result = $this->editor->replace_link_in_html( $html, 'https://example.com', null, '' );

		$this->assertStringNotContainsString( 'rel=', $result );
	}

	/**
	 * @test
	 * @since 1.0.0
	 */
	public function replace_link_in_html_replaces_both_url_and_rel(): void {
		$html   = '<p><a href="https://old.com" rel="dofollow">Link</a></p>';
		$result = $this->editor->replace_link_in_html( $html, 'https://old.com', 'https://new.com', 'nofollow' );

		$this->assertStringContainsString( 'href="https://new.com"', $result );
		$this->assertStringContainsString( 'rel="nofollow"', $result );
	}

	/**
	 * @test
	 * @since 1.0.0
	 */
	public function replace_link_in_html_returns_unchanged_when_url_not_found(): void {
		$html   = '<p><a href="https://other.com">Link</a></p>';
		$result = $this->editor->replace_link_in_html( $html, 'https://notfound.com', 'https://new.com', null );

		$this->assertSame( $html, $result );
	}

	/**
	 * @test
	 * @since 1.0.0
	 */
	public function replace_link_in_html_returns_empty_string_for_empty_html(): void {
		$result = $this->editor->replace_link_in_html( '', 'https://example.com', 'https://new.com', null );

		$this->assertSame( '', $result );
	}

	// -------------------------------------------------------------------------
	// unlink_in_html
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * @since 1.0.0
	 */
	public function unlink_in_html_removes_link_and_preserves_text(): void {
		$html   = '<p>Click <a href="https://example.com">here</a> now.</p>';
		$result = $this->editor->unlink_in_html( $html, 'https://example.com' );

		$this->assertStringContainsString( 'here', $result );
		$this->assertStringNotContainsString( '<a ', $result );
		$this->assertStringNotContainsString( 'href=', $result );
	}

	/**
	 * @test
	 * @since 1.0.0
	 */
	public function unlink_in_html_removes_multiple_occurrences(): void {
		$html = '<p><a href="https://example.com">One</a> and <a href="https://example.com">Two</a>.</p>';

		$result = $this->editor->unlink_in_html( $html, 'https://example.com' );

		$this->assertStringContainsString( 'One', $result );
		$this->assertStringContainsString( 'Two', $result );
		$this->assertStringNotContainsString( 'href=', $result );
	}

	/**
	 * @test
	 * @since 1.0.0
	 */
	public function unlink_in_html_leaves_other_links_intact(): void {
		$html   = '<p><a href="https://remove.com">Remove</a> and <a href="https://keep.com">Keep</a>.</p>';
		$result = $this->editor->unlink_in_html( $html, 'https://remove.com' );

		$this->assertStringContainsString( 'href="https://keep.com"', $result );
		$this->assertStringNotContainsString( 'href="https://remove.com"', $result );
	}

	/**
	 * @test
	 * @since 1.0.0
	 */
	public function unlink_in_html_returns_unchanged_when_url_not_found(): void {
		$html   = '<p><a href="https://other.com">Link</a></p>';
		$result = $this->editor->unlink_in_html( $html, 'https://notfound.com' );

		$this->assertSame( $html, $result );
	}

	/**
	 * @test
	 * @since 1.0.0
	 */
	public function unlink_in_html_returns_empty_string_for_empty_html(): void {
		$result = $this->editor->unlink_in_html( '', 'https://example.com' );

		$this->assertSame( '', $result );
	}

	// -------------------------------------------------------------------------
	// sanitize_csv_value (via CsvExporter — tested indirectly here for awareness)
	// -------------------------------------------------------------------------
}
