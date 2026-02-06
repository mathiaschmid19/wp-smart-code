<?php
/**
 * Main Plugin Class for Edge Code Snippets.
 *
 * @package ECS
 * @since 1.0.0
 */

declare( strict_types=1 );

namespace ECS;

/**
 * Main plugin class using the singleton pattern.
 *
 * @since 1.0.0
 */
class Plugin {
	/**
	 * The single instance of the Plugin class.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Database instance.
	 *
	 * @var DB|null
	 */
	private ?DB $db = null;

	/**
	 * Snippet model instance.
	 *
	 * @var Snippet|null
	 */
	private ?Snippet $snippet = null;

	/**
	 * Admin instance.
	 *
	 * @var Admin|null
	 */
	private ?Admin $admin = null;

	/**
	 * Admin Editor instance.
	 *
	 * @var AdminEditor|null
	 */
	private ?AdminEditor $admin_editor = null;

	/**
	 * REST API instance.
	 *
	 * @var REST|null
	 */
	private ?REST $rest = null;

	/**
	 * Sandbox instance for safe snippet execution.
	 *
	 * @var Sandbox|null
	 */
	private ?Sandbox $sandbox = null;

	/**
	 * Import/Export instance.
	 *
	 * @var ImportExport|null
	 */
	private ?ImportExport $import_export = null;

	/**
	 * Shortcode instance.
	 *
	 * @var Shortcode|null
	 */
	private ?Shortcode $shortcode = null;

