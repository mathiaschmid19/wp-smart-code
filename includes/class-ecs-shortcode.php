<?php
/**
 * Shortcode Handler for Edge Code Snippets.
 *
 * @package ECS
 * @since 1.0.0
 */

declare( strict_types=1 );

namespace ECS;

/**
 * Shortcode class for handling snippet shortcodes.
 *
 * @since 1.0.0
 */
class Shortcode {
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
	 * Initialize shortcode hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		// Register main shortcode
		add_shortcode( 'ecs_snippet', [ $this, 'render_snippet_shortcode' ] );
		
		// Register utility shortcodes
		add_shortcode( 'ecs_snippet_list', [ $this, 'render_snippet_list_shortcode' ] );
		add_shortcode( 'ecs_snippet_count', [ $this, 'render_snippet_count_shortcode' ] );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[ECS] Shortcode hooks registered.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Render snippet shortcode.
	 *
	 * @param array  $atts    Shortcode attributes.
	 * @param string $content Shortcode content.
	 * @param string $tag     Shortcode tag.
	 * @return string Rendered output.
	 */
	public function render_snippet_shortcode( $atts, string $content = '', string $tag = '' ): string {
		// Parse attributes
		$atts = shortcode_atts(
			[
				'id'       => '',
				'slug'     => '',
				'type'     => '',
				'code'     => '',
				'active'   => '1',
				'echo'     => '1',
				'class'    => '',
				'style'    => '',
				'wrapper'  => 'div',
				'title'    => '',
				'note'     => '',
			],
			$atts,
			$tag
		);

		// Determine snippet source
		$snippet_data = null;

		if ( ! empty( $atts['id'] ) ) {
			// Get by ID
			$snippet_data = $this->snippet->get( (int) $atts['id'] );
		} elseif ( ! empty( $atts['slug'] ) ) {
			// Get by slug
			$snippet_data = $this->snippet->get_by_slug( sanitize_text_field( $atts['slug'] ) );
		} elseif ( ! empty( $atts['code'] ) && ! empty( $atts['type'] ) ) {
			// Inline code
			$snippet_data = [
				'id'       => 0,
				'title'    => $atts['title'] ?: 'Inline Snippet',
				'slug'     => 'inline-' . uniqid(),
				'type'     => sanitize_text_field( $atts['type'] ),
				'code'     => $atts['code'],
				'active'   => '1' === $atts['active'],
				'conditions' => '{}',
			];
		}

		// Check if snippet exists and is active
		if ( ! $snippet_data || ( '1' === $atts['active'] && ! $snippet_data['active'] ) ) {
			return $this->render_error( 'Snippet not found or inactive.' );
		}

		// Check conditions for active snippets
		if ( $snippet_data['active'] && ! empty( $snippet_data['conditions'] ) ) {
			$conditions = json_decode( $snippet_data['conditions'], true );
			if ( ! Conditions::should_run( $snippet_data ) ) {
				return ''; // Don't render if conditions aren't met
			}
		}

		// Prepare wrapper attributes
		$wrapper_attrs = $this->build_wrapper_attributes( $atts, $snippet_data );

		// Execute snippet based on type
		$output = $this->execute_snippet_output( $snippet_data, $atts );

		// Wrap output if requested
		if ( ! empty( $atts['wrapper'] ) && 'none' !== $atts['wrapper'] ) {
			$output = sprintf(
				'<%1$s%2$s>%3$s</%1$s>',
				sanitize_html_class( $atts['wrapper'] ),
				$wrapper_attrs,
				$output
			);
		}

		return $output;
	}

	/**
	 * Render snippet list shortcode.
	 *
	 * @param array  $atts    Shortcode attributes.
	 * @param string $content Shortcode content.
	 * @param string $tag     Shortcode tag.
	 * @return string Rendered output.
	 */
	public function render_snippet_list_shortcode( $atts, string $content = '', string $tag = '' ): string {
		// Parse attributes
		$atts = shortcode_atts(
			[
				'type'     => '',
				'active'   => '',
				'limit'    => '10',
				'orderby'  => 'title',
				'order'    => 'ASC',
				'class'    => 'ecs-snippet-list',
				'show_type' => '1',
				'show_status' => '1',
				'link'     => '0',
			],
			$atts,
			$tag
		);

		// Get snippets
		$snippets = $this->snippet->all( [
			'type'    => $atts['type'] ?: null,
			'active'  => $atts['active'] ?: null,
			'limit'   => (int) $atts['limit'],
			'orderby' => $atts['orderby'],
			'order'   => $atts['order'],
		] );

		if ( empty( $snippets ) ) {
			return '<p class="ecs-no-snippets">No snippets found.</p>';
		}

		// Build list
		$output = '<ul class="' . esc_attr( $atts['class'] ) . '">';
		
		foreach ( $snippets as $snippet ) {
			$item_class = 'ecs-snippet-item';
			if ( $snippet['active'] ) {
				$item_class .= ' ecs-snippet-active';
			} else {
				$item_class .= ' ecs-snippet-inactive';
			}

			$output .= '<li class="' . esc_attr( $item_class ) . '">';
			
			// Title
			$title = esc_html( $snippet['title'] );
			if ( '1' === $atts['link'] ) {
				$edit_url = admin_url( 'admin.php?page=wp-smart-code-editor&id=' . $snippet['id'] );
				$title = '<a href="' . esc_url( $edit_url ) . '">' . $title . '</a>';
			}
			$output .= '<strong class="ecs-snippet-title">' . $title . '</strong>';

			// Type badge
			if ( '1' === $atts['show_type'] ) {
				$type_badge = '<span class="ecs-badge ecs-badge-' . esc_attr( $snippet['type'] ) . '">' . 
					esc_html( strtoupper( $snippet['type'] ) ) . '</span>';
				$output .= ' ' . $type_badge;
			}

			// Status
			if ( '1' === $atts['show_status'] ) {
				$status = $snippet['active'] ? 'Active' : 'Inactive';
				$status_class = $snippet['active'] ? 'ecs-status-active' : 'ecs-status-inactive';
				$output .= ' <span class="ecs-snippet-status ' . esc_attr( $status_class ) . '">' . 
					esc_html( $status ) . '</span>';
			}

			$output .= '</li>';
		}
		
		$output .= '</ul>';

		return $output;
	}

