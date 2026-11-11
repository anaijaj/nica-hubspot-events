<?php
/**
 * Pure helper functions (no WordPress calls) so they can be unit-tested on their own.
 *
 * @package NICA_HubSpot_Events
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'NICA_HSE_TESTING' ) ) {
	exit;
}

final class NICA_HSE_Util {

	/**
	 * Which HubSpot property feeds which field on the website.
	 * The values are HubSpot *internal* property names; they can be changed
	 * on the plugin's settings page.
	 */
	public static function default_map() {
		return array(
			'title'         => 'event_name',
			'league'        => 'league',
			'type'          => 'event_type',
			'start'         => 'event_start_date',
			'end'           => 'event_end_date',
			'description'   => 'event_description',
			'image'         => 'featured_image',
			'flyer'         => 'event_flyer',
			'host_name'     => 'event_host_name',
			'host_email'    => 'event_host_email',
			'venue_name'    => 'venue_name',
			'venue_address' => 'venue_address',
			'registration'  => 'registration_link',
			'volunteer'     => 'volunteer_link',
			'results'       => 'results_link',
			'photos'        => 'photos_link',
		);
	}

	/** Turn the "key=property" lines from the settings page into a map. */
	public static function parse_map( $text ) {
		$map = self::default_map();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
			$line = trim( $line );
			if ( '' === $line || '#' === $line[0] || false === strpos( $line, '=' ) ) {
				continue;
			}
			list( $key, $prop ) = array_map( 'trim', explode( '=', $line, 2 ) );
			if ( isset( $map[ $key ] ) && preg_match( '/^[a-z0-9_]+$/i', $prop ) ) {
				$map[ $key ] = $prop;
			}
		}
		return $map;
	}

	public static function map_to_text( array $map ) {
		$lines = array();
		foreach ( $map as $key => $prop ) {
			$lines[] = $key . '=' . $prop;
		}
		return implode( "\n", $lines );
	}

	/** Internal property names to request from HubSpot. */
	public static function properties_to_request( array $map ) {
		return array_values( array_unique( array_values( $map ) ) );
	}

	/**
	 * Accepts what HubSpot may send for a date (2026-08-22, an ISO timestamp, or
	 * milliseconds since 1970) and returns Y-m-d, or '' if it can't be understood.
	 */
	public static function normalize_date( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return '';
		}
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})(?:$|[T ])/', $raw, $m ) ) {
			return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? $m[1] . '-' . $m[2] . '-' . $m[3] : '';
		}
		if ( ctype_digit( $raw ) ) {
			if ( 8 === strlen( $raw ) ) {
				$y = (int) substr( $raw, 0, 4 );
				$mo = (int) substr( $raw, 4, 2 );
				$d = (int) substr( $raw, 6, 2 );
				return checkdate( $mo, $d, $y ) ? sprintf( '%04d-%02d-%02d', $y, $mo, $d ) : '';
			}
			$n = (int) $raw;
			if ( strlen( $raw ) >= 12 ) {
				$n = intdiv( $n, 1000 ); // milliseconds.
			}
			return gmdate( 'Y-m-d', $n );
		}
		try {
			$d = new DateTimeImmutable( $raw, new DateTimeZone( 'UTC' ) );
			return $d->format( 'Y-m-d' );
		} catch ( Exception $e ) {
			return '';
		}
	}

	private static function dt( $ymd ) {
		if ( ! is_string( $ymd ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ymd ) ) {
			return null;
		}
		try {
			return new DateTimeImmutable( $ymd . ' 00:00:00', new DateTimeZone( 'UTC' ) );
		} catch ( Exception $e ) {
			return null;
		}
	}

	/**
	 * "August 22 – 23", "August 30 – September 1", "December 12".
	 * With $with_year the year is added ("August 22 – 23, 2026").
	 */
	public static function format_date_range( $start, $end = '', $with_year = false ) {
		$s = self::dt( $start );
		if ( ! $s ) {
			return '';
		}
		$e = self::dt( $end );
		if ( ! $e || $e <= $s ) {
			return $s->format( $with_year ? 'F j, Y' : 'F j' );
		}
		$dash = " \u{2013} ";
		if ( $s->format( 'Y' ) !== $e->format( 'Y' ) ) {
			return $s->format( 'F j, Y' ) . $dash . $e->format( 'F j, Y' );
		}
		if ( $s->format( 'm' ) === $e->format( 'm' ) ) {
			$out = $s->format( 'F j' ) . $dash . $e->format( 'j' );
		} else {
			$out = $s->format( 'F j' ) . $dash . $e->format( 'F j' );
		}
		return $with_year ? $out . ', ' . $s->format( 'Y' ) : $out;
	}

	/** "08/22/2026 — 08/23/2026" (or just one date for a one-day event). */
	public static function format_date_numeric( $start, $end = '' ) {
		$s = self::dt( $start );
		if ( ! $s ) {
			return '';
		}
		$e = self::dt( $end );
		if ( ! $e || $e <= $s ) {
			return $s->format( 'm/d/Y' );
		}
		return $s->format( 'm/d/Y' ) . " \u{2014} " . $e->format( 'm/d/Y' );
	}

	/** True when the event's last day is before $today (all Y-m-d). */
	public static function is_past( $start, $end, $today ) {
		$last = '' !== (string) $end ? $end : $start;
		return '' !== (string) $last && $last < $today;
	}

	/** Only http(s) links with a host survive. Bare domains get https://. */
	public static function safe_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url || preg_match( '/[\s<>"\']/', $url ) ) {
			return '';
		}
		if ( ! preg_match( '#^[a-z][a-z0-9+.-]*:#i', $url ) && preg_match( '#^[a-z0-9-]+(\.[a-z0-9-]+)+(/.*)?$#i', $url ) ) {
			$url = 'https://' . $url;
		}
		$parts = parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || empty( $parts['scheme'] ) ) {
			return '';
		}
		if ( ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
			return '';
		}
		return $url;
	}

	public static function safe_email( $email ) {
		$email = trim( (string) $email );
		return false !== filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : '';
	}

	/** One-line plain text. */
	public static function clean_text( $text ) {
		$text = strip_tags( (string) $text );
		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}

	private static function map_query( $address, $venue ) {
		$address = self::clean_text( $address );
		$venue   = self::clean_text( $venue );
		if ( '' !== $address ) {
			return $address;
		}
		return $venue;
	}

	/** URL for the embedded map (no API key). '' when there is nothing to map. */
	public static function maps_embed_url( $address, $venue = '' ) {
		$q = self::map_query( $address, $venue );
		return '' === $q ? '' : 'https://www.google.com/maps?q=' . rawurlencode( $q ) . '&output=embed';
	}

	/** "Get directions" link. */
	public static function maps_link( $address, $venue = '' ) {
		$q = self::map_query( $address, $venue );
		return '' === $q ? '' : 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $q );
	}

	/** Body for HubSpot's CRM search endpoint, limited to one league. */
	public static function build_search_body( $league_prop, $league_value, array $props, $after = null, $limit = 100 ) {
		$body = array(
			'filterGroups' => array(
				array(
					'filters' => array(
						array(
							'propertyName' => $league_prop,
							'operator'     => 'EQ',
							'value'        => $league_value,
						),
					),
				),
			),
			'properties'   => array_values( $props ),
			'limit'        => (int) $limit,
		);
		if ( null !== $after && '' !== $after ) {
			$body['after'] = (string) $after;
		}
		return $body;
	}

	/** HubSpot property definition options => [ value => label ]. */
	public static function options_to_labels( array $options ) {
		$out = array();
		foreach ( $options as $opt ) {
			if ( is_array( $opt ) && isset( $opt['value'] ) ) {
				$out[ (string) $opt['value'] ] = isset( $opt['label'] ) ? (string) $opt['label'] : (string) $opt['value'];
			}
		}
		return $out;
	}

	/**
	 * Turns one HubSpot record into the array the website uses.
	 * Returns null if the record lacks an id, a name or a start date.
	 */
	public static function record_to_event( array $record, array $map, array $type_labels = array() ) {
		$p   = isset( $record['properties'] ) && is_array( $record['properties'] ) ? $record['properties'] : array();
		$get = function ( $key ) use ( $p, $map ) {
			$prop = isset( $map[ $key ] ) ? $map[ $key ] : '';
			return ( '' !== $prop && isset( $p[ $prop ] ) && is_scalar( $p[ $prop ] ) ) ? trim( (string) $p[ $prop ] ) : '';
		};

		$id    = isset( $record['id'] ) ? preg_replace( '/[^0-9A-Za-z_-]/', '', (string) $record['id'] ) : '';
		$title = self::clean_text( $get( 'title' ) );
		$start = self::normalize_date( $get( 'start' ) );
		if ( '' === $id || '' === $title || '' === $start ) {
			return null;
		}
		$end = self::normalize_date( $get( 'end' ) );
		if ( '' === $end || $end < $start ) {
			$end = $start;
		}

		$type_value = $get( 'type' );
		$type_label = '' === $type_value ? '' : ( isset( $type_labels[ $type_value ] ) ? $type_labels[ $type_value ] : $type_value );

		$image = $get( 'image' );
		if ( '' !== $image && ! ctype_digit( $image ) ) {
			$image = self::safe_url( $image );
		}

		return array(
			'hs_id'         => $id,
			'title'         => $title,
			'league'        => self::clean_text( $get( 'league' ) ),
			'type_value'    => self::clean_text( $type_value ),
			'type_label'    => self::clean_text( $type_label ),
			'start'         => $start,
			'end'           => $end,
			'description'   => $get( 'description' ),
			'image_ref'     => $image,
			'flyer'         => self::safe_url( $get( 'flyer' ) ),
			'host_name'     => self::clean_text( $get( 'host_name' ) ),
			'host_email'    => self::safe_email( $get( 'host_email' ) ),
			'venue_name'    => self::clean_text( $get( 'venue_name' ) ),
			'venue_address' => self::clean_text( $get( 'venue_address' ) ),
			'registration'  => self::safe_url( $get( 'registration' ) ),
			'volunteer'     => self::safe_url( $get( 'volunteer' ) ),
			'results'       => self::safe_url( $get( 'results' ) ),
			'photos'        => self::safe_url( $get( 'photos' ) ),
		);
	}

	/**
	 * Buttons for an event: every link that exists, as [ label, url, kind ].
	 * Upcoming events lead with Register / Volunteer; finished events lead with Results / Photos.
	 * kind is 'download' for the flyer and 'external' for everything else (used for the icon).
	 */
	public static function buttons( array $d, $is_past ) {
		$upcoming = array(
			'registration' => array( 'Register', 'external' ),
			'volunteer'    => array( 'Volunteer', 'external' ),
			'flyer'        => array( 'Event Flyer', 'download' ),
			'photos'       => array( 'Photos', 'external' ),
			'results'      => array( 'Results', 'external' ),
		);
		$past = array(
			'results'      => array( 'Results', 'external' ),
			'photos'       => array( 'Photos', 'external' ),
			'flyer'        => array( 'Event Flyer', 'download' ),
			'registration' => array( 'Register', 'external' ),
			'volunteer'    => array( 'Volunteer', 'external' ),
		);
		$order = $is_past ? $past : $upcoming;
		$out   = array();
		foreach ( $order as $key => $meta ) {
			if ( $is_past && in_array( $key, array( 'registration', 'volunteer' ), true ) ) {
				continue; // sign-ups for an event that already happened are just noise.
			}
			if ( ! empty( $d[ $key ] ) ) {
				$out[] = array( $meta[0], $d[ $key ], $meta[1] );
			}
		}
		return $out;
	}
}
