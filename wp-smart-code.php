<?php
/**
 * Plugin Name: WP Smart Code
 * Plugin URI: https://github.com/mathiaschmid19/WP-Smart-Code
 * Description: Safely manage and execute PHP, JavaScript, CSS, and HTML code snippets in WordPress.
 * Version: 1.0.5
 * Author: Amine Ouhannou
 * Author URI: https://amineouhannou.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-smart-code
 * Domain Path: /languages
 * Requires at least: 5.9
 * Requires PHP: 7.4
 *
 * @package ECS
 * @since 1.0.0
 */

declare( strict_types=1 );

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'ECS_VERSION', '1.0.1' );
define( 'ECS_FILE', __FILE__ );
define( 'ECS_DIR', plugin_dir_path( __FILE__ ) );
define( 'ECS_URL', plugin_dir_url( __FILE__ ) );
define( 'ECS_BASENAME', plugin_basename( __FILE__ ) );

// Include autoloader.
require_once ECS_DIR . 'includes/class-ecs-autoloader.php';

// Register autoloader.
\ECS\Autoloader::register();

// Initialize plugin on plugins_loaded hook.
add_action(
	'plugins_loaded',
	static function (): void {
		try {
			\ECS\Plugin::instance();
		} catch ( Exception $e ) {
			// Log the error
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[ECS] Plugin initialization failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}
	}
);

// Add plugin action links
add_filter( 'plugin_action_links_' . ECS_BASENAME, 'ecs_add_plugin_action_links' );

/**
 * Add custom action links to the plugin list page.
 *
 * @param array $links Existing plugin action links.
 * @return array Modified plugin action links.
 */
function ecs_add_plugin_action_links( array $links ): array {
	$custom_links = [
		'snippets' => sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'admin.php?page=code-snippet' ),
			__( 'Snippets List', 'wp-smart-code' )
		),
		'add_new' => sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'admin.php?page=wp-smart-code-editor' ),
			__( 'Add New', 'wp-smart-code' )
		),
	];

	// Merge custom links with existing links (Settings, Deactivate, etc.)
	return array_merge( $custom_links, $links );
}

/**
 * Activation hook.
 *
 * @return void
 */
function ecs_activate(): void {
	try {
		// Create database table.
		$db = new \ECS\DB();
		$db->install();

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[ECS] Plugin activated successfully.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	} catch ( Exception $e ) {
		// Log the error and deactivate the plugin
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[ECS] Plugin activation failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		
		// Deactivate the plugin
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die( 
			esc_html__( 'Plugin activation failed. Please check the error logs for more details.', 'wp-smart-code' ),
			esc_html__( 'Plugin Activation Error', 'wp-smart-code' ),
			[ 'back_link' => true ]
		);
	}
}

/**
 * Deactivation hook.
 *
 * @return void
 */
function ecs_deactivate(): void {
	// Placeholder for deactivation logic.
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( '[ECS] Plugin deactivated.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}

register_activation_hook( ECS_FILE, 'ecs_activate' );
register_deactivation_hook( ECS_FILE, 'ecs_deactivate' );

/**
 * Helper function to check if a snippet should run
 *
 * @param array $snippet Snippet data
 * @return bool True if snippet should run
 */
function ecs_should_run( array $snippet ): bool {
	return \ECS\Conditions::should_run( $snippet );
}