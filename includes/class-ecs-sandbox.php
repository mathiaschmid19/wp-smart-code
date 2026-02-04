<?php
/**
 * ECS Sandbox - Safe Snippet Execution System
 *
 * @package ECS
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ECS;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sandbox class for safe snippet execution
 *
 * This class provides controlled execution of PHP, JavaScript, CSS, and HTML snippets
 * with safety layers including syntax validation, permission checks, and error handling.
 */
class Sandbox
{
    /**
     * Singleton instance
     *
     * @var Sandbox|null
     */
    private static $instance = null;

    /**
     * Active snippets cache
     *
     * @var array
     */
    private $active_snippets = [];

    /**
     * Get singleton instance
     *
     * @return Sandbox
     */
    public static function get_instance(): Sandbox
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     *
     * @return void
     */
    private function init_hooks(): void
    {
        // Execute PHP snippets early - before any output
        add_action('wp', [$this, 'execute_php_snippets'], 1);
        add_action('admin_init', [$this, 'execute_php_snippets'], 1);
        
        // Hook into WordPress frontend execution points for other types
        add_action('wp_head', [$this, 'execute_head_snippets'], 1);
        add_action('wp_footer', [$this, 'execute_footer_snippets'], 1);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_script_snippets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_style_snippets']);

        // Admin hooks for testing
        add_action('admin_init', [$this, 'maybe_execute_admin_snippets']);

        // Hooks initialized
    }

