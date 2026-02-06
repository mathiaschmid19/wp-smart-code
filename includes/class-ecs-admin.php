<?php
/**
 * Admin Handler for Edge Code Snippets.
 *
 * @package ECS
 * @since 1.0.0
 */

declare( strict_types=1 );

namespace ECS;

/**
 * Admin class for managing the plugin admin interface.
 *
 * @since 1.0.0
 */
class Admin {
	/**
	 * Snippet model instance.
	 *
	 * @var Snippet
	 */
	private Snippet $snippet;

	/**
	 * Constructor.
	 *
	 * @param Snippet $snippet Snippet model instance.
	 */
	public function __construct( Snippet $snippet ) {
		$this->snippet = $snippet;
	}

	/**
	 * Initialize admin hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		// Only initialize in admin area
		if ( ! is_admin() ) {
			return;
		}

		// Note: Menu registration is handled by Plugin class
		// Register admin menu on admin_menu hook.
		// add_action( 'admin_menu', [ $this, 'register_menu' ] );

		// Enqueue admin assets.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

		// Add AJAX handlers
		add_action( 'wp_ajax_ecs_toggle_snippet', [ $this, 'ajax_toggle_snippet' ] );
		add_action( 'wp_ajax_ecs_delete_snippet', [ $this, 'ajax_delete_snippet' ] );
		add_action( 'wp_ajax_ecs_bulk_action', [ $this, 'ajax_bulk_action' ] );

		// Add filter to save screen options
		add_filter( 'set-screen-option', [ $this, 'set_screen_option' ], 10, 3 );

		// Admin hooks registered
	}

	/**
	 * Register admin menu.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		$page_hook = add_submenu_page(
			'tools.php',
			__( 'Edge Code Snippets', 'wp-smart-code' ),
			__( 'Edge Code', 'wp-smart-code' ),
			'manage_options',
			'wp-smart-code',
			[ $this, 'render_page' ]
		);

		// Add screen options on page load
		if ( $page_hook ) {
			add_action( "load-{$page_hook}", [ $this, 'add_screen_options' ] );
		}

		// Admin menu registered
	}

	/**
	 * Add screen options for the snippets list table.
	 *
	 * @return void
	 */
	public function add_screen_options(): void {
		$option = 'per_page';
		$args   = [
			'label'   => __( 'Snippets per page', 'wp-smart-code' ),
			'default' => 20,
			'option'  => 'snippets_per_page',
		];

		add_screen_option( $option, $args );
	}

	/**
	 * Save screen option value.
	 *
	 * @param mixed  $status Screen option value. Default false to skip.
	 * @param string $option The option name.
	 * @param mixed  $value  The option value.
	 * @return mixed
	 */
	public function set_screen_option( $status, string $option, $value ) {
		if ( 'snippets_per_page' === $option ) {
			return $value;
		}
		return $status;
	}

	/**
	 * Render admin page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-smart-code' ) );
		}

		// Handle actions
		$this->handle_actions();

		// Get list table instance and prepare items
		$list_table = $this->get_list_table();
		$list_table->prepare_items();

		// Debug logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[ECS] List table items count: ' . count( $list_table->items ?? [] ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		// Make data available in template
		$admin = $this;

		// Include template.
		include ECS_DIR . 'includes/admin/page-snippets.php';
	}

	/**
	 * Get snippets based on current tab.
	 *
	 * @param string $tab Current tab.
	 * @return array
	 */
	private function get_snippets_by_tab( string $tab ): array {
		$args = [ 'limit' => 100 ];
		
		// Check if deleted column exists
		global $wpdb;
		$table_name = $wpdb->prefix . 'ecs_snippets';
		$deleted_column_exists = $wpdb->get_results( $wpdb->prepare(
			"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
			WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'deleted'",
			DB_NAME,
			$table_name
		) );
		
		switch ( $tab ) {
			case 'active':
				$args['active'] = 1;
				if ( ! empty( $deleted_column_exists ) ) {
					$args['deleted'] = 0; // Exclude deleted snippets
				}
				break;
			case 'inactive':
				$args['active'] = 0;
				if ( ! empty( $deleted_column_exists ) ) {
					$args['deleted'] = 0; // Exclude deleted snippets
				}
				break;
			case 'trash':
				if ( ! empty( $deleted_column_exists ) ) {
					$args['deleted'] = 1;
				} else {
					// No deleted column, return empty array
					return [];
				}
				break;
			case 'all':
			default:
				if ( ! empty( $deleted_column_exists ) ) {
					$args['deleted'] = 0; // Exclude deleted snippets by default
				}
				break;
		}
		
		return $this->snippet->all( $args );
	}

