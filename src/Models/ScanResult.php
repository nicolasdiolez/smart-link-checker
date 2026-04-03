<?php
/**
 * Scan result data transfer object.
 *
 * @package MuriLinkTracker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace MuriLinkTracker\Models;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable DTO representing a raw extracted link before classification and persistence.
 *
 * Returned by ContentParser and BlockParser, consumed by LinkExtractor.
 *
 * @since 1.0.0
 */
readonly class ScanResult {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $url           The link URL as found in content.
	 * @param string      $anchor_text   Visible link text.
	 * @param string      $rel           Raw rel attribute string.
	 * @param string      $source_type   Where the link was found (post_content, post_excerpt, custom_field, block_attribute).
	 * @param int|null    $link_position Position of the link in the content (zero-based).
	 * @param string|null $block_name    Gutenberg block name if applicable.
	 */
	public function __construct(
		public string $url,
		public string $anchor_text,
		public string $rel,
		public string $source_type,
		public ?int $link_position,
		public ?string $block_name,
	) {}
}