	/**
	 * AI AJAX handler instance.
	 *
	 * @var AI_Ajax|null
	 */
	private ?AI_Ajax $ai_ajax = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init();
	}

	/**
	 * Initialize the plugin.
	 *
	 * @return void
	 */
	private function init(): void {
		// Load textdomain and prepare directories on init.
		add_action( 'init', [ $this, 'load_textdomain' ] );
		add_action( 'init', [ $this, 'prepare_directories' ] );

		// Initialize database and models immediately so they are available for admin hooks.
		$this->initialize_db();

		// Initialize sandbox for safe snippet execution.
		$this->initialize_sandbox();

		// Handle single-item actions early, before any output
		add_action( 'admin_init', [ $this, 'handle_single_actions' ] );

		// Register admin interface directly.
		add_action( 'admin_menu', [ $this, 'initialize_admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'initialize_admin_assets' ] );

		// Initialize admin editor for snippet editing.
		$this->initialize_admin_editor();

		// Initialize import/export system.
		$this->initialize_import_export();

		// Initialize shortcode system.
		$this->initialize_shortcode();

		// Initialize AI AJAX handlers.
		$this->initialize_ai_ajax();

		// Initialize Admin class early for AJAX handlers.
		// AJAX requests don't trigger admin_menu, so we need to register handlers earlier.
		$this->initialize_admin();

		// Register REST API routes.
		add_action( 'rest_api_init', [ $this, 'initialize_rest_api' ] );

		// Add admin notice for database issues.
		add_action( 'admin_notices', [ $this, 'check_database_schema' ] );

		// Add admin notice for snippet execution errors.
		add_action( 'admin_notices', [ $this, 'display_snippet_error_notices' ] );

		// Customize admin footer for our plugin pages.
		add_action( 'admin_footer', [ $this, 'customize_admin_footer' ] );


		// Plugin initialized
	}

	/**
	 * Load the plugin's text domain for translations.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'wp-smart-code',
			false,
			dirname( ECS_BASENAME ) . '/languages'
		);

		// Text domain loaded
	}

	/**
	 * Prepare plugin directories (create if they don't exist).
	 *
	 * @return void
	 */
	public function prepare_directories(): void {
		$directories = [
			'admin'      => ECS_DIR . 'admin',
			'languages'  => ECS_DIR . 'languages',
			'assets'     => ECS_DIR . 'assets',
			'assets-js'  => ECS_DIR . 'assets/js',
			'assets-css' => ECS_DIR . 'assets/css',
		];

		foreach ( $directories as $dir_name => $dir_path ) {
			if ( ! is_dir( $dir_path ) ) {
				wp_mkdir_p( $dir_path );
			}
		}
	}

	/**
	 * Initialize database and CRUD instances.
	 *
	 * @return void
	 */
	public function initialize_db(): void {

		if ( $this->db && $this->snippet ) {
			return;
		}

		try {
			$this->db = new DB();
			
			// Install database table if it doesn't exist
			if ( ! $this->db->table_exists() ) {
				$this->db->install();
			} else {
				// Run migrations for existing tables
				$this->db->migrate();
			}
			
			$this->snippet = new Snippet( $this->db );

		} catch ( \Throwable $throwable ) {
			// Handle initialization errors silently
		}
	}

	/**
	 * Initialize sandbox for safe snippet execution.
	 *
	 * @return void
	 */
	public function initialize_sandbox(): void {

		try {
			// Initialize sandbox instance using autoloader
			$this->sandbox = Sandbox::get_instance();
		} catch ( \Throwable $throwable ) {
			// Handle sandbox initialization errors silently
		}
	}

	/**
	 * Initialize admin editor for snippet editing and saving.
	 *
	 * @return void
	 */
	public function initialize_admin_editor(): void {

		// Ensure database and models are initialized
		if ( ! $this->snippet || ! $this->db ) {
			$this->initialize_db();
		}

		// Check if we have the snippet model
		if ( ! $this->snippet ) {
			return;
		}

		// Create admin editor instance and initialize
		$this->admin_editor = new AdminEditor( $this->snippet );
		$this->admin_editor->init();

	}

	/**
	 * Initialize Admin class for AJAX handlers.
	 *
	 * @return void
	 */
	public function initialize_admin(): void {

		// Ensure database and models are initialized
		if ( ! $this->snippet || ! $this->db ) {
			$this->initialize_db();
		}

		// Check if we have the snippet model
		if ( ! $this->snippet ) {
			return;
		}

		// Create admin instance if not exists
		if ( ! $this->admin ) {
			$this->admin = new Admin( $this->snippet );
			$this->admin->init(); // Initialize admin hooks (AJAX handlers)
		}

	}

	/**
	 * Initialize import/export system.
	 *
	 * @return void
	 */
	public function initialize_import_export(): void {

		// Ensure database and models are initialized
		if ( ! $this->snippet || ! $this->db ) {
			$this->initialize_db();
		}

		// Check if we have the snippet model
		if ( ! $this->snippet ) {
			return;
		}

		// Create import/export instance and initialize
		$this->import_export = new ImportExport( $this->snippet );
		$this->import_export->init();

	}

	/**
	 * Initialize shortcode system.
	 *
	 * @return void
	 */
	public function initialize_shortcode(): void {

		// Ensure database and models are initialized
		if ( ! $this->snippet || ! $this->db ) {
			$this->initialize_db();
		}

		// Check if we have the snippet model
		if ( ! $this->snippet ) {
			return;
		}

		// Create shortcode instance and initialize
		$this->shortcode = new Shortcode( $this->snippet );
		$this->shortcode->init();

	}

	/**
	 * Initialize admin menu directly.
	 *
	 * @return void
	 */
	public function initialize_admin_menu(): void {

		// Ensure database and models are initialized.

		if ( ! $this->snippet || ! $this->db ) {
			$this->initialize_db();

		}

		// Check if we have the snippet model
		if ( ! $this->snippet ) {
			return;
		}

		// Create admin instance if not exists
		if ( ! $this->admin ) {
			$this->admin = new Admin( $this->snippet );
			$this->admin->init(); // Initialize admin hooks (AJAX, etc.)
		}

		// Register the menu directly as a top-level menu
		$page_hook = add_menu_page(
			__( 'Smart Code', 'wp-smart-code' ),
			__( 'Smart Code', 'wp-smart-code' ),
			'manage_options',
			'wp-smart-code',
			[ $this, 'render_admin_page' ],
			'data:image/svg+xml;base64,PHN2ZyBmaWxsPSJub25lIiBoZWlnaHQ9IjQ4IiB2aWV3Qm94PSIwIDAgNDAgNDgiIHdpZHRoPSI0MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cGF0aCBkPSJtMCA5YzAtMi43NjE0MiAyLjIzODU4LTUgNS01aDEwYzIuNzYxNCAwIDUgMi4yMzg1OCA1IDV2OS44MTkyYy4wMDAyLjA2LjAwMDMuMTIwMy4wMDAzLjE4MDggMCAyLjc1NzUgMi4yMzIyIDQuOTkzNiA0Ljk4ODEgNWguMDExNiAxMGMyLjc2MTQgMCA1IDIuMjM4NiA1IDV2MTBjMCAyLjc2MTQtMi4yMzg2IDUtNSA1aC0xMGMtMi43NjE0IDAtNS0yLjIzODYtNS01di0xMGMwLS4wMTM5LjAwMDEtLjAyNzcuMDAwMi0uMDQxNi0uMDIyNC0yLjc0MjItMi4yNTIzLTQuOTU4NC00Ljk5OTktNC45NTg0LS4wMTI5IDAtLjAyNTggMC0uMDM4NyAwaC05Ljk2MTZjLTIuNzYxNDIgMC01LTIuMjM4Ni01LTV6IiBmaWxsPSIjZmZmIi8+PC9zdmc+', // Base64 encoded SVG logo
			30 // Position after Comments
		);

		// Add submenu for editor page
		add_submenu_page(
			'wp-smart-code',
			__( 'Add New Snippet', 'wp-smart-code' ),
			__( 'Add New', 'wp-smart-code' ),
			'manage_options',
			'wp-smart-code-editor',
			[ $this, 'render_editor_page' ]
		);

		// Add submenu for library page
		add_submenu_page(
			'wp-smart-code',
			__( 'Snippet Library', 'wp-smart-code' ),
			__( 'Library', 'wp-smart-code' ),
			'manage_options',
			'wp-smart-code-library',
			[ $this, 'render_library_page' ]
		);

		// Add submenu for tools page
		add_submenu_page(
			'wp-smart-code',
			__( 'Tools', 'wp-smart-code' ),
			__( 'Tools', 'wp-smart-code' ),
			'manage_options',
			'wp-smart-code-tools',
			[ $this, 'render_tools_page' ]
		);

		// Add hidden submenu for database fix page
		add_submenu_page(
			'',
			__( 'Fix Database', 'wp-smart-code' ),
			'',
			'manage_options',
			'ecs-fix-database',
			[ $this, 'render_fix_database_page' ]
		);

		// Admin menu registered
	}

	/**
	 * Initialize admin assets.
	 *
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function initialize_admin_assets( string $hook ): void {
		// Only load on our plugin pages
		if ( 'toplevel_page_code-snippet' !== $hook && 
			 'smart-code_page_wp-smart-code-editor' !== $hook && 
			 'smart-code_page_wp-smart-code-tools' !== $hook && 
			 'smart-code_page_wp-smart-code-library' !== $hook ) {
			return;
		}

		// Loading admin assets

		// Enqueue admin CSS
		wp_enqueue_style(
			'ecs-admin',
			ECS_URL . 'assets/css/admin.css',
			[],
			ECS_VERSION,
			'all'
		);

		// Make WordPress API available
		wp_enqueue_script( 'wp-api-fetch' );

		// Enqueue appropriate JavaScript based on page
		if ( 'smart-code_page_wp-smart-code-editor' === $hook ) {
			// Enqueue CodeMirror from WordPress core with proper settings
			$codemirror_settings = wp_enqueue_code_editor(
				array(
					'type'       => 'application/x-httpd-php',
					'codemirror' => array(
						'mode'              => 'application/x-httpd-php',
						'lineNumbers'       => true,
						'lineWrapping'      => true,
						'indentUnit'        => 2,
						'tabSize'           => 2,
						'indentWithTabs'    => false,
						'autoCloseBrackets' => true,
						'matchBrackets'     => true,
						'autoCloseTags'     => true,
						'styleActiveLine'   => true,
						'continueComments'  => true,
						'extraKeys'         => array(
							'Ctrl-Space' => 'autocomplete',
							'Ctrl-/'     => 'toggleComment',
							'Cmd-/'      => 'toggleComment',
						),
					),
				)
			);
			
			// Enqueue additional CodeMirror modes
			wp_enqueue_script( 'code-editor' );
			wp_enqueue_style( 'code-editor' );
			
			// Enqueue specific CodeMirror modes for better syntax highlighting
			wp_add_inline_script(
				'code-editor',
				'wp.codeEditor.defaultSettings.codemirror.mode = "application/x-httpd-php";'
			);
			
			// Enqueue Highlight.js from CDN for additional highlighting
			wp_enqueue_script(
				'highlight-js',
				'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js',
				[],
				'11.9.0',
				true
			);
			
			// Enqueue Highlight.js CSS theme
			wp_enqueue_style(
				'highlight-js-theme',
				'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github.min.css',
				[],
				'11.9.0'
			);

			// Enqueue CodeMirror Themes
			$cm_version = '5.65.16';
			$themes = [ 'dracula', 'monokai', 'material', 'solarized', 'nord' ];
			foreach ( $themes as $theme ) {
				wp_enqueue_style(
					'codemirror-theme-' . $theme,
					"https://cdnjs.cloudflare.com/ajax/libs/codemirror/{$cm_version}/theme/{$theme}.min.css",
					[],
					$cm_version
				);
			}
			
			// Editor page JavaScript
			wp_enqueue_script(
				'ecs-editor',
				ECS_URL . 'assets/js/editor.js',
				[ 'jquery', 'wp-i18n', 'wp-api-fetch', 'code-editor', 'highlight-js' ],
				ECS_VERSION,
				true
			);

			// Enqueue AI Assistant script
			wp_enqueue_script(
				'ecs-ai-assistant',
				ECS_URL . 'assets/js/ai-assistant.js',
				[ 'jquery' ],
				ECS_VERSION,
				true
			);

			// Localize editor script
			wp_localize_script(
				'ecs-editor',
				'ecsEditorData',
				[
					'nonce'   => wp_create_nonce( 'wp_rest' ),
					'restUrl' => rest_url(),
					'listUrl' => admin_url( 'admin.php?page=code-snippet' ),
				]
			);

			// Localize AI Assistant script
			wp_localize_script( 'ecs-ai-assistant', 'ecsAiData', [
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ecs_ai_nonce' ),
			] );
		} else {
			// Snippets list page JavaScript
			wp_enqueue_script(
				'ecs-admin',
				ECS_URL . 'assets/js/admin.js',
				[ 'jquery', 'wp-i18n', 'wp-api-fetch' ],
				ECS_VERSION,
				true
			);

			// Localize admin script
			wp_localize_script(
				'ecs-admin',
				'ecsData',
				[
					'nonce'    => wp_create_nonce( 'wp_rest' ),
					'restUrl'  => rest_url(),
					'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
					'i18n'     => [
						'confirmDelete' => __( 'Are you sure you want to delete this snippet?', 'wp-smart-code' ),
						'loading'       => __( 'Loading...', 'wp-smart-code' ),
						'error'         => __( 'An error occurred.', 'wp-smart-code' ),
					],
				]
			);
		}
	}

	/**
	 * Handle single-item actions (toggle, trash, restore, delete).
	 *
	 * @return void
	 */
	public function handle_single_actions(): void {
		// Only run on our plugin page
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'wp-smart-code' ) {
			return;
		}

		// Check if action and ID are set
		if ( ! isset( $_GET['action'] ) || ! isset( $_GET['id'] ) ) {
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_GET['action'] ) );
		$id = absint( $_GET['id'] );
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		// Verify user capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'wp-smart-code' ) );
		}

		// Verify nonce
		if ( ! wp_verify_nonce( $nonce, $action . '_snippet_' . $id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'wp-smart-code' ) );
		}

		// Validate action
		$allowed_actions = [ 'toggle', 'trash', 'restore', 'delete', 'duplicate' ];
		if ( ! in_array( $action, $allowed_actions, true ) ) {
			wp_die( esc_html__( 'Invalid action.', 'wp-smart-code' ) );
		}

		// Verify snippet exists
		$snippet = $this->snippet->get( $id );
		if ( ! $snippet ) {
			add_settings_error( 'ecs_messages', 'ecs_message', __( 'Snippet not found.', 'wp-smart-code' ), 'error' );
			wp_safe_redirect( admin_url( 'admin.php?page=code-snippet' ) );
			exit;
		}

		// Process the action
		$result = false;
		$message = '';
		
		// Preserve current view if set
		$current_view = isset( $_GET['view'] ) ? sanitize_text_field( wp_unslash( $_GET['view'] ) ) : '';
		$redirect_url = admin_url( 'admin.php?page=code-snippet' );

		switch ( $action ) {
			case 'duplicate':
				$new_id = $this->snippet->duplicate( $id );
				$result = ( $new_id !== false );
				$message = $result ? __( 'Snippet copied successfully.', 'wp-smart-code' ) : __( 'Failed to copy snippet.', 'wp-smart-code' );
				// Preserve current view
				if ( $current_view ) {
					$redirect_url = add_query_arg( 'view', $current_view, $redirect_url );
				}
				break;

			case 'toggle':
				$new_status = $snippet['active'] ? 0 : 1;
				$result = $this->snippet->update( $id, [ 'active' => $new_status ] );
				if ( $result !== false ) {
					$status_text = $new_status ? __( 'activated', 'wp-smart-code' ) : __( 'deactivated', 'wp-smart-code' );
					/* translators: %s: status text (activated or deactivated) */
					$message = sprintf( __( 'Snippet %s successfully.', 'wp-smart-code' ), $status_text );
				} else {
					$message = __( 'Failed to update snippet status.', 'wp-smart-code' );
				}
				// Preserve current view
				if ( $current_view ) {
					$redirect_url = add_query_arg( 'view', $current_view, $redirect_url );
				}
				break;

			case 'trash':
				$result = $this->snippet->soft_delete( $id );
				$message = $result ? __( 'Snippet moved to trash.', 'wp-smart-code' ) : __( 'Failed to move snippet to trash.', 'wp-smart-code' );
				// Preserve current view
				if ( $current_view ) {
					$redirect_url = add_query_arg( 'view', $current_view, $redirect_url );
				}
				break;

			case 'restore':
				$result = $this->snippet->restore( $id );
				$message = $result ? __( 'Snippet restored successfully.', 'wp-smart-code' ) : __( 'Failed to restore snippet.', 'wp-smart-code' );
				$redirect_url = add_query_arg( 'view', 'trash', $redirect_url );
				break;

			case 'delete':
				$result = $this->snippet->delete( $id );
				$message = $result ? __( 'Snippet deleted permanently.', 'wp-smart-code' ) : __( 'Failed to delete snippet.', 'wp-smart-code' );
				$redirect_url = add_query_arg( 'view', 'trash', $redirect_url );
				break;
		}

		// Store message in transient for display after redirect
		$notice_type = $result ? 'success' : 'error';
		set_transient( 'ecs_admin_notice', [
			'message' => $message,
			'type' => $notice_type
		], 30 );

		// Redirect (this removes action and id parameters)
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Render admin page.
	 *
	 * @return void
	 */
	public function render_admin_page(): void {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-smart-code' ) );
		}

		if ( ! $this->snippet ) {
			wp_die( esc_html__( 'Plugin not properly initialized.', 'wp-smart-code' ) );
		}

		// Display transient admin notice if exists
		$notice = get_transient( 'ecs_admin_notice' );
		if ( $notice ) {
			delete_transient( 'ecs_admin_notice' );
			add_settings_error( 'ecs_messages', 'ecs_message', $notice['message'], $notice['type'] );
		}

		// Ensure list table class is loaded
		if ( ! class_exists( 'ECS\Snippets_List_Table' ) ) {
			require_once ECS_DIR . 'includes/class-ecs-snippets-list-table.php';
		}

		// Create list table instance
		$list_table = new Snippets_List_Table( $this->snippet );
		$list_table->prepare_items();

		// Make admin instance available in template
		$admin = $this->admin;

		// Include template
		include ECS_DIR . 'includes/admin/page-snippets.php';
	}

	/**
	 * Render snippet editor page.
	 *
	 * @return void
	 */
	public function render_editor_page(): void {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-smart-code' ) );
		}

		if ( ! $this->snippet ) {
			wp_die( esc_html__( 'Plugin not properly initialized.', 'wp-smart-code' ) );
		}

		// Get snippet if editing
		$snippet = null;
		if ( isset( $_GET['snippet_id'] ) && ! empty( $_GET['snippet_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$snippet_id = intval( $_GET['snippet_id'] );
			$snippet = $this->snippet->get( $snippet_id );
			
			if ( ! $snippet ) {
				wp_die( esc_html__( 'Snippet not found.', 'wp-smart-code' ) );
			}
		}

		// Make admin instance available in template
		$admin = $this->admin;

		// Include the editor template
		include ECS_DIR . 'includes/admin/page-snippet-editor.php';
	}

	/**
	 * Render snippet library page.
	 *
	 * @return void
	 */
	public function render_library_page(): void {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-smart-code' ) );
		}

		if ( ! $this->snippet ) {
			wp_die( esc_html__( 'Plugin not properly initialized.', 'wp-smart-code' ) );
		}

		// Make admin instance available in template
		$admin = $this->admin;

		// Include the library template
		include ECS_DIR . 'includes/admin/page-library.php';
	}

	/**
	 * Get the database instance.
	 *
	 * @return DB|null
	 */
	public function get_db(): ?DB {
		return $this->db;
	}

	/**
	 * Get the snippet model instance.
	 *
	 * @return Snippet|null
	 */
	public function get_snippet(): ?Snippet {
		return $this->snippet;
	}

	/**
	 * Get the admin instance.
	 *
	 * @return Admin|null
	 */
	public function get_admin(): ?Admin {
		return $this->admin;
	}

	/**
	 * Get the sandbox instance.
	 *
	 * @return Sandbox|null
	 */
	public function get_sandbox(): ?Sandbox {
		return $this->sandbox;
	}

	/**
	 * Get the shortcode instance.
	 *
	 * @return Shortcode|null
	 */
	public function get_shortcode(): ?Shortcode {
		return $this->shortcode;
	}

	/**
	 * Initialize AI AJAX handlers.
	 *
	 * @return void
	 */
	private function initialize_ai_ajax(): void {
		$this->ai_ajax = new AI_Ajax();
		$this->ai_ajax->init();
	}

	/**
	 * Initialize REST API.
	 *
	 * @return void
	 */
	public function initialize_rest_api(): void {
		if ( ! $this->snippet ) {
			return;
		}

		$this->rest = new REST( $this->snippet );
		$this->rest->register_routes();

		// REST API initialized
	}

	/**
	 * Get the REST API instance.
	 *
	 * @return REST|null
	 */
	public function get_rest(): ?REST {
		return $this->rest;
	}

	/**
	 * Render tools page.
	 *
	 * @return void
	 */
	public function render_tools_page(): void {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-smart-code' ) );
		}

		// Make admin instance available in template
		$admin = $this->admin;

		// Include the tools template
		include ECS_DIR . 'includes/admin/page-tools.php';
	}

	/**
	 * Render database fix page.
	 *
	 * @return void
	 */
	public function render_fix_database_page(): void {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-smart-code' ) );
		}

		global $wpdb;
		$table_name = $this->db->get_table_name();
		$fixed = false;
		$errors = [];

		// Check if fix was requested
		if ( isset( $_POST['ecs_fix_database'] ) && check_admin_referer( 'ecs_fix_database', 'ecs_fix_nonce' ) ) {
			// Check if mode column exists
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$column_exists = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
					WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'mode'",
					DB_NAME,
					$table_name
				),
				ARRAY_A
			);

			if ( empty( $column_exists ) ) {
				// Add mode column
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$result = $wpdb->query(
					"ALTER TABLE {$table_name} 
					ADD COLUMN mode VARCHAR(20) NOT NULL DEFAULT 'auto_insert' AFTER active"
				);

				if ( $result === false ) {
					$errors[] = __( 'Failed to add mode column: ', 'wp-smart-code' ) . $wpdb->last_error;
				} else {
					$fixed = true;

					// Add index for mode column
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
					$index_result = $wpdb->query(
						"ALTER TABLE {$table_name} 
						ADD KEY idx_mode (mode)"
					);

					// Clear the dismissed transient
					delete_transient( 'ecs_db_check_dismissed' );
				}
			} else {
				$errors[] = __( 'Mode column already exists.', 'wp-smart-code' );
			}
		}

		// Get current table structure
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$columns = $wpdb->get_results( "SHOW COLUMNS FROM {$table_name}", ARRAY_A );
		$has_mode = false;
		foreach ( $columns as $column ) {
			if ( $column['Field'] === 'mode' ) {
				$has_mode = true;
				break;
			}
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Fix Database', 'wp-smart-code' ); ?></h1>

			<?php if ( $fixed ) : ?>
				<div class="notice notice-success">
					<p>
						<strong><?php esc_html_e( 'Success!', 'wp-smart-code' ); ?></strong>
						<?php esc_html_e( 'The database has been fixed. You can now create snippets.', 'wp-smart-code' ); ?>
					</p>
				</div>
				<p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-smart-code-editor' ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'Create a Snippet', 'wp-smart-code' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=code-snippet' ) ); ?>" class="button button-secondary">
						<?php esc_html_e( 'View All Snippets', 'wp-smart-code' ); ?>
					</a>
				</p>
			<?php endif; ?>

			<?php if ( ! empty( $errors ) ) : ?>
				<div class="notice notice-error">
					<p><strong><?php esc_html_e( 'Errors:', 'wp-smart-code' ); ?></strong></p>
					<ul>
						<?php foreach ( $errors as $error ) : ?>
							<li><?php echo esc_html( $error ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( ! $has_mode && ! $fixed ) : ?>
				<div class="notice notice-warning">
					<p>
						<strong><?php esc_html_e( 'Database Update Required', 'wp-smart-code' ); ?></strong>
					</p>
					<p>
						<?php esc_html_e( 'The database table is missing the "mode" column. Click the button below to fix this automatically.', 'wp-smart-code' ); ?>
					</p>
				</div>

				<form method="post">
					<?php wp_nonce_field( 'ecs_fix_database', 'ecs_fix_nonce' ); ?>
					<p>
						<button type="submit" name="ecs_fix_database" class="button button-primary button-large">
							<?php esc_html_e( 'Fix Database Now', 'wp-smart-code' ); ?>
						</button>
					</p>
				</form>
			<?php elseif ( $has_mode && ! $fixed ) : ?>
				<div class="notice notice-success">
					<p>
						<strong><?php esc_html_e( 'No issues found!', 'wp-smart-code' ); ?></strong>
						<?php esc_html_e( 'The database is up to date.', 'wp-smart-code' ); ?>
					</p>
				</div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Current Table Structure', 'wp-smart-code' ); ?></h2>
			<table class="widefat">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Column Name', 'wp-smart-code' ); ?></th>
						<th><?php esc_html_e( 'Type', 'wp-smart-code' ); ?></th>
						<th><?php esc_html_e( 'Null', 'wp-smart-code' ); ?></th>
						<th><?php esc_html_e( 'Default', 'wp-smart-code' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $columns as $column ) : ?>
						<tr<?php echo ( $column['Field'] === 'mode' ) ? ' style="background-color: #fff3cd;"' : ''; ?>>
							<td><strong><?php echo esc_html( $column['Field'] ); ?></strong></td>
							<td><?php echo esc_html( $column['Type'] ); ?></td>
							<td><?php echo esc_html( $column['Null'] ); ?></td>
							<td><?php echo esc_html( $column['Default'] ?? 'NULL' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=code-snippet' ) ); ?>" class="button">
					<?php esc_html_e( 'Back to Snippets', 'wp-smart-code' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Check database schema and show admin notice if there are issues.
	 *
	 * @return void
	 */
	public function check_database_schema(): void {
		// Only check on admin pages
		if ( ! is_admin() ) {
			return;
		}

		// Only show to admins
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check if already dismissed
		if ( get_transient( 'ecs_db_check_dismissed' ) ) {
			return;
		}

		// Check if database table exists
		if ( ! $this->db || ! $this->db->table_exists() ) {
			return;
		}

		global $wpdb;
		$table_name = $this->db->get_table_name();

		// Check if mode column exists
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$column_exists = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
				WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'mode'",
				DB_NAME,
				$table_name
			),
			ARRAY_A
		);

		if ( empty( $column_exists ) ) {
			// Show admin notice with fix button
			?>
			<div class="notice notice-error">
				<p>
					<strong><?php esc_html_e( 'WP Smart Code - Database Update Required', 'wp-smart-code' ); ?></strong>
				</p>
				<p>
					<?php esc_html_e( 'The database table is missing the "mode" column. This will cause errors when creating snippets.', 'wp-smart-code' ); ?>
				</p>
				<p>
					<strong><?php esc_html_e( 'Solution:', 'wp-smart-code' ); ?></strong>
					<?php esc_html_e( 'Please deactivate and reactivate the plugin, or click the button below to fix automatically:', 'wp-smart-code' ); ?>
				</p>
				<p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ecs-fix-database' ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'Fix Database Now', 'wp-smart-code' ); ?>
					</a>
					<a href="<?php echo esc_url( add_query_arg( 'ecs_dismiss_db_notice', '1' ) ); ?>" class="button button-secondary">
						<?php esc_html_e( 'Dismiss (I will fix it manually)', 'wp-smart-code' ); ?>
					</a>
				</p>
			</div>
			<?php
		}

		// Handle dismiss action
		if ( isset( $_GET['ecs_dismiss_db_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			set_transient( 'ecs_db_check_dismissed', true, DAY_IN_SECONDS );
			wp_safe_redirect( remove_query_arg( 'ecs_dismiss_db_notice' ) );
			exit;
		}
	}

	/**
	 * Customize admin footer for plugin pages.
	 *
	 * @return void
	 */
	public function customize_admin_footer(): void {
		// safe check for function existence
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		// Only customize footer on our plugin pages
		$current_screen = get_current_screen();
		if ( ! $current_screen || ! in_array( $current_screen->id, [
			'toplevel_page_code-snippet',
			'smart-code_page_wp-smart-code-editor',
			'smart-code_page_wp-smart-code-tools'
		], true ) ) {
			return;
		}

		// Get plugin version
		$plugin_version = defined( 'ECS_VERSION' ) ? ECS_VERSION : '1.0.0';
		$current_year = date( 'Y' );
		
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			// Prevent multiple executions
			if (window.ecsFooterCustomized) {
				return;
			}
			
			// Wait for the page to be fully loaded
			setTimeout(function() {
				// Ensure we're targeting the correct footer element
				var $footer = $('#wpfooter');
				if ($footer.length && !$footer.find('#ecs-footer-text').length) {
					// Clear existing content
					$footer.find('.update-nag, p').remove();
					
					// Add our custom footer content
					$footer.html(
						'<p id="ecs-footer-text">' +
						'<strong>WP Smart Code</strong> v<?php echo esc_js( $plugin_version ); ?> | ' +
						'© <?php echo esc_js( $current_year ); ?> <a href="https://amineouhannou.com" target="_blank">Amine Ouhannou</a> | ' +
						'<a href="https://github.com/mathiaschmid19/wp-smart-code" target="_blank"><?php esc_html_e( 'GitHub', 'wp-smart-code' ); ?></a>' +
						'</p>'
					);
					
					// Ensure footer is positioned correctly
					$footer.css({
						'position': 'relative',
						'bottom': 'auto',
						'margin-top': '20px'
					});
					
					// Mark as customized
					window.ecsFooterCustomized = true;
				}
			}, 200);
		});
		</script>
		<?php
	}

	/**
	 * Display admin notices for snippets that were deactivated due to errors.
	 *
	 * @return void
	 */
	public function display_snippet_error_notices(): void {
		// Only show on our plugin pages - but NOT on the editor page (editor handles its own notices inline)
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$current_screen = get_current_screen();
		
		// Skip on editor page - notices are handled inline in the template
		if ( $current_screen && $current_screen->id === 'smart-code_page_wp-smart-code-editor' ) {
			return;
		}
		
		if ( ! $current_screen || ! in_array( $current_screen->id, [
			'toplevel_page_code-snippet',
			'smart-code_page_wp-smart-code-tools'
		], true ) ) {
			return;
		}

		// Check for any snippet error transients
		global $wpdb;
		$table_name = $wpdb->prefix . 'ecs_snippets';
		
		// Get all snippet IDs to check for errors
		$snippet_ids = $wpdb->get_col( "SELECT id FROM {$table_name}" );
		
		foreach ( $snippet_ids as $snippet_id ) {
			$error_data = get_transient( 'ecs_snippet_error_' . $snippet_id );
			
			if ( $error_data && is_array( $error_data ) ) {
				// Get snippet title
				$snippet = $wpdb->get_row( $wpdb->prepare(
					"SELECT title FROM {$table_name} WHERE id = %d",
					$snippet_id
				) );
				
				$snippet_title = $snippet ? $snippet->title : "Snippet #{$snippet_id}";
				$error_message = $error_data['error'] ?? __( 'Unknown error', 'wp-smart-code' );
				$edit_url = admin_url( 'admin.php?page=wp-smart-code-editor&snippet_id=' . $snippet_id );
				
				?>
				<div class="notice notice-error is-dismissible ecs-snippet-error-notice" data-snippet-id="<?php echo esc_attr( $snippet_id ); ?>">
					<p>
						<strong><?php esc_html_e( 'WP Smart Code:', 'wp-smart-code' ); ?></strong>
						<?php
						printf(
							/* translators: %1$s: Snippet title, %2$s: Error message */
							esc_html__( 'Snippet "%1$s" was deactivated due to an execution error: %2$s', 'wp-smart-code' ),
							esc_html( $snippet_title ),
							esc_html( $error_message )
						);
						?>
						<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small" style="margin-left: 10px;">
							<?php esc_html_e( 'Edit Snippet', 'wp-smart-code' ); ?>
						</a>
					</p>
				</div>
				<?php
				
				// Delete the transient after showing
				delete_transient( 'ecs_snippet_error_' . $snippet_id );
			}
		}
	}

}
