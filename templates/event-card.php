<?php
/**
 * One event card. Copy to your-theme/nica-hubspot-events/event-card.php to customise.
 * Available: $post_id, $d (see NICA_HSE_Render::data).
 *
 * @package NICA_HubSpot_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dates    = NICA_HSE_Util::format_date_range( $d['start'], $d['end'], $d['is_past'] );
$maps_url = NICA_HSE_Util::maps_link( $d['venue_address'], $d['venue_name'] );
?>
<article class="nica-card<?php echo $d['is_past'] ? ' nica-card--past' : ''; ?>">
	<?php if ( '' !== $d['type'] ) : ?>
		<span class="nica-badge"><?php echo esc_html( $d['type'] ); ?></span>
	<?php endif; ?>

	<p class="nica-card__date"><?php echo esc_html( $dates ); ?></p>

	<?php if ( '' !== $d['venue_name'] ) : ?>
		<p class="nica-card__venue"><?php echo NICA_HSE_Render::icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <span><?php echo esc_html( $d['venue_name'] ); ?></span></p>
	<?php endif; ?>

	<h3 class="nica-card__title"><a href="<?php echo esc_url( $d['url'] ); ?>"><?php echo esc_html( $d['title'] ); ?></a></h3>

	<?php if ( '' !== $d['venue_address'] ) : ?>
		<p class="nica-card__address">
			<?php if ( '' !== $maps_url ) : ?>
				<a href="<?php echo esc_url( $maps_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $d['venue_address'] ); ?></a>
			<?php else : ?>
				<?php echo esc_html( $d['venue_address'] ); ?>
			<?php endif; ?>
		</p>
	<?php endif; ?>

	<div class="nica-card__actions">
		<div class="nica-card__buttons">
			<?php echo NICA_HSE_Render::buttons_html( $d, $d['url'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</div>
		<button type="button" class="nica-share" data-nica-share data-url="<?php echo esc_url( $d['url'] ); ?>" data-title="<?php echo esc_attr( $d['title'] ); ?>" aria-label="<?php echo esc_attr( 'Share ' . $d['title'] ); ?>">
			<?php echo NICA_HSE_Render::icon( 'share' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</button>
	</div>
</article>
