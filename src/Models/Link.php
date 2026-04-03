<?php
/**
 * Link data transfer object.
 *
 * @package MuriLinkTracker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace MuriLinkTracker\Models;

defined( 'ABSPATH' ) || exit;

use MuriLinkTracker\Models\Enums\LinkStatus;

/**
 * Immutable DTO representing a row in the mltr_links table.
 *
 * @since 1.0.0
 */
readonly class Link {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param int                     $id                Link ID.
	 * @param string                  $url               Original URL.
	 * @param string                  $url_hash          SHA-256 hash of the URL.
	 * @param string|null             $final_url         Final URL after redirects.
	 * @param int|null                $http_status       HTTP response code.
	 * @param LinkStatus              $status_category   Verification status.
	 * @param bool                    $is_external       Whether the link is external.
	 * @param bool                    $is_affiliate      Whether the link is an affiliate link.
	 * @param string|null             $affiliate_network Detected affiliate network name.
	 * @param int|null                $response_time     Response time in milliseconds.
	 * @param int                     $redirect_count    Number of redirect hops.
	 * @param string|null             $redirect_chain    JSON-encoded redirect chain.
	 * @param \DateTimeImmutable|null $last_checked   Last verification datetime.
	 * @param int                     $check_count       Total number of checks performed.
	 * @param string|null             $last_error        Last error message.
	 * @param \DateTimeImmutable      $created_at        Creation datetime.
	 * @param \DateTimeImmutable      $updated_at        Last update datetime.
	 */
	public function __construct(
		public int $id,
		public string $url,
		public string $url_hash,
		public ?string $final_url,
		public ?int $http_status,
		public LinkStatus $status_category,
		public bool $is_external,
		public bool $is_affiliate,
		public ?string $affiliate_network,
		public ?int $response_time,
		public int $redirect_count,
		public ?string $redirect_chain,
		public ?\DateTimeImmutable $last_checked,
		public int $check_count,
		public ?string $last_error,
		public \DateTimeImmutable $created_at,
		public \DateTimeImmutable $updated_at,
	) {}

	/**
	 * Creates a Link instance from a database row object.
	 *
	 * @since 1.0.0
	 *
	 * @param object $row A stdClass row from $wpdb->get_row().
	 * @return self
	 */
	public static function from_db_row( object $row ): self {
		return new self(
			id:                (int) $row->id,
			url:               $row->url,
			url_hash:          $row->url_hash,
			final_url:         $row->final_url,
			http_status:       null !== $row->http_status ? (int) $row->http_status : null,
			status_category:   LinkStatus::from( $row->status_category ),
			is_external:       (bool) $row->is_external,
			is_affiliate:      (bool) $row->is_affiliate,
			affiliate_network: $row->affiliate_network,
			response_time:     null !== $row->response_time ? (int) $row->response_time : null,
			redirect_count:    (int) $row->redirect_count,
			redirect_chain:    $row->redirect_chain ?? null,
			last_checked:      null !== $row->last_checked ? new \DateTimeImmutable( $row->last_checked ) : null,
			check_count:       (int) $row->check_count,
			last_error:        $row->last_error,
			created_at:        new \DateTimeImmutable( $row->created_at ),
			updated_at:        new \DateTimeImmutable( $row->updated_at ),
		);
	}
}