	/**
	 * Get counts for each tab.
	 *
	 * @return array
	 */
	private function get_tab_counts(): array {
		// First, let's check if the deleted column exists
		global $wpdb;
		$table_name = $wpdb->prefix . 'ecs_snippets';
		
		// Check if deleted column exists
		$deleted_column_exists = $wpdb->get_results( $wpdb->prepare(
			"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
			WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'deleted'",
			DB_NAME,
			$table_name
		) );
		
		// If deleted column doesn't exist, use fallback queries
		if ( empty( $deleted_column_exists ) ) {
			// Fallback: treat all snippets as non-deleted
			$all_count = $this->snippet->count();
			$active_count = $this->snippet->count( [ 'active' => 1 ] );
			$inactive_count = $this->snippet->count( [ 'active' => 0 ] );
			$trash_count = 0; // No deleted snippets yet
		} else {
			// Use the new deleted column
			$all_count = $this->snippet->count( [ 'deleted' => 0 ] );
			$active_count = $this->snippet->count( [ 'active' => 1, 'deleted' => 0 ] );
			$inactive_count = $this->snippet->count( [ 'active' => 0, 'deleted' => 0 ] );
			$trash_count = $this->snippet->count( [ 'deleted' => 1 ] );
		}
		
		return [
			'all' => $all_count,
			'active' => $active_count,
			'inactive' => $inactive_count,
			'trash' => $trash_count,
		];
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		// Only load on our plugin pages.
		$allowed_pages = [
			'toplevel_page_code-snippet',
			'smart-code_page_wp-smart-code-editor',
			'smart-code_page_wp-smart-code-tools',
		];
		
		if ( ! in_array( $hook, $allowed_pages, true ) ) {
			return;
		}

		// Enqueue admin CSS.
		wp_enqueue_style(
			'ecs-admin',
			ECS_URL . 'assets/css/admin.css',
			[],
			ECS_VERSION,
			'all'
		);

		// Enqueue admin JavaScript.
		wp_enqueue_script(
			'ecs-admin',
			ECS_URL . 'assets/js/admin.js',
			[ 'jquery', 'wp-i18n' ],
			ECS_VERSION,
			true
		);

		// Enqueue admin snippets JavaScript for list table interactions.
		wp_enqueue_script(
			'ecs-admin-snippets',
			ECS_URL . 'assets/js/admin-snippets.js',
			[ 'jquery' ],
			ECS_VERSION,
			true
		);

		// Localize script with data.
		wp_localize_script(
			'ecs-admin',
			'ecsData',
			[
				'nonce'    => wp_create_nonce( 'ecs-admin-nonce' ),
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'i18n'     => [
					'confirmDelete' => __( 'Are you sure you want to delete this snippet?', 'wp-smart-code' ),
					'loading'       => __( 'Loading...', 'wp-smart-code' ),
					'error'         => __( 'An error occurred.', 'wp-smart-code' ),
				],
			]
		);

		// Localize admin snippets script with additional data.
		wp_localize_script(
			'ecs-admin-snippets',
			'ecsData',
			[
				'nonce'    => wp_create_nonce( 'ecs-admin-nonce' ),
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'i18n'     => [
					'confirmDelete'     => __( 'Are you sure you want to delete this snippet?', 'wp-smart-code' ),
					'confirmTrash'      => __( 'Are you sure you want to move this snippet to trash?', 'wp-smart-code' ),
					'confirmRestore'    => __( 'Are you sure you want to restore this snippet?', 'wp-smart-code' ),
					'confirmBulkDelete' => __( 'Are you sure you want to permanently delete the selected snippets?', 'wp-smart-code' ),
					'confirmBulkTrash'  => __( 'Are you sure you want to move the selected snippets to trash?', 'wp-smart-code' ),
					'loading'           => __( 'Loading...', 'wp-smart-code' ),
					'processing'        => __( 'Processing...', 'wp-smart-code' ),
					'error'             => __( 'An error occurred.', 'wp-smart-code' ),
					'selectSnippets'    => __( 'Please select at least one snippet.', 'wp-smart-code' ),
					'selectAction'      => __( 'Please select an action.', 'wp-smart-code' ),
				],
			]
		);

		// Admin assets enqueued
	}

