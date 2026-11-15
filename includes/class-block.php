<?php
/**
 * Newsletter block.
 *
 * @package NewsletterPublisher
 */

namespace NLWP;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the server-rendered newsletter block.
 */
class Block {

	const NAME = 'newsletter-wp-plugin/newsletter';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'editor_assets' ) );
	}

	/**
	 * Register the block from metadata.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		register_block_type(
			NLWP_DIR . 'blocks/newsletter',
			array(
				'render_callback' => array( __CLASS__, 'render' ),
			)
		);
	}

	/**
	 * Enqueue the block editor script and block data.
	 *
	 * @return void
	 */
	public static function editor_assets(): void {
		wp_enqueue_style(
			'nlwp-newsletter',
			NLWP_URL . 'assets/css/newsletter.css',
			array(),
			NLWP_VERSION
		);

		$settings = Settings::get_settings();
		wp_add_inline_style( 'nlwp-newsletter', Renderer::inline_css( $settings, Languages::current_code() ) );

		wp_enqueue_script(
			'nlwp-block-editor',
			NLWP_URL . 'blocks/newsletter/index.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n', 'wp-server-side-render' ),
			NLWP_VERSION,
			true
		);

		$issues = get_posts(
			array(
				'post_type'      => Post_Types::ISSUE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => 100,
				'no_found_rows'  => true,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		$issue_options = array();
		foreach ( $issues as $issue ) {
			$issue_options[] = array(
				'value' => (string) $issue->ID,
				'label' => sprintf( '%1$s (%2$s)', get_the_title( $issue ), $issue->post_status ),
			);
		}

		$languages = array(
			array(
				'value' => '',
				'label' => __( 'Site language', 'newsletter-wp-plugin' ),
			),
		);

		foreach ( Languages::all( true ) as $language ) {
			$languages[] = array(
				'value' => $language['code'],
				'label' => $language['label'],
			);
		}

		wp_localize_script(
			'nlwp-block-editor',
			'nlwpBlock',
			array(
				'issues'    => $issue_options,
				'languages' => $languages,
				'hasIssues' => ! empty( $issue_options ),
				'defaults'  => array(
					'showTitle' => true,
					'showIntro' => ! empty( $settings['show_intro'] ),
				),
			)
		);

		wp_set_script_translations( 'nlwp-block-editor', 'newsletter-wp-plugin', NLWP_DIR . 'languages' );
	}

	/**
	 * Render the block on the front end.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render( array $attributes = array() ): string {
		$display_mode = isset( $attributes['displayMode'] ) ? sanitize_key( (string) $attributes['displayMode'] ) : '';

		$content = Renderer::render(
			array(
				'issue_id'     => isset( $attributes['issueId'] ) ? absint( $attributes['issueId'] ) : 0,
				'language'     => isset( $attributes['language'] ) ? sanitize_text_field( (string) $attributes['language'] ) : '',
				'display_mode' => $display_mode,
				'show_title'   => array_key_exists( 'showTitle', $attributes ) && null !== $attributes['showTitle'] ? (bool) $attributes['showTitle'] : null,
				'show_intro'   => array_key_exists( 'showIntro', $attributes ) && null !== $attributes['showIntro'] ? (bool) $attributes['showIntro'] : null,
			)
		);

		if ( '' === $content ) {
			return '';
		}

		return '<div ' . get_block_wrapper_attributes( array( 'class' => 'nlwp-block' ) ) . '>' . $content . '</div>';
	}
}
