<?php
/**
 * Single event page. Copy to your-theme/nica-hubspot-events/single-event.php to customise.
 *
 * @package NICA_HubSpot_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

NICA_HSE_Render::page_open();

$events_url = NICA_HSE_Settings::get()['events_url'];

while ( have_posts() ) :
	the_post();
	$post_id    = get_the_ID();
	$d          = NICA_HSE_Render::data( $post_id );
	$dates      = NICA_HSE_Util::format_date_range( $d['start'], $d['end'], true );
	$image      = get_the_post_thumbnail_url( $post_id, 'full' );
	$maps_link  = NICA_HSE_Util::maps_link( $d['venue_address'], $d['venue_name'] );
	$maps_embed = NICA_HSE_Util::maps_embed_url( $d['venue_address'], $d['venue_name'] );
	$hero_style = $image ? ' style="background-image:url(' . esc_url( $image ) . ')"' : '';
	$content    = trim( get_the_content() );
	?>
	<main class="nica-single">
		<header class="nica-hero<?php echo $image ? ' nica-hero--image' : ''; ?>"<?php echo $hero_style; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
			<div class="nica-hero__inner">
				<?php if ( '' !== $d['type'] ) : ?>
					<span class="nica-badge nica-badge--hero"><?php echo esc_html( $d['type'] ); ?></span>
				<?php endif; ?>
				<h1 class="nica-hero__title"><?php echo esc_html( $d['title'] ); ?></h1>
				<p class="nica-hero__meta"><?php echo NICA_HSE_Render::icon( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <span><?php echo esc_html( $dates ); ?></span></p>
				<?php if ( '' !== $d['venue_name'] || '' !== $d['venue_address'] ) : ?>
					<p class="nica-hero__meta">
						<?php echo NICA_HSE_Render::icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<span><?php echo esc_html( '' !== $d['venue_name'] ? $d['venue_name'] : $d['venue_address'] ); ?></span>
					</p>
					<?php if ( '' !== $d['venue_name'] && '' !== $d['venue_address'] ) : ?>
						<p class="nica-hero__address"><?php echo esc_html( $d['venue_address'] ); ?></p>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</header>

		<div class="nica-single__wrap">
			<?php if ( '' !== $events_url ) : ?>
				<p class="nica-back"><a href="<?php echo esc_url( $events_url ); ?>">&larr; All events</a></p>
			<?php endif; ?>

			<?php $buttons = NICA_HSE_Render::buttons_html( $d ); ?>
			<?php if ( '' !== $buttons ) : ?>
				<div class="nica-actions"><?php echo $buttons; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
			<?php endif; ?>

			<div class="nica-single__grid">
				<div class="nica-single__main">
					<?php if ( '' !== $content ) : ?>
						<h2 class="nica-h2">About this event</h2>
						<div class="nica-description"><?php echo apply_filters( 'the_content', $content ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
					<?php endif; ?>
				</div>

				<aside class="nica-single__side">
					<div class="nica-panel">
						<!--<dl class="nica-facts">
							<dt>Date</dt>
							<dd><?php echo esc_html( $dates ); ?></dd>

							<?php if ( '' !== $d['venue_name'] || '' !== $d['venue_address'] ) : ?>
								<dt>Location</dt>
								<dd>
									<?php if ( '' !== $d['venue_name'] ) : ?>
										<strong><?php echo esc_html( $d['venue_name'] ); ?></strong><br />
									<?php endif; ?>
									<?php echo esc_html( $d['venue_address'] ); ?>
									<?php if ( '' !== $maps_link ) : ?>
										<br /><a href="<?php echo esc_url( $maps_link ); ?>" target="_blank" rel="noopener noreferrer">Get directions</a>
									<?php endif; ?>
								</dd>
							<?php endif; ?>

							<?php if ( '' !== $d['host_name'] || '' !== $d['host_email'] ) : ?>
								<dt>Hosted by</dt>
								<dd>
									<?php echo esc_html( $d['host_name'] ); ?>
									<?php if ( '' !== $d['host_email'] ) : ?>
										<br /><a href="mailto:<?php echo esc_attr( antispambot( $d['host_email'] ) ); ?>"><?php echo esc_html( antispambot( $d['host_email'] ) ); ?></a>
									<?php endif; ?>
								</dd>
							<?php endif; ?>
						</dl>-->
						<div class="nica-facts">
	<div class="nica-fact">
		<div class="nica-fact__label"><?php echo NICA_HSE_Render::icon( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> Date</div>
		<div class="nica-fact__value"><?php echo esc_html( $dates ); ?></div>
	</div>

	<?php if ( '' !== $d['venue_name'] || '' !== $d['venue_address'] ) : ?>
		<div class="nica-fact">
			<div class="nica-fact__label"><?php echo NICA_HSE_Render::icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> Location</div>
			<div class="nica-fact__value">
				<?php if ( '' !== $d['venue_name'] ) : ?>
					<strong><?php echo esc_html( $d['venue_name'] ); ?></strong><br />
				<?php endif; ?>
				<?php echo esc_html( $d['venue_address'] ); ?>
				<?php if ( '' !== $maps_link ) : ?>
					<br /><a href="<?php echo esc_url( $maps_link ); ?>" target="_blank" rel="noopener noreferrer">Get directions</a>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( '' !== $d['host_name'] || '' !== $d['host_email'] ) : ?>
		<div class="nica-fact">
			<div class="nica-fact__label"><?php echo NICA_HSE_Render::icon( 'user' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> Hosted by</div>
			<div class="nica-fact__value">
				<?php echo esc_html( $d['host_name'] ); ?>
				<?php if ( '' !== $d['host_email'] ) : ?>
					<br /><a href="mailto:<?php echo esc_attr( antispambot( $d['host_email'] ) ); ?>"><?php echo esc_html( antispambot( $d['host_email'] ) ); ?></a>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>
</div>
					</div>

					<?php if ( '' !== $maps_embed ) : ?>
						<div class="nica-map">
							<iframe title="<?php echo esc_attr( 'Map of ' . ( '' !== $d['venue_name'] ? $d['venue_name'] : $d['venue_address'] ) ); ?>" src="<?php echo esc_url( $maps_embed ); ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
						</div>
					<?php endif; ?>

					<button type="button" class="nica-btn nica-btn--ghost nica-share-wide" data-nica-share data-url="<?php echo esc_url( $d['url'] ); ?>" data-title="<?php echo esc_attr( $d['title'] ); ?>">
						<?php echo NICA_HSE_Render::icon( 'share' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> Share this event
					</button>
				</aside>
			</div>
		</div>
	</main>
	<?php
endwhile;

NICA_HSE_Render::page_close();
