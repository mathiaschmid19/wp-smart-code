<?php
/**
 * Import/Export Handler for Edge Code Snippets.
 *
 * @package ECS
 * @since 1.0.0
 */

declare( strict_types=1 );

namespace ECS;

/**
 * Import/Export class for managing snippet import and export operations.
 *
 * @since 1.0.0
 */
class ImportExport {
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
	 * Initialize import/export hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		// Only initialize in admin area
		if ( ! is_admin() ) {
			return;
		}

		// Handle export request
		add_action( 'admin_post_ecs_export_snippets', [ $this, 'handle_export' ] );

		// Handle import request
		add_action( 'admin_post_ecs_import_snippets', [ $this, 'handle_import' ] );

		// AJAX handlers for single snippet export
		add_action( 'wp_ajax_ecs_export_snippet', [ $this, 'ajax_export_single' ] );

		// Log initialization
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[ECS] Import/Export hooks registered.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Export all snippets to JSON.
	 *
	 * @param array $options Export options.
	 * @return array Export data.
	 */
	public function export_all( array $options = [] ): array {
		$defaults = [
			'include_inactive' => true,
			'include_metadata' => true,
		];

		$options = wp_parse_args( $options, $defaults );

		// Get all snippets
		$filters = [];
		if ( ! $options['include_inactive'] ) {
			$filters['active'] = 1;
		}

		$snippets = $this->snippet->all( $filters );

		// Format snippets for export
		$export_data = [
			'version'      => ECS_VERSION,
			'exported_at'  => current_time( 'mysql' ),
			'exported_by'  => get_current_user_id(),
			'site_url'     => get_site_url(),
			'snippets'     => [],
		];

		foreach ( $snippets as $snippet ) {
			$export_snippet = [
				'title'      => $snippet['title'],
				'slug'       => $snippet['slug'],
				'type'       => $snippet['type'],
				'code'       => $snippet['code'],
				'active'     => (bool) $snippet['active'],
				'conditions' => $snippet['conditions'],
			];

			if ( $options['include_metadata'] ) {
				$export_snippet['created_at'] = $snippet['created_at'];
				$export_snippet['updated_at'] = $snippet['updated_at'];
			}

			$export_data['snippets'][] = $export_snippet;
		}

		return $export_data;
	}

	/**
	 * Export single snippet to JSON.
	 *
	 * @param int $snippet_id Snippet ID.
	 * @return array|false Export data or false on failure.
	 */
	public function export_single( int $snippet_id ) {
		$snippet = $this->snippet->get( $snippet_id );

		if ( ! $snippet ) {
			return false;
		}

		return [
			'version'     => ECS_VERSION,
			'exported_at' => current_time( 'mysql' ),
			'exported_by' => get_current_user_id(),
			'site_url'    => get_site_url(),
			'snippet'     => [
				'title'      => $snippet['title'],
				'slug'       => $snippet['slug'],
				'type'       => $snippet['type'],
				'code'       => $snippet['code'],
				'active'     => (bool) $snippet['active'],
				'conditions' => $snippet['conditions'],
				'created_at' => $snippet['created_at'],
				'updated_at' => $snippet['updated_at'],
			],
		];
	}

	/**
	 * Import snippets from JSON.
	 *
	 * @param array $data Import data.
	 * @param array $options Import options.
	 * @return array Import results.
	 */
	public function import_from_json( array $data, array $options = [] ): array {
		$defaults = [
			'skip_duplicates'   => false,
			'deactivate_on_import' => true,
			'update_existing'   => false,
		];

		$options = wp_parse_args( $options, $defaults );

		$results = [
			'success' => [],
			'skipped' => [],
			'errors'  => [],
			'total'   => 0,
			'format'  => 'unknown',
		];

		// Detect format and convert if necessary
		$format = FormatAdapter::detect_format( $data );
		$results['format'] = $format;

		// Debug logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[ECS Import] Detected format: ' . $format ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[ECS Import] Data structure: ' . print_r( array_keys( $data ), true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		if ( 'unknown' === $format ) {
			$results['errors'][] = __( 'Unknown or unsupported import format.', 'wp-smart-code' );
			return $results;
		}