	/**
	 * Get snippet type label.
	 *
	 * @param string $type Snippet type.
	 * @return string
	 */
	public function get_type_label( string $type ): string {
		$types = [
			'php'  => __( 'PHP', 'wp-smart-code' ),
			'js'   => __( 'JavaScript', 'wp-smart-code' ),
			'css'  => __( 'CSS', 'wp-smart-code' ),
			'html' => __( 'HTML', 'wp-smart-code' ),
		];

		return $types[ $type ] ?? ucfirst( $type );
	}

	/**
	 * Get snippet status label.
	 *
	 * @param int $active Whether snippet is active.
	 * @return string
	 */
	public function get_status_label( int $active ): string {
		return $active ? __( 'Active', 'wp-smart-code' ) : __( 'Inactive', 'wp-smart-code' );
	}

	/**
	 * Get list table instance
	 *
	 * @return Snippets_List_Table
	 */
	public function get_list_table(): Snippets_List_Table {
		$list_table = new Snippets_List_Table( $this->snippet );
		return $list_table;
	}

	/**
	 * Get snippet model instance
	 *
	 * @return Snippet
	 */
	public function get_snippet(): Snippet {
		return $this->snippet;
	}

	/**
	 * Handle actions
	 *
	 * @return void
	 */
	private function handle_actions(): void {
		if ( ! isset( $_GET['action'] ) || ! isset( $_GET['id'] ) ) {
			return;
		}

		try {
			$action = sanitize_text_field( wp_unslash( $_GET['action'] ) );
			$id = absint( $_GET['id'] );
			$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

			// Verify user capability
			if ( ! current_user_can( 'manage_options' ) ) {
				$this->log_security_error( 'Action failed: Insufficient permissions', [
					'user_id' => get_current_user_id(),
					'user_login' => wp_get_current_user()->user_login ?? 'Unknown',
					'action' => $action,
					'snippet_id' => $id,
					'ip' => $this->get_client_ip()
				] );
				wp_die( esc_html__( 'You do not have permission to perform this action.', 'wp-smart-code' ) );
			}

			// Verify nonce
			if ( ! wp_verify_nonce( $nonce, $action . '_snippet_' . $id ) ) {
				$this->log_security_error( 'Action failed: Invalid nonce', [
					'user_id' => get_current_user_id(),
					'action' => $action,
					'snippet_id' => $id,
					'ip' => $this->get_client_ip(),
					'provided_nonce' => $nonce
				] );
				wp_die( esc_html__( 'Security check failed.', 'wp-smart-code' ) );
			}

			// Validate action
			$allowed_actions = [ 'toggle', 'trash', 'restore', 'delete' ];
			if ( ! in_array( $action, $allowed_actions, true ) ) {
				$this->log_error( 'Action failed: Invalid action', [ 'action' => $action, 'allowed_actions' => $allowed_actions ] );
				wp_die( esc_html__( 'Invalid action.', 'wp-smart-code' ) );
			}

			// Verify snippet exists
			$snippet = $this->snippet->get( $id );
			if ( ! $snippet ) {
				$this->log_error( 'Action failed: Snippet not found', [ 'snippet_id' => $id, 'action' => $action ] );
				$this->add_admin_notice( __( 'Snippet not found.', 'wp-smart-code' ), 'error' );
				wp_safe_redirect( admin_url( 'admin.php?page=code-snippet' ) );
				exit;
			}

			switch ( $action ) {
				case 'toggle':
					$this->toggle_snippet( $id );
					break;
				case 'trash':
					$this->trash_snippet( $id );
					break;
				case 'restore':
					$this->restore_snippet( $id );
					break;
				case 'delete':
					$this->delete_snippet( $id );
					break;
			}
		} catch ( \Exception $e ) {
			$this->log_error( 'Exception in handle_actions', [
				'message' => $e->getMessage(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
				'action' => $action ?? 'unknown',
				'snippet_id' => $id ?? 'unknown'
			] );
			$this->add_admin_notice( __( 'An unexpected error occurred. Please try again.', 'wp-smart-code' ), 'error' );
			wp_safe_redirect( admin_url( 'admin.php?page=code-snippet' ) );
			exit;
		}
	}

	/**
	 * Toggle snippet status
	 *
	 * @param int $id Snippet ID
	 * @return void
	 */
	private function toggle_snippet( int $id ): void {
		$snippet = $this->snippet->get( $id );
		if ( ! $snippet ) {
			$this->log_error( 'Toggle snippet failed: Snippet not found', [ 'snippet_id' => $id ] );
			$this->add_admin_notice( __( 'Snippet not found.', 'wp-smart-code' ), 'error' );
			wp_safe_redirect( admin_url( 'admin.php?page=code-snippet' ) );
			exit;
		}

		$new_status = $snippet['active'] ? 0 : 1;
		$result = $this->snippet->update( $id, [ 'active' => $new_status ] );

		if ( $result !== false ) {
			do_action( 'ecs_snippet_status_toggled', $id, (bool) $new_status );
			$status_text = $new_status ? __( 'activated', 'wp-smart-code' ) : __( 'deactivated', 'wp-smart-code' );
			$this->add_admin_notice( 
				sprintf( 
					/* translators: %s: Status text (activated/deactivated) */
					__( 'Snippet %s successfully.', 'wp-smart-code' ), 
					$status_text 
				), 
				'success' 
			);
		} else {
			$this->log_database_error( 'Failed to toggle snippet status', [
				'snippet_id' => $id,
				'new_status' => $new_status,
				'operation' => 'toggle'
			] );
			$this->add_admin_notice( __( 'Failed to update snippet status. Please try again.', 'wp-smart-code' ), 'error' );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=code-snippet' ) );
		exit;
	}

	/**
	 * Trash snippet
	 *
	 * @param int $id Snippet ID
	 * @return void
	 */
	private function trash_snippet( int $id ): void {
		$result = $this->snippet->soft_delete( $id );
		
		if ( $result ) {
			$this->add_admin_notice( __( 'Snippet moved to trash successfully.', 'wp-smart-code' ), 'success' );
		} else {
			$this->log_database_error( 'Failed to trash snippet', [
				'snippet_id' => $id,
				'operation' => 'trash'
			] );
			$this->add_admin_notice( __( 'Failed to move snippet to trash. Please try again.', 'wp-smart-code' ), 'error' );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=code-snippet' ) );
		exit;
	}

	/**
	 * Restore snippet
	 *
	 * @param int $id Snippet ID
	 * @return void
	 */
	private function restore_snippet( int $id ): void {
		$result = $this->snippet->restore( $id );
		
		if ( $result ) {
			$this->add_admin_notice( __( 'Snippet restored successfully.', 'wp-smart-code' ), 'success' );
		} else {
			$this->log_database_error( 'Failed to restore snippet', [
				'snippet_id' => $id,
				'operation' => 'restore'
			] );
			$this->add_admin_notice( __( 'Failed to restore snippet. Please try again.', 'wp-smart-code' ), 'error' );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=code-snippet&view=trash' ) );
		exit;
	}

	/**
	 * Delete snippet permanently
	 *
	 * @param int $id Snippet ID
	 * @return void
	 */
	private function delete_snippet( int $id ): void {
		$result = $this->snippet->delete( $id );
		
		if ( $result ) {
			$this->add_admin_notice( __( 'Snippet deleted permanently.', 'wp-smart-code' ), 'success' );
		} else {
			$this->log_database_error( 'Failed to delete snippet permanently', [
				'snippet_id' => $id,
				'operation' => 'permanent_delete'
			] );
			$this->add_admin_notice( __( 'Failed to delete snippet permanently. Please try again.', 'wp-smart-code' ), 'error' );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=code-snippet&view=trash' ) );
		exit;
	}

	/**
	 * AJAX handler for toggling snippet status.
	 *
	 * @return void
	 */
	public function ajax_toggle_snippet(): void {
		try {
			// Verify AJAX request
			if ( ! $this->verify_ajax_request() ) {
				return;
			}

			$id = absint( $_POST['id'] ?? 0 );
			if ( ! $id ) {
				$this->log_error( 'Toggle snippet failed: Invalid snippet ID', [ 'provided_id' => $_POST['id'] ?? 'not_set' ] );
				$this->send_ajax_response( false, __( 'Invalid snippet ID.', 'wp-smart-code' ) );
				return;
			}

			$snippet = $this->snippet->get( $id );
			if ( ! $snippet ) {
				$this->log_error( 'Toggle snippet failed: Snippet not found', [ 'snippet_id' => $id ] );
				$this->send_ajax_response( false, __( 'Snippet not found.', 'wp-smart-code' ) );
				return;
			}

			$new_status = isset( $_POST['active'] ) ? absint( $_POST['active'] ) : ( $snippet['active'] ? 0 : 1 );
			$result = $this->snippet->update( $id, [ 'active' => $new_status ] );

			if ( $result !== false ) {
				// Fire action hook for snippet status toggled
				do_action( 'ecs_snippet_status_toggled', $id, (bool) $new_status );

				$this->send_ajax_response(
					true,
					sprintf(
						/* translators: %s: New status label */
						__( 'Snippet status changed to %s.', 'wp-smart-code' ),
						$new_status ? __( 'Active', 'wp-smart-code' ) : __( 'Inactive', 'wp-smart-code' )
					),
					[
						'active' => $new_status,
						'status' => $new_status ? __( 'Active', 'wp-smart-code' ) : __( 'Inactive', 'wp-smart-code' ),
					]
				);
			} else {
				$this->log_database_error( 'Failed to toggle snippet status', [
					'snippet_id' => $id,
					'new_status' => $new_status,
					'operation' => 'update'
				] );
				$this->send_ajax_response( false, __( 'Failed to update snippet. Please try again.', 'wp-smart-code' ) );
			}
		} catch ( \Exception $e ) {
			$this->log_error( 'Exception in ajax_toggle_snippet', [
				'message' => $e->getMessage(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
				'snippet_id' => $id ?? 'unknown'
			] );
			$this->send_ajax_response( false, __( 'An unexpected error occurred. Please try again.', 'wp-smart-code' ) );
		}
	}

	/**
	 * AJAX handler for deleting a single snippet.
	 *
	 * @return void
	 */
	public function ajax_delete_snippet(): void {
		try {
			// Verify AJAX request
			if ( ! $this->verify_ajax_request() ) {
				return;
			}

			$id = absint( $_POST['id'] ?? 0 );
			if ( ! $id ) {
				$this->log_error( 'Delete snippet failed: Invalid snippet ID', [ 'provided_id' => $_POST['id'] ?? 'not_set' ] );
				$this->send_ajax_response( false, __( 'Invalid snippet ID.', 'wp-smart-code' ) );
				return;
			}

			$snippet = $this->snippet->get( $id );
			if ( ! $snippet ) {
				$this->log_error( 'Delete snippet failed: Snippet not found', [ 'snippet_id' => $id ] );
				$this->send_ajax_response( false, __( 'Snippet not found.', 'wp-smart-code' ) );
				return;
			}

			// Check if this is a permanent delete or soft delete
			$permanent = isset( $_POST['permanent'] ) && $_POST['permanent'];

			if ( $permanent ) {
				// Permanently delete the snippet
				$result = $this->snippet->delete( $id );
				$message = __( 'Snippet permanently deleted.', 'wp-smart-code' );
				$operation = 'permanent_delete';
			} else {
				// Soft delete (move to trash)
				$result = $this->snippet->soft_delete( $id );
				$message = __( 'Snippet moved to trash.', 'wp-smart-code' );
				$operation = 'soft_delete';
			}

			if ( $result ) {
				$this->send_ajax_response( true, $message );
			} else {
				$this->log_database_error( 'Failed to delete snippet', [
					'snippet_id' => $id,
					'operation' => $operation,
					'permanent' => $permanent
				] );
				$this->send_ajax_response( false, __( 'Failed to delete snippet. Please try again.', 'wp-smart-code' ) );
			}
		} catch ( \Exception $e ) {
			$this->log_error( 'Exception in ajax_delete_snippet', [
				'message' => $e->getMessage(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
				'snippet_id' => $id ?? 'unknown'
			] );
			$this->send_ajax_response( false, __( 'An unexpected error occurred. Please try again.', 'wp-smart-code' ) );
		}
	}

	/**
	 * AJAX handler for bulk actions.
	 *
	 * @return void
	 */
	public function ajax_bulk_action(): void {
		try {
			// Verify AJAX request
			if ( ! $this->verify_ajax_request() ) {
				return;
			}

			$action = sanitize_text_field( $_POST['bulk_action'] ?? '' );
			$snippet_ids = array_map( 'absint', $_POST['snippet'] ?? [] );

			if ( empty( $snippet_ids ) ) {
				$this->log_error( 'Bulk action failed: No snippets selected', [ 'action' => $action ] );
				$this->send_ajax_response( false, __( 'No snippets selected.', 'wp-smart-code' ) );
				return;
			}

			if ( empty( $action ) ) {
				$this->log_error( 'Bulk action failed: Invalid action', [ 'provided_action' => $_POST['bulk_action'] ?? 'not_set' ] );
				$this->send_ajax_response( false, __( 'Invalid action.', 'wp-smart-code' ) );
				return;
			}

			// Validate action
			$allowed_actions = [ 'activate', 'deactivate', 'trash', 'restore', 'delete' ];
			if ( ! in_array( $action, $allowed_actions, true ) ) {
				$this->log_error( 'Bulk action failed: Unknown action', [ 'action' => $action, 'allowed_actions' => $allowed_actions ] );
				$this->send_ajax_response( false, __( 'Invalid action.', 'wp-smart-code' ) );
				return;
			}

			do_action( 'ecs_before_bulk_action', $action, $snippet_ids );

			$updated = 0;
			$failed = 0;
			$failed_ids = [];

			foreach ( $snippet_ids as $id ) {
				$result = false;

				// Verify snippet exists before processing
				$snippet = $this->snippet->get( $id );
				if ( ! $snippet ) {
					$failed++;
					$failed_ids[] = $id;
					$this->log_error( 'Bulk action failed: Snippet not found', [ 'snippet_id' => $id, 'action' => $action ] );
					continue;
				}

				switch ( $action ) {
					case 'activate':
						$result = $this->snippet->update( $id, [ 'active' => 1 ] );
						if ( $result !== false ) {
							do_action( 'ecs_snippet_status_toggled', $id, true );
						}
						break;

					case 'deactivate':
						$result = $this->snippet->update( $id, [ 'active' => 0 ] );
						if ( $result !== false ) {
							do_action( 'ecs_snippet_status_toggled', $id, false );
						}
						break;

					case 'trash':
						$result = $this->snippet->soft_delete( $id );
						break;

					case 'restore':
						$result = $this->snippet->restore( $id );
						break;

					case 'delete':
						$result = $this->snippet->delete( $id );
						break;
				}

				if ( $result !== false ) {
					$updated++;
				} else {
					$failed++;
					$failed_ids[] = $id;
					$this->log_database_error( 'Bulk action failed on snippet', [
						'snippet_id' => $id,
						'action' => $action,
						'operation' => 'bulk_action'
					] );
				}
			}

			do_action( 'ecs_after_bulk_action', $action, $snippet_ids, $updated > 0 );

			if ( $updated > 0 ) {
				$message = sprintf(
					/* translators: %d: Number of updated snippets */
					_n( '%d snippet updated.', '%d snippets updated.', $updated, 'wp-smart-code' ),
					$updated
				);

				if ( $failed > 0 ) {
					$message .= ' ' . sprintf(
						/* translators: %d: Number of failed snippets */
						_n( '%d snippet failed.', '%d snippets failed.', $failed, 'wp-smart-code' ),
						$failed
					);
				}

				$this->send_ajax_response( true, $message, [ 'updated' => $updated, 'failed' => $failed ] );
			} else {
				$this->log_error( 'Bulk action failed: No snippets updated', [
					'action' => $action,
					'snippet_ids' => $snippet_ids,
					'failed_ids' => $failed_ids
				] );
				$this->send_ajax_response( false, __( 'Failed to update snippets. Please try again.', 'wp-smart-code' ) );
			}
		} catch ( \Exception $e ) {
			$this->log_error( 'Exception in ajax_bulk_action', [
				'message' => $e->getMessage(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
				'action' => $action ?? 'unknown',
				'snippet_ids' => $snippet_ids ?? []
			] );
			$this->send_ajax_response( false, __( 'An unexpected error occurred. Please try again.', 'wp-smart-code' ) );
		}
	}

	/**
	 * Verify AJAX request with nonce and capability checks.
	 *
	 * @return bool True if request is valid, false otherwise.
	 */
	private function verify_ajax_request(): bool {
		// Verify nonce
		if ( ! check_ajax_referer( 'ecs-admin-nonce', 'nonce', false ) ) {
			$this->log_security_error( 'AJAX request failed: Invalid nonce', [
				'user_id' => get_current_user_id(),
				'ip' => $this->get_client_ip(),
				'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
				'action' => $_POST['action'] ?? 'Unknown'
			] );
			$this->send_ajax_response( false, __( 'Security check failed.', 'wp-smart-code' ), [], 403 );
			return false;
		}

		// Verify user capability
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->log_security_error( 'AJAX request failed: Insufficient permissions', [
				'user_id' => get_current_user_id(),
				'user_login' => wp_get_current_user()->user_login ?? 'Unknown',
				'ip' => $this->get_client_ip(),
				'action' => $_POST['action'] ?? 'Unknown'
			] );
			$this->send_ajax_response( false, __( 'You do not have permission to perform this action.', 'wp-smart-code' ), [], 403 );
			return false;
		}

		return true;
	}

	/**
	 * Send consistent AJAX JSON response.
	 *
	 * @param bool   $success Whether the operation was successful.
	 * @param string $message Response message.
	 * @param array  $data    Optional additional data.
	 * @param int    $status_code HTTP status code (default: 200).
	 * @return void
	 */
	private function send_ajax_response( bool $success, string $message, array $data = [], int $status_code = 200 ): void {
		$response = [
			'message' => $message,
		];

		if ( ! empty( $data ) ) {
			$response = array_merge( $response, $data );
		}

		if ( $success ) {
			wp_send_json_success( $response, $status_code );
		} else {
			wp_send_json_error( $response, $status_code );
		}
	}

	/**
	 * Log security-related errors with enhanced context.
	 *
	 * @param string $message Error message.
	 * @param array  $context Additional context data.
	 * @return void
	 */
	private function log_security_error( string $message, array $context = [] ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$log_entry = [
			'timestamp' => current_time( 'mysql' ),
			'type' => 'SECURITY_ERROR',
			'message' => $message,
			'context' => $context,
			'request_uri' => $_SERVER['REQUEST_URI'] ?? 'Unknown',
			'referer' => $_SERVER['HTTP_REFERER'] ?? 'Unknown'
		];

		error_log( '[ECS] ' . wp_json_encode( $log_entry ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
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

	/**
	 * Get client IP address.
	 *
	 * @return string Client IP address.
	 */
	private function get_client_ip(): string {
		$ip_keys = [ 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR' ];
		
		foreach ( $ip_keys as $key ) {
			if ( array_key_exists( $key, $_SERVER ) === true ) {
				$ip = $_SERVER[ $key ];
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = explode( ',', $ip )[0];
				}
				$ip = trim( $ip );
				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
			}
		}
		
		return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
	}

	/**
	 * Display admin notice for errors or success messages.
	 *
	 * @param string $message The message to display.
	 * @param string $type    The notice type (success, error, warning, info).
	 * @return void
	 */
	public function add_admin_notice( string $message, string $type = 'error' ): void {
		add_settings_error( 'ecs_messages', 'ecs_message', $message, $type );
	}
}