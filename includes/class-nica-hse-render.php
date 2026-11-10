<?php
/**
 * Post type, shortcode, single event template and styles.
 *
 * @package NICA_HubSpot_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NICA_HSE_Render {

	const POST_TYPE = 'nica_event';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_shortcode( 'nica_events', array( __CLASS__, 'shortcode' ) );
		add_filter( 'template_include', array( __CLASS__, 'template' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'metabox' ) );
	}

	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'       => array(
					'name'          => 'League Events',
					'singular_name' => 'League Event',
					'edit_item'     => 'View League Event',
				),
				'public'       => true,
				'has_archive'  => false,
				'show_in_rest' => false,
				'menu_icon'    => 'dashicons-calendar-alt',
				'supports'     => array( 'title', 'thumbnail' ),
				'rewrite'      => array(
					'slug'       => apply_filters( 'nica_hse_rewrite_slug', 'league-event' ),
					'with_front' => false,
				),
			)
		);
	}

	public static function register_assets() {
		wp_register_style( 'nica-hse', NICA_HSE_URL . 'assets/events.css', array(), NICA_HSE_VERSION );
		wp_register_script( 'nica-hse', NICA_HSE_URL . 'assets/events.js', array(), NICA_HSE_VERSION, true );
	}

	private static function enqueue() {
		self::register_assets();
		wp_enqueue_style( 'nica-hse' );
		wp_enqueue_script( 'nica-hse' );
	}

	public static function maybe_enqueue() {
		if ( is_singular( self::POST_TYPE ) ) {
			self::enqueue();
			return;
		}
		if ( is_singular() ) {
			$post = get_post();
			if ( $post && has_shortcode( $post->post_content, 'nica_events' ) ) {
				self::enqueue();
			}
		}
	}

	public static function template( $template ) {
		if ( is_singular( self::POST_TYPE ) ) {
			$theme = locate_template( 'nica-hubspot-events/single-event.php' );
			return $theme ? $theme : NICA_HSE_DIR . 'templates/single-event.php';
		}
		return $template;
	}

	public static function metabox() {
		add_meta_box(
			'nica_hse_info',
			'Managed in HubSpot',
			function ( $post ) {
				$synced = (int) get_post_meta( $post->ID, '_hs_synced_at', true );
				echo '<p>This event is copied from HubSpot. Edit it there; changes made here are overwritten at the next sync.</p>';
				echo '<p>HubSpot record ID: <strong>' . esc_html( (string) get_post_meta( $post->ID, '_hs_object_id', true ) ) . '</strong></p>';
				if ( $synced ) {
					echo '<p>Last synced: ' . esc_html( wp_date( 'M j, Y g:i a', $synced ) ) . '</p>';
				}
			},
			self::POST_TYPE,
			'side'
		);
	}

	/** All stored values for one event. */
	public static function data( $post_id ) {
		$m = function ( $key ) use ( $post_id ) {
			return (string) get_post_meta( $post_id, '_nica_' . $key, true );
		};
		$d = array(
			'title'         => get_the_title( $post_id ),
			'url'           => get_permalink( $post_id ),
			'type'          => $m( 'type' ),
			'start'         => $m( 'start' ),
			'end'           => $m( 'end' ),
			'host_name'     => $m( 'host_name' ),
			'host_email'    => $m( 'host_email' ),
			'venue_name'    => $m( 'venue_name' ),
			'venue_address' => $m( 'venue_address' ),
			'flyer'         => $m( 'flyer' ),
			'registration'  => $m( 'registration' ),
			'volunteer'     => $m( 'volunteer' ),
			'results'       => $m( 'results' ),
			'photos'        => $m( 'photos' ),
		);
		$d['is_past'] = NICA_HSE_Util::is_past( $d['start'], $d['end'], wp_date( 'Y-m-d' ) );
		return $d;
	}

	/** Small inline icons. */
	public static function icon( $name ) {
		$icons = array(
			'pin'      => '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M12 21s-6.5-5.7-6.5-11a6.5 6.5 0 0 1 13 0c0 5.3-6.5 11-6.5 11z"/><circle cx="12" cy="10" r="2.4" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>',
			'calendar' => '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><rect x="3.5" y="5" width="17" height="15" rx="2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M3.5 10h17M8 3v4M16 3v4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
			'share'    => '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><g fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5.5" r="2.5"/><circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="18.5" r="2.5"/><path d="M8.2 10.8l7.6-4M8.2 13.2l7.6 4"/></g></svg>',
		);
		return isset( $icons[ $name ] ) ? $icons[ $name ] : '';
	}

	/** Buttons as HTML. The first one is the filled (primary) button. */
	public static function buttons_html( array $d, $with_details_url = '' ) {
		$buttons = NICA_HSE_Util::buttons( $d, $d['is_past'] );
		$html    = '';
		foreach ( $buttons as $i => $b ) {
			$class = 0 === $i ? 'nica-btn nica-btn--primary' : 'nica-btn nica-btn--secondary';
			$html .= '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $b[1] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $b[0] ) . '</a>';
		}
		if ( '' !== $with_details_url ) {
			$class = $buttons ? 'nica-btn nica-btn--ghost' : 'nica-btn nica-btn--primary';
			$html .= '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $with_details_url ) . '">Learn more</a>';
		}
		return $html;
	}

	/**
	 * Opens/closes a full page. Classic themes use get_header()/get_footer();
	 * block themes have no header.php, so their header/footer template parts are used.
	 */
	public static function page_open() {
		if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
			?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php
			wp_body_open();
			echo '<div class="wp-site-blocks">';
			echo do_blocks( '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->' ); // phpcs:ignore WordPress.Security.EscapeOutput
			return;
		}
		get_header();
	}

	public static function page_close() {
		if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
			echo do_blocks( '<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->' ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo '</div>';
			wp_footer();
			echo '</body></html>';
			return;
		}
		get_footer();
	}

	public static function card( $post_id ) {
		$d = self::data( $post_id );
		ob_start();
		include self::locate( 'event-card.php' );
		return ob_get_clean();
	}

	public static function locate( $file ) {
		$theme = locate_template( 'nica-hubspot-events/' . $file );
		return $theme ? $theme : NICA_HSE_DIR . 'templates/' . $file;
	}

	/**
	 * [nica_events show="upcoming|past|all" limit="-1" type="" columns="3"]
	 * Shows this site's events (the sync only copies this site's league).
	 */
	public static function shortcode( $atts ) {
		self::enqueue();
		$a = shortcode_atts(
			array(
				'show'    => 'upcoming',
				'limit'   => -1,
				'type'    => '',
				'columns' => 3,
			),
			$atts,
			'nica_events'
		);
		$show    = in_array( $a['show'], array( 'upcoming', 'past', 'all' ), true ) ? $a['show'] : 'upcoming';
		$limit   = (int) $a['limit'];
		$columns = max( 1, min( 4, (int) $a['columns'] ) );
		$today   = wp_date( 'Y-m-d' );

		$ids = array();
		if ( 'upcoming' === $show || 'all' === $show ) {
			$ids = array_merge( $ids, self::query( 'upcoming', $today, $a['type'], $limit ) );
		}
		if ( 'past' === $show || 'all' === $show ) {
			$ids = array_merge( $ids, self::query( 'past', $today, $a['type'], $limit ) );
		}
		if ( 'all' === $show && $limit > 0 ) {
			$ids = array_slice( $ids, 0, $limit );
		}

		if ( ! $ids ) {
			return '<p class="nica-events-empty">' . esc_html( 'past' === $show ? 'No past events yet.' : 'No upcoming events right now. Check back soon!' ) . '</p>';
		}
		$out = '<div class="nica-events" style="--nica-cols:' . (int) $columns . '">';
		foreach ( $ids as $id ) {
			$out .= self::card( $id );
		}
		return $out . '</div>';
	}

	private static function query( $which, $today, $type, $limit ) {
		$meta = array(
			array(
				'key'     => '_nica_end',
				'value'   => $today,
				'compare' => 'upcoming' === $which ? '>=' : '<',
				'type'    => 'DATE',
			),
		);
		if ( '' !== trim( (string) $type ) ) {
			$meta[] = array(
				'key'   => '_nica_type',
				'value' => sanitize_text_field( $type ),
			);
		}
		return get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'publish',
				'numberposts'      => $limit,
				'fields'           => 'ids',
				'meta_key'         => '_nica_start', // phpcs:ignore WordPress.DB.SlowDBQuery
				'orderby'          => 'meta_value',
				'meta_type'        => 'DATE',
				'order'            => 'upcoming' === $which ? 'ASC' : 'DESC',
				'meta_query'       => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery
				'suppress_filters' => true,
			)
		);
	}
}
