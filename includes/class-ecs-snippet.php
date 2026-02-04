<?php
/**
 * Snippet Model for Edge Code Snippets.
 *
 * @package ECS
 * @since 1.0.0
 */

declare( strict_types=1 );

namespace ECS;

/**
 * Snippet class for CRUD operations on code snippets.
 *
 * @since 1.0.0
 */
class Snippet {
	/**
	 * Snippet ID.
	 *
	 * @var int
	 */
	private int $id = 0;

	/**
	 * Snippet title.
	 *
	 * @var string
	 */
	private string $title = '';

	/**
	 * Snippet slug.
	 *
	 * @var string
	 */
	private string $slug = '';

	/**
	 * Snippet type (php, js, css, html).
	 *
	 * @var string
	 */
	private string $type = '';

	/**
	 * Snippet code content.
	 *
	 * @var string
	 */
	private string $code = '';

	/**
	 * Whether snippet is active.
	 *
	 * @var bool
	 */
	private bool $active = false;

	/**
	 * Snippet execution mode.
	 *
	 * @var string
	 */
	private string $mode = 'auto_insert';

	/**
	 * Snippet conditions (JSON).
	 *
	 * @var string|null
	 */
	private ?string $conditions = null;

	/**
	 * Author ID.
	 *
	 * @var int
	 */
	private int $author_id = 0;

	/**
	 * Created timestamp.
	 *
	 * @var string
	 */
	private string $created_at = '';

	/**
	 * Updated timestamp.
	 *
	 * @var string
	 */
	private string $updated_at = '';

	/**
	 * Database instance.
	 *
	 * @var DB
	 */
	private DB $db;

	/**
	 * Constructor.
	 *
	 * @param DB $db Database instance.
	 */
	public function __construct( DB $db ) {
		$this->db = $db;
	}

