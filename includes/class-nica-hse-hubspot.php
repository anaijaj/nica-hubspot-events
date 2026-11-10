<?php
/**
 * Small HubSpot API client (read-only).
 *
 * @package NICA_HubSpot_Events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NICA_HSE_HubSpot {

	private $token;
	private $base;

	public function __construct( $token, $base = 'https://api.hubapi.com' ) {
		$this->token = $token;
		$this->base  = rtrim( $base, '/' );
	}

	/** @return array|WP_Error */
	private function request( $method, $path, $body = null, $retry = true ) {
		$args = array(
			'method'  => $method,
			'timeout' => 20,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->token,
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
			),
		);
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}
		$res = wp_remote_request( $this->base . $path, $args );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( 429 === $code && $retry ) {
			sleep( 2 );
			return $this->request( $method, $path, $body, false );
		}
		$data = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( $code < 200 || $code >= 300 ) {
			$msg = is_array( $data ) && ! empty( $data['message'] ) ? $data['message'] : 'no details';
			return new WP_Error( 'nica_hse_http', sprintf( 'HubSpot answered HTTP %d (%s)', $code, $msg ), array( 'status' => $code ) );
		}
		return is_array( $data ) ? $data : array();
	}

	/**
	 * All records of the object whose league property equals $league_value.
	 *
	 * @return array|WP_Error
	 */
	public function search_events( $object_type, $league_prop, $league_value, array $props ) {
		$records = array();
		$after   = null;
		for ( $page = 0; $page < 20; $page++ ) {
			$body = NICA_HSE_Util::build_search_body( $league_prop, $league_value, $props, $after );
			$data = $this->request( 'POST', '/crm/v3/objects/' . rawurlencode( $object_type ) . '/search', $body );
			if ( is_wp_error( $data ) ) {
				return $data;
			}
			if ( ! empty( $data['results'] ) && is_array( $data['results'] ) ) {
				$records = array_merge( $records, $data['results'] );
			}
			$after = isset( $data['paging']['next']['after'] ) ? $data['paging']['next']['after'] : null;
			if ( null === $after ) {
				return $records;
			}
		}
		return new WP_Error( 'nica_hse_too_many', 'More than 2,000 events matched; stopping so nothing is unpublished by mistake.' );
	}

	/** All property definitions of the object. @return array|WP_Error */
	public function get_properties( $object_type ) {
		$data = $this->request( 'GET', '/crm/v3/properties/' . rawurlencode( $object_type ) );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		return isset( $data['results'] ) && is_array( $data['results'] ) ? $data['results'] : array();
	}

	/** One property definition. @return array|WP_Error */
	public function get_property( $object_type, $name ) {
		return $this->request( 'GET', '/crm/v3/properties/' . rawurlencode( $object_type ) . '/' . rawurlencode( $name ) );
	}

	/** Download URL for a HubSpot file id (signed if the file is private). @return string|WP_Error */
	public function file_url( $file_id ) {
		$file = $this->request( 'GET', '/files/v3/files/' . rawurlencode( (string) $file_id ) );
		if ( is_wp_error( $file ) ) {
			return $file;
		}
		if ( ! empty( $file['access'] ) && 0 === strpos( (string) $file['access'], 'PRIVATE' ) ) {
			$signed = $this->request( 'GET', '/files/v3/files/' . rawurlencode( (string) $file_id ) . '/signed-url' );
			if ( is_wp_error( $signed ) ) {
				return $signed;
			}
			$file = $signed;
		}
		$url = isset( $file['url'] ) ? NICA_HSE_Util::safe_url( $file['url'] ) : '';
		return '' !== $url ? $url : new WP_Error( 'nica_hse_nofile', 'HubSpot returned no download URL for file ' . $file_id );
	}
}
