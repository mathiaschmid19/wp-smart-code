<?php
/**
 * Revision Model for Edge Code Snippets.
 *
 * @package ECS
 * @since 1.0.0
 */

declare( strict_types=1 );

namespace ECS;

/**
 * Revision class for managing snippet version history.
 *
 * @since 1.0.0
 */
class Revision {
	/**
	 * Table name for revisions.
	 *
	 * @var string
	 */
	private string $table_name;

	/**
	 * Maximum number of revisions to keep per snippet.
	 *
	 * @var int
	 */
	private int $max_revisions = 10;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'ecs_snippet_revisions';
	}

	/**
	 * Get the table name.
	 *
	 * @return string
	 */
	public function get_table_name(): string {
		return $this->table_name;
	}

	/**
	 * Install the revisions table.
	 *
	 * @return bool True on success.
	 */
	public function install(): bool {
		global $wpdb;

		// Require dbDelta if not already loaded.
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$this->table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
			snippet_id BIGINT UNSIGNED NOT NULL,
			title VARCHAR(255) NOT NULL,
			code LONGTEXT NOT NULL,
			type VARCHAR(50) NOT NULL,
			author_id BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL,
			KEY idx_snippet_id (snippet_id),
			KEY idx_created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );

		return $this->table_exists();
	}

	/**
	 * Check if the revisions table exists.
	 *
	 * @return bool
	 */
	public function table_exists(): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->query( $wpdb->prepare( 'SHOW TABLES LIKE %s', $this->table_name ) ) > 0;
	}

	/**
	 * Create a revision for a snippet.
	 *
	 * @param int   $snippet_id Snippet ID.
	 * @param array $data       Snippet data (title, code, type).
	 * @return int|false Revision ID on success, false on failure.
	 */
	public function create( int $snippet_id, array $data ) {
		global $wpdb;

		// Ensure table exists
		if ( ! $this->table_exists() ) {
			$this->install();
		}

		$insert_data = [
			'snippet_id' => $snippet_id,
			'title'      => $data['title'] ?? '',
			'code'       => $data['code'] ?? '',
			'type'       => $data['type'] ?? 'php',
			'author_id'  => $data['author_id'] ?? get_current_user_id(),
			'created_at' => current_time( 'mysql' ),
		];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$this->table_name,
			$insert_data,
			[ '%d', '%s', '%s', '%s', '%d', '%s' ]
		);

		if ( $result === false ) {
			return false;
		}

		$revision_id = (int) $wpdb->insert_id;

		// Clean up old revisions (keep only max_revisions)
		$this->cleanup_old_revisions( $snippet_id );

		return $revision_id;
	}

	/**
	 * Get all revisions for a snippet.
	 *
	 * @param int $snippet_id Snippet ID.
	 * @param int $limit      Maximum number of revisions to return.
	 * @return array Array of revisions.
	 */
	public function get_by_snippet( int $snippet_id, int $limit = 10 ): array {
		global $wpdb;

		// Ensure table exists
		if ( ! $this->table_exists() ) {
			return [];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$revisions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.*, u.display_name as author_name
				FROM {$this->table_name} r
				LEFT JOIN {$wpdb->users} u ON r.author_id = u.ID
				WHERE r.snippet_id = %d
				ORDER BY r.created_at DESC
				LIMIT %d",
				$snippet_id,
				$limit
			),
			ARRAY_A
		);

		return $revisions ?: [];
	}

	/**
	 * Get a single revision by ID.
	 *
	 * @param int $revision_id Revision ID.
	 * @return array|null Revision data or null if not found.
	 */
	public function get( int $revision_id ): ?array {
		global $wpdb;

		// Ensure table exists
		if ( ! $this->table_exists() ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$revision = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT r.*, u.display_name as author_name
				FROM {$this->table_name} r
				LEFT JOIN {$wpdb->users} u ON r.author_id = u.ID
				WHERE r.id = %d",
				$revision_id
			),
			ARRAY_A
		);

		return $revision ?: null;
	}

	/**
	 * Get the count of revisions for a snippet.
	 *
	 * @param int $snippet_id Snippet ID.
	 * @return int Number of revisions.
	 */
	public function count( int $snippet_id ): int {
		global $wpdb;

		// Ensure table exists
		if ( ! $this->table_exists() ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name} WHERE snippet_id = %d",
				$snippet_id
			)
		);

		return (int) $count;
	}

	/**
	 * Delete a single revision.
	 *
	 * @param int $revision_id Revision ID.
	 * @return bool True on success.
	 */
	public function delete( int $revision_id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->delete(
			$this->table_name,
			[ 'id' => $revision_id ],
			[ '%d' ]
		);

		return $result !== false;
	}

	/**
	 * Delete all revisions for a snippet.
	 *
	 * @param int $snippet_id Snippet ID.
	 * @return bool True on success.
	 */
	public function delete_all_for_snippet( int $snippet_id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->delete(
			$this->table_name,
			[ 'snippet_id' => $snippet_id ],
			[ '%d' ]
		);

		return $result !== false;
	}

	/**
	 * Clean up old revisions, keeping only the most recent ones.
	 *
	 * @param int $snippet_id Snippet ID.
	 * @return void
	 */
	private function cleanup_old_revisions( int $snippet_id ): void {
		global $wpdb;

		// Get the ID of the oldest revision to keep
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$keep_after_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->table_name}
				WHERE snippet_id = %d
				ORDER BY created_at DESC
				LIMIT 1 OFFSET %d",
				$snippet_id,
				$this->max_revisions - 1
			)
		);

		if ( $keep_after_id ) {
			// Delete revisions older than the cutoff
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$this->table_name}
					WHERE snippet_id = %d AND id < %d",
					$snippet_id,
					$keep_after_id
				)
			);
		}
	}

	/**
	 * Compare two revisions and return the diff.
	 *
	 * @param int $old_revision_id Older revision ID.
	 * @param int $new_revision_id Newer revision ID.
	 * @return array|null Comparison data or null if revisions not found.
	 */
	public function compare( int $old_revision_id, int $new_revision_id ): ?array {
		$old = $this->get( $old_revision_id );
		$new = $this->get( $new_revision_id );

		if ( ! $old || ! $new ) {
			return null;
		}

		return [
			'old' => $old,
			'new' => $new,
			'code_changed' => $old['code'] !== $new['code'],
			'title_changed' => $old['title'] !== $new['title'],
		];
	}

	/**
	 * Get the maximum number of revisions to keep.
	 *
	 * @return int
	 */
	public function get_max_revisions(): int {
		return $this->max_revisions;
	}

	/**
	 * Set the maximum number of revisions to keep.
	 *
	 * @param int $max Maximum revisions.
	 * @return void
	 */
	public function set_max_revisions( int $max ): void {
		$this->max_revisions = max( 1, $max );
	}
}
