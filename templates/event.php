<?php
/**
 * Single event template.
 *
 * @package NewsletterPublisher
 *
 * @var array $event
 * @var bool  $accordion
 */

defined( 'ABSPATH' ) || exit;

$has_body = '' !== $event['description_html'] || '' !== $event['attachment_html'];
?>
<?php if ( $accordion ) : ?>
	<details class="nlwp-event nlwp-event-accordion" id="<?php echo esc_attr( $event['anchor'] ); ?>"<?php echo $event['open'] ? ' open' : ''; ?>>
		<summary class="nlwp-event-summary">
			<?php if ( $event['number'] ) : ?>
				<span class="nlwp-event-number"><?php echo esc_html( (string) $event['number'] ); ?>.</span>
			<?php endif; ?>
			<?php if ( '' !== $event['title_html'] ) : ?>
				<span class="nlwp-event-title"><?php echo $event['title_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<?php endif; ?>
			<?php echo $event['date_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</summary>

		<?php if ( $has_body ) : ?>
			<div class="nlwp-event-body">
				<?php echo $event['description_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo $event['attachment_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		<?php endif; ?>
	</details>
<?php else : ?>
	<article class="nlwp-event nlwp-event-static" id="<?php echo esc_attr( $event['anchor'] ); ?>">
		<?php if ( '' !== $event['title_html'] || '' !== $event['date_html'] || $event['number'] ) : ?>
			<div class="nlwp-event-heading">
				<?php if ( $event['number'] ) : ?>
					<span class="nlwp-event-number"><?php echo esc_html( (string) $event['number'] ); ?>.</span>
				<?php endif; ?>
				<?php if ( '' !== $event['title_html'] ) : ?>
					<span class="nlwp-event-title"><?php echo $event['title_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<?php endif; ?>
				<?php echo $event['date_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		<?php endif; ?>

		<?php if ( $has_body ) : ?>
			<div class="nlwp-event-body">
				<?php echo $event['description_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo $event['attachment_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		<?php endif; ?>
	</article>
<?php endif; ?>
