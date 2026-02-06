<?php
/**
 * Admin View: Snippet Library Page
 *
 * @package ECS
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define built-in snippets
$library_snippets = [
	[
		'title'       => 'Allow SVG Uploads',
		'description' => 'Enable SVG file uploads in the WordPress Media Library (Admins only).',
		'type'        => 'php',
		'code'        => "/**\n * Allow SVG uploads for administrator users.\n *\n * @param array \$upload_mimes Allowed mime types.\n *\n * @return mixed\n */\nadd_filter(\n\t'upload_mimes',\n\tfunction ( \$upload_mimes ) {\n\t\t// By default, only administrator users are allowed to add SVGs.\n\t\t// To enable more user types edit or comment the lines below but beware of\n\t\t// the security risks if you allow any user to upload SVG files.\n\t\tif ( ! current_user_can( 'administrator' ) ) {\n\t\t\treturn \$upload_mimes;\n\t\t}\n\n\t\t\$upload_mimes['svg']  = 'image/svg+xml';\n\t\t\$upload_mimes['svgz'] = 'image/svg+xml';\n\n\t\treturn \$upload_mimes;\n\t}\n);\n\n/**\n * Add SVG files mime check.\n *\n * @param array        \$wp_check_filetype_and_ext Values for the extension, mime type, and corrected filename.\n * @param string       \$file Full path to the file.\n * @param string       \$filename The name of the file (may differ from \$file due to \$file being in a tmp directory).\n * @param string[]     \$mimes Array of mime types keyed by their file extension regex.\n * @param string|false \$real_mime The actual mime type or false if the type cannot be determined.\n */\nadd_filter(\n\t'wp_check_filetype_and_ext',\n\tfunction ( \$wp_check_filetype_and_ext, \$file, \$filename, \$mimes, \$real_mime ) {\n\n\t\tif ( ! \$wp_check_filetype_and_ext['type'] ) {\n\n\t\t\t\$check_filetype  = wp_check_filetype( \$filename, \$mimes );\n\t\t\t\$ext             = \$check_filetype['ext'];\n\t\t\t\$type            = \$check_filetype['type'];\n\t\t\t\$proper_filename = \$filename;\n\n\t\t\tif ( \$type && 0 === strpos( \$type, 'image/' ) && 'svg' !== \$ext ) {\n\t\t\t\t\$ext  = false;\n\t\t\t\t\$type = false;\n\t\t\t}\n\n\t\t\t\$wp_check_filetype_and_ext = compact( 'ext', 'type', 'proper_filename' );\n\t\t}\n\n\t\treturn \$wp_check_filetype_and_ext;\n\n\t},\n\t10,\n\t5\n);",
		'tags'        => [ 'Media', 'System', 'Security' ]
	],
	[
		'title'       => 'Disable XML-RPC',
		'description' => 'Disable XML-RPC API to prevent brute force attacks and improve security.',
		'type'        => 'php',
		'code'        => "add_filter( 'xmlrpc_enabled', '__return_false' );\n\n// Hide xmlrpc.php header\nadd_filter( 'wp_headers', function( \$headers ) {\n\tunset( \$headers['X-Pingback'] );\n\treturn \$headers;\n} );",
		'tags'        => [ 'Security', 'Performance' ]
	],
	[
		'title'       => 'Disable Gutenberg Editor',
		'description' => 'Completely disable the Gutenberg editor and revert to Classic Editor.',
		'type'        => 'php',
		'code'        => "add_filter( 'use_block_editor_for_post', '__return_false', 10 );\n\n// Disable Gutenberg styles\nadd_action( 'wp_enqueue_scripts', function() {\n\twp_dequeue_style( 'wp-block-library' );\n\twp_dequeue_style( 'wp-block-library-theme' );\n\twp_dequeue_style( 'wc-block-style' ); // WooCommerce\n}, 100 );",
		'tags'        => [ 'Editor', 'Optimization' ]
	],
	[
		'title'       => 'Hide Admin Bar for Non-Admins',
		'description' => 'Hide the top admin bar for all users except Administrators.',
		'type'        => 'php',
		'code'        => "add_action( 'after_setup_theme', function() {\n\tif ( ! current_user_can( 'administrator' ) && ! is_admin() ) {\n\t\tshow_admin_bar( false );\n\t}\n} );",
		'tags'        => [ 'Admin', 'UI' ]
	],
	[
		'title'       => 'Remove WordPress Version',
		'description' => 'Remove WordPress version number from page source for better security.',
		'type'        => 'php',
		'code'        => "remove_action( 'wp_head', 'wp_generator' );\nadd_filter( 'the_generator', '__return_empty_string' );",
		'tags'        => [ 'Security' ]
	],
	[
		'title'       => 'Disable Comments Completely',
		'description' => 'Disable comments on all posts and remove comments menu from admin.',
		'type'        => 'php',
		'code'        => "add_action( 'admin_init', function() {\n\t// Redirect any user trying to access comments page\n\tglobal \$pagenow;\n\tif ( \$pagenow === 'edit-comments.php' ) {\n\t\twp_redirect( admin_url() );\n\t\texit;\n\t}\n\n\t// Remove comments metabox from dashboard\n\tremove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );\n\n\t// Disable support for comments and trackbacks in post types\n\tforeach ( get_post_types() as \$post_type ) {\n\t\tif ( post_type_supports( \$post_type, 'comments' ) ) {\n\t\t\tremove_post_type_support( \$post_type, 'comments' );\n\t\t\tremove_post_type_support( \$post_type, 'trackbacks' );\n\t\t}\n\t}\n} );\n\n// Close comments on the front-end\nadd_filter( 'comments_open', '__return_false', 20, 2 );\nadd_filter( 'pings_open', '__return_false', 20, 2 );\n\n// Hide existing comments\nadd_filter( 'comments_array', '__return_empty_array', 10, 2 );\n\n// Remove comments page in menu\nadd_action( 'admin_menu', function() {\n\tremove_menu_page( 'edit-comments.php' );\n} );\n\n// Remove comments links from admin bar\nadd_action( 'init', function() {\n\tif ( is_admin_bar_showing() ) {\n\t\tremove_action( 'admin_bar_menu', 'wp_admin_bar_comments_menu', 60 );\n\t}\n} );",
		'tags'        => [ 'Clean Up', 'Admin' ]
	],
	[
		'title'       => 'Google Analytics Tracking',
		'description' => 'Add Google Analytics tracking code to the header.',
		'type'        => 'html',
		'code'        => "<!-- Google Analytics -->\n<script async src=\"https://www.googletagmanager.com/gtag/js?id=GA_MEASUREMENT_ID\"></script>\n<script>\n  window.dataLayer = window.dataLayer || [];\n  function gtag(){dataLayer.push(arguments);}\n  gtag('js', new Date());\n\n  gtag('config', 'GA_MEASUREMENT_ID');\n</script>\n<!-- End Google Analytics -->",
		'mode'        => 'auto_insert',
		'tags'        => [ 'Analytics', 'Marketing' ]
	],
	[
		'title'       => 'Disable Auto Updates',
		'description' => 'Disable all automatic background updates (Core, Plugins, Themes).',
		'type'        => 'php',
		'code'        => "add_filter( 'automatic_updater_disabled', '__return_true' );\nadd_filter( 'auto_update_core', '__return_false' );\nadd_filter( 'auto_update_plugin', '__return_false' );\nadd_filter( 'auto_update_theme', '__return_false' );",
		'tags'        => [ 'Updates', 'Control' ]
	]
];