	/**
	 * Create a new snippet.
	 *
	 * @param array{
	 *   title: string,
	 *   slug: string,
	 *   type: string,
	 *   code: string,
	 *   active?: bool,
	 *   mode?: string,
	 *   conditions?: string|null,
	 *   author_id?: int
	 * } $data Snippet data.
	 *
	 * @return int|false Snippet ID on success, false on failure.
	 */
	public function create( array $data ) {
		global $wpdb;

		try {
			$table = $this->db->get_table_name();
			$author_id = $data['author_id'] ?? get_current_user_id();
			$active = $data['active'] ?? false;
			$mode = $data['mode'] ?? 'auto_insert';
			$conditions = $data['conditions'] ?? null;

			$insert_data = [
				'title'      => $data['title'] ?? '',
				'slug'       => $data['slug'] ?? '',
				'type'       => $data['type'] ?? '',
				'code'       => $data['code'] ?? '',
				'active'     => $active ? 1 : 0,
				'mode'       => $mode,
				'conditions' => $conditions,
				'author_id'  => $author_id,
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			];

			$formats = [
				'%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s',
			];

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$result = $wpdb->insert( $table, $insert_data, $formats );

			if ( $result ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( "[ECS] Snippet created: {$wpdb->insert_id}" ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
				return $wpdb->insert_id;
			}

			$this->log_database_error( 'Failed to create snippet', [
				'data' => $insert_data,
				'wpdb_error' => $wpdb->last_error,
				'operation' => 'create'
			] );
			return false;
		} catch ( \Exception $e ) {
			$this->log_error( 'Exception in create method', [
				'message' => $e->getMessage(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
				'data' => $data
			] );
			return false;
		}
	}

	/**
	 * Get a snippet by ID.
	 *
	 * @param int $id Snippet ID.
	 * @return array|null Snippet data or null if not found.
	 */
	public function get( int $id ): ?array {
		global $wpdb;

		try {
			$table = $this->db->get_table_name();

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				ARRAY_A
			);

			if ( $wpdb->last_error ) {
				$this->log_database_error( 'Failed to get snippet by ID', [
					'snippet_id' => $id,
					'wpdb_error' => $wpdb->last_error,
					'operation' => 'get'
				] );
				return null;
			}

			return $result ?? null;
		} catch ( \Exception $e ) {
			$this->log_error( 'Exception in get method', [
				'message' => $e->getMessage(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
				'snippet_id' => $id
			] );
			return null;
		}
	}

	/**
	 * Get a snippet by slug.
	 *
	 * @param string $slug Snippet slug.
	 * @return array|null Snippet data or null if not found.
	 */
	public function get_by_slug( string $slug ): ?array {
		global $wpdb;

		$table = $this->db->get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s", $slug ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		return $result ?? null;
	}

	/**
	 * Update a snippet.
	 *
	 * @param int   $id Snippet ID.
	 * @param array $data Data to update.
	 * @return int|false Number of rows affected or false on failure.
	 */
	public function update( int $id, array $data ) {
		global $wpdb;

		try {
			$table = $this->db->get_table_name();

			// Get the current snippet data before updating (for revision)
			$current_snippet = $this->get( $id );
			
			// Create a revision of the current state before updating
			// Only create revision if code or title is being changed
			if ( $current_snippet && ( isset( $data['code'] ) || isset( $data['title'] ) ) ) {
				$this->create_revision( $id, $current_snippet );
			}

			$update_data = [];
			$formats = [];

			if ( isset( $data['title'] ) ) {
				$update_data['title'] = $data['title'];
				$formats[] = '%s';
			}
			if ( isset( $data['slug'] ) ) {
				$update_data['slug'] = $data['slug'];
				$formats[] = '%s';
			}
			if ( isset( $data['type'] ) ) {
				$update_data['type'] = $data['type'];
				$formats[] = '%s';
			}
			if ( isset( $data['code'] ) ) {
				$update_data['code'] = $data['code'];
				$formats[] = '%s';
			}
			if ( isset( $data['active'] ) ) {
				$update_data['active'] = $data['active'] ? 1 : 0;
				$formats[] = '%d';
			}
			if ( isset( $data['mode'] ) ) {
				$update_data['mode'] = $data['mode'];
				$formats[] = '%s';
			}
			if ( isset( $data['conditions'] ) ) {
				$update_data['conditions'] = $data['conditions'];
				$formats[] = '%s';
			}
			if ( isset( $data['deleted'] ) ) {
				$update_data['deleted'] = $data['deleted'] ? 1 : 0;
				$formats[] = '%d';
			}

			$update_data['updated_at'] = current_time( 'mysql' );
			$formats[] = '%s';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$result = $wpdb->update(
				$table,
				$update_data,
				[ 'id' => $id ],
				$formats,
				[ '%d' ]
			);

			if ( $result === false ) {
				$this->log_database_error( 'Failed to update snippet', [
					'snippet_id' => $id,
					'data' => $update_data,
					'wpdb_error' => $wpdb->last_error,
					'operation' => 'update'
				] );
				return false;
			}

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "[ECS] Snippet updated: {$id}" ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}

			return (int) $result;
		} catch ( \Exception $e ) {
			$this->log_error( 'Exception in update method', [
				'message' => $e->getMessage(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
				'snippet_id' => $id,
				'data' => $data
			] );
			return false;
		}
	}

	/**
	 * Create a revision for a snippet.
	 *
	 * @param int   $snippet_id Snippet ID.
	 * @param array $snippet_data Current snippet data.
	 * @return int|false Revision ID on success, false on failure.
	 */
	private function create_revision( int $snippet_id, array $snippet_data ) {
		try {
			$revision = new Revision();
			return $revision->create( $snippet_id, [
				'title'     => $snippet_data['title'] ?? '',
				'code'      => $snippet_data['code'] ?? '',
				'type'      => $snippet_data['type'] ?? 'php',
				'author_id' => get_current_user_id(),
			] );
		} catch ( \Exception $e ) {
			// Log but don't fail the update if revision creation fails
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[ECS] Failed to create revision: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return false;
		}
	}

	/**
	 * Delete a snippet.
	 *
	 * @param int $id Snippet ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete( int $id ): bool {
		global $wpdb;

		try {
			$table = $this->db->get_table_name();

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$result = $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );

			if ( $result ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( "[ECS] Snippet deleted: {$id}" ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
				return true;
			}

			$this->log_database_error( 'Failed to delete snippet', [
				'snippet_id' => $id,
				'wpdb_error' => $wpdb->last_error,
				'operation' => 'delete'
			] );
			return false;
		} catch ( \Exception $e ) {
			$this->log_error( 'Exception in delete method', [
				'message' => $e->getMessage(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
				'snippet_id' => $id
			] );
			return false;
		}
	}

	/**
	 * Get all snippets with optional filters.
	 *
	 * @param array{
	 *   type?: string,
	 *   active?: bool,
	 *   author_id?: int,
	 *   deleted?: bool,
	 *   search?: string,
	 *   orderby?: string,
	 *   order?: string,
	 *   limit?: int,
	 *   offset?: int
	 * } $args Query arguments.
	 *
	 * @return array Array of snippets.
	 */
	public function all( array $args = [] ): array {
		global $wpdb;

		$table = $this->db->get_table_name();

		// Apply filter hook for query arguments.
		$args = apply_filters( 'ecs_snippets_query_args', $args, 'all' );

		$limit = $args['limit'] ?? 50;
		$offset = $args['offset'] ?? 0;
		$orderby = $args['orderby'] ?? 'created_at';
		$order = strtoupper( $args['order'] ?? 'DESC' );

		// Validate order direction.
		if ( ! in_array( $order, [ 'ASC', 'DESC' ], true ) ) {
			$order = 'DESC';
		}

		// Validate orderby column.
		$allowed_orderby = [ 'id', 'title', 'slug', 'type', 'active', 'mode', 'created_at', 'updated_at' ];
		if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
			$orderby = 'created_at';
		}

		$where = [];
		$where_args = [];

		if ( isset( $args['type'] ) ) {
			$where[] = 'type = %s';
			$where_args[] = $args['type'];
		}

		if ( isset( $args['active'] ) ) {
			$where[] = 'active = %d';
			$where_args[] = $args['active'] ? 1 : 0;
		}

		if ( isset( $args['author_id'] ) ) {
			$where[] = 'author_id = %d';
			$where_args[] = $args['author_id'];
		}

		// Handle deleted filter only if column exists.
		if ( isset( $args['deleted'] ) && $this->has_deleted_column() ) {
			$where[] = 'deleted = %d';
			$where_args[] = $args['deleted'] ? 1 : 0;
		}

		// Handle search functionality.
		if ( ! empty( $args['search'] ) ) {
			$search_fields = apply_filters( 'ecs_snippets_search_fields', [ 'title', 'slug' ] );
			$search_conditions = [];

			foreach ( $search_fields as $field ) {
				$search_conditions[] = "{$field} LIKE %s";
				$where_args[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			}

			if ( ! empty( $search_conditions ) ) {
				$where[] = '(' . implode( ' OR ', $search_conditions ) . ')';
			}
		}

		$where_clause = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

		$query = "SELECT * FROM {$table} {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$where_args[] = $limit;
		$where_args[] = $offset;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare( $query, ...$where_args ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		return $results ?? [];
	}

	/**
	 * Get count of snippets with optional filters.
	 *
	 * @param array{
	 *   type?: string,
	 *   active?: bool,
	 *   author_id?: int,
	 *   deleted?: bool,
	 *   search?: string
	 * } $args Query arguments.
	 *
	 * @return int Total count of snippets.
	 */
	public function count( array $args = [] ): int {
		global $wpdb;

		$table = $this->db->get_table_name();

		// Apply filter hook for query arguments.
		$args = apply_filters( 'ecs_snippets_query_args', $args, 'count' );

		$where = [];
		$where_args = [];

		if ( isset( $args['type'] ) ) {
			$where[] = 'type = %s';
			$where_args[] = $args['type'];
		}

		if ( isset( $args['active'] ) ) {
			$where[] = 'active = %d';
			$where_args[] = $args['active'] ? 1 : 0;
		}

		if ( isset( $args['author_id'] ) ) {
			$where[] = 'author_id = %d';
			$where_args[] = $args['author_id'];
		}

		// Handle deleted filter only if column exists.
		if ( isset( $args['deleted'] ) && $this->has_deleted_column() ) {
			$where[] = 'deleted = %d';
			$where_args[] = $args['deleted'] ? 1 : 0;
		}

		// Handle search functionality.
		if ( ! empty( $args['search'] ) ) {
			$search_fields = apply_filters( 'ecs_snippets_search_fields', [ 'title', 'slug' ] );
			$search_conditions = [];

			foreach ( $search_fields as $field ) {
				$search_conditions[] = "{$field} LIKE %s";
				$where_args[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			}

			if ( ! empty( $search_conditions ) ) {
				$where[] = '(' . implode( ' OR ', $search_conditions ) . ')';
			}
		}

		$where_clause = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

		$query = "SELECT COUNT(*) as total FROM {$table} {$where_clause}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( ! empty( $where_args ) ) {
			$result = $wpdb->get_var(
				$wpdb->prepare( $query, ...$where_args ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			);
		} else {
			$result = $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return (int) $result;
	}

	/**
	 * Soft delete a snippet (move to trash).
	 *
	 * @param int $id Snippet ID.
	 * @return bool True on success, false on failure.
	 */
	public function soft_delete( int $id ): bool {
		global $wpdb;

		try {
			// Check if deleted column exists.
			if ( ! $this->has_deleted_column() ) {
				$this->log_error( 'Cannot soft delete: deleted column does not exist', [ 'snippet_id' => $id ] );
				return false;
			}

			$table = $this->db->get_table_name();

			$update_data = [
				'deleted'    => 1,
				'updated_at' => current_time( 'mysql' ),
			];

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$result = $wpdb->update(
				$table,
				$update_data,
				[ 'id' => $id ],
				[ '%d', '%s' ],
				[ '%d' ]
			);

			if ( $result ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( "[ECS] Snippet soft deleted: {$id}" ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
				return true;
			}

			$this->log_database_error( 'Failed to soft delete snippet', [
				'snippet_id' => $id,
				'wpdb_error' => $wpdb->last_error,
				'operation' => 'soft_delete'
			] );
			return false;
		} catch ( \Exception $e ) {
			$this->log_error( 'Exception in soft_delete method', [
				'message' => $e->getMessage(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
				'snippet_id' => $id
			] );
			return false;
		}
	}

	/**
	 * Restore a snippet from trash.
	 *
	 * @param int $id Snippet ID.
	 * @return bool True on success, false on failure.
	 */
	public function restore( int $id ): bool {
		global $wpdb;

		try {
			// Check if deleted column exists.
			if ( ! $this->has_deleted_column() ) {
				$this->log_error( 'Cannot restore: deleted column does not exist', [ 'snippet_id' => $id ] );
				return false;
			}

			$table = $this->db->get_table_name();

			$update_data = [
				'deleted'    => 0,
				'updated_at' => current_time( 'mysql' ),
			];

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$result = $wpdb->update(
				$table,
				$update_data,
				[ 'id' => $id ],
				[ '%d', '%s' ],
				[ '%d' ]
			);

			if ( $result ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( "[ECS] Snippet restored: {$id}" ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
				return true;
			}

			$this->log_database_error( 'Failed to restore snippet', [
				'snippet_id' => $id,
				'wpdb_error' => $wpdb->last_error,
				'operation' => 'restore'
			] );
			return false;
		} catch ( \Exception $e ) {
			$this->log_error( 'Exception in restore method', [
				'message' => $e->getMessage(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
				'snippet_id' => $id
			] );
			return false;
		}
	}

	/**
	 * Check if the deleted column exists in the database table.
	 *
	 * @return bool True if column exists, false otherwise.
	 */
	private function has_deleted_column(): bool {
		global $wpdb;

		try {
			$table = $this->db->get_table_name();

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$column = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s',
					DB_NAME,
					$table,
					'deleted'
				)
			);

			if ( $wpdb->last_error ) {
				$this->log_database_error( 'Failed to check deleted column existence', [
					'table' => $table,
					'wpdb_error' => $wpdb->last_error,
					'operation' => 'has_deleted_column'
				] );
				return false;
			}

			return ! empty( $column );
		} catch ( \Exception $e ) {
			$this->log_error( 'Exception in has_deleted_column method', [
				'message' => $e->getMessage(),
				'file' => $e->getFile(),
				'line' => $e->getLine()
			] );
			return false;
		}
	}

	/**
	 * Log database-related errors with enhanced context.
	 *
	 * @param string $message Error message.
	 * @param array  $context Additional context data.
	 * @return void
	 */
	private function log_database_error( string $message, array $context = [] ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		global $wpdb;

		$log_entry = [
			'timestamp' => current_time( 'mysql' ),
			'type' => 'DATABASE_ERROR',
			'message' => $message,
			'context' => $context,
			'wpdb_last_error' => $wpdb->last_error ?? 'None',
			'wpdb_last_query' => $wpdb->last_query ?? 'None'
		];

		error_log( '[ECS] ' . wp_json_encode( $log_entry ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	/**
	 * Log general errors with enhanced context.
	 *
	 * @param string $message Error message.
	 * @param array  $context Additional context data.
	 * @return void
	 */
	private function log_error( string $message, array $context = [] ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$log_entry = [
			'timestamp' => current_time( 'mysql' ),
			'type' => 'ERROR',
			'message' => $message,
			'context' => $context,
			'user_id' => get_current_user_id()
		];

		error_log( '[ECS] ' . wp_json_encode( $log_entry ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}
