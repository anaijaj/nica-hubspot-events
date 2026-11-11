<?php
define( 'NICA_HSE_TESTING', true );
require __DIR__ . '/../includes/class-nica-hse-util.php';

$fail = 0; $n = 0;
function eq( $name, $got, $want ) {
	global $fail, $n; $n++;
	if ( $got !== $want ) { $fail++; echo "FAIL $name\n  got:  " . var_export( $got, true ) . "\n  want: " . var_export( $want, true ) . "\n"; }
}
$U = 'NICA_HSE_Util';
$d = "\u{2013}";

// dates
eq( 'iso', $U::normalize_date( '2026-08-22' ), '2026-08-22' );
eq( 'iso time', $U::normalize_date( '2026-08-22T00:00:00Z' ), '2026-08-22' );
eq( 'ymd8', $U::normalize_date( '20260822' ), '2026-08-22' );
eq( 'ms', $U::normalize_date( '1787356800000' ), '2026-08-22' );
eq( 'seconds', $U::normalize_date( '1787356800' ), '2026-08-22' );
eq( 'bad', $U::normalize_date( 'soon' ), '' );
eq( 'bad day', $U::normalize_date( '2026-02-31' ), '' );
eq( 'empty', $U::normalize_date( '' ), '' );

// ranges
eq( 'same month', $U::format_date_range( '2026-08-22', '2026-08-23' ), "August 22 $d 23" );
eq( 'cross month', $U::format_date_range( '2026-08-30', '2026-09-01' ), "August 30 $d September 1" );
eq( 'single', $U::format_date_range( '2026-12-12', '2026-12-12' ), 'December 12' );
eq( 'no end', $U::format_date_range( '2026-12-12' ), 'December 12' );
eq( 'year', $U::format_date_range( '2026-08-22', '2026-08-23', true ), "August 22 $d 23, 2026" );
eq( 'cross year', $U::format_date_range( '2026-12-30', '2027-01-02' ), "December 30, 2026 $d January 2, 2027" );
eq( 'bad start', $U::format_date_range( 'x' ), '' );

// numeric
eq( 'numeric range', $U::format_date_numeric( '2026-08-22', '2026-08-23' ), "08/22/2026 \u{2014} 08/23/2026" );
eq( 'numeric single', $U::format_date_numeric( '2026-08-22', '2026-08-22' ), '08/22/2026' );
eq( 'numeric none', $U::format_date_numeric( 'x' ), '' );

// past
eq( 'past', $U::is_past( '2026-08-01', '2026-08-02', '2026-08-03' ), true );
eq( 'last day is today', $U::is_past( '2026-08-01', '2026-08-03', '2026-08-03' ), false );
eq( 'multi-day ongoing', $U::is_past( '2026-08-01', '2026-08-05', '2026-08-03' ), false );
eq( 'no end', $U::is_past( '2026-08-01', '', '2026-08-03' ), true );

// urls / emails
eq( 'https', $U::safe_url( 'https://a.com/x' ), 'https://a.com/x' );
eq( 'bare', $U::safe_url( 'a.com/x' ), 'https://a.com/x' );
eq( 'js', $U::safe_url( 'javascript:alert(1)' ), '' );
eq( 'data', $U::safe_url( 'data:text/html,hi' ), '' );
eq( 'quote', $U::safe_url( 'https://a.com/"onclick=' ), '' );
eq( 'space', $U::safe_url( 'https://a.com/a b' ), '' );
eq( 'ftp', $U::safe_url( 'ftp://a.com' ), '' );
eq( 'email ok', $U::safe_email( 'a@b.org' ), 'a@b.org' );
eq( 'email bad', $U::safe_email( 'nope' ), '' );

// text / maps
eq( 'clean', $U::clean_text( "  <b>Hi</b>\n there " ), 'Hi there' );
eq( 'embed', $U::maps_embed_url( '1 Main St, Ocala, FL', 'Park' ), 'https://www.google.com/maps?q=1%20Main%20St%2C%20Ocala%2C%20FL&output=embed' );
eq( 'embed venue only', $U::maps_embed_url( '', 'Park' ), 'https://www.google.com/maps?q=Park&output=embed' );
eq( 'embed none', $U::maps_embed_url( '', '' ), '' );
eq( 'link', $U::maps_link( '1 Main St', '' ), 'https://www.google.com/maps/search/?api=1&query=1%20Main%20St' );

// map parse round trip
$map = $U::default_map();
eq( 'roundtrip', $U::parse_map( $U::map_to_text( $map ) ), $map );
$custom = $U::parse_map( "title = my_title\n# comment\nleague=lg" );
eq( 'custom title', $custom['title'], 'my_title' );
eq( 'custom league', $custom['league'], 'lg' );
eq( 'default kept', $custom['start'], $map['start'] );
eq( 'props unique', count( $U::properties_to_request( $map ) ), count( array_unique( $map ) ) );

