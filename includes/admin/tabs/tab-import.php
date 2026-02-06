<?php
/**
 * Import Tab Content.
 *
 * @package ECS
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="ecs-tools-panel">
	<div class="ecs-card ecs-tools-hero-card">
		<h2 class="ecs-tools-hero-title"><?php esc_html_e( 'Import Snippets', 'wp-smart-code' ); ?></h2>
		<p class="ecs-tools-hero-description">
			<?php esc_html_e( 'Import snippets from a JSON file exported from WP Smart Code or other snippet plugins.', 'wp-smart-code' ); ?>
		</p>
	</div>

	<?php
	// Show import results if available
	if ( isset( $_GET['import'] ) && 'complete' === $_GET['import'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$imported = isset( $_GET['imported'] ) ? absint( $_GET['imported'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$skipped  = isset( $_GET['skipped'] ) ? absint( $_GET['skipped'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$errors   = isset( $_GET['errors'] ) ? absint( $_GET['errors'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$total    = isset( $_GET['total'] ) ? absint( $_GET['total'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$format   = isset( $_GET['format'] ) ? sanitize_text_field( wp_unslash( $_GET['format'] ) ) : 'unknown'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		
		$import_results = get_transient( 'ecs_import_results_' . get_current_user_id() );
		?>
		<div class="notice notice-success is-dismissible">
			<p>
				<strong><?php esc_html_e( 'Import Complete!', 'wp-smart-code' ); ?></strong>
				<?php if ( 'unknown' !== $format ) : ?>
					<span class="ecs-format-badge"><?php echo esc_html( \ECS\FormatAdapter::get_format_name( $format ) ); ?></span>
				<?php endif; ?>
			</p>
			<p>
				<?php
				printf(
					/* translators: 1: imported count, 2: total count, 3: skipped count, 4: errors count */
					esc_html__( 'Successfully imported %1$d of %2$d snippets. Skipped: %3$d, Errors: %4$d', 'wp-smart-code' ),
					absint( $imported ),
					absint( $total ),
					absint( $skipped ),
					absint( $errors )
				);
				?>
			</p>
			<?php if ( $import_results && ( ! empty( $import_results['errors'] ) || ! empty( $import_results['skipped'] ) ) ) : ?>
				<details>
					<summary><?php esc_html_e( 'View Details', 'wp-smart-code' ); ?></summary>
					<?php if ( ! empty( $import_results['skipped'] ) ) : ?>
						<p><strong><?php esc_html_e( 'Skipped:', 'wp-smart-code' ); ?></strong></p>
						<ul>
							<?php foreach ( $import_results['skipped'] as $message ) : ?>
								<li><?php echo esc_html( $message ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<?php if ( ! empty( $import_results['errors'] ) ) : ?>
						<p><strong><?php esc_html_e( 'Errors:', 'wp-smart-code' ); ?></strong></p>
						<ul>
							<?php foreach ( $import_results['errors'] as $message ) : ?>
								<li><?php echo esc_html( $message ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</details>
				<?php
				// Clean up transient
				delete_transient( 'ecs_import_results_' . get_current_user_id() );
				?>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<!-- Import Form Card -->
	<div class="ecs-card ecs-tools-main-card">
		<div class="ecs-card-content">
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ecs-import-form">
				<?php wp_nonce_field( 'ecs_import_snippets', 'ecs_import_nonce' ); ?>
				<input type="hidden" name="action" value="ecs_import_snippets">

				<!-- File Upload Section -->
				<div class="ecs-upload-section">
					<label for="ecs-import-file" class="ecs-upload-label">
						<div class="ecs-upload-icon">📁</div>
						<div class="ecs-upload-text">
							<span class="ecs-upload-title"><?php esc_html_e( 'Choose JSON File', 'wp-smart-code' ); ?></span>
							<span class="ecs-upload-hint"><?php esc_html_e( 'or drag and drop here', 'wp-smart-code' ); ?></span>
						</div>
						<input type="file" id="ecs-import-file" name="import_file" accept=".json,application/json" required class="ecs-file-input-hidden">
					</label>
					<span class="ecs-file-name" id="ecs-file-name"></span>
				</div>

				<!-- Import Options -->
				<div class="ecs-options-section">
					<h3 class="ecs-options-title">
						<span class="ecs-icon">⚙️</span>
						<?php esc_html_e( 'Import Options', 'wp-smart-code' ); ?>
					</h3>
					
					<div class="ecs-options-grid">
						<label class="ecs-option-card">
							<input type="checkbox" name="deactivate_on_import" value="1" checked>
							<div class="ecs-option-content">
								<div class="ecs-option-icon">🛡️</div>
								<div class="ecs-option-text">
									<strong class="ecs-option-title"><?php esc_html_e( 'Deactivate snippets on import', 'wp-smart-code' ); ?></strong>
									<span class="ecs-option-description"><?php esc_html_e( 'Recommended for safety. Review snippets before activating.', 'wp-smart-code' ); ?></span>
								</div>
							</div>
						</label>

						<label class="ecs-option-card">
							<input type="checkbox" name="skip_duplicates" value="1" checked>
							<div class="ecs-option-content">
								<div class="ecs-option-icon">⏭️</div>
								<div class="ecs-option-text">
									<strong class="ecs-option-title"><?php esc_html_e( 'Skip existing snippets', 'wp-smart-code' ); ?></strong>
									<span class="ecs-option-description"><?php esc_html_e( 'Prevent importing snippets that already exist (based on slug).', 'wp-smart-code' ); ?></span>
								</div>
							</div>
						</label>

						<label class="ecs-option-card ecs-option-warning">
							<input type="checkbox" name="update_existing" value="1">
							<div class="ecs-option-content">
								<div class="ecs-option-icon">⚠️</div>
								<div class="ecs-option-text">
									<strong class="ecs-option-title"><?php esc_html_e( 'Update existing snippets', 'wp-smart-code' ); ?></strong>
									<span class="ecs-option-description"><?php esc_html_e( 'Overwrite existing snippets. Local changes will be lost!', 'wp-smart-code' ); ?></span>
								</div>
							</div>
						</label>
					</div>
				</div>

				<!-- Submit Button -->
				<div class="ecs-form-actions">
					<button type="submit" class="button button-primary button-hero">
						<span class="ecs-button-icon">📥</span>
						<?php esc_html_e( 'Import Snippets', 'wp-smart-code' ); ?>
					</button>
				</div>
			</form>
		</div>
	</div>

	<!-- Info Cards Grid -->
	<div class="ecs-info-cards-grid">
		<!-- Supported Formats Card -->
		<div class="ecs-card ecs-info-card">
			<div class="ecs-card-header-compact">
				<span class="ecs-card-icon">🔄</span>
				<h3 class="ecs-card-title-compact"><?php esc_html_e( 'Supported Formats', 'wp-smart-code' ); ?></h3>
			</div>
			<div class="ecs-card-content">
				<p class="ecs-info-intro"><?php esc_html_e( 'We automatically detect and convert:', 'wp-smart-code' ); ?></p>
				<ul class="ecs-format-list">
					<li class="ecs-format-item">
						<span class="ecs-guideline-icon">✓</span>
						<strong><?php esc_html_e( 'WP Smart Code', 'wp-smart-code' ); ?></strong>
						<span class="ecs-format-type"><?php esc_html_e( 'Native', 'wp-smart-code' ); ?></span>
					</li>
					<li class="ecs-format-item">
						<span class="ecs-guideline-icon">✓</span>
						<strong><?php esc_html_e( 'WPCode', 'wp-smart-code' ); ?></strong>
					</li>
					<li class="ecs-format-item">
						<span class="ecs-guideline-icon">✓</span>
						<strong><?php esc_html_e( 'Code Snippets', 'wp-smart-code' ); ?></strong>
					</li>
				</ul>
			</div>
		</div>

		<!-- Guidelines Card -->
		<div class="ecs-card ecs-info-card">
			<div class="ecs-card-header-compact">
				<span class="ecs-card-icon">📋</span>
				<h3 class="ecs-card-title-compact"><?php esc_html_e( 'Import Guidelines', 'wp-smart-code' ); ?></h3>
			</div>
			<div class="ecs-card-content">
				<ul class="ecs-guideline-list">
					<li class="ecs-guideline-item ecs-guideline-success">
						<span class="ecs-guideline-icon">✓</span>
						<?php esc_html_e( 'Backup current snippets before importing', 'wp-smart-code' ); ?>
					</li>
					<li class="ecs-guideline-item ecs-guideline-success">
						<span class="ecs-guideline-icon">✓</span>
						<?php esc_html_e( 'Review code before activating snippets', 'wp-smart-code' ); ?>
					</li>
					<li class="ecs-guideline-item ecs-guideline-success">
						<span class="ecs-guideline-icon">✓</span>
						<?php esc_html_e( 'Test in staging environment first', 'wp-smart-code' ); ?>
					</li>
					<li class="ecs-guideline-item ecs-guideline-warning">
						<span class="ecs-guideline-icon">!</span>
						<?php esc_html_e( 'Only import from trusted sources', 'wp-smart-code' ); ?>
					</li>
				</ul>
			</div>
		</div>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	// File upload feedback
	$('#ecs-import-file').on('change', function() {
		const fileName = $(this).val().split('\\').pop();
		if (fileName) {
			$('#ecs-file-name').text('Selected: ' + fileName).show();
		} else {
			$('#ecs-file-name').hide();
		}
	});
});
</script>

