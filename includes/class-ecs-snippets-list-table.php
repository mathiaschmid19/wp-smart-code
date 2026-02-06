<?php
/**
 * Snippets List Table for Edge Code Snippets.
 *
 * @package ECS
 * @since 1.0.0
 */

declare( strict_types=1 );

namespace ECS;

// Load WP_List_Table if not already loaded.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Snippets_List_Table class extending WP_List_Table.
 *
 * @since 1.0.0
 */
class Snippets_List_Table extends \WP_List_Table {
	/**
	 * Snippet model instance.
	 *
	 * @var Snippet
	 */
	private Snippet $snippet_model;

	/**
	 * Current view (all, active, inactive, trash).
	 *
	 * @var string
	 */
	private string $current_view = 'all';

	/**
	 * Constructor.
	 *
	 * @param Snippet $snippet_model Snippet model instance.
	 */
	public function __construct( Snippet $snippet_model ) {
		$this->snippet_model = $snippet_model;

		// Set current view from URL parameter.
		$this->current_view = isset( $_GET['view'] ) ? sanitize_text_field( wp_unslash( $_GET['view'] ) ) : 'all';

		parent::__construct(
			[
				'singular' => 'snippet',
				'plural'   => 'snippets',
				'ajax'     => false,
			]
		);
	}

	/**
	 * Prepare items for display.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		// Process bulk actions.
		$this->process_bulk_action();

		// Get current page number.
		$current_page = $this->get_pagenum();

		// Get per page option.
		$per_page = $this->get_items_per_page( 'snippets_per_page', 20 );
		$per_page = apply_filters( 'ecs_snippets_per_page', $per_page );

		// Build query arguments.
		$args = [
			'limit'  => $per_page,
			'offset' => ( $current_page - 1 ) * $per_page,
		];

		// Add type filter from URL.
		if ( ! empty( $_REQUEST['type'] ) ) {
			$args['type'] = sanitize_text_field( wp_unslash( $_REQUEST['type'] ) );
		}

		// Add tag filter from URL.
		if ( ! empty( $_REQUEST['tag'] ) ) {
			$args['tag'] = sanitize_text_field( wp_unslash( $_REQUEST['tag'] ) );
		}

		// Add view filter.
		switch ( $this->current_view ) {
			case 'active':
				$args['active'] = 1;
				$args['deleted'] = 0;
				break;
			case 'inactive':
				$args['active'] = 0;
				$args['deleted'] = 0;
				break;
			case 'trash':
				$args['deleted'] = 1;
				break;
			case 'all':
			default:
				$args['deleted'] = 0;
				break;
		}

		// Add search query.
		if ( ! empty( $_REQUEST['s'] ) ) {
			$args['search'] = sanitize_text_field( wp_unslash( $_REQUEST['s'] ) );
		}

		// Add sorting.
		if ( ! empty( $_REQUEST['orderby'] ) ) {
			$args['orderby'] = sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) );
		}
		if ( ! empty( $_REQUEST['order'] ) ) {
			$args['order'] = sanitize_text_field( wp_unslash( $_REQUEST['order'] ) );
		}

		// Debug logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[ECS] Prepare items args: ' . wp_json_encode( $args ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		// Get items.
		$this->items = $this->snippet_model->all( $args );

		// Debug logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[ECS] Items retrieved: ' . count( $this->items ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		// Get total items for pagination.
		$count_args = $args;
		unset( $count_args['limit'], $count_args['offset'], $count_args['orderby'], $count_args['order'] );
		$total_items = $this->snippet_model->count( $count_args );

		// Debug logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[ECS] Total items count: ' . $total_items ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		// Set pagination arguments.
		$this->set_pagination_args(
			[
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			]
		);

		// Set columns
		$this->_column_headers = [
			$this->get_columns(),
			[],
			$this->get_sortable_columns(),
			$this->get_primary_column_name(),
		];
	}

	/**
	 * Get table columns.
	 *
	 * @return array
	 */
	public function get_columns(): array {
		$columns = [
			'cb'     => '<input type="checkbox" />',
			'title'  => __( 'Title', 'wp-smart-code' ),
			'type'   => __( 'Type', 'wp-smart-code' ),
			'tags'   => __( 'Tags', 'wp-smart-code' ),
			'mode'   => __( 'Mode', 'wp-smart-code' ),
			'author' => __( 'Author', 'wp-smart-code' ),
			'active' => __( 'Active', 'wp-smart-code' ),
		];

		return apply_filters( 'ecs_snippets_list_columns', $columns );
	}

