<?php
/**
 * Links repository for CRUD operations on the flc_links table.
 *
 * @package FlavorLinkChecker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace FlavorLinkChecker\Database;

defined( 'ABSPATH' ) || exit;

use FlavorLinkChecker\Models\Enums\LinkStatus;
use FlavorLinkChecker\Models\Link;

/**
 * Handles all database operations for the flc_links table.
 *
 * @since 1.0.0
 */
class LinksRepository {

	/**
	 * Fully qualified table name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private readonly string $table;

	/**
	 * Initializes the repository with the WordPress database instance.
	 *
	 * @since 1.0.0
	 *
	 * @param \wpdb $wpdb WordPress database abstraction.
	 */
	public function __construct(
		private readonly \wpdb $wpdb,
	) {
		$this->table = $this->wpdb->prefix . 'flc_links';
	}

	/**
	 * Finds a link by its ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Link ID.
	 * @return Link|null
	 */
	public function find( int $id ): ?Link {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $this->wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$this->wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d',
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$this->table,
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$id
			)
		);

		return null !== $row ? Link::from_db_row( $row ) : null;
	}

	/**
	 * Finds a link by its URL hash.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url_hash SHA-256 hash of the URL.
	 * @return Link|null
	 */
	public function find_by_hash( string $url_hash ): ?Link {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $this->wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$this->wpdb->prepare(
				'SELECT * FROM %i WHERE url_hash = %s',
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$this->table,
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$url_hash
			)
		);

		return null !== $row ? Link::from_db_row( $row ) : null;
	}

	/**
	 * Inserts a new link or returns the existing one's ID if the URL hash already exists.
	 *
	 * Uses INSERT IGNORE to handle race conditions atomically.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $url               The link URL.
	 * @param string      $url_hash          SHA-256 hash of the URL.
	 * @param bool        $is_external       Whether the link is external.
	 * @param bool        $is_affiliate      Whether the link is an affiliate link.
	 * @param string|null $affiliate_network Detected affiliate network name.
	 * @return int The link ID (newly inserted or existing).
	 */
	public function insert_or_get(
		string $url,
		string $url_hash,
		bool $is_external,
		bool $is_affiliate,
		?string $affiliate_network,
	): int {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->query(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$this->wpdb->prepare(
				'INSERT IGNORE INTO %i (url, url_hash, is_external, is_affiliate, affiliate_network) VALUES (%s, %s, %d, %d, %s)',
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$this->table,
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$url,
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$url_hash,
				(int) $is_external,
				(int) $is_affiliate,
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$affiliate_network
			)
		);

		// If insert_id is 0, the row already existed (INSERT IGNORE).
		if ( $this->wpdb->insert_id > 0 ) {
			return (int) $this->wpdb->insert_id;
		}

		$existing = $this->find_by_hash( $url_hash );
		return null !== $existing ? $existing->id : 0;
	}

	/**
	 * Updates HTTP check results for a link.
	 *
	 * @since 1.0.0
	 *
	 * @param int         $id              Link ID.
	 * @param int         $http_status     HTTP response code.
	 * @param LinkStatus  $status_category Resulting status category.
	 * @param string|null $final_url      Final URL after redirects.
	 * @param int         $response_time   Response time in milliseconds.
	 * @param int         $redirect_count  Number of redirect hops.
	 * @param string|null $redirect_chain JSON-encoded redirect chain or null.
	 * @param string|null $last_error     Error message if applicable.
	 * @return bool True on success.
	 */
	public function update_check_result(
		int $id,
		int $http_status,
		LinkStatus $status_category,
		?string $final_url,
		int $response_time,
		int $redirect_count,
		?string $redirect_chain,
		?string $last_error,
	): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $this->wpdb->query(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$this->wpdb->prepare(
				'UPDATE %i SET http_status = %d, status_category = %s, final_url = %s, response_time = %d, redirect_count = %d, redirect_chain = %s, last_error = %s, last_checked = %s, check_count = check_count + 1 WHERE id = %d',
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$this->table,
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$http_status,
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$status_category->value,
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$final_url,
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$response_time,
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$redirect_count,
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$redirect_chain,
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$last_error,
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				current_time( 'mysql', true ),
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$id
			)
		);

		return false !== $updated;
	}

	/**
	 * Finds links that need an HTTP check (pending or stale).
	 *
	 * @since 1.0.0
	 *
	 * @param int $limit        Max number of links to return.
	 * @param int $recheck_days Number of days before a link is considered stale.
	 * @return Link[]
	 */
	public function find_pending_or_stale( int $limit, int $recheck_days = 7 ): array {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $this->wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$this->wpdb->prepare(
				"SELECT * FROM %i WHERE status_category = 'pending' OR (last_checked IS NOT NULL AND last_checked < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)) ORDER BY last_checked ASC, id ASC LIMIT %d",
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$this->table,
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$recheck_days,
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$limit
			)
		);

		return array_map( array( Link::class, 'from_db_row' ), $rows );
	}

	/**
	 * Bulk inserts links using a transaction for performance.
	 *
	 * Skips URLs that already exist (INSERT IGNORE).
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array{url: string, url_hash: string, is_external: bool, is_affiliate: bool, affiliate_network: string|null}> $links Array of link data.
	 * @return array<string, int> Map of url_hash => link ID for all inserted/existing links.
	 */
	public function bulk_insert( array $links ): array {
		if ( empty( $links ) ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->query( 'SET autocommit = 0' );

		foreach ( $links as $link ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$this->wpdb->query(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$this->wpdb->prepare(
					'INSERT IGNORE INTO %i (url, url_hash, is_external, is_affiliate, affiliate_network) VALUES (%s, %s, %d, %d, %s)',
					// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$this->table,
					// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$link['url'],
					// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$link['url_hash'],
					(int) $link['is_external'],
					(int) $link['is_affiliate'],
					// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$link['affiliate_network']
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->query( 'COMMIT' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->query( 'SET autocommit = 1' );

		// Build the hash => ID map by querying all the hashes.
		$hashes       = array_column( $links, 'url_hash' );
		$placeholders = implode( ',', array_fill( 0, count( $hashes ), '%s' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $this->wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			$this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT id, url_hash FROM %i WHERE url_hash IN ($placeholders)",
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$this->table,
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				...$hashes
			)
		);

		$map = array();
		foreach ( $rows as $row ) {
			$map[ $row->url_hash ] = (int) $row->id;
		}

		return $map;
	}

	/**
	 * Deletes a link and all its instances.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Link ID.
	 * @return bool True on success.
	 */
	public function delete( int $id ): bool {
		$instances_table = $this->wpdb->prefix . 'flc_instances';

		// Delete instances first.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->query(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$this->wpdb->prepare(
				'DELETE FROM %i WHERE link_id = %d',
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$instances_table,
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$id
			)
		);

		// Delete the link.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $this->wpdb->query(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$this->wpdb->prepare(
				'DELETE FROM %i WHERE id = %d',
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$this->table,
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$id
			)
		);

		return false !== $deleted && $deleted > 0;
	}

	/**
	 * Counts links grouped by status category.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, int> e.g. ['ok' => 150, 'broken' => 3, ...].
	 */
	public function count_by_status(): array {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $this->wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$this->wpdb->prepare(
				'SELECT status_category, COUNT(*) as count FROM %i GROUP BY status_category',
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$this->table
			)
		);

		$counts = array();
		foreach ( $rows as $row ) {
			$counts[ $row->status_category ] = (int) $row->count;
		}

		return $counts;
	}

	/**
	 * Counts links grouped by affiliate network.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, int> e.g. ['amazon' => 42, 'awin' => 15].
	 */
	public function count_by_network(): array {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $this->wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$this->wpdb->prepare(
				"SELECT COALESCE(affiliate_network, 'unknown') as network, COUNT(*) as count FROM %i WHERE is_affiliate = 1 GROUP BY affiliate_network ORDER BY count DESC",
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$this->table
			)
		);

		$counts = array();
		foreach ( $rows as $row ) {
			$counts[ $row->network ] = (int) $row->count;
		}

		return $counts;
	}

	/**
	 * Returns comprehensive category statistics for the dashboard.
	 *
	 * Uses a single query with conditional aggregation for efficiency.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, int>
	 */
	public function get_category_stats(): array {
		$like_pattern = $this->wpdb->esc_like( 'redirect_loop' ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $this->wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$this->wpdb->prepare(
				'SELECT
					COUNT(*) as total,
					SUM(is_external = 1) as external_count,
					SUM(is_external = 0) as internal_count,
					SUM(is_affiliate = 1) as affiliate_count,
					SUM(is_affiliate = 1 AND is_external = 0) as cloaked_count,
					SUM(is_affiliate = 1 AND is_external = 1) as direct_affiliate_count,
					SUM(CASE WHEN status_category = \'ok\' THEN 1 ELSE 0 END) as ok_count,
					SUM(CASE WHEN status_category = \'broken\' THEN 1 ELSE 0 END) as broken_count,
					SUM(CASE WHEN status_category = \'pending\' THEN 1 ELSE 0 END) as pending_count,
					SUM(redirect_count = 1) as single_redirect_count,
					SUM(redirect_count > 1) as chain_redirect_count,
					SUM(last_error LIKE %s) as loop_count
				FROM %i',
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$like_pattern,
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$this->table
			)
		);

		if ( null === $row ) {
			return array_fill_keys(
				array( 'total', 'external_count', 'internal_count', 'affiliate_count', 'cloaked_count', 'direct_affiliate_count', 'ok_count', 'broken_count', 'pending_count', 'single_redirect_count', 'chain_redirect_count', 'loop_count' ),
				0
			);
		}

		return array(
			'total'                  => (int) $row->total,
			'external_count'         => (int) $row->external_count,
			'internal_count'         => (int) $row->internal_count,
			'affiliate_count'        => (int) $row->affiliate_count,
			'cloaked_count'          => (int) $row->cloaked_count,
			'direct_affiliate_count' => (int) $row->direct_affiliate_count,
			'ok_count'               => (int) $row->ok_count,
			'broken_count'           => (int) $row->broken_count,
			'pending_count'          => (int) $row->pending_count,
			'single_redirect_count'  => (int) $row->single_redirect_count,
			'chain_redirect_count'   => (int) $row->chain_redirect_count,
			'loop_count'             => (int) $row->loop_count,
		);
	}

	/**
	 * Deletes orphan links that have no instances referencing them.
	 *
	 * @since 1.0.0
	 *
	 * @return int Number of deleted orphan links.
	 */
	public function cleanup_orphans(): int {
		$instances_table = $this->wpdb->prefix . 'flc_instances';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $this->wpdb->query(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$this->wpdb->prepare(
				'DELETE l FROM %i l LEFT JOIN %i i ON l.id = i.link_id WHERE i.id IS NULL',
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$this->table,
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$instances_table
			)
		);

		return false !== $deleted ? $deleted : 0;
	}

	/**
	 * Deletes all links from the table.
	 *
	 * @since 1.2.0
	 *
	 * @return int Number of deleted rows.
	 */
	public function truncate(): int {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $this->wpdb->query(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$this->wpdb->prepare( 'DELETE FROM %i', $this->table )
		);

		return false !== $deleted ? $deleted : 0;
	}
}