    /**
     * Execute PHP snippets safely
     *
     * @param string $code The PHP code to execute
     * @param array $context Additional context for execution
     * @return array Result array with success status and output/error
     */
    public function execute_php(string $code, array $context = []): array
    {
        // Check permissions
        if (!current_user_can('manage_options')) {
            return [
                'success' => false,
                'error' => 'Insufficient permissions to execute PHP snippets',
                'output' => ''
            ];
        }

        // Decode HTML entities that might have been encoded during storage
        $code = html_entity_decode($code, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Validate PHP syntax
        $syntax_check = $this->validate_php_syntax($code);
        if (!$syntax_check['valid']) {
            return [
                'success' => false,
                'error' => 'PHP syntax error: ' . $syntax_check['error'],
                'output' => ''
            ];
        }

        // Sanitize code (remove dangerous functions)
        $sanitized_code = $this->sanitize_php_code($code);
        if ($sanitized_code !== $code) {
            return [
                'success' => false,
                'error' => 'Code contains potentially dangerous functions',
                'output' => ''
            ];
        }

        // Execute code safely
        try {
            ob_start();
            
            // Set error handler to catch errors
            set_error_handler([$this, 'handle_php_error']);
            
            // Execute the code
            eval($sanitized_code);
            
            // Restore error handler
            restore_error_handler();
            
            $output = ob_get_clean();
            
            return [
                'success' => true,
                'error' => '',
                'output' => $output
            ];
            
        } catch (Throwable $e) {
            // Restore error handler
            restore_error_handler();
            ob_end_clean();
            
            return [
                'success' => false,
                'error' => 'Execution error: ' . $e->getMessage(),
                'output' => ''
            ];
        }
    }

    /**
     * Execute JavaScript snippets
     *
     * @param string $code The JavaScript code
     * @param string $location Where to inject (head, footer)
     * @return array Result array
     */
    public function execute_js(string $code, string $location = 'footer'): array
    {
        if (empty($code)) {
            return ['success' => false, 'error' => 'No JavaScript code provided'];
        }

        // Decode HTML entities that might have been encoded during storage
        $code = html_entity_decode($code, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Sanitize JavaScript code
        $sanitized_code = $this->sanitize_js_code($code);
        
        // Store for later output
        $this->store_js_snippet($sanitized_code, $location);
        
        return ['success' => true, 'error' => ''];
    }

    /**
     * Execute CSS snippets
     *
     * @param string $code The CSS code
     * @return array Result array
     */
    public function execute_css(string $code): array
    {
        if (empty($code)) {
            return ['success' => false, 'error' => 'No CSS code provided'];
        }

        // Decode HTML entities that might have been encoded during storage
        $code = html_entity_decode($code, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Sanitize CSS code
        $sanitized_code = $this->sanitize_css_code($code);
        
        // Store for later output
        $this->store_css_snippet($sanitized_code);
        
        return ['success' => true, 'error' => ''];
    }

    /**
     * Execute HTML snippets
     *
     * @param string $code The HTML code
     * @param string $location Where to inject (head, footer, body)
     * @return array Result array
     */
    public function execute_html(string $code, string $location = 'body'): array
    {
        if (empty($code)) {
            return ['success' => false, 'error' => 'No HTML code provided'];
        }

        // Sanitize HTML code
        $sanitized_code = $this->sanitize_html_code($code);
        
        // Store for later output
        $this->store_html_snippet($sanitized_code, $location);
        
        return ['success' => true, 'error' => ''];
    }

    /**
     * Validate PHP syntax using built-in PHP tokenizer
     *
     * @param string $code PHP code to validate
     * @return array Validation result
     */
    private function validate_php_syntax(string $code): array
    {
        // Use the new SyntaxValidator class
        $result = SyntaxValidator::validate_php($code);
        
        // Log validation result in debug mode
        // Syntax validation completed
        
        return $result;
    }

    /**
     * Sanitize PHP code by removing dangerous functions
     *
     * @param string $code PHP code to sanitize
     * @return string Sanitized code (or empty if dangerous code detected)
     */
    private function sanitize_php_code(string $code): string
    {
        // Allow filter to bypass security checks (use with extreme caution!)
        $bypass_security = apply_filters('ecs_bypass_php_security', false);
        if ($bypass_security && current_user_can('manage_options')) {
            return $code;
        }

        // Only block the most critical dangerous functions
        // Allow WordPress and common PHP functions to work
        $dangerous_functions = [
            'exec', 'system', 'shell_exec', 'passthru', 'proc_open', 'popen',
            'pcntl_exec', 'proc_terminate', 'proc_close', 'proc_get_status',
            'eval', 'create_function', 'assert',
            // Don't block file functions - they're needed for WordPress
            // Don't block database functions - WordPress handles security
            // Don't block ini_set - WordPress uses it
        ];

        // Allow custom filtering of dangerous functions list
        $dangerous_functions = apply_filters('ecs_dangerous_php_functions', $dangerous_functions);

        $pattern = '/\b(' . implode('|', array_map('preg_quote', $dangerous_functions)) . ')\s*\(/i';
        
        if (preg_match($pattern, $code)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[ECS Sandbox] Blocked dangerous function in code: ' . substr($code, 0, 100));
            }
            return ''; // Return empty string if dangerous functions found
        }

        return $code;
    }

    /**
     * Sanitize JavaScript code
     *
     * @param string $code JavaScript code to sanitize
     * @return string Sanitized code
     */
    private function sanitize_js_code(string $code): string
    {
        // Remove script tags if present
        $code = preg_replace('/<\/?script[^>]*>/i', '', $code);
        
        // Basic XSS prevention
        $code = str_replace(['<script', '</script>'], ['&lt;script', '&lt;/script&gt;'], $code);
        
        return trim($code);
    }

    /**
     * Sanitize CSS code
     *
     * @param string $code CSS code to sanitize
     * @return string Sanitized code
     */
    private function sanitize_css_code(string $code): string
    {
        // Remove style tags if present
        $code = preg_replace('/<\/?style[^>]*>/i', '', $code);
        
        // Basic CSS injection prevention
        $code = preg_replace('/@import[^;]+;/i', '', $code);
        $code = preg_replace('/expression\s*\(/i', '', $code);
        
        return trim($code);
    }

    /**
     * Sanitize HTML code
     *
     * @param string $code HTML code to sanitize
     * @return string Sanitized code
     */
    private function sanitize_html_code(string $code): string
    {
        // Use WordPress kses for HTML sanitization
        $allowed_tags = wp_kses_allowed_html('post');
        
        // Add some additional tags for snippets
        $allowed_tags['script'] = [
            'src' => true,
            'type' => true,
            'async' => true,
            'defer' => true
        ];
        
        $allowed_tags['style'] = [
            'type' => true,
            'media' => true
        ];
        
        return wp_kses($code, $allowed_tags);
    }

    /**
     * Store JavaScript snippet for later output
     *
     * @param string $code JavaScript code
     * @param string $location Output location
     * @return void
     */
    private function store_js_snippet(string $code, string $location): void
    {
        if (!isset($this->active_snippets['js'][$location])) {
            $this->active_snippets['js'][$location] = [];
        }
        
        $this->active_snippets['js'][$location][] = $code;
    }

    /**
     * Store CSS snippet for later output
     *
     * @param string $code CSS code
     * @return void
     */
    private function store_css_snippet(string $code): void
    {
        if (!isset($this->active_snippets['css'])) {
            $this->active_snippets['css'] = [];
        }
        
        $this->active_snippets['css'][] = $code;
    }

    /**
     * Store HTML snippet for later output
     *
     * @param string $code HTML code
     * @param string $location Output location
     * @return void
     */
    private function store_html_snippet(string $code, string $location): void
    {
        if (!isset($this->active_snippets['html'][$location])) {
            $this->active_snippets['html'][$location] = [];
        }
        
        $this->active_snippets['html'][$location][] = $code;
    }

    /**
     * Execute head snippets
     *
     * @return void
     */
    public function execute_head_snippets(): void
    {
        // Execute head snippets
        $this->output_snippets('head');
    }

    /**
     * Execute footer snippets
     *
     * @return void
     */
    public function execute_footer_snippets(): void
    {
        // Execute footer snippets
        $this->output_snippets('footer');
    }

    /**
     * Enqueue script snippets
     *
     * @return void
     */
    public function enqueue_script_snippets(): void
    {
        // Enqueue script snippets
        $this->output_snippets('scripts');
    }

    /**
     * Enqueue style snippets
     *
     * @return void
     */
    public function enqueue_style_snippets(): void
    {
        // Enqueue style snippets
        $this->output_snippets('styles');
    }

    /**
     * Output snippets based on type and location
     *
     * @param string $type Snippet type (head, footer, scripts, styles)
     * @return void
     */
    private function output_snippets(string $type): void
    {
        // Get active snippets from database
        $snippets = $this->get_active_snippets();
        
        foreach ($snippets as $snippet) {
            $this->execute_snippet($snippet, $type);
        }
    }

    /**
     * Get active snippets from database
     *
     * @return array Active snippets
     */
    private function get_active_snippets(): array
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ecs_snippets';
        
        $snippets = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE active = %d ORDER BY created_at ASC",
                1
            ),
            ARRAY_A
        );
        
