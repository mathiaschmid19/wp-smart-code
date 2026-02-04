<?php
/**
 * Editor Admin Handler for Edge Code Snippets.
 *
 * @package ECS
 * @since 1.0.0
 */

declare( strict_types=1 );

namespace ECS;

/**
 * Admin Editor class for managing snippet editing and saving.
 *
 * @since 1.0.0
 */
class AdminEditor {
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
	 * Initialize editor hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		// Only initialize in admin area
		if ( ! is_admin() ) {
			return;
		}

		// Handle form submission
		add_action( 'admin_post_ecs_save_snippet', [ $this, 'handle_save_snippet' ] );

		// Log initialization
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[ECS] Editor hooks registered.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Handle snippet save.
	 *
	 * @return void
	 */
	public function handle_save_snippet(): void {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to save snippets.', 'code-snippet' ) );
		}

		// Verify nonce
		if ( ! isset( $_POST['ecs_snippet_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ecs_snippet_nonce'] ) ), 'ecs_save_snippet' ) ) {
			wp_die( esc_html__( 'Security verification failed.', 'code-snippet' ) );
		}

		// Get snippet data
		$snippet_id = isset( $_POST['snippet_id'] ) ? absint( $_POST['snippet_id'] ) : 0;
		$title      = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$slug       = isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '';
		$type       = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : 'php';
		$code       = isset( $_POST['code'] ) ? wp_unslash( $_POST['code'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$active     = isset( $_POST['active'] ) && '1' === $_POST['active'];

		// Get conditions from hidden field
		$conditions_json = isset( $_POST['conditions'] ) ? wp_unslash( $_POST['conditions'] ) : '{}'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		// Validate conditions JSON
		$conditions = json_decode( $conditions_json, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$conditions = [];
		}

		// Auto-generate slug if empty
		if ( empty( $slug ) ) {
			$slug = sanitize_title( $title );
		}

		// Validate required fields
		if ( empty( $title ) ) {
			wp_die( esc_html__( 'Title is required.', 'code-snippet' ) );
		}

		if ( empty( $code ) ) {
			wp_die( esc_html__( 'Code is required.', 'code-snippet' ) );
		}

		// Validate syntax
		$validation_result = SyntaxValidator::validate( $code, $type );
		if ( ! $validation_result['valid'] ) {
			$error_message = $validation_result['error'];
			if ( $validation_result['line'] > 0 ) {
				/* translators: %1$s: Error message, %2$d: Line number */
				$error_message = sprintf( __( '%1$s on line %2$d', 'code-snippet' ), $error_message, $validation_result['line'] );
			}
			wp_die( esc_html( $error_message ) );
		}

		// If activating, perform a dry-run execution to check for runtime errors
		$snippet_error = null;
		if ( $active && $type === 'php' ) {
			// Get sandbox instance
			$sandbox = Sandbox::get_instance();
			
			// Execute in sandbox
			$execution_result = $sandbox->execute_php( $code );
			
			if ( ! $execution_result['success'] ) {
				// Deactivate if there's an error
				$active = false;
				
				// Store error message for later display
				$snippet_error = $execution_result['error'];
			}
		}

		// Get mode
		$mode = isset( $_POST['mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : 'auto_insert';
		if ( ! in_array( $mode, [ 'auto_insert', 'shortcode' ], true ) ) {
			$mode = 'auto_insert';
		}

		// Validate mode for snippet type
		if ( $mode === 'shortcode' && in_array( $type, [ 'css', 'js' ], true ) ) {
			wp_die( esc_html__( 'Shortcode mode is not available for CSS and JavaScript snippets. Please use Auto Insert mode instead.', 'code-snippet' ) );
		}

		// Prepare data
		$data = [
			'title'      => $title,
			'slug'       => $slug,
			'type'       => $type,
			'code'       => $code,
			'active'     => $active ? 1 : 0,
			'mode'       => $mode,
			'conditions' => ! empty( $conditions ) ? wp_json_encode( $conditions ) : null,
			'author_id'  => get_current_user_id(),
		];

		// Save snippet
		if ( $snippet_id > 0 ) {
			// Update existing snippet
			$result = $this->snippet->update( $snippet_id, $data );
			if ( ! $result && $result !== 0 ) {
				wp_die( esc_html__( 'Failed to update snippet.', 'code-snippet' ) );
			}
			
			// Build redirect URL
			$redirect_args = [
				'page'       => 'wp-smart-code-editor',
				'snippet_id' => $snippet_id,
				'message'    => $snippet_error ? 'error' : 'updated',
			];
			
			// Add error message if there was an execution error
			if ( $snippet_error ) {
				$redirect_args['error_msg'] = urlencode( $snippet_error );
			}
			
			$redirect_url = add_query_arg( $redirect_args, admin_url( 'admin.php' ) );
		} else {
			// Create new snippet
			$new_id = $this->snippet->create( $data );
			if ( ! $new_id ) {
				wp_die( esc_html__( 'Failed to create snippet.', 'code-snippet' ) );
			}
			
			// Build redirect URL
			$redirect_args = [
				'page'       => 'wp-smart-code-editor',
				'snippet_id' => $new_id,
				'message'    => $snippet_error ? 'error' : 'created',
			];
			
			// Add error message if there was an execution error
			if ( $snippet_error ) {
				$redirect_args['error_msg'] = urlencode( $snippet_error );
			}
			
			$redirect_url = add_query_arg( $redirect_args, admin_url( 'admin.php' ) );
		}

		// Redirect
		wp_safe_redirect( $redirect_url );
		exit;
	}
}

