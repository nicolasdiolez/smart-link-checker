<?php
/**
 * Link instance data transfer object.
 *
 * @package MuriLinkTracker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace MuriLinkTracker\Models;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable DTO representing a row in the mltr_instances table.
 *
 * Each instance tracks one occurrence of a link within a specific post.
 *
 * @since 1.0.0
 */
class LinkInstance {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param int                $id            Instance ID.
	 * @param int                $link_id       FK to mltr_links.id.
	 * @param int                $post_id       WordPress post ID.
	 * @param string             $source_type   Where the link was found (post_content, post_excerpt, custom_field, block_attribute).
	 * @param string|null        $anchor_text   Visible link text.
	 * @param bool               $rel_nofollow  Whether rel contains nofollow.
	 * @param bool               $rel_sponsored Whether rel contains sponsored.
	 * @param bool               $rel_ugc       Whether rel contains ugc.
	 * @param bool               $is_dofollow   True when no nofollow/sponsored/ugc.
	 * @param int|null           $link_position Position of the link in the content.
	 * @param string|null        $block_name    Gutenberg block name if applicable.
	 * @param \DateTimeImmutable $created_at    Creation datetime.
	 */
	public function __construct(
		public readonly int $id,
		public readonly int $link_id,
		public readonly int $post_id,
		public readonly string $source_type,
		public readonly ?string $anchor_text,
		public readonly bool $rel_nofollow,
		public readonly bool $rel_sponsored,
		public readonly bool $rel_ugc,
		public readonly bool $is_dofollow,
		public readonly ?int $link_position,
		public readonly ?string $block_name,
		public readonly \DateTimeImmutable $created_at,
	) {}

	/**
	 * Creates a LinkInstance from a database row object.
	 *
	 * @since 1.0.0
	 *
	 * @param object $row A stdClass row from $wpdb->get_row().
	 * @return self
	 */
	public static function from_db_row( object $row ): self {
		return new self(
			id:            (int) $row->id,
			link_id:       (int) $row->link_id,
			post_id:       (int) $row->post_id,
			source_type:   $row->source_type,
			anchor_text:   $row->anchor_text,
			rel_nofollow:  (bool) $row->rel_nofollow,
			rel_sponsored: (bool) $row->rel_sponsored,
			rel_ugc:       (bool) $row->rel_ugc,
			is_dofollow:   (bool) $row->is_dofollow,
			link_position: null !== $row->link_position ? (int) $row->link_position : null,
			block_name:    $row->block_name,
			created_at:    new \DateTimeImmutable( $row->created_at ),
		);
	}
}
