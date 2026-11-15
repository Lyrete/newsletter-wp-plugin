<?php
/**
 * Newsletter wrapper template.
 *
 * @package NewsletterPublisher
 *
 * @var array $settings
 * @var array $groups
 */

defined( 'ABSPATH' ) || exit;

$title_tag    = in_array( $title_tag, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ? $title_tag : 'h2';
$category_tag = in_array( $category_tag, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ? $category_tag : 'h3';
?>
<div class="<?php echo esc_attr( $classes ); ?>" lang="<?php echo esc_attr( $lang ); ?>" dir="<?php echo esc_attr( $dir ); ?>">
	<?php echo $logo_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

	<?php if ( '' !== $title_html || '' !== $intro_html || '' !== $issue_date_html ) : ?>
		<header class="nlwp-header">
			<?php if ( '' !== $title_html ) : ?>
				<<?php echo esc_html( $title_tag ); ?> class="nlwp-title"><?php echo $title_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $title_tag ); ?>>
			<?php endif; ?>

			<?php echo $issue_date_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

			<?php if ( '' !== $intro_html ) : ?>
				<div class="nlwp-intro"><?php echo $intro_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<?php endif; ?>
		</header>
	<?php endif; ?>

	<?php if ( empty( $groups ) ) : ?>
		<div class="nlwp-empty"><?php echo wp_kses_post( $empty_text ); ?></div>
	<?php else : ?>
		<div class="nlwp-groups">
			<?php foreach ( $groups as $group ) : ?>
				<section class="nlwp-category nlwp-category-<?php echo esc_attr( (string) $group['term_id'] ); ?>" data-term="<?php echo esc_attr( (string) $group['term_id'] ); ?>">
					<?php if ( $show_categories && '' !== $group['label'] ) : ?>
						<<?php echo esc_html( $category_tag ); ?> class="nlwp-category-title"><?php echo $group['label']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></<?php echo esc_html( $category_tag ); ?>>
					<?php endif; ?>

					<div class="nlwp-events">
						<?php foreach ( $group['events'] as $event ) : ?>
							<?php
							echo \NLWP\Renderer::template(
								'event',
								array(
									'event'     => $event,
									'accordion' => $accordion,
								)
							); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							?>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