		// Convert to ECS format if needed
		if ( 'ecs' !== $format ) {
			$data = FormatAdapter::convert_to_ecs_format( $data, $format );
			
			// Debug logging after conversion
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[ECS Import] After conversion - Data structure: ' . print_r( array_keys( $data ), true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				if ( isset( $data['snippets'] ) ) {
					error_log( '[ECS Import] Snippets count: ' . count( $data['snippets'] ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
			}
		}

		// Validate import data (after conversion, should be in ECS format)
		if ( ! isset( $data['snippets'] ) || ! is_array( $data['snippets'] ) ) {
			// Check if this is a single snippet export
			if ( isset( $data['snippet'] ) && is_array( $data['snippet'] ) ) {
				$data['snippets'] = [ $data['snippet'] ];
			} else {
				$results['errors'][] = sprintf(
					/* translators: %s: format name */
					__( 'Invalid %s import data format after conversion.', 'wp-smart-code' ),
					FormatAdapter::get_format_name( $format )
				);
				return $results;
			}
		}

		$results['total'] = count( $data['snippets'] );

		foreach ( $data['snippets'] as $index => $snippet_data ) {
			// Validate required fields
			if ( empty( $snippet_data['title'] ) || empty( $snippet_data['code'] ) || empty( $snippet_data['type'] ) ) {
				$results['errors'][] = sprintf(
					/* translators: %d: snippet index */
					__( 'Snippet #%d is missing required fields.', 'wp-smart-code' ),
					$index + 1
				);
				continue;
			}

			// Check for existing snippet with same slug
			$existing_slug = $this->snippet->get_by_slug( $snippet_data['slug'] );

			if ( $existing_slug ) {
				if ( $options['skip_duplicates'] ) {
					$results['skipped'][] = sprintf(
						/* translators: %s: snippet title */
						__( 'Snippet "%s" already exists (skipped).', 'wp-smart-code' ),
						$snippet_data['title']
					);
					continue;
				}

				if ( $options['update_existing'] ) {
					// Update existing snippet
					$update_data = [
						'title'      => $snippet_data['title'],
						'type'       => $snippet_data['type'],
						'code'       => $snippet_data['code'],
						'active'     => $options['deactivate_on_import'] ? 0 : ( $snippet_data['active'] ? 1 : 0 ),
						'conditions' => $snippet_data['conditions'] ?? null,
					];

					$updated = $this->snippet->update( $existing_slug['id'], $update_data );

					if ( $updated ) {
						$results['success'][] = sprintf(
							/* translators: %s: snippet title */
							__( 'Snippet "%s" updated successfully.', 'wp-smart-code' ),
							$snippet_data['title']
						);
					} else {
						$results['errors'][] = sprintf(
							/* translators: %s: snippet title */
							__( 'Failed to update snippet "%s".', 'wp-smart-code' ),
							$snippet_data['title']
						);
					}
					continue;
				}

				// Generate unique slug
				$base_slug = sanitize_title( $snippet_data['title'] );
				$new_slug  = $base_slug;
				$counter   = 1;

				while ( $this->snippet->get_by_slug( $new_slug ) ) {
					$new_slug = $base_slug . '-' . $counter;
					$counter++;
				}

				$snippet_data['slug'] = $new_slug;
			}

			// Validate syntax before importing
			$validation_result = SyntaxValidator::validate( $snippet_data['code'], $snippet_data['type'] );
			if ( ! $validation_result['valid'] ) {
				$results['errors'][] = sprintf(
					/* translators: 1: snippet title, 2: error message */
					__( 'Snippet "%1$s" has syntax errors: %2$s', 'wp-smart-code' ),
					$snippet_data['title'],
					$validation_result['error']
				);
				continue;
			}

			// Prepare data for import
			$import_data = [
				'title'      => $snippet_data['title'],
				'slug'       => $snippet_data['slug'] ?? sanitize_title( $snippet_data['title'] ),
				'type'       => $snippet_data['type'],
				'code'       => $snippet_data['code'],
				'active'     => $options['deactivate_on_import'] ? 0 : ( $snippet_data['active'] ? 1 : 0 ),
				'conditions' => $snippet_data['conditions'] ?? null,
				'author_id'  => get_current_user_id(),
			];

			// Create snippet
			$snippet_id = $this->snippet->create( $import_data );

			if ( $snippet_id ) {
				$results['success'][] = sprintf(
					/* translators: %s: snippet title */
					__( 'Snippet "%s" imported successfully.', 'wp-smart-code' ),
					$snippet_data['title']
				);
			} else {
				$results['errors'][] = sprintf(
					/* translators: %s: snippet title */
					__( 'Failed to import snippet "%s".', 'wp-smart-code' ),
					$snippet_data['title']
				);
			}
		}

		return $results;
	}

	/**
	 * Handle export request.
	 *
	 * @return void
	 */
	public function handle_export(): void {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to export snippets.', 'wp-smart-code' ) );
		}

