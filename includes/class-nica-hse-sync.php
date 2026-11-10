<?php
/**
 * Copies HubSpot events for this site's league into WordPress.
 *
 * @package NICA_HubSpot_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NICA_HSE_Sync {

	const LOCK     = 'nica_hse_lock';
	const CRON     = 'nica_hse_cron_sync';
	const INTERVAL = 'nica_hse_15min';

	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval
		add_action( self::CRON, array( __CLASS__, 'run' ) );
		add_action( 'init', array( __CLASS__, 'ensure_scheduled' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_route' ) );
	}

	public static function schedules( $schedules ) {
		$schedules[ self::INTERVAL ] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => 'Every 15 minutes (HubSpot Events)',
		);
		return $schedules;
	}

	public static function ensure_scheduled() {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + 60, self::INTERVAL, self::CRON );
		}
	}

	/** Optional endpoint so n8n or a HubSpot workflow can trigger a sync right away. */
	public static function register_route() {
		register_rest_route(
			'nica-hse/v1',
			'/sync',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_sync' ),
				'permission_callback' => array( __CLASS__, 'rest_permission' ),
			)
		);
	}

	public static function rest_permission( WP_REST_Request $request ) {
		$s = NICA_HSE_Settings::get();
		if ( '' === $s['secret'] ) {
			return new WP_Error( 'nica_hse_disabled', 'No sync secret is configured.', array( 'status' => 403 ) );
		}
		$given = (string) $request->get_header( 'x_nica_secret' );
		return hash_equals( $s['secret'], $given )
			? true
			: new WP_Error( 'nica_hse_forbidden', 'Wrong secret.', array( 'status' => 403 ) );
	}

	public static function rest_sync() {
		$r = self::run();
		return rest_ensure_response(
			array(
				'created'     => $r['created'],
				'updated'     => $r['updated'],
				'unchanged'   => $r['unchanged'],
				'unpublished' => $r['unpublished'],
				'error'       => $r['error'],
			)
		);
	}

	/** Runs one sync. Returns the summary array (also saved for the settings page). */
	public static function run() {
		$summary = array(
			'time'        => time(),
			'created'     => 0,
			'updated'     => 0,
			'unchanged'   => 0,
			'unpublished' => 0,
			'skipped'     => array(),
			'warnings'    => array(),
			'error'       => '',
		);
		$s = NICA_HSE_Settings::get();
		if ( '' === $s['token'] || '' === $s['object_type'] || '' === $s['league'] ) {
			$summary['error'] = 'Settings are incomplete: token, object type ID and league are all required.';
			update_option( 'nica_hse_last_sync', $summary, false );
			return $summary;
		}
		if ( get_transient( self::LOCK ) ) {
			$summary['error'] = 'Another sync is already running.';
			return $summary;
		}
		set_transient( self::LOCK, 1, 2 * MINUTE_IN_SECONDS );

		try {
			self::do_sync( $s, $summary );
		} finally {
			delete_transient( self::LOCK );
		}
		update_option( 'nica_hse_last_sync', $summary, false );
		return $summary;
	}

	private static function do_sync( array $s, array &$summary ) {
		$client = new NICA_HSE_HubSpot( $s['token'], $s['api_base'] );
		$map    = $s['map'];

		$records = $client->search_events( $s['object_type'], $map['league'], $s['league'], NICA_HSE_Util::properties_to_request( $map ) );
		if ( is_wp_error( $records ) ) {
			$summary['error'] = $records->get_error_message();
			return;
		}

		// Event Type is a dropdown: turn its internal values into the labels people see.
		$type_labels = array();
		$prop        = $client->get_property( $s['object_type'], $map['type'] );
		if ( is_wp_error( $prop ) ) {
			$summary['warnings'][] = 'Could not read the Event Type options, so internal values are shown: ' . $prop->get_error_message();
		} elseif ( ! empty( $prop['options'] ) && is_array( $prop['options'] ) ) {
			$type_labels = NICA_HSE_Util::options_to_labels( $prop['options'] );
		}

		$seen = array();
		foreach ( $records as $record ) {
			if ( ! is_array( $record ) ) {
				continue;
			}
			$event = NICA_HSE_Util::record_to_event( $record, $map, $type_labels );
			$rid   = isset( $record['id'] ) ? (string) $record['id'] : '?';
			if ( null === $event ) {
				$summary['skipped'][] = 'HubSpot record ' . $rid . ' needs an event name and a start date.';
				continue;
			}
			$seen[ $event['hs_id'] ] = true;
			self::upsert( $event, $client, $summary );
		}

		self::unpublish_missing( $seen, $summary );
	}

	private static function find_post( $hs_id ) {
		$ids = get_posts(
			array(
				'post_type'        => NICA_HSE_Render::POST_TYPE,
				'post_status'      => 'any',
				'numberposts'      => 1,
				'fields'           => 'ids',
				'meta_key'         => '_hs_object_id', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'       => $hs_id, // phpcs:ignore WordPress.DB.SlowDBQuery
				'suppress_filters' => true,
			)
		);
		return $ids ? (int) $ids[0] : 0;
	}

	private static function upsert( array $ev, NICA_HSE_HubSpot $client, array &$summary ) {
		$post_id = self::find_post( $ev['hs_id'] );
		$hash    = md5( wp_json_encode( $ev ) );

		if ( $post_id ) {
			$flag      = (bool) get_post_meta( $post_id, '_hs_unpublished_by_sync', true );
			$same      = get_post_meta( $post_id, '_hs_hash', true ) === $hash;
			if ( $same && ! $flag ) {
				// Text is current. Only retry the picture (a no-op unless it changed or failed before).
				self::sync_image( $post_id, $ev, $client, $summary );
				$summary['unchanged']++;
				return;
			}
			$args = array(
				'ID'           => $post_id,
				'post_title'   => $ev['title'],
				'post_content' => wp_kses_post( $ev['description'] ),
			);
			if ( $flag ) {
				$args['post_status'] = 'publish';
			}
			$result = wp_update_post( $args, true );
			if ( is_wp_error( $result ) ) {
				$summary['skipped'][] = 'Could not update "' . $ev['title'] . '": ' . $result->get_error_message();
				return;
			}
			delete_post_meta( $post_id, '_hs_unpublished_by_sync' );
			$summary['updated']++;
		} else {
			$post_id = wp_insert_post(
				array(
					'post_type'    => NICA_HSE_Render::POST_TYPE,
					'post_status'  => 'publish',
					'post_title'   => $ev['title'],
					'post_content' => wp_kses_post( $ev['description'] ),
				),
				true
			);
			if ( is_wp_error( $post_id ) ) {
				$summary['skipped'][] = 'Could not create "' . $ev['title'] . '": ' . $post_id->get_error_message();
				return;
			}
			$summary['created']++;
		}

		update_post_meta( $post_id, '_hs_object_id', $ev['hs_id'] );
		update_post_meta( $post_id, '_nica_league', $ev['league'] );
		update_post_meta( $post_id, '_nica_type', $ev['type_label'] );
		update_post_meta( $post_id, '_nica_start', $ev['start'] );
		update_post_meta( $post_id, '_nica_end', $ev['end'] );
		foreach ( array( 'host_name', 'host_email', 'venue_name', 'venue_address', 'flyer', 'registration', 'volunteer', 'results', 'photos' ) as $key ) {
			update_post_meta( $post_id, '_nica_' . $key, $ev[ $key ] );
		}

		self::sync_image( $post_id, $ev, $client, $summary );
		update_post_meta( $post_id, '_hs_hash', $hash );
		update_post_meta( $post_id, '_hs_synced_at', time() );
	}

	/** Copies the Featured Image into the Media Library (HubSpot file links can expire). */
	private static function sync_image( $post_id, array $ev, NICA_HSE_HubSpot $client, array &$summary ) {
		$ref = $ev['image_ref'];
		if ( '' === $ref ) {
			if ( get_post_meta( $post_id, '_hs_image_ref', true ) ) {
				delete_post_thumbnail( $post_id );
				delete_post_meta( $post_id, '_hs_image_ref' );
			}
			return;
		}
		if ( get_post_meta( $post_id, '_hs_image_ref', true ) === $ref && has_post_thumbnail( $post_id ) ) {
			return;
		}

		$url = ctype_digit( $ref ) ? $client->file_url( $ref ) : $ref;
		if ( is_wp_error( $url ) ) {
			$summary['warnings'][] = '"' . $ev['title'] . '": featured image not copied (' . $url->get_error_message() . ').';
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $url, 30 );
		if ( is_wp_error( $tmp ) ) {
			$summary['warnings'][] = '"' . $ev['title'] . '": featured image download failed (' . $tmp->get_error_message() . ').';
			return;
		}
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$name = sanitize_file_name( rawurldecode( basename( $path ) ) );
		if ( '' === $name || ! wp_check_filetype( $name )['ext'] ) {
			$name = 'event-' . $ev['hs_id'] . '.jpg';
		}
		$info = @getimagesize( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( ! $info ) {
			wp_delete_file( $tmp );
			$summary['warnings'][] = '"' . $ev['title'] . '": the featured image is not a picture WordPress can read.';
			return;
		}
		$attachment_id = media_handle_sideload( array( 'name' => $name, 'tmp_name' => $tmp ), $post_id );
		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $tmp );
			$summary['warnings'][] = '"' . $ev['title'] . '": featured image could not be saved (' . $attachment_id->get_error_message() . ').';
			return;
		}
		set_post_thumbnail( $post_id, $attachment_id );
		update_post_meta( $post_id, '_hs_image_ref', $ref );
	}

	/** Events that vanished from HubSpot go back to draft (never deleted). */
	private static function unpublish_missing( array $seen, array &$summary ) {
		$ids = get_posts(
			array(
				'post_type'        => NICA_HSE_Render::POST_TYPE,
				'post_status'      => 'publish',
				'numberposts'      => -1,
				'fields'           => 'ids',
				'meta_key'         => '_hs_object_id', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_compare'     => 'EXISTS',
				'suppress_filters' => true,
			)
		);
		$gone = array();
		foreach ( $ids as $id ) {
			if ( ! isset( $seen[ (string) get_post_meta( $id, '_hs_object_id', true ) ] ) ) {
				$gone[] = $id;
			}
		}
		if ( ! $gone ) {
			return;
		}
		if ( ! $seen ) {
			$summary['warnings'][] = 'HubSpot returned no usable events for this league, so ' . count( $gone ) . ' published event(s) were left alone. Check the League value if that is unexpected.';
			return;
		}
		foreach ( $gone as $id ) {
			wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
			update_post_meta( $id, '_hs_unpublished_by_sync', 1 );
			$summary['unpublished']++;
		}
	}
}
