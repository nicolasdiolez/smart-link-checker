<?php
/**
 * HTML link editor — modifies anchor tags within post content.
 *
 * @package FlavorLinkChecker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace FlavorLinkChecker\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * Provides safe DOM-based methods to replace or remove links inside HTML content
 * and persist changes directly to the database without touching post_modified.
 *
 * @since 1.0.0
 */
class LinkHtmlEditor {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param \wpdb $wpdb WordPress database instance.
	 */
	public function __construct(
		private readonly \wpdb $wpdb,
	) {}

	/**
	 * Replaces a link's URL and/or rel attribute in HTML content.
	 *
	 * Uses DOMDocument for safe, encoding-aware parsing. Returns the
	 * original HTML unchanged if the URL is not found.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $html    HTML content to modify.
	 * @param string      $old_url URL to search for.
	 * @param string|null $new_url New URL (null = keep current).
	 * @param string|null $new_rel New rel attribute value (null = keep current; empty string = remove attribute).
	 * @return string Updated HTML.
	 */
	public function replace_link_in_html( string $html, string $old_url, ?string $new_url, ?string $new_rel ): string {
		if ( empty( $html ) ) {
			return $html;
		}

		$dom = new \DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		$modified = false;

		foreach ( $dom->getElementsByTagName( 'a' ) as $node ) {
			/** @var \DOMElement $node */
			if ( $node->getAttribute( 'href' ) !== $old_url ) {
				continue;
			}

			if ( null !== $new_url ) {
				$node->setAttribute( 'href', $new_url );
				$modified = true;
			}

			if ( null !== $new_rel ) {
				if ( '' === $new_rel ) {
					$node->removeAttribute( 'rel' );
				} else {
					$node->setAttribute( 'rel', $new_rel );
				}
				$modified = true;
			}
		}

		if ( ! $modified ) {
			return $html;
		}

		$output = $dom->saveHTML();
		// Remove the XML encoding declaration we added.
		$output = str_replace( '<?xml encoding="utf-8">', '', $output );

		return $output;
	}

	/**
	 * Removes a link from HTML, replacing <a> tags with their text content.
	 *
	 * Collects matching nodes before modifying the DOM to avoid iterator
	 * invalidation issues.
	 *
	 * @since 1.0.0
	 *
	 * @param string $html HTML content to modify.
	 * @param string $url  URL to unlink.
	 * @return string Updated HTML.
	 */
	public function unlink_in_html( string $html, string $url ): string {
		if ( empty( $html ) ) {
			return $html;
		}

		$dom = new \DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		$modified = false;
		$nodes    = array();

		// Collect matching nodes first (can't modify DOM while iterating).
		foreach ( $dom->getElementsByTagName( 'a' ) as $node ) {
			/** @var \DOMElement $node */
			if ( $node->getAttribute( 'href' ) === $url ) {
				$nodes[] = $node;
			}
		}

		foreach ( $nodes as $node ) {
			$text_node = $dom->createTextNode( $node->textContent );
			$node->parentNode->replaceChild( $text_node, $node );
			$modified = true;
		}

		if ( ! $modified ) {
			return $html;
		}

		$output = $dom->saveHTML();
		$output = str_replace( '<?xml encoding="utf-8">', '', $output );

		return $output;
	}

	/**
	 * Updates a post's content directly in the database to avoid touching
	 * post_modified and creating unnecessary revisions.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $post_id     Post ID.
	 * @param string $new_content New post content.
	 * @return void
	 */
	public function update_post_content_silently( int $post_id, string $new_content ): void {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$this->wpdb->update(
			$this->wpdb->posts,
			array( 'post_content' => $new_content ),
			array( 'ID' => $post_id )
		);

		\clean_post_cache( $post_id );
	}
}