	/**
	 * Render snippet count shortcode.
	 *
	 * @param array  $atts    Shortcode attributes.
	 * @param string $content Shortcode content.
	 * @param string $tag     Shortcode tag.
	 * @return string Rendered output.
	 */
	public function render_snippet_count_shortcode( $atts, string $content = '', string $tag = '' ): string {
		// Parse attributes
		$atts = shortcode_atts(
			[
				'type'   => '',
				'active' => '',
				'format' => 'count', // count, text, detailed
			],
			$atts,
			$tag
		);

		// Get count
		$count = $this->snippet->count( [
			'type'   => $atts['type'] ?: null,
			'active' => $atts['active'] ?: null,
		] );

		// Format output
		switch ( $atts['format'] ) {
			case 'text':
				$type_text = $atts['type'] ? ' ' . $atts['type'] . ' ' : ' ';
				$active_text = $atts['active'] ? ( '1' === $atts['active'] ? ' active' : ' inactive' ) : '';
				return sprintf( 
					/* translators: %d: count, %s: type, %s: status */
					_n( 
						'%1$d%2$ssnippet%3$s', 
						'%1$d%2$ssnippets%3$s', 
						$count, 
						'wp-smart-code' 
					),
					$count,
					$type_text,
					$active_text
				);

			case 'detailed':
				$total = $this->snippet->count();
				$active = $this->snippet->count( [ 'active' => '1' ] );
				$inactive = $total - $active;
				
				return sprintf(
					/* translators: %d: total, %d: active, %d: inactive */
					__( '%1$d total (%2$d active, %3$d inactive)', 'wp-smart-code' ),
					$total,
					$active,
					$inactive
				);

			case 'count':
			default:
				return (string) $count;
		}
	}

	/**
	 * Execute snippet and return output.
	 *
	 * @param array $snippet_data Snippet data.
	 * @param array $atts         Shortcode attributes.
	 * @return string Output.
	 */
	private function execute_snippet_output( array $snippet_data, array $atts ): string {
		$code = $snippet_data['code'];
		$type = $snippet_data['type'];

		// Check if snippet type is allowed for shortcode execution
		if ( in_array( $type, [ 'css', 'js' ], true ) ) {
			return $this->render_error( 'CSS and JavaScript snippets cannot be executed via shortcode. Please use Auto Insert mode instead.' );
		}

		// Handle different output types
		if ( '1' !== $atts['echo'] ) {
			// Capture output instead of echoing
			ob_start();
		}

		switch ( $type ) {
			case 'php':
				// Execute PHP code through sandbox for security
				if ( current_user_can( 'manage_options' ) ) {
					// Use sandbox for consistent security checks
					$sandbox = Sandbox::get_instance();
					$result = $sandbox->execute_php( $code );
					if ( ! $result['success'] ) {
						return $this->render_error( $result['error'] );
					}
					echo $result['output']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				} else {
					return $this->render_error( 'PHP execution not allowed for current user.' );
				}
				break;

			case 'html':
				// Output HTML with allowed tags
				echo wp_kses_post( $code );
				break;

			default:
				return $this->render_error( 'Unknown snippet type: ' . $type );
		}

		// Return captured output or empty string
		if ( '1' !== $atts['echo'] ) {
			return ob_get_clean();
		}

		return '';
	}

	/**
	 * Build wrapper attributes.
	 *
	 * @param array $atts         Shortcode attributes.
	 * @param array $snippet_data Snippet data.
	 * @return string Attributes string.
	 */
	private function build_wrapper_attributes( array $atts, array $snippet_data ): string {
		$attrs = [];

		// Add classes
		$classes = [ 'ecs-snippet', 'ecs-snippet-' . $snippet_data['type'] ];
		if ( ! empty( $atts['class'] ) ) {
			$classes[] = $atts['class'];
		}
		$attrs['class'] = implode( ' ', $classes );

		// Add styles
		if ( ! empty( $atts['style'] ) ) {
			$attrs['style'] = $atts['style'];
		}

		// Add data attributes
		$attrs['data-snippet-id'] = $snippet_data['id'];
		$attrs['data-snippet-slug'] = $snippet_data['slug'];
		$attrs['data-snippet-type'] = $snippet_data['type'];

		// Build attributes string
		$attr_string = '';
		foreach ( $attrs as $key => $value ) {
			$attr_string .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
		}

		return $attr_string;
	}

	/**
	 * Render error message.
	 *
	 * @param string $message Error message.
	 * @return string Error HTML.
	 */
	private function render_error( string $message ): string {
		if ( current_user_can( 'manage_options' ) ) {
			return '<div class="ecs-error">' . esc_html( $message ) . '</div>';
		}
		return ''; // Don't show errors to non-admins
	}
}
