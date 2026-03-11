<?php
/**
 * HTML content parser for link extraction.
 *
 * @package FlavorLinkChecker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace FlavorLinkChecker\Scanner;

defined( 'ABSPATH' ) || exit;

use FlavorLinkChecker\Models\ScanResult;

/**
 * Extracts links from HTML content using DOMDocument.
 *
 * @since 1.0.0
 */
class ContentParser {

	/**
	 * URL schemes that should be skipped during extraction.
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	private const SKIP_PREFIXES = array( '#', 'mailto:', 'tel:', 'javascript:', 'data:' );

	/**
	 * Extracts all anchor links from an HTML string.
	 *
	 * @since 1.0.0
	 *
	 * @param string $html        Raw HTML content.
	 * @param string $source_type One of: post_content, post_excerpt, custom_field.
	 * @return ScanResult[] Array of extracted link data.
	 */
	public function parse( string $html, string $source_type = 'post_content' ): array {
		$html = trim( $html );
		if ( '' === $html ) {
			return array();
		}

		// Quick check: skip expensive DOMDocument allocation if no anchor tags exist.
		if ( ! str_contains( $html, '<a ' ) && ! str_contains( $html, '<a>' ) && ! str_contains( $html, 'href=' ) ) {
			return array();
		}

		$dom = new \DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML(
			'<?xml encoding="utf-8">' . $html,
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);
		libxml_clear_errors();

		$results  = array();
		$position = 0;

		foreach ( $dom->getElementsByTagName( 'a' ) as $node ) {
			/** @var \DOMElement $node */
			$href = $node->getAttribute( 'href' );

			if ( $this->should_skip_url( $href ) ) {
				continue;
			}

			$results[] = new ScanResult(
				url:           $href,
				anchor_text:   trim( $node->textContent ),
				rel:           $node->getAttribute( 'rel' ),
				source_type:   $source_type,
				link_position: $position,
				block_name:    null,
			);

			++$position;
		}

		return $results;
	}

	/**
	 * Checks if a URL should be skipped during extraction.
	 *
	 * @since 1.0.0
	 *
	 * @param string $href The href attribute value.
	 * @return bool True if the URL should be skipped.
	 */
	private function should_skip_url( string $href ): bool {
		$href = trim( $href );

		if ( '' === $href ) {
			return true;
		}

		foreach ( self::SKIP_PREFIXES as $prefix ) {
			if ( str_starts_with( $href, $prefix ) ) {
				return true;
			}
		}

		return false;
	}
}
