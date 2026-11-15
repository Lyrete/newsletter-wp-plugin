<?php
/**
 * Shared helper functions.
 *
 * @package NewsletterPublisher
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sanitize a Y-m-d date string.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function nlwp_sanitize_date( $value ): string {
	if ( ! is_scalar( $value ) ) {
		return '';
	}

	$value = trim( (string) $value );
	if ( '' === $value ) {
		return '';
	}

	$date   = DateTimeImmutable::createFromFormat( 'Y-m-d', $value );
	$errors = DateTimeImmutable::getLastErrors();

	if ( ! $date ) {
		return '';
	}

	if ( is_array( $errors ) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) {
		return '';
	}

	return $date->format( 'Y-m-d' );
}

/**
 * Add a number of days to a Y-m-d date.
 *
 * @param mixed $date Date string.
 * @param int   $days Number of days.
 * @return string
 */
function nlwp_add_days( $date, int $days ): string {
	$date = nlwp_sanitize_date( $date );
	if ( '' === $date ) {
		return '';
	}

	try {
		$datetime = new DateTimeImmutable( $date, wp_timezone() );
	} catch ( Exception $e ) {
		return $date;
	}

	$modifier = $days >= 0 ? '+' . $days : (string) $days;
	return $datetime->modify( $modifier . ' days' )->format( 'Y-m-d' );
}

/**
 * Sanitize a boolean-ish value into "1" or "0".
 *
 * @param mixed $value Raw value.
 * @return string
 */
function nlwp_sanitize_bool( $value ): string {
	return $value ? '1' : '0';
}

/**
 * Sanitize an array of post IDs.
 *
 * @param mixed $value Raw value.
 * @return int[]
 */
function nlwp_sanitize_id_array( $value ): array {
	if ( ! is_array( $value ) ) {
		$value = array( $value );
	}

	$ids = array_map(
		static function ( $id ) {
			return is_scalar( $id ) ? absint( $id ) : 0;
		},
		$value
	);
	return array_values( array_unique( array_filter( $ids ) ) );
}

/**
 * Sanitize a plain-text separator while keeping meaningful edge spaces.
 *
 * @param mixed $value Raw separator.
 * @return string
 */
function nlwp_sanitize_separator( $value ): string {
	if ( ! is_scalar( $value ) ) {
		return '';
	}

	$raw   = (string) $value;
	$value = wp_strip_all_tags( $raw );
	$value = preg_replace( '/[\r\n\t ]+/u', ' ', $value );
	$value = is_string( $value ) ? trim( $value ) : '';

	if ( '' === $value ) {
		return '';
	}

	if ( preg_match( '/^\s/u', $raw ) ) {
		$value = ' ' . $value;
	}
	if ( preg_match( '/\s$/u', $raw ) ) {
		$value .= ' ';
	}

	return $value;
}

/**
 * Sanitize custom CSS.
 *
 * @param mixed $css Raw CSS.
 * @return string
 */
function nlwp_sanitize_css( $css ): string {
	$css = is_scalar( $css ) ? (string) $css : '';
	$css = str_ireplace( array( '<?php', '?>', '</style', '<style' ), '', $css );
	return trim( wp_strip_all_tags( $css ) );
}

/**
 * Render a newsletter from a theme or another plugin.
 *
 * @param array $args Render arguments.
 * @return string
 */
function nlwp_render_newsletter( array $args = array() ): string {
	return NLWP\Renderer::render( $args );
}

/**
 * Output a newsletter.
 *
 * @param array $args Render arguments.
 * @return void
 */
function nlwp_the_newsletter( array $args = array() ): void {
	echo nlwp_render_newsletter( $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Render a media-picker field for admin screens.
 *
 * @param string $name  Input name.
 * @param int    $value Attachment ID.
 * @param string $label Field label.
 * @return void
 */
function nlwp_admin_media_field( string $name, int $value, string $label = '' ): void {
	$value    = absint( $value );
	$filename = '';
	$url      = '';

	if ( $value ) {
		$url = wp_get_attachment_url( $value );
		if ( $url ) {
			$filename = wp_basename( $url );
		}
	}

	echo '<div class="nlwp-media-field">';
	if ( '' !== $label ) {
		echo '<span class="nlwp-field-label">' . esc_html( $label ) . '</span>';
	}
	echo '<input type="hidden" class="nlwp-media-id" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '">';
	echo '<div class="nlwp-media-preview">';
	if ( $url && wp_attachment_is_image( $value ) ) {
		echo wp_get_attachment_image( $value, 'thumbnail', false, array( 'class' => 'nlwp-media-thumb' ) );
	} elseif ( $filename ) {
		echo '<span class="nlwp-media-filename">' . esc_html( $filename ) . '</span>';
	} else {
		echo '<span class="nlwp-media-empty">' . esc_html__( 'No file selected', 'newsletter-wp-plugin' ) . '</span>';
	}
	echo '</div>';
	echo '<button type="button" class="button nlwp-media-select">' . esc_html__( 'Select file', 'newsletter-wp-plugin' ) . '</button> ';
	echo '<button type="button" class="button-link nlwp-media-remove">' . esc_html__( 'Remove', 'newsletter-wp-plugin' ) . '</button>';
	echo '</div>';
}
