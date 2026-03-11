<?php
/**
 * Instances repository for CRUD operations on the flc_instances table.
 *
 * @package FlavorLinkChecker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace FlavorLinkChecker\Database;

defined( 'ABSPATH' ) || exit;

use FlavorLinkChecker\Models\LinkInstance;

/**
 * Handles all database operations for the flc_instances table.
 *
 * @since 1.0.0
 */
class InstancesRepository {

	/**
	 * Fully qualified table name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private readonly string $table;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param \wpdb $wpdb WordPress database abstraction.
	 */
	public function __construct(
		private readonly \wpdb $wpdb,
	) {
		$this->table = $this->wpdb->prefix . 'flc_instances';
	}

	/**
	 * Finds all instances for a given post.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id WordPress post ID.
	 * @return LinkInstance[]
	 */
	public function find_by_post( int $post_id ): array {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $this->wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$this->wpdb->prepare(
				'SELECT * FROM %i WHERE post_id = %d ORDER BY link_position ASC',
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$this->table,
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$post_id
			)
		);

		return array_map( array( LinkInstance::class, 'from_db_row' ), $rows );
	}

	/**
	 * Finds all instances for a given link.
	 *
	 * @since 1.0.0
	 *
	 * @param int $link_id FK to flc_links.id.
	 * @return LinkInstance[]
	 */
	public function find_by_link( int $link_id ): array {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $this->wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$this->wpdb->prepare(
				'SELECT * FROM %i WHERE link_id = %d',
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$this->table,
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$link_id
			)
		);

		return array_map( array( LinkInstance::class, 'from_db_row' ), $rows );
	}

	/**
	 * Deletes all instances for a given post.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id WordPress post ID.
	 * @return int Number of deleted rows.
	 */
	public function delete_by_post( int $post_id ): int {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $this->wpdb->query(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$this->wpdb->prepare(
				'DELETE FROM %i WHERE post_id = %d',
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$this->table,
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$post_id
			)
		);

		return false !== $deleted ? $deleted : 0;
	}

	/**
	 * Bulk inserts instances using a transaction for performance.
	 *
	 * @since 1.0.0
	 *
	 * @param array $instances Instances to insert. Each element is an associative
	 *                         array with keys: link_id, post_id, source_type,
	 *                         anchor_text, rel_nofollow, rel_sponsored, rel_ugc,
	 *                         is_dofollow, link_position, block_name.
	 * @return void
	 */
	public function bulk_insert( array $instances ): void {
		if ( empty( $instances ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->query( 'SET autocommit = 0' );

		foreach ( $instances as $instance ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$this->wpdb->query(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$this->wpdb->prepare(
					'INSERT INTO %i (link_id, post_id, source_type, anchor_text, rel_nofollow, rel_sponsored, rel_ugc, is_dofollow, link_position, block_name) VALUES (%d, %d, %s, %s, %d, %d, %d, %d, %d, %s)',
					// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$this->table,
					// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$instance['link_id'],
					// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$instance['post_id'],
					// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$instance['source_type'],
					// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$instance['anchor_text'],
					(int) $instance['rel_nofollow'],
					(int) $instance['rel_sponsored'],
					(int) $instance['rel_ugc'],
					(int) $instance['is_dofollow'],
					// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$instance['link_position'],
					// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$instance['block_name']
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->query( 'COMMIT' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->query( 'SET autocommit = 1' );
	}

	/**
	 * Syncs instances for a post: deletes old ones, inserts new ones.
	 *
	 * Uses a transactional delete-and-reinsert strategy for atomicity.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $post_id   WordPress post ID.
	 * @param array $instances New instances to insert. Each element is an associative
	 *                         array with keys: link_id, post_id, source_type,
	 *                         anchor_text, rel_nofollow, rel_sponsored, rel_ugc,
	 *                         is_dofollow, link_position, block_name.
	 * @return void
	 */
	public function sync_for_post( int $post_id, array $instances ): void {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->query( 'SET autocommit = 0' );

		$this->delete_by_post( $post_id );

		foreach ( $instances as $instance ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$this->wpdb->query(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$this->wpdb->prepare(
					'INSERT INTO %i (link_id, post_id, source_type, anchor_text, rel_nofollow, rel_sponsored, rel_ugc, is_dofollow, link_position, block_name) VALUES (%d, %d, %s, %s, %d, %d, %d, %d, %d, %s)',
					// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$this->table,
					// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$instance['link_id'],
					// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$instance['post_id'],
					// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$instance['source_type'],
					// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$instance['anchor_text'],
					(int) $instance['rel_nofollow'],
					(int) $instance['rel_sponsored'],
					(int) $instance['rel_ugc'],
					(int) $instance['is_dofollow'],
					// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$instance['link_position'],
					// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$instance['block_name']
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->query( 'COMMIT' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->wpdb->query( 'SET autocommit = 1' );
	}

	/**
	 * Counts instances grouped by link ID for a batch of link IDs.
	 *
	 * @since 1.0.0
	 *
	 * @param int[] $link_ids Array of link IDs.
	 * @return array<int, int> Map of link_id => instance count.
	 */
	public function count_by_link_ids( array $link_ids ): array {
		if ( empty( $link_ids ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $link_ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $this->wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.NotPrepared
			$this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT link_id, COUNT(*) as count FROM %i WHERE link_id IN ($placeholders) GROUP BY link_id",
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$this->table,
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				...$link_ids
			)
		);

		$counts = array();
		foreach ( $rows as $row ) {
			$counts[ (int) $row->link_id ] = (int) $row->count;
		}

		return $counts;
	}

	/**
	 * Counts the number of instances for a post.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id WordPress post ID.
	 * @return int Instance count.
	 */
	public function count_by_post( int $post_id ): int {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $this->wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$this->wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE post_id = %d',
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$this->table,
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$post_id
			)
		);

		return (int) $count;
	}

	/**
	 * Deletes all instances from the table.
	 *
	 * @since 1.0.0
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
