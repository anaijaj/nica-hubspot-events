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

$dates    = NICA_HSE_Util::format_date_numeric( $d['start'], $d['end'] );
$where    = trim( $d['venue_name'] . ( '' !== $d['venue_name'] && '' !== $d['venue_address'] ? ', ' : '' ) . $d['venue_address'] );
$maps_url = NICA_HSE_Util::maps_link( $d['venue_address'], $d['venue_name'] );
?>
<article class="nica-card<?php echo $d['is_past'] ? ' nica-card--past' : ''; ?>">
	<div class="nica-card__top">
		<div class="nica-card__date"><?php echo esc_html( $dates ); ?></div>
		<?php if ( '' !== $d['type'] ) : ?>
			<span class="nica-badge"><?php echo esc_html( $d['type'] ); ?></span>
		<?php endif; ?>
	</div>

	<h3 class="nica-card__title"><a href="<?php echo esc_url( $d['url'] ); ?>"><?php echo esc_html( $d['title'] ); ?></a></h3>

	<?php if ( '' !== $where ) : ?>
		<div class="nica-card__where">
			<?php if ( '' !== $maps_url ) : ?>
				<a href="<?php echo esc_url( $maps_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $where ); ?></a>
			<?php else : ?>
				<?php echo esc_html( $where ); ?>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="nica-card__buttons">
		<?php echo NICA_HSE_Render::buttons_html( $d, $d['url'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	</div>
</article>