        // Process active snippets
        
        return $snippets ?: [];
    }

    /**
     * Execute PHP snippets early
     *
     * @return void
     */
    public function execute_php_snippets(): void
    {
        // Prevent double execution
        static $executed = false;
        if ($executed) {
            return;
        }
        $executed = true;
        
        // Execute PHP snippets
        
        // Get active snippets
        $snippets = $this->get_active_snippets();
        
        foreach ($snippets as $snippet) {
            if (isset($snippet['type']) && $snippet['type'] === 'php') {
                // Check if snippet is in auto_insert mode
                $mode = $snippet['mode'] ?? 'auto_insert';
                if ($mode !== 'auto_insert') {
                    // Skip snippet - not in auto_insert mode
                    continue;
                }
                
                // Check if snippet should run based on conditions
                if (!Conditions::should_run($snippet)) {
                    // Skip snippet - conditions not met
                    continue;
                }
                
                $code = $snippet['code'] ?? '';
                $snippet_id = (int) ($snippet['id'] ?? 0);
                if (!empty($code)) {
                    // Execute PHP snippet with error handling
                    $this->execute_php_frontend($code, $snippet_id);
                }
            }
        }
    }

    /**
     * Execute a single snippet
     *
     * @param array $snippet Snippet data
     * @param string $context Execution context
     * @return void
     */
    private function execute_snippet(array $snippet, string $context): void
    {
        $type = $snippet['type'] ?? '';
        $code = $snippet['code'] ?? '';
        
        if (empty($code)) {
            return;
        }

        // Check if snippet is in auto_insert mode
        $mode = $snippet['mode'] ?? 'auto_insert';
        if ($mode !== 'auto_insert') {
            // Skip snippet - not in auto_insert mode
            return;
        }

        // Check if snippet should run based on conditions
        if (!Conditions::should_run($snippet)) {
            // Skip snippet - conditions not met
            return;
        }

        switch ($type) {
            case 'php':
                // PHP snippets are executed separately via execute_php_snippets()
                // Don't execute them again here
                break;
                
            case 'js':
                if ($context === 'scripts' || $context === 'footer') {
                    $this->execute_js_frontend($code);
                }
                break;
                
            case 'css':
                if ($context === 'styles' || $context === 'head') {
                    $this->execute_css_frontend($code);
                }
                break;
                
            case 'html':
                if ($context === 'head' || $context === 'footer') {
                    $this->execute_html_frontend($code, $context);
                }
                break;
        }
    }

    /**
     * Execute PHP snippet on frontend (actual execution)
     *
     * @param string $code PHP code to execute
     * @param int $snippet_id Optional snippet ID for error handling
     * @return void
     */
    private function execute_php_frontend(string $code, int $snippet_id = 0): void
    {
        // Execute PHP snippet

        // Check if code is empty
        if (empty(trim($code))) {
            return;
        }

        // Decode HTML entities that might have been encoded during storage
        $code = html_entity_decode($code, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Remove PHP tags if present
        $code = preg_replace('/^<\?php\s*/i', '', $code);
        $code = preg_replace('/\s*\?>$/', '', $code);
        
        // Trim the code
        $code = trim($code);
        
        if (empty($code)) {
            return;
        }

        // Execute the code directly
        try {
            // Set error handler to catch warnings/notices
            set_error_handler([$this, 'handle_php_error']);
            
            // Execute the code using eval
            // Note: eval doesn't need <?php tags
            eval($code);
            
            // Restore error handler
            restore_error_handler();
            
            // PHP snippet executed successfully
            
        } catch (\Throwable $e) {
            // Restore error handler
            restore_error_handler();
            
            // Deactivate the snippet to prevent further errors
            if ($snippet_id > 0) {
                $this->deactivate_snippet_on_error($snippet_id, $e->getMessage());
            }
            
            // Log error in debug mode
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[ECS] PHP execution error: ' . $e->getMessage()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            }
        }
    }

    /**
     * Deactivate a snippet due to execution error
     *
     * @param int $snippet_id Snippet ID
     * @param string $error_message Error message
     * @return void
     */
    private function deactivate_snippet_on_error(int $snippet_id, string $error_message): void
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ecs_snippets';
        
        // Deactivate the snippet
        $result = $wpdb->update(
            $table_name,
            ['active' => 0, 'updated_at' => current_time('mysql')],
            ['id' => $snippet_id],
            ['%d', '%s'],
            ['%d']
        );
        
        if ($result !== false) {
            // Store notice for admin
            set_transient(
                'ecs_snippet_error_' . $snippet_id,
                [
                    'snippet_id' => $snippet_id,
                    'error' => $error_message,
                    'time' => current_time('mysql')
                ],
                HOUR_IN_SECONDS
            );
            
            // Log the deactivation
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("[ECS] Snippet #{$snippet_id} deactivated due to execution error: {$error_message}"); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            }
        }
    }

    /**
     * Execute JavaScript snippet on frontend
     *
     * @param string $code JavaScript code
     * @return void
     */
    private function execute_js_frontend(string $code): void
    {
        if (empty($code)) {
            return;
        }

        // Decode HTML entities that might have been encoded during storage
        $code = html_entity_decode($code, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Sanitize JavaScript code
        $sanitized_code = $this->sanitize_js_code($code);
        
        // Output JavaScript directly
        echo '<script type="text/javascript">' . "\n";
        echo $sanitized_code . "\n";
        echo '</script>' . "\n";
    }

    /**
     * Execute CSS snippet on frontend
     *
     * @param string $code CSS code
     * @return void
     */
    private function execute_css_frontend(string $code): void
    {
        if (empty($code)) {
            return;
        }

        // Decode HTML entities that might have been encoded during storage
        $code = html_entity_decode($code, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Sanitize CSS code
        $sanitized_code = $this->sanitize_css_code($code);
        
        // Output CSS directly
        echo '<style type="text/css">' . "\n";
        echo $sanitized_code . "\n";
        echo '</style>' . "\n";
    }

    /**
     * Execute HTML snippet on frontend
     *
     * @param string $code HTML code
     * @param string $location Output location
     * @return void
     */
    private function execute_html_frontend(string $code, string $location): void
    {
        if (empty($code)) {
            return;
        }

        // Decode HTML entities that might have been encoded during storage
        $code = html_entity_decode($code, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Sanitize HTML code
        $sanitized_code = $this->sanitize_html_code($code);
        
        // Output HTML directly
        echo $sanitized_code . "\n";
    }

    /**
     * Handle PHP errors during execution
     *
     * @param int $errno Error number
     * @param string $errstr Error string
     * @param string $errfile Error file
     * @param int $errline Error line
     * @return bool
     */
    public function handle_php_error(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        // Handle PHP error silently
        
        // Don't execute PHP internal error handler
        return true;
    }

    /**
     * Maybe execute admin snippets (for testing)
     *
     * @return void
     */
    public function maybe_execute_admin_snippets(): void
    {
        // Only execute in admin if user has proper permissions
        if (!current_user_can('manage_options')) {
            return;
        }

        // Check if this is a snippet test request
        if (isset($_GET['ecs_test_snippet']) && wp_verify_nonce($_GET['_wpnonce'], 'ecs_test_snippet')) {
            $snippet_id = intval($_GET['ecs_test_snippet']);
            $this->test_snippet($snippet_id);
        }
    }

    /**
     * Test a snippet safely
     *
     * @param int $snippet_id Snippet ID to test
     * @return void
     */
    private function test_snippet(int $snippet_id): void
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ecs_snippets';
        
        $snippet = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE id = %d",
                $snippet_id
            ),
            ARRAY_A
        );
        
        if (!$snippet) {
            wp_die('Snippet not found');
        }
        
        $type = $snippet['type'];
        $code = $snippet['code'];
        
        echo "<h2>Testing Snippet: {$snippet['title']}</h2>";
        echo "<h3>Type: {$type}</h3>";
        echo "<h3>Code:</h3>";
        echo "<pre>" . esc_html($code) . "</pre>";
        
        echo "<h3>Result:</h3>";
        
        switch ($type) {
            case 'php':
                $result = $this->execute_php($code);
                break;
            case 'js':
                $result = $this->execute_js($code);
                break;
            case 'css':
                $result = $this->execute_css($code);
                break;
            case 'html':
                $result = $this->execute_html($code);
                break;
            default:
                $result = ['success' => false, 'error' => 'Unknown snippet type'];
        }
        
        if ($result['success']) {
            echo "<div style='color: green;'>✓ Success</div>";
            if (!empty($result['output'])) {
                echo "<h4>Output:</h4>";
                echo "<pre>" . esc_html($result['output']) . "</pre>";
            }
        } else {
            echo "<div style='color: red;'>✗ Error: " . esc_html($result['error']) . "</div>";
        }
        
        wp_die();
    }
}
