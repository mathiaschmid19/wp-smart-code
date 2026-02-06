<?php
/**
 * System Info Tab Content.
 *
 * @package ECS
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Collect system information
$php_version        = phpversion();
$wp_version         = get_bloginfo( 'version' );
$plugin_version     = ECS_VERSION;
$active_theme       = wp_get_theme();
$memory_limit       = ini_get( 'memory_limit' );
$max_upload_size    = wp_max_upload_size();
$time_limit         = ini_get( 'max_execution_time' );
$server_software    = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : 'Unknown';

// Database info
global $wpdb;
$table_name = $wpdb->prefix . 'ecs_snippets';
$total_snippets = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$db_size = $wpdb->get_var( "SELECT SUM(data_length + index_length) FROM information_schema.TABLES WHERE table_schema = '{$wpdb->dbname}' AND table_name = '{$table_name}'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

// Snippet stats by type
$php_count  = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE type = 'php'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$js_count   = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE type = 'js'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$css_count  = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE type = 'css'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$html_count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE type = 'html'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

?>
<div class="ecs-tools-panel">
	<div class="ecs-tools-header">
		<h2><?php esc_html_e( 'System Information', 'wp-smart-code' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'View system information and plugin statistics for troubleshooting.', 'wp-smart-code' ); ?>
		</p>
	</div>

	<div class="ecs-tools-body">
		<!-- Plugin Info -->
		<div class="ecs-info-section">
			<h3>
				<?php esc_html_e( 'Plugin Information', 'wp-smart-code' ); ?>
			</h3>
			<table class="ecs-info-table">
				<tr>
					<th><?php esc_html_e( 'Plugin Version:', 'wp-smart-code' ); ?></th>
					<td><?php echo esc_html( $plugin_version ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Total Snippets:', 'wp-smart-code' ); ?></th>
					<td><?php echo esc_html( $total_snippets ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Database Size:', 'wp-smart-code' ); ?></th>
					<td><?php echo $db_size ? esc_html( size_format( $db_size ) ) : esc_html__( 'N/A', 'wp-smart-code' ); ?></td>
				</tr>
			</table>
		</div>

		<!-- Snippet Statistics -->
		<div class="ecs-info-section">
			<h3>
				<?php esc_html_e( 'Snippet Statistics', 'wp-smart-code' ); ?>
			</h3>
			<table class="ecs-info-table">
				<tr>
					<th><?php esc_html_e( 'PHP Snippets:', 'wp-smart-code' ); ?></th>
					<td><span class="ecs-badge ecs-badge-php"><?php echo esc_html( $php_count ); ?></span></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'JavaScript Snippets:', 'wp-smart-code' ); ?></th>
					<td><span class="ecs-badge ecs-badge-js"><?php echo esc_html( $js_count ); ?></span></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'CSS Snippets:', 'wp-smart-code' ); ?></th>
					<td><span class="ecs-badge ecs-badge-css"><?php echo esc_html( $css_count ); ?></span></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'HTML Snippets:', 'wp-smart-code' ); ?></th>
					<td><span class="ecs-badge ecs-badge-html"><?php echo esc_html( $html_count ); ?></span></td>
				</tr>
			</table>
		</div>

		<!-- WordPress Environment -->
		<div class="ecs-info-section">
			<h3>
				<?php esc_html_e( 'WordPress Environment', 'wp-smart-code' ); ?>
			</h3>
			<table class="ecs-info-table">
				<tr>
					<th><?php esc_html_e( 'WordPress Version:', 'wp-smart-code' ); ?></th>
					<td><?php echo esc_html( $wp_version ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Active Theme:', 'wp-smart-code' ); ?></th>
					<td><?php echo esc_html( $active_theme->get( 'Name' ) . ' ' . $active_theme->get( 'Version' ) ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Site URL:', 'wp-smart-code' ); ?></th>
					<td><?php echo esc_html( get_site_url() ); ?></td>
				</tr>
			</table>
		</div>

		<!-- Server Environment -->
		<div class="ecs-info-section">
			<h3>
				<?php esc_html_e( 'Server Environment', 'wp-smart-code' ); ?>
			</h3>
			<table class="ecs-info-table">
				<tr>
					<th><?php esc_html_e( 'PHP Version:', 'wp-smart-code' ); ?></th>
					<td><?php echo esc_html( $php_version ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Server Software:', 'wp-smart-code' ); ?></th>
					<td><?php echo esc_html( $server_software ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'PHP Memory Limit:', 'wp-smart-code' ); ?></th>
					<td><?php echo esc_html( $memory_limit ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Max Upload Size:', 'wp-smart-code' ); ?></th>
					<td><?php echo esc_html( size_format( $max_upload_size ) ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Max Execution Time:', 'wp-smart-code' ); ?></th>
					<td><?php echo esc_html( $time_limit . 's' ); ?></td>
				</tr>
			</table>
		</div>

		<!-- Copy System Info -->
		<div class="ecs-form-actions">
			<button type="button" class="button button-secondary button-large" id="ecs-copy-system-info">
				<?php esc_html_e( 'Copy System Info', 'wp-smart-code' ); ?>
			</button>
		</div>

		<!-- Hidden textarea for copying -->
		<textarea id="ecs-system-info-text" style="position: absolute; left: -9999px;">
=== Edge Code Snippets - System Information ===

Plugin Version: <?php echo $plugin_version; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

Total Snippets: <?php echo $total_snippets; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

Database Size: <?php echo $db_size ? size_format( $db_size ) : 'N/A'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>


=== Snippet Statistics ===
PHP: <?php echo $php_count; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

JavaScript: <?php echo $js_count; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

CSS: <?php echo $css_count; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

HTML: <?php echo $html_count; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>


=== WordPress Environment ===
WordPress Version: <?php echo $wp_version; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

Active Theme: <?php echo $active_theme->get( 'Name' ) . ' ' . $active_theme->get( 'Version' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

Site URL: <?php echo get_site_url(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>


=== Server Environment ===
PHP Version: <?php echo $php_version; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

Server Software: <?php echo $server_software; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

PHP Memory Limit: <?php echo $memory_limit; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

Max Upload Size: <?php echo size_format( $max_upload_size ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

Max Execution Time: <?php echo $time_limit; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>s
		</textarea>
	</div>
</div>