// search body: league filter is the whole point
$body = $U::build_search_body( 'league', 'florida', array( 'a', 'b' ) );
eq( 'filter prop', $body['filterGroups'][0]['filters'][0]['propertyName'], 'league' );
eq( 'filter op', $body['filterGroups'][0]['filters'][0]['operator'], 'EQ' );
eq( 'filter val', $body['filterGroups'][0]['filters'][0]['value'], 'florida' );
eq( 'no after', isset( $body['after'] ), false );
eq( 'after', $U::build_search_body( 'l', 'v', array(), '100' )['after'], '100' );

// labels
eq( 'labels', $U::options_to_labels( array( array( 'value' => 'race', 'label' => 'Race Weekend' ), array( 'value' => 'x' ) ) ), array( 'race' => 'Race Weekend', 'x' => 'x' ) );

// record -> event
$rec = array( 'id' => '123', 'properties' => array(
	'event_name' => '  Ocala <i>Showdown</i> ', 'league' => 'florida', 'event_type' => 'race',
	'event_start_date' => '2026-08-22', 'event_end_date' => '2026-08-23',
	'event_description' => '<p>Hi</p>', 'featured_image' => '98765',
	'event_flyer' => 'javascript:x', 'event_host_email' => 'h@x.org', 'registration_link' => 'reg.com/go',
	'venue_name' => 'Park', 'venue_address' => '1 Main St',
) );
$ev = $U::record_to_event( $rec, $map, array( 'race' => 'Race Weekend' ) );
eq( 'ev title', $ev['title'], 'Ocala Showdown' );
eq( 'ev type', $ev['type_label'], 'Race Weekend' );
eq( 'ev image id', $ev['image_ref'], '98765' );
eq( 'ev bad flyer', $ev['flyer'], '' );
eq( 'ev reg', $ev['registration'], 'https://reg.com/go' );
eq( 'ev end', $ev['end'], '2026-08-23' );
$rec2 = $rec; $rec2['properties']['event_end_date'] = '2026-08-01';
eq( 'end before start', $U::record_to_event( $rec2, $map )['end'], '2026-08-22' );
$rec3 = $rec; unset( $rec3['properties']['event_start_date'] );
eq( 'no start', $U::record_to_event( $rec3, $map ), null );
eq( 'no id', $U::record_to_event( array( 'properties' => $rec['properties'] ), $map ), null );
$rec4 = $rec; $rec4['properties']['featured_image'] = 'https://cdn.x.com/a.jpg';
eq( 'image url', $U::record_to_event( $rec4, $map )['image_ref'], 'https://cdn.x.com/a.jpg' );
$rec5 = $rec; $rec5['properties']['featured_image'] = 'javascript:1';
eq( 'image bad', $U::record_to_event( $rec5, $map )['image_ref'], '' );
eq( 'unknown type value', $U::record_to_event( $rec, $map, array() )['type_label'], 'race' );

// numeric dates (card design)
eq( 'numeric range', $U::format_date_numeric( '2026-08-22', '2026-08-23' ), "08/22/2026 \u{2014} 08/23/2026" );
eq( 'numeric single', $U::format_date_numeric( '2026-08-22', '2026-08-22' ), '08/22/2026' );
eq( 'numeric no end', $U::format_date_numeric( '2026-08-22' ), '08/22/2026' );
eq( 'numeric bad', $U::format_date_numeric( 'x' ), '' );

// buttons: every link that exists; order and sign-up buttons depend on past/upcoming
$all = array( 'registration' => 'r', 'volunteer' => 'v', 'flyer' => 'f', 'results' => 'x', 'photos' => 'p' );
eq( 'upcoming', array_column( $U::buttons( $all, false ), 0 ), array( 'Register', 'Volunteer', 'Event Flyer', 'Photos', 'Results' ) );
eq( 'past hides sign-ups', array_column( $U::buttons( $all, true ), 0 ), array( 'Results', 'Photos', 'Event Flyer' ) );
eq( 'no reg', array_column( $U::buttons( array( 'volunteer' => 'v', 'flyer' => 'f' ), false ), 0 ), array( 'Volunteer', 'Event Flyer' ) );
eq( 'flyer is download', $U::buttons( array( 'flyer' => 'f' ), false )[0][2], 'download' );
eq( 'others external', $U::buttons( array( 'photos' => 'p' ), true )[0][2], 'external' );
eq( 'none', $U::buttons( array(), false ), array() );

echo "$n checks, $fail failed\n";
exit( $fail ? 1 : 0 );
