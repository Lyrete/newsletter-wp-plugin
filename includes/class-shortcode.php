<?php
/**
 * Newsletter shortcode.
 *
 * @package NewsletterPublisher
 */

namespace NLWP;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the [nlwp_newsletter] shortcode.
 */
class Shortcode {

	const TAG = 'nlwp_newsletter';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_shortcode( self::TAG, array( __CLASS__, 'render' ) );
	}

	/**
	 * Render the shortcode.
	 *
	 * @param array|string $atts    Shortcode attributes.
	 * @param string|null  $content Enclosed content.
	 * @return string
	 */
	public static function render( $atts = array(), $content = null ): string {
		unset( $content );

		$atts = shortcode_atts(
			array(
				'issue_id'     => 0,
				'issue'        => 0,
				'language'     => '',
				'display_mode' => '',
				'mode'         => '',
				'show_title'   => '',
				'show_intro'   => '',
				'class'        => '',
			),
			is_array( $atts ) ? $atts : array(),
			self::TAG
		);

		$issue_id = absint( $atts['issue_id'] );
		if ( ! $issue_id ) {
			$issue_id = absint( $atts['issue'] );
		}

		$display_mode = '' !== (string) $atts['display_mode'] ? (string) $atts['display_mode'] : (string) $atts['mode'];

		return Renderer::render(
			array(
				'issue_id'     => $issue_id,
				'language'     => sanitize_text_field( (string) $atts['language'] ),
				'display_mode' => sanitize_key( $display_mode ),
				'show_title'   => self::boolean_or_null( $atts['show_title'] ),
				'show_intro'   => self::boolean_or_null( $atts['show_intro'] ),
				'class'        => sanitize_text_field( (string) $atts['class'] ),
			)
		);
	}

	/**
	 * Convert an attribute to a nullable boolean.
	 *
	 * @param mixed $value Raw value.
	 * @return bool|null
	 */
	private static function boolean_or_null( $value ): ?bool {
		if ( '' === $value || null === $value ) {
			return null;
		}

		return filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
	}
}