?>

<div class="wrap ecs-snippets-page">
	
	<!-- Professional Header -->
	<header class="ecs-page-header">
		<div class="ecs-header-content">
			<div class="ecs-logo-section">
				<svg class="ecs-logo-icon" fill="none" height="48" viewBox="0 0 40 48" width="40" xmlns="http://www.w3.org/2000/svg">
					<path d="m0 9c0-2.76142 2.23858-5 5-5h10c2.7614 0 5 2.23858 5 5v9.8192c.0002.06.0003.1203.0003.1808 0 2.7575 2.2322 4.9936 4.9881 5h.0116 10c2.7614 0 5 2.2386 5 5v10c0 2.7614-2.2386 5-5 5h-10c-2.7614 0-5-2.2386-5-5v-10c0-.0139.0001-.0277.0002-.0416-.0224-2.7422-2.2523-4.9584-4.9999-4.9584-.0129 0-.0258 0-.0387 0h-9.9616c-2.76142 0-5-2.2386-5-5z" fill="#2271b1"/>
				</svg>
				<h1 class="ecs-logo-text"><?php esc_html_e( 'Snippet Library', 'wp-smart-code' ); ?></h1>
			</div>
			
			<div class="ecs-header-actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=code-snippet' ) ); ?>" class="button button-secondary">
					<?php esc_html_e( 'Back to Snippets', 'wp-smart-code' ); ?>
				</a>
			</div>
		</div>
	</header>

	<div class="ecs-page-content">
		
		<div id="ecs-library-notices"></div>
		
		<div class="ecs-library-grid">
			<?php foreach ( $library_snippets as $index => $item ) : ?>
				<div class="ecs-library-card">
					<div class="ecs-library-card-header">
						<div class="ecs-library-icon <?php echo esc_attr( $item['type'] ); ?>">
							<?php echo strtoupper( esc_html( $item['type'] ) ); ?>
						</div>
						<div class="ecs-library-tags">
							<?php foreach ( $item['tags'] as $tag ) : ?>
								<span class="ecs-library-tag"><?php echo esc_html( $tag ); ?></span>
							<?php endforeach; ?>
						</div>
					</div>
					
					<h3 class="ecs-library-title"><?php echo esc_html( $item['title'] ); ?></h3>
					<p class="ecs-library-desc"><?php echo esc_html( $item['description'] ); ?></p>
					
					<div class="ecs-library-footer">
						<button type="button" class="button button-primary ecs-import-btn" 
								data-index="<?php echo esc_attr( $index ); ?>">
							<span class="dashicons dashicons-download"></span>
							<?php esc_html_e( 'Import', 'wp-smart-code' ); ?>
						</button>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</div>

