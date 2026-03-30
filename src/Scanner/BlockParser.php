<?php
/**
 * Gutenberg block parser for link extraction.
 *
 * @package FlavorLinkChecker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace FlavorLinkChecker\Scanner;

defined( 'ABSPATH' ) || exit;

use FlavorLinkChecker\Models\ScanResult;

/**
 * Extracts links from Gutenberg blocks recursively.
 *
 * Delegates HTML parsing to ContentParser and additionally extracts URLs
 * from known block attributes (e.g. core/button url, core/image href).
 *
 * @since 1.0.0
 */
class BlockParser {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param ContentParser $content_parser HTML parser for block innerHTML.
	 */
	public function __construct(
		private readonly ContentParser $content_parser,
	) {}

	/**
	 * Extracts all links from Gutenberg block content.
	 *
	 * @since 1.0.0
	 *
	 * @param string $post_content Raw post_content with block delimiters.
	 * @return ScanResult[] Array of extracted link data.
	 */
	public function parse( string $post_content ): array {
		$blocks  = parse_blocks( $post_content );
		$results = array();

		$this->extract_from_blocks( $blocks, $results );

		return $results;
	}

	/**
	 * Recursively processes blocks and their innerBlocks.
	 *
	 * @since 1.0.0
	 *
	 * @param array        $blocks  Parsed block array from parse_blocks().
	 * @param ScanResult[] $results Accumulated results (passed by reference).
	 */
	private function extract_from_blocks( array $blocks, array &$results ): void {
		$url_attrs_map = $this->get_url_attributes();

		foreach ( $blocks as $block ) {
			$block_name = $block['blockName'] ?? null;

			// Extract links from the block's rendered HTML.
			if ( ! empty( $block['innerHTML'] ) ) {
				$html_results = $this->content_parser->parse( $block['innerHTML'], 'post_content' );

				// Tag each result with the block name.
				foreach ( $html_results as $result ) {
					$results[] = new ScanResult(
						url:           $result->url,
						anchor_text:   $result->anchor_text,
						rel:           $result->rel,
						source_type:   $result->source_type,
						link_position: $result->link_position,
						block_name:    $block_name,
					);
				}
			}

			// Extract URLs from known block attributes.
			if ( null !== $block_name && ! empty( $block['attrs'] ) && isset( $url_attrs_map[ $block_name ] ) ) {
				foreach ( $url_attrs_map[ $block_name ] as $attr_name ) {
					if ( ! empty( $block['attrs'][ $attr_name ] ) && is_string( $block['attrs'][ $attr_name ] ) ) {
						$results[] = new ScanResult(
							url:           $block['attrs'][ $attr_name ],
							anchor_text:   '',
							rel:           '',
							source_type:   'block_attribute',
							link_position: null,
							block_name:    $block_name,
						);
					}
				}
			}

			// Recurse into nested blocks.
			if ( ! empty( $block['innerBlocks'] ) ) {
				$this->extract_from_blocks( $block['innerBlocks'], $results );
			}
		}
	}

	/**
	 * Returns a map of block types to their URL-containing attribute names.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string[]> Block name => array of attribute names.
	 */
	private function get_url_attributes(): array {
		$defaults = array(
			'core/button'          => array( 'url' ),
			'core/image'           => array( 'url', 'href' ),
			'core/media-text'      => array( 'mediaLink', 'href' ),
			'core/navigation-link' => array( 'url' ),
			'core/file'            => array( 'href' ),
			'core/cover'           => array( 'url' ),
		);

		/**
		 * Filters the map of block types to their URL attribute names.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, string[]> $defaults Block name => attribute names.
		 */
		return apply_filters( 'slkc/scanner/block_url_attributes', $defaults );
	}
}
