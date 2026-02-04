<?php
/**
 * Database Handler for Edge Code Snippets.
 *
 * @package ECS
 * @since 1.0.0
 */

declare( strict_types=1 );

namespace ECS;

/**
 * Database class for managing ECS tables and schema.
 *
 * @since 1.0.0
 */
class DB {
	/**
	 * Table name for snippets.
	 *
	 * @var string
	 */
	private string $table_name;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'ecs_snippets';
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
	 * Install the database table using dbDelta.
	 *
	 * @return void
	 * @throws Exception If table creation fails.
	 */
	public function install(): void {
		global $wpdb;

		// Require dbDelta if not already loaded.
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$this->table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
			title VARCHAR(255) NOT NULL,
			slug VARCHAR(255) NOT NULL UNIQUE,
			type VARCHAR(50) NOT NULL,
			code LONGTEXT NOT NULL,
			active TINYINT(1) NOT NULL DEFAULT 0,
			deleted TINYINT(1) NOT NULL DEFAULT 0,
			mode VARCHAR(20) NOT NULL DEFAULT 'auto_insert',
			conditions LONGTEXT DEFAULT NULL,
			author_id BIGINT UNSIGNED NOT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			KEY idx_type (type),
			KEY idx_active (active),
			KEY idx_deleted (deleted),
			KEY idx_mode (mode),
			KEY idx_author_id (author_id),
			KEY idx_created_at (created_at)
		) {$charset_collate};";

		$result = dbDelta( $sql );

		// Check if table was created successfully
		if ( ! $this->table_exists() ) {
			throw new Exception( "Failed to create database table: {$this->table_name}" );
		}

		// Run migrations for existing tables
		$this->migrate();

		// Log table creation.
		// Database table installed
	}

	/**
	 * Check if the table exists.
	 *
	 * @return bool
	 */
	public function table_exists(): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->query( $wpdb->prepare( 'SHOW TABLES LIKE %s', $this->table_name ) ) > 0;
	}

	/**
	 * Migrate the database to add new columns.
	 *
	 * @return void
	 */
	public function migrate(): void {
		global $wpdb;

		// Check if mode column exists
		$column_exists = $wpdb->get_results( $wpdb->prepare(
			"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
			WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'mode'",
			DB_NAME,
			$this->table_name
		) );

		if ( empty( $column_exists ) ) {
		// Mode column does not exist, adding it
			
			// Add mode column
			$result1 = $wpdb->query( "ALTER TABLE {$this->table_name} ADD COLUMN mode VARCHAR(20) NOT NULL DEFAULT 'auto_insert' AFTER active" );
			if ( $result1 === false ) {
				// Keep critical error logging for database failures
				error_log( '[ECS] Failed to add mode column: ' . $wpdb->last_error );
			} else {
				// Mode column added successfully
			}
			
			// Add index for mode column
			$result2 = $wpdb->query( "ALTER TABLE {$this->table_name} ADD KEY idx_mode (mode)" );
			if ( $result2 === false ) {
				// Keep critical error logging for database failures
				error_log( '[ECS] Failed to add mode index: ' . $wpdb->last_error );
			} else {
				// Mode index added successfully
			}
			// Mode column already exists, skipping migration
		}
		
		// Check if deleted column exists
		$deleted_column_exists = $wpdb->get_results( $wpdb->prepare(
			"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
			WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'deleted'",
			DB_NAME,
			$this->table_name
		) );
		
		if ( empty( $deleted_column_exists ) ) {
			// Add deleted column
			$result3 = $wpdb->query( "ALTER TABLE {$this->table_name} ADD COLUMN deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER active" );
			if ( $result3 === false ) {
				error_log( '[ECS] Failed to add deleted column: ' . $wpdb->last_error );
			}
			
			// Add index for deleted column
			$result4 = $wpdb->query( "ALTER TABLE {$this->table_name} ADD KEY idx_deleted (deleted)" );
			if ( $result4 === false ) {
				error_log( '[ECS] Failed to add deleted index: ' . $wpdb->last_error );
			}
		}
	}

	/**
	 * Drop the table (cleanup).
	 *
	 * @return void
	 */
	public function drop_table(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query( "DROP TABLE IF EXISTS {$this->table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