	/**
	 * Get sortable columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns(): array {
		$sortable = [
			'title'  => [ 'title', false ],
			'type'   => [ 'type', false ],
			'author' => [ 'author_id', false ],
		];

		return apply_filters( 'ecs_snippets_sortable_columns', $sortable );
	}

	/**
	 * Render default column.
	 *
	 * @param array  $item        Snippet data.
	 * @param string $column_name Column name.
	 * @return string
	 */
	public function column_default( $item, $column_name ): string {
		switch ( $column_name ) {
			case 'type':
				$type_labels = [
					'php'  => 'PHP',
					'js'   => 'JavaScript',
					'css'  => 'CSS',
					'html' => 'HTML',
				];
				$label = $type_labels[ $item['type'] ] ?? ucfirst( $item['type'] );
				return '<span class="badge badge-' . esc_attr( $item['type'] ) . '">' . esc_html( $label ) . '</span>';

			case 'mode':
				$mode = $item['mode'] ?? 'auto_insert';
				$mode_label = $mode === 'shortcode' ? __( 'Shortcode', 'wp-smart-code' ) : __( 'Auto Insert', 'wp-smart-code' );
				$mode_class = $mode === 'shortcode' ? 'mode-shortcode' : 'mode-auto-insert';
				return '<span class="badge ' . esc_attr( $mode_class ) . '">' . esc_html( $mode_label ) . '</span>';

			case 'author':
				$author = get_user_by( 'ID', (int) $item['author_id'] );
				return $author ? esc_html( $author->display_name ) : __( 'Unknown', 'wp-smart-code' );

			case 'active':
				$snippet_id = (int) $item['id'];
				$active = (int) $item['active'];
				$checked = $active ? 'checked' : '';
				
				return sprintf(
					'<label class="ecs-toggle-switch" data-snippet-id="%d">
						<input type="checkbox" class="ecs-toggle-input ecs-snippet-toggle" %s data-snippet-id="%d">
						<span class="ecs-toggle-slider"></span>
					</label>',
					$snippet_id,
					$checked,
					$snippet_id
				);

			default:
				return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
		}
	}

	/**
	 * Render tags column.
	 *
	 * @param array $item Snippet data.
	 * @return string
	 */
	public function column_tags( $item ): string {
		$tags_json = $item['tags'] ?? '[]';
		$tags = json_decode( $tags_json, true );

		if ( empty( $tags ) || ! is_array( $tags ) ) {
			return '<span aria-hidden="true">—</span>';
		}

		$output = [];
		foreach ( $tags as $tag ) {
			$filter_url = add_query_arg( 'tag', $tag, admin_url( 'admin.php?page=code-snippet' ) );
			$output[] = sprintf( 
				'<a href="%s" class="ecs-tag">%s</a>',
				esc_url( $filter_url ),
				esc_html( $tag )
			);
		}

		return implode( ' ', $output );
	}

	/**
	 * Render checkbox column.
	 *
	 * @param array $item Snippet data.
	 * @return string
	 */
	public function column_cb( $item ): string {
		return sprintf(
			'<input type="checkbox" name="snippet[]" value="%d" />',
			(int) $item['id']
		);
	}