		// Verify nonce
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ecs_export_snippets' ) ) {
			wp_die( esc_html__( 'Security verification failed.', 'wp-smart-code' ) );
		}

		// Get export options
		$include_inactive = isset( $_GET['include_inactive'] ) && '1' === $_GET['include_inactive'];

		$options = [
			'include_inactive' => $include_inactive,
			'include_metadata' => true,
		];

		// Export snippets
		$export_data = $this->export_all( $options );

		// Generate filename
		$filename = 'wp-smart-code-' . gmdate( 'Y-m-d-His' ) . '.json';

		// Set headers for download
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// Output JSON
		echo wp_json_encode( $export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		exit;
	}

	/**
	 * Handle import request.
	 *
	 * @return void
	 */
	public function handle_import(): void {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to import snippets.', 'wp-smart-code' ) );
		}

		// Verify nonce
		if ( ! isset( $_POST['ecs_import_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ecs_import_nonce'] ) ), 'ecs_import_snippets' ) ) {
			wp_die( esc_html__( 'Security verification failed.', 'wp-smart-code' ) );
		}

		// Check if file was uploaded
		if ( ! isset( $_FILES['import_file'] ) || UPLOAD_ERR_OK !== $_FILES['import_file']['error'] ) {
			$error_message = 'No file was uploaded or upload failed.';
			if ( isset( $_FILES['import_file']['error'] ) ) {
				$upload_errors = [
					UPLOAD_ERR_INI_SIZE => 'File too large (upload_max_filesize)',
					UPLOAD_ERR_FORM_SIZE => 'File too large (MAX_FILE_SIZE)',
					UPLOAD_ERR_PARTIAL => 'File only partially uploaded',
					UPLOAD_ERR_NO_FILE => 'No file uploaded',
					UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
					UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
					UPLOAD_ERR_EXTENSION => 'File upload stopped by extension',
				];
				$error_message .= ' Error: ' . ( $upload_errors[ $_FILES['import_file']['error'] ] ?? 'Unknown error' );
			}
			wp_die( esc_html( $error_message ) );
		}

		// Validate file type
		$file_name = sanitize_file_name( $_FILES['import_file']['name'] );
		$file_ext = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );
		
		// Debug logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[ECS Import] File name: ' . $file_name ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[ECS Import] File extension: ' . $file_ext ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		
		if ( 'json' !== $file_ext ) {
			wp_die( esc_html__( 'Please upload a valid JSON file.', 'wp-smart-code' ) );
		}

		// Read file contents
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$file_contents = file_get_contents( $_FILES['import_file']['tmp_name'] );

		if ( false === $file_contents ) {
			wp_die( esc_html__( 'Failed to read uploaded file.', 'wp-smart-code' ) );
		}

		// Parse JSON
		$import_data = json_decode( $file_contents, true );

		// Debug logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[ECS Import] JSON decode result: ' . ( $import_data ? 'Success' : 'Failed' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				error_log( '[ECS Import] JSON error: ' . json_last_error_msg() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$json_error = json_last_error_msg();
			wp_die( sprintf( 
				/* translators: %s: JSON error message */
				esc_html__( 'Invalid JSON file format: %s', 'wp-smart-code' ), 
				esc_html( $json_error ) 
			) );
		}

		// Ensure we have an array
		if ( ! is_array( $import_data ) ) {
			wp_die( esc_html__( 'JSON file must contain an array or object.', 'wp-smart-code' ) );
		}

		// Get import options
		$options = [
			'skip_duplicates'      => isset( $_POST['skip_duplicates'] ) && '1' === $_POST['skip_duplicates'],
			'deactivate_on_import' => isset( $_POST['deactivate_on_import'] ) && '1' === $_POST['deactivate_on_import'],
			'update_existing'      => isset( $_POST['update_existing'] ) && '1' === $_POST['update_existing'],
		];

		// Import snippets
		$results = $this->import_from_json( $import_data, $options );

		// Redirect with results
		$redirect_url = add_query_arg(
			[
				'page'              => 'wp-smart-code-tools',
				'tab'               => 'import',
				'import'            => 'complete',
				'imported'          => count( $results['success'] ),
				'skipped'           => count( $results['skipped'] ),
				'errors'            => count( $results['errors'] ),
				'total'             => $results['total'],
				'format'            => $results['format'],
			],
			admin_url( 'admin.php' )
		);

		// Store detailed results in transient for display
		if ( ! empty( $results['errors'] ) || ! empty( $results['skipped'] ) ) {
			set_transient( 'ecs_import_results_' . get_current_user_id(), $results, 300 );
		}

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * AJAX handler for single snippet export.
	 *
	 * @return void
	 */
	public function ajax_export_single(): void {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'wp-smart-code' ) ] );
		}

		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => __( 'Security verification failed.', 'wp-smart-code' ) ] );
		}

		// Get snippet ID
		$snippet_id = isset( $_POST['snippet_id'] ) ? absint( $_POST['snippet_id'] ) : 0;

		if ( ! $snippet_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid snippet ID.', 'wp-smart-code' ) ] );
		}

		// Export snippet
		$export_data = $this->export_single( $snippet_id );

		if ( ! $export_data ) {
			wp_send_json_error( [ 'message' => __( 'Snippet not found.', 'wp-smart-code' ) ] );
		}

		wp_send_json_success( [ 'data' => $export_data ] );
	}
}

