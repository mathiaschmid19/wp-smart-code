<?php
/**
 * Admin Page Template for WP Smart Code.
 *
 * @package ECS
 * @since 1.0.0
 *
 * @var Admin                $admin Admin class instance.
 * @var Snippets_List_Table  $list_table List table instance.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="wrap ecs-snippets-page">
	<!-- Fixed Header with Logo -->
	<div class="ecs-page-header">
		<div class="ecs-header-content">
			<div class="ecs-logo-section">
				<img src="data:image/svg+xml;base64,PHN2ZyBmaWxsPSJub25lIiBoZWlnaHQ9IjQ4IiB2aWV3Qm94PSIwIDAgNDggNDgiIHdpZHRoPSI0OCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxuczp4bGluaz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayI+PGZpbHRlciBpZD0iYSIgY29sb3ItaW50ZXJwb2xhdGlvbi1maWx0ZXJzPSJzUkdCIiBmaWx0ZXJVbml0cz0idXNlclNwYWNlT25Vc2UiIGhlaWdodD0iNTQiIHdpZHRoPSI0OCIgeD0iMCIgeT0iLTMiPjxmZUZsb29kIGZsb29kLW9wYWNpdHk9IjAiIHJlc3VsdD0iQmFja2dyb3VuZEltYWdlRml4Ii8+PGZlQmxlbmQgaW49IlNvdXJjZUdyYXBoaWMiIGluMj0iQmFja2dyb3VuZEltYWdlRml4IiBtb2RlPSJub3JtYWwiIHJlc3VsdD0ic2hhcGUiLz48ZmVDb2xvck1hdHJpeCBpbj0iU291cmNlQWxwaGEiIHJlc3VsdD0iaGFyZEFscGhhIiB0eXBlPSJtYXRyaXgiIHZhbHVlcz0iMCAwIDAgMCAwIDAgMCAwIDAgMCAwIDAgMCAwIDAgMCAwIDAgMTI3IDAiLz48ZmVPZmZzZXQgZHk9Ii0zIi8+PGZlR2F1c3NpYW5CbHVyIHN0ZERldmlhdGlvbj0iMS41Ii8+PGZlQ29tcG9zaXRlIGluMj0iaGFyZEFscGhhIiBrMj0iLTEiIGszPSIxIiBvcGVyYXRvcj0iYXJpdGhtZXRpYyIvPjxmZUNvbG9yTWF0cml4IHR5cGU9Im1hdHJpeCIgdmFsdWVzPSIwIDAgMCAwIDAgMCAwIDAgMCAwIDAgMCAwIDAgMCAwIDAgMCAwLjEgMCIvPjxmZUJsZW5kIGluMj0ic2hhcGUiIG1vZGU9Im5vcm1hbCIgcmVzdWx0PSJlZmZlY3QxX2lubmVyU2hhZG93XzMwNTFfNDY4NzUiLz48ZmVDb2xvck1hdHJpeCBpbj0iU291cmNlQWxwaGEiIHJlc3VsdD0iaGFyZEFscGhhIiB0eXBlPSJtYXRyaXgiIHZhbHVlcz0iMCAwIDAgMCAwIDAgMCAwIDAgMCAwIDAgMCAwIDAgMCAwIDAgMTI3IDAiLz48ZmVPZmZzZXQgZHk9IjMiLz48ZmVHYXVzc2lhbkJsdXIgc3RkRGV2aWF0aW9uPSIxLjUiLz48ZmVDb21wb3NpdGUgaW4yPSJoYXJkQWxwaGEiIGsyPSItMSIgazM9IjEiIG9wZXJhdG9yPSJhcml0aG1ldGljIi8+PGZlQ29sb3JNYXRyaXggdHlwZT0ibWF0cml4IiB2YWx1ZXM9IjAgMCAwIDAgMSAwIDAgMCAwIDEgMCAwIDAgMCAxIDAgMCAwIDAuMSAwIi8+PGZlQmxlbmQgaW4yPSJlZmZlY3QxX2lubmVyU2hhZG93XzMwNTFfNDY4NzUiIG1vZGU9Im5vcm1hbCIgcmVzdWx0PSJlZmZlY3QyX2lubmVyU2hhZG93XzMwNTFfNDY4NzUiLz48ZmVDb2xvck1hdHJpeCBpbj0iU291cmNlQWxwaGEiIHJlc3VsdD0iaGFyZEFscGhhIiB0eXBlPSJtYXRyaXgiIHZhbHVlcz0iMCAwIDAgMCAwIDAgMCAwIDAgMCAwIDAgMCAwIDAgMCAwIDAgMTI3IDAiLz48ZmVNb3JwaG9sb2d5IGluPSJTb3VyY2VBbHBoYSIgb3BlcmF0b3I9ImVyb2RlIiByYWRpdXM9IjEiIHJlc3VsdD0iZWZmZWN0M19pbm5lclNoYWRvd18zMDUxXzQ2ODc1Ii8+PGZlT2Zmc2V0Lz48ZmVDb21wb3NpdGUgaW4yPSJoYXJkQWxwaGEiIGsyPSItMSIgazM9IjEiIG9wZXJhdG9yPSJhcml0aG1ldGljIi8+PGZlQ29sb3JNYXRyaXggdHlwZT0ibWF0cml4IiB2YWx1ZXM9IjAgMCAwIDAgMC4wNjI3NDUxIDAgMCAwIDAgMC4wOTQxMTc2IDAgMCAwIDAgMC4xNTY4NjMgMCAwIDAgMC4yNCAwIi8+PGZlQmxlbmQgaW4yPSJlZmZlY3QyX2lubmVyU2hhZG93XzMwNTFfNDY4NzUiIG1vZGU9Im5vcm1hbCIgcmVzdWx0PSJlZmZlY3QzX2lubmVyU2hhZG93XzMwNTFfNDY4NzUiLz48L2ZpbHRlcj48ZmlsdGVyIGlkPSJiIiBjb2xvci1pbnRlcnBvbGF0aW9uLWZpbHRlcnM9InNSR0IiIGZpbHRlclVuaXRzPSJ1c2VyU3BhY2VPblVzZSIgaGVpZ2h0PSI0MiIgd2lkdGg9IjM2IiB4PSI2IiB5PSI1LjI1Ij48ZmVGbG9vZCBmbG9vZC1vcGFjaXR5PSIwIiByZXN1bHQ9IkJhY2tncm91bmRJbWFnZUZpeCIvPjxmZUNvbG9yTWF0cml4IGluPSJTb3VyY2VBbHBoYSIgcmVzdWx0PSJoYXJkQWxwaGEiIHR5cGU9Im1hdHJpeCIgdmFsdWVzPSIwIDAgMCAwIDAgMCAwIDAgMCAwIDAgMCAwIDAgMCAwIDAgMCAxMjcgMCIvPjxmZU1vcnBob2xvZ3kgaW49IlNvdXJjZUFscGhhIiBvcGVyYXRvcj0iZXJvZGUiIHJhZGl1cz0iMS41IiByZXN1bHQ9ImVmZmVjdDFfZHJvcFNoYWRvd18zMDUxXzQ2ODc1Ii8+PGZlT2Zmc2V0IGR5PSIyLjI1Ii8+PGZlR2F1c3NpYW5CbHVyIHN0ZERldmlhdGlvbj0iMi4yNSIvPjxmZUNvbXBvc2l0ZSBpbjI9ImhhcmRBbHBoYSIgb3BlcmF0b3I9Im91dCIvPjxmZUNvbG9yTWF0cml4IHR5cGU9Im1hdHJpeCIgdmFsdWVzPSIwIDAgMCAwIDAuMTQxMTc2IDAgMCAwIDAgMC4xNDExNzYgMCAwIDAgMCAwLjE0MTE3NiAwIDAgMCAwLjEgMCIvPjxmZUJsZW5kIGluMj0iQmFja2dyb3VuZEltYWdlRml4IiBtb2RlPSJub3JtYWwiIHJlc3VsdD0iZWZmZWN0MV9kcm9wU2hhZG93XzMwNTFfNDY4NzUiLz48ZmVCbGVuZCBpbj0iU291cmNlR3JhcGhpYyIgaW4yPSJlZmZlY3QxX2Ryb3BTaGFkb3dfMzA1MV80Njg3NSIgbW9kZT0ibm9ybWFsIiByZXN1bHQ9InNoYXBlIi8+PC9maWx0ZXI+PGxpbmVhckdyYWRpZW50IGlkPSJjIiBncmFkaWVudFVuaXRzPSJ1c2VyU3BhY2VPblVzZSIgeDE9IjI0IiB4Mj0iMjYiIHkxPSIuMDAwMDAxIiB5Mj0iNDgiPjxzdG9wIG9mZnNldD0iMCIgc3RvcC1jb2xvcj0iI2ZmZiIgc3RvcC1vcGFjaXR5PSIwIi8+PHN0b3Agb2Zmc2V0PSIxIiBzdG9wLWNvbG9yPSIjZmZmIiBzdG9wLW9wYWNpdHk9Ii4xMiIvPjwvbGluZWFyR3JhZGllbnQ+PGxpbmVhckdyYWRpZW50IGlkPSJkIiBncmFkaWVudFVuaXRzPSJ1c2VyU3BhY2VPblVzZSIgeDE9IjI0IiB4Mj0iMjQiIHkxPSI5IiB5Mj0iMzkiPjxzdG9wIG9mZnNldD0iMCIgc3RvcC1jb2xvcj0iI2ZmZiIgc3RvcC1vcGFjaXR5PSIuOCIvPjxzdG9wIG9mZnNldD0iMSIgc3RvcC1jb2xvcj0iI2ZmZiIgc3RvcC1vcGFjaXR5PSIuNSIvPjwvbGluZWFyR3JhZGllbnQ+PGxpbmVhckdyYWRpZW50IGlkPSJlIiBncmFkaWVudFVuaXRzPSJ1c2VyU3BhY2VPblVzZSIgeDE9IjI0IiB4Mj0iMjQiIHkxPSIwIiB5Mj0iNDgiPjxzdG9wIG9mZnNldD0iMCIgc3RvcC1jb2xvcj0iI2ZmZiIgc3RvcC1vcGFjaXR5PSIuMTIiLz48c3RvcCBvZmZzZXQ9IjEiIHN0b3AtY29sb3I9IiNmZmYiIHN0b3Atb3BhY2l0eT0iMCIvPjwvbGluZWFyR3JhZGllbnQ+PGNsaXBQYXRoIGlkPSJmIj48cmVjdCBoZWlnaHQ9IjQ4IiByeD0iMTIiIHdpZHRoPSI0OCIvPjwvY2xpcFBhdGg+PGcgZmlsdGVyPSJ1cmwoI2EpIj48ZyBjbGlwLXBhdGg9InVybCgjZikiPjxyZWN0IGZpbGw9IiMyMjI2MkYiIGhlaWdodD0iNDgiIHJ4PSIxMiIgd2lkdGg9IjQ4Ii8+PHBhdGggZD0ibTAgMGg0OHY0OGgtNDh6IiBmaWxsPSJ1cmwoI2MpIi8+PGcgZmlsdGVyPSJ1cmwoI2IpIj48cGF0aCBkPSJtOSAxMi43NWMwLTIuMDcxMSAxLjY3ODktMy43NSAzLjc1LTMuNzVoNy41YzIuMDcxMSAwIDMuNzUgMS42Nzg5IDMuNzUgMy43NXY3LjM2NDRjLjAwMDIuMDQ1LjAwMDMuMDkwMi4wMDAzLjEzNTYgMCAyLjA2ODEgMS42NzQxIDMuNzQ1MiAzLjc0MSAzLjc1aC4wMDg3IDcuNWMyLjA3MTEgMCAzLjc1IDEuNjc4OSAzLjc1IDMuNzV2Ny41YzAgMi4wNzExLTEuNjc4OSAzLjc1LTMuNzUgMy43NWgtNy41Yy0yLjA3MTEgMC0zLjc1LTEuNjc4OS0zLjc1LTMuNzV2LTcuNWMwLS4wMTA0IDAtLjAyMDguMDAwMS0uMDMxMi0uMDE2Ny0yLjA1NjctMS42ODkyLTMuNzE4OC0zLjc0OTgtMy43MTg4LS4wMDk3IDAtLjAxOTQgMC0uMDI5MSAwaC03LjQ3MTJjLTIuMDcxMSAwLTMuNzUtMS42Nzg5LTMuNzUtMy43NXoiIGZpbGw9InVybCgjZCkiLz48L2c+PC9nPjxyZWN0IGhlaWdodD0iNDYiIHJ4PSIxMSIgc3Ryb2tlPSJ1cmwoI2UpIiBzdHJva2Utd2lkdGg9IjIiIHdpZHRoPSI0NiIgeD0iMSIgeT0iMSIvPjwvZz48L3N2Zz4=" alt="WP Smart Code" class="ecs-logo-icon" />
				<span class="ecs-logo-text">WP Smart Code</span>
			</div>
			<div class="ecs-header-actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-smart-code-tools' ) ); ?>" class="button button-secondary button-large">
					<span class="dashicons dashicons-admin-tools"></span>
					<?php esc_html_e( 'Tools', 'wp-smart-code' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-smart-code-editor' ) ); ?>" class="button button-primary button-large">
					<span class="dashicons dashicons-plus-alt"></span>
					<?php esc_html_e( 'Add New Snippet', 'wp-smart-code' ); ?>
				</a>
			</div>
		</div>
	</div>

	<!-- Type Filter Tabs -->
	<div class="ecs-type-tabs">
		<div class="ecs-type-tabs-wrapper">
			<?php
			$current_type = isset( $_GET['type'] ) ? sanitize_text_field( wp_unslash( $_GET['type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$snippet_model = $admin->get_snippet();
			
			// Get counts for each type
			$all_count = $snippet_model->count( [ 'deleted' => 0 ] );
			$php_count = $snippet_model->count( [ 'type' => 'php', 'deleted' => 0 ] );
			$js_count = $snippet_model->count( [ 'type' => 'js', 'deleted' => 0 ] );
			$css_count = $snippet_model->count( [ 'type' => 'css', 'deleted' => 0 ] );
			$html_count = $snippet_model->count( [ 'type' => 'html', 'deleted' => 0 ] );
			?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=code-snippet' ) ); ?>" 
			   class="ecs-type-tab <?php echo empty( $current_type ) ? 'active' : ''; ?>">
				<?php esc_html_e( 'All Snippets', 'wp-smart-code' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=code-snippet&type=php' ) ); ?>" 
			   class="ecs-type-tab <?php echo 'php' === $current_type ? 'active' : ''; ?>">
				<?php esc_html_e( 'PHP', 'wp-smart-code' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=code-snippet&type=js' ) ); ?>" 
			   class="ecs-type-tab <?php echo 'js' === $current_type ? 'active' : ''; ?>">
				<?php esc_html_e( 'JavaScript', 'wp-smart-code' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=code-snippet&type=css' ) ); ?>" 
			   class="ecs-type-tab <?php echo 'css' === $current_type ? 'active' : ''; ?>">
				<?php esc_html_e( 'CSS', 'wp-smart-code' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=code-snippet&type=html' ) ); ?>" 
			   class="ecs-type-tab <?php echo 'html' === $current_type ? 'active' : ''; ?>">
				<?php esc_html_e( 'HTML', 'wp-smart-code' ); ?>
			</a>
		</div>
	</div>

	<!-- Page Content -->
	<div class="ecs-page-content">

	<?php
	// Display admin notices
	settings_errors( 'ecs_messages' );

	// Fire action hook before snippets list
	do_action( 'ecs_before_snippets_list' );
	?>

	<form method="get">
		<input type="hidden" name="page" value="code-snippet">
		<?php
		if ( isset( $list_table ) && is_object( $list_table ) ) {
			$list_table->views();
			$list_table->search_box( __( 'Search Snippets', 'wp-smart-code' ), 'snippet' );
			$list_table->display();
		}
		?>
	</form>

	<?php
	// Fire action hook after snippets list
	do_action( 'ecs_after_snippets_list' );
	?>
	</div><!-- .ecs-page-content -->
</div><!-- .ecs-snippets-page -->