	/**
	 * Render title column with row actions.
	 *
	 * @param array $item Snippet data.
	 * @return string
	 */
	public function column_title( $item ): string {
		$snippet_id = (int) $item['id'];
		$title = '<strong>' . esc_html( $item['title'] ) . '</strong>';

		// Build row actions based on current view.
		$actions = [];

		if ( $this->current_view === 'trash' ) {
			// Trash view actions.
			$actions['restore'] = sprintf(
				'<a href="%s">%s</a>',
				wp_nonce_url(
					admin_url( 'admin.php?page=code-snippet&action=restore&id=' . $snippet_id ),
					'restore_snippet_' . $snippet_id
				),
				__( 'Restore', 'wp-smart-code' )
			);
			$actions['delete'] = sprintf(
				'<a href="%s" class="delete">%s</a>',
				wp_nonce_url(
					admin_url( 'admin.php?page=code-snippet&action=delete&id=' . $snippet_id ),
					'delete_snippet_' . $snippet_id
				),
				__( 'Delete Permanently', 'wp-smart-code' )
			);
		} else {
			// Normal view actions.
			$actions['edit'] = sprintf(
				'<a href="%s">%s</a>',
				admin_url( 'admin.php?page=wp-smart-code-editor&snippet_id=' . $snippet_id ),
				__( 'Edit', 'wp-smart-code' )
			);

			// Duplicate action.
			$actions['duplicate'] = sprintf(
				'<a href="%s">%s</a>',
				wp_nonce_url(
					admin_url( 'admin.php?page=code-snippet&action=duplicate&id=' . $snippet_id ),
					'duplicate_snippet_' . $snippet_id
				),
				__( 'Duplicate', 'wp-smart-code' )
			);

			// Toggle action.
			$active = (int) $item['active'];
			$toggle_text = $active ? __( 'Deactivate', 'wp-smart-code' ) : __( 'Activate', 'wp-smart-code' );
			$actions['toggle'] = sprintf(
				'<a href="%s">%s</a>',
				wp_nonce_url(
					admin_url( 'admin.php?page=code-snippet&action=toggle&id=' . $snippet_id ),
					'toggle_snippet_' . $snippet_id
				),
				$toggle_text
			);

			$actions['trash'] = sprintf(
				'<a href="%s" class="delete">%s</a>',
				wp_nonce_url(
					admin_url( 'admin.php?page=code-snippet&action=trash&id=' . $snippet_id ),
					'trash_snippet_' . $snippet_id
				),
				__( 'Trash', 'wp-smart-code' )
			);
		}

		$actions = apply_filters( 'ecs_snippet_row_actions', $actions, $item );

		return $title . $this->row_actions( $actions );
	}

	/**
	 * Get bulk actions.
	 *
	 * @return array
	 */
	public function get_bulk_actions(): array {
		$actions = [];

		if ( $this->current_view === 'trash' ) {
			$actions['restore'] = __( 'Restore', 'wp-smart-code' );
			$actions['delete']  = __( 'Delete Permanently', 'wp-smart-code' );
		} else {
			$actions['activate']   = __( 'Activate', 'wp-smart-code' );
			$actions['deactivate'] = __( 'Deactivate', 'wp-smart-code' );
			$actions['trash']      = __( 'Move to Trash', 'wp-smart-code' );
		}

		return apply_filters( 'ecs_bulk_actions', $actions, $this->current_view );
	}

	/**
	 * Process bulk actions.
	 *
	 * @return void
	 */
	public function process_bulk_action(): void {
		try {
			// Check if action is set.
			$action = $this->current_action();
			if ( ! $action ) {
				return;
			}

			// Get selected snippets.
			$snippet_ids = isset( $_REQUEST['snippet'] ) ? array_map( 'absint', (array) $_REQUEST['snippet'] ) : [];
			if ( empty( $snippet_ids ) ) {
				return;
			}

			// Verify nonce.
			if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'bulk-snippets' ) ) {
				$this->log_security_error( 'Bulk action failed: Invalid nonce', [
					'user_id' => get_current_user_id(),
					'action' => $action,
					'snippet_ids' => $snippet_ids,
					'ip' => $this->get_client_ip()
				] );
				wp_die( esc_html__( 'Security check failed.', 'wp-smart-code' ) );
			}

			// Check permissions.
			if ( ! current_user_can( 'manage_options' ) ) {
				$this->log_security_error( 'Bulk action failed: Insufficient permissions', [
					'user_id' => get_current_user_id(),
					'user_login' => wp_get_current_user()->user_login ?? 'Unknown',
					'action' => $action,
					'snippet_ids' => $snippet_ids,
					'ip' => $this->get_client_ip()
				] );
				wp_die( esc_html__( 'You do not have permission to perform this action.', 'wp-smart-code' ) );
			}

