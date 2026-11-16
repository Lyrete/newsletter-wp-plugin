<?php
/**
 * Minimal HTML email template.
 *
 * @package NewsletterPublisher
 *
 * @var string $header
 * @var string $title
 * @var string $date
 * @var string $intro
 * @var array  $groups
 * @var string $footer
 */

defined( 'ABSPATH' ) || exit;

$blocks = array();

if ( '' !== $header ) {
	$blocks[] = '<p>' . $header . '</p>';
}
if ( '' !== $title ) {
	$blocks[] = '<h1>' . $title . '</h1>';
}
if ( '' !== $date ) {
	$blocks[] = '<p>' . $date . '</p>';
}
if ( '' !== $intro ) {
	$blocks[] = '<p>' . $intro . '</p>';
}

foreach ( $groups as $group ) {
	if ( ! empty( $group['label'] ) ) {
		$blocks[] = '<h2>' . $group['label'] . '</h2>';
	}

	foreach ( $group['events'] as $event ) {
		$event_title = (string) $event['title'];
		if ( ! empty( $event['number'] ) ) {
			$event_title = $event['number'] . '. ' . $event_title;
		}

		$blocks[] = '<h3>' . $event_title . '</h3>';

		if ( ! empty( $event['date'] ) ) {
			$blocks[] = '<p>' . $event['date'] . '</p>';
		}
		if ( ! empty( $event['description'] ) ) {
			$blocks[] = '<p>' . $event['description'] . '</p>';
		}
		if ( ! empty( $event['attachment'] ) ) {
			$attachment = $event['attachment'];
			$blocks[]   = '<p>' . $attachment['label'] . ': <a href="' . esc_url( $attachment['url'] ) . '">' . $attachment['name'] . '</a></p>';
		}
	}
}

if ( '' !== $footer ) {
	$blocks[] = '<p>' . $footer . '</p>';
}

echo implode( "\n", $blocks ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