<style>
/* Library Specific Styles */
.ecs-library-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
	gap: 20px;
	margin-top: 20px;
}

.ecs-library-card {
	background: #fff;
	border: 1px solid #c3c4c7;
	border-radius: 4px;
	padding: 20px;
	display: flex;
	flex-direction: column;
	transition: all 0.2s ease;
	position: relative;
	overflow: hidden;
}

.ecs-library-card:hover {
	border-color: #2271b1;
	box-shadow: 0 4px 10px rgba(0,0,0,0.05);
	transform: translateY(-2px);
}

.ecs-library-card-header {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	margin-bottom: 15px;
}

.ecs-library-icon {
	font-size: 10px;
	font-weight: 700;
	padding: 4px 8px;
	border-radius: 3px;
	text-transform: uppercase;
}

.ecs-library-icon.php { background: #777bb4; color: #fff; }
.ecs-library-icon.js { background: #f7df1e; color: #000; }
.ecs-library-icon.css { background: #1572b6; color: #fff; }
.ecs-library-icon.html { background: #e34f26; color: #fff; }

.ecs-library-tags {
	display: flex;
	gap: 5px;
	flex-wrap: wrap;
	justify-content: flex-end;
}

.ecs-library-tag {
	background: #f0f0f1;
	color: #646970;
	padding: 2px 6px;
	border-radius: 3px;
	font-size: 11px;
}

.ecs-library-title {
	margin: 0 0 10px 0;
	font-size: 16px;
	font-weight: 600;
	color: #1d2327;
}

.ecs-library-desc {
	margin: 0 0 20px 0;
	color: #50575e;
	font-size: 13px;
	line-height: 1.5;
	flex-grow: 1;
}

.ecs-library-footer {
	margin-top: auto;
	display: flex;
	justify-content: flex-end;
}

.ecs-import-btn {
	display: inline-flex !important;
	align-items: center;
	gap: 5px;
}
</style>

<script>
// Pass PHP data to JS
const ecsLibrarySnippets = <?php echo json_encode( $library_snippets ); ?>;

jQuery(document).ready(function($) {
	$('.ecs-import-btn').on('click', async function() {
		const btn = $(this);
		const index = btn.data('index');
		const originalText = btn.html();
		
		if (btn.hasClass('updating-message')) return;
		
		// Get data from JS object using index
		const item = ecsLibrarySnippets[index];
		if (!item) return;

		const snippetData = {
			title: item.title,
			code: item.code,
			type: item.type,
			mode: item.mode || 'auto_insert',
			active: false // Import as inactive by default
		};
		
		// Set loading state
		btn.addClass('updating-message').prop('disabled', true).text('<?php esc_html_e( 'Importing...', 'wp-smart-code' ); ?>');
		
		try {
			// API Call
			const response = await wp.apiFetch({
				path: '/ecs/v1/snippets',
				method: 'POST',
				data: snippetData
			});
			
			// Success - Show Checked Icon and Text
			btn.removeClass('button-primary').addClass('button-disabled')
			   .html('<span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Imported!', 'wp-smart-code' ); ?>');
			   
			// Show success notice with Edit button
			const editUrl = '<?php echo esc_url( admin_url( 'admin.php?page=wp-smart-code-editor&snippet_id=' ) ); ?>' + response.id;
			const notice = $(`
				<div class="notice notice-success is-dismissible ecs-editor-notice">
					<p>
						<?php esc_html_e( 'Snippet imported successfully!', 'wp-smart-code' ); ?> 
						<a href="${editUrl}" class="button button-small" style="margin-left: 10px; vertical-align: middle;">
							<?php esc_html_e( 'Edit Snippet', 'wp-smart-code' ); ?>
						</a>
					</p>
					<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss</span></button>
				</div>
			`);
			
			$('#ecs-library-notices').html(notice);
			notice.find('.notice-dismiss').on('click', function() { notice.remove(); });
			
		} catch (error) {
			// Error
			console.error(error);
			btn.removeClass('updating-message').prop('disabled', false).html(originalText);
			
			const notice = $(`
				<div class="notice notice-error is-dismissible ecs-editor-notice">
					<p><?php esc_html_e( 'Failed to import snippet: ', 'wp-smart-code' ); ?>${error.message}</p>
					<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss</span></button>
				</div>
			`);
			
			$('#ecs-library-notices').html(notice);
			notice.find('.notice-dismiss').on('click', function() { notice.remove(); });
		}
	});
});
</script>