			// Validate action
			$allowed_actions = [ 'activate', 'deactivate', 'trash', 'restore', 'delete' ];
			if ( ! in_array( $action, $allowed_actions, true ) ) {
				$this->log_error( 'Bulk action failed: Invalid action', [ 'action' => $action, 'allowed_actions' => $allowed_actions ] );
				add_settings_error( 'ecs_messages', 'ecs_message', __( 'Invalid action.', 'wp-smart-code' ), 'error' );
				wp_safe_redirect( remove_query_arg( [ 'action', 'action2', 'snippet', '_wpnonce', '_wp_http_referer' ] ) );
				exit;
			}

			do_action( 'ecs_before_bulk_action', $action, $snippet_ids );

			$updated = 0;
			$failed = 0;

			foreach ( $snippet_ids as $snippet_id ) {
				// Verify snippet exists
				$snippet = $this->snippet_model->get( $snippet_id );
				if ( ! $snippet ) {
					$failed++;
					$this->log_error( 'Bulk action failed: Snippet not found', [ 'snippet_id' => $snippet_id, 'action' => $action ] );
					continue;
				}

				$result = false;
				switch ( $action ) {
					case 'activate':
						$result = $this->snippet_model->update( $snippet_id, [ 'active' => 1 ] );
						break;

					case 'deactivate':
						$result = $this->snippet_model->update( $snippet_id, [ 'active' => 0 ] );
						break;

					case 'trash':
						$result = $this->snippet_model->soft_delete( $snippet_id );
						break;

					case 'restore':
						$result = $this->snippet_model->restore( $snippet_id );
						break;

					case 'delete':
						$result = $this->snippet_model->delete( $snippet_id );
						break;
				}

				if ( $result !== false ) {
					$updated++;
				} else {
					$failed++;
					$this->log_error( 'Bulk action failed on snippet', [
						'snippet_id' => $snippet_id,
						'action' => $action,
						'operation' => 'bulk_action'
					] );
				}
			}

			do_action( 'ecs_after_bulk_action', $action, $snippet_ids, $updated > 0 );

			// Display messages.
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

