<?php
/**
 * Plain-text email template.
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
	$blocks[] = $header;
}
if ( '' !== $title ) {
	$blocks[] = $title;
}
if ( '' !== $date ) {
	$blocks[] = $date;
}
if ( '' !== $intro ) {
	$blocks[] = $intro;
}

foreach ( $groups as $group ) {
	if ( ! empty( $group['label'] ) ) {
		$blocks[] = $group['label'];
	}

	foreach ( $group['events'] as $event ) {
		$lines       = array();
		$event_title = (string) $event['title'];
		if ( ! empty( $event['number'] ) ) {
			$event_title = $event['number'] . '. ' . $event_title;
		}
		$lines[] = $event_title;

		if ( ! empty( $event['date'] ) ) {
			$lines[] = $event['date'];
		}
		if ( ! empty( $event['description'] ) ) {
			$lines[] = $event['description'];
		}
		if ( ! empty( $event['attachment'] ) ) {
			$attachment = $event['attachment'];
			$lines[]    = $attachment['label'] . ': ' . $attachment['name'] . ' (' . $attachment['url'] . ')';
		}

		$blocks[] = implode( "\n", $lines );
	}
}

if ( '' !== $footer ) {
	$blocks[] = $footer;
}

echo implode( "\n\n", $blocks ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