				add_settings_error( 'ecs_messages', 'ecs_message', $message, 'success' );
			} elseif ( $failed > 0 ) {
				add_settings_error( 'ecs_messages', 'ecs_message', __( 'Failed to update snippets. Please try again.', 'wp-smart-code' ), 'error' );
			}

			// Redirect to remove query parameters.
			$redirect_url = remove_query_arg( [ 'action', 'action2', 'snippet', '_wpnonce', '_wp_http_referer' ] );
			if ( ! empty( $this->current_view ) && $this->current_view !== 'all' ) {
				$redirect_url = add_query_arg( 'view', $this->current_view, $redirect_url );
			}
			wp_safe_redirect( $redirect_url );
			exit;
		} catch ( \Exception $e ) {
			$this->log_error( 'Exception in process_bulk_action', [
				'message' => $e->getMessage(),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
				'action' => $action ?? 'unknown',
				'snippet_ids' => $snippet_ids ?? []
			] );
			add_settings_error( 'ecs_messages', 'ecs_message', __( 'An unexpected error occurred. Please try again.', 'wp-smart-code' ), 'error' );
			wp_safe_redirect( remove_query_arg( [ 'action', 'action2', 'snippet', '_wpnonce', '_wp_http_referer' ] ) );
			exit;
		}
	}

	/**
	 * Get views (tabs).
	 *
	 * @return array
	 */
	protected function get_views(): array {
		$views = [];
		$current = $this->current_view;
		
		// Get type filter from URL.
		$type_filter = ! empty( $_REQUEST['type'] ) ? '&type=' . sanitize_text_field( wp_unslash( $_REQUEST['type'] ) ) : '';
		
		// Build count args with type filter if present.
		$count_args = [ 'deleted' => 0 ];
		if ( ! empty( $_REQUEST['type'] ) ) {
			$count_args['type'] = sanitize_text_field( wp_unslash( $_REQUEST['type'] ) );
		}

		// Get counts for each view.
		$all_count = $this->snippet_model->count( $count_args );
		$active_count = $this->snippet_model->count( array_merge( $count_args, [ 'active' => 1 ] ) );
		$inactive_count = $this->snippet_model->count( array_merge( $count_args, [ 'active' => 0 ] ) );
		
		$trash_args = [ 'deleted' => 1 ];
		if ( ! empty( $_REQUEST['type'] ) ) {
			$trash_args['type'] = sanitize_text_field( wp_unslash( $_REQUEST['type'] ) );
		}
		$trash_count = $this->snippet_model->count( $trash_args );

		// All view.
		$class = ( $current === 'all' ) ? ' class="current"' : '';
		$views['all'] = sprintf(
			'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
			admin_url( 'admin.php?page=code-snippet' . $type_filter ),
			$class,
			__( 'All', 'wp-smart-code' ),
			$all_count
		);

		// Active view.
		$class = ( $current === 'active' ) ? ' class="current"' : '';
		$views['active'] = sprintf(
			'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
			admin_url( 'admin.php?page=code-snippet&view=active' . $type_filter ),
			$class,
			__( 'Active', 'wp-smart-code' ),
			$active_count
		);

		// Inactive view.
		$class = ( $current === 'inactive' ) ? ' class="current"' : '';
		$views['inactive'] = sprintf(
			'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
			admin_url( 'admin.php?page=code-snippet&view=inactive' . $type_filter ),
			$class,
			__( 'Inactive', 'wp-smart-code' ),
			$inactive_count
		);

		// Trash view.
		$class = ( $current === 'trash' ) ? ' class="current"' : '';
		$views['trash'] = sprintf(
			'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
			admin_url( 'admin.php?page=code-snippet&view=trash' . $type_filter ),
			$class,
			__( 'Trash', 'wp-smart-code' ),
			$trash_count
		);

		return $views;
	}

	/**
	 * Display the search box.
	 *
	 * @param string $text     The 'submit' button label.
	 * @param string $input_id ID attribute value for the search input field.
	 * @return void
	 */
	public function search_box( $text, $input_id ): void {
		if ( empty( $_REQUEST['s'] ) && ! $this->has_items() ) {
			return;
		}

		$input_id = $input_id . '-search-input';

		if ( ! empty( $_REQUEST['orderby'] ) ) {
			echo '<input type="hidden" name="orderby" value="' . esc_attr( sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) ) . '" />';
		}
		if ( ! empty( $_REQUEST['order'] ) ) {
			echo '<input type="hidden" name="order" value="' . esc_attr( sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) ) . '" />';
		}
		if ( ! empty( $_REQUEST['view'] ) ) {
			echo '<input type="hidden" name="view" value="' . esc_attr( sanitize_text_field( wp_unslash( $_REQUEST['view'] ) ) ) . '" />';
		}
		if ( ! empty( $_REQUEST['type'] ) ) {
			echo '<input type="hidden" name="type" value="' . esc_attr( sanitize_text_field( wp_unslash( $_REQUEST['type'] ) ) ) . '" />';
		}
		if ( ! empty( $_REQUEST['tag'] ) ) {
			echo '<input type="hidden" name="tag" value="' . esc_attr( sanitize_text_field( wp_unslash( $_REQUEST['tag'] ) ) ) . '" />';
		}
		?>
		<p class="search-box">
			<label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html( $text ); ?>:</label>
			<input type="search" id="<?php echo esc_attr( $input_id ); ?>" name="s" value="<?php _admin_search_query(); ?>" />
			<?php submit_button( $text, '', '', false, array( 'id' => 'search-submit' ) ); ?>
		</p>
		<?php
	}

	/**
	 * Message to display when no items are found.
	 *
	 * @return void
	 */
	public function no_items(): void {
		if ( ! empty( $_REQUEST['s'] ) ) {
			esc_html_e( 'No snippets found.', 'wp-smart-code' );
		} else {
			esc_html_e( 'No snippets available.', 'wp-smart-code' );
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
}
