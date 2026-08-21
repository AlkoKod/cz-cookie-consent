<?php
/**
 * REST endpoint for storing consents.
 *
 * POST /wp-json/czcc/v1/consent
 *
 * The endpoint is public by design: consents of anonymous visitors must be
 * logged too, and pages are typically served from full-page cache, so a
 * classic nonce cannot be relied on for logged-out visitors (a nonce baked
 * into a cached page goes stale). Protection instead:
 *  - strict input validation against the known configuration,
 *  - rate limiting per hashed IP,
 *  - no raw personal data is ever stored,
 *  - the endpoint can only append consent log rows, nothing else.
 * For logged-in users the standard REST cookie auth + nonce applies, which
 * attributes the record to the user.
 *
 * @package CZ_Cookie_Consent
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST controller.
 */
class CZCC_REST_Controller {

	const RATE_LIMIT_MAX    = 20;
	const RATE_LIMIT_WINDOW = 600; // seconds.

	/**
	 * Registers routes.
	 */
	public static function register_routes() {
		register_rest_route(
			'czcc/v1',
			'/consent',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'save_consent' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'uuid'       => array(
						'type'              => 'string',
						'required'          => true,
						'validate_callback' => function ( $value ) {
							return is_string( $value ) && preg_match( '/^[A-Za-z0-9_-]{6,64}$/', $value );
						},
					),
					'categories' => array(
						'type'     => 'array',
						'required' => true,
					),
					'services'   => array(
						'type'     => 'object',
						'required' => false,
					),
					'gcm'        => array(
						'type'     => 'object',
						'required' => true,
					),
					'language'   => array(
						'type'              => 'string',
						'required'          => false,
						'validate_callback' => function ( $value ) {
							return is_string( $value ) && preg_match( '/^[a-z]{2}(-[a-zA-Z]{2})?$/', $value );
						},
					),
					'revision'   => array(
						'type'              => 'string',
						'required'          => false,
						'validate_callback' => function ( $value ) {
							return is_string( $value ) && preg_match( '/^[0-9]{1,10}$/', $value );
						},
					),
					'source'     => array(
						'type'              => 'string',
						'required'          => true,
						'validate_callback' => function ( $value ) {
							return in_array( $value, CZCC_Consent_Repository::sources(), true );
						},
					),
				),
			)
		);
	}

	/**
	 * Handles POST /consent.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function save_consent( WP_REST_Request $request ) {
		if ( ! self::rate_limit_ok() ) {
			return new WP_Error( 'czcc_rate_limited', __( 'Too many requests.', 'cz-cookie-consent' ), array( 'status' => 429 ) );
		}

		$settings = CZCC_Settings::get();

		// Validate categories against enabled configuration.
		$valid_categories = array_merge( array( 'necessary' ), (array) $settings['enabled_categories'] );
		$categories       = array();
		foreach ( (array) $request['categories'] as $category ) {
			if ( ! is_string( $category ) ) {
				continue;
			}
			$category = sanitize_key( $category );
			if ( in_array( $category, $valid_categories, true ) && ! in_array( $category, $categories, true ) ) {
				$categories[] = $category;
			}
		}
		if ( ! in_array( 'necessary', $categories, true ) ) {
			$categories[] = 'necessary';
		}

		// Validate services against the registry.
		$known_slugs = CZCC_Service_Registry::known_slugs( $settings );
		$services    = array();
		foreach ( (array) $request['services'] as $category => $slugs ) {
			$category = sanitize_key( (string) $category );
			if ( ! in_array( $category, $valid_categories, true ) || ! is_array( $slugs ) ) {
				continue;
			}
			foreach ( $slugs as $slug ) {
				if ( ! is_string( $slug ) ) {
					continue;
				}
				$slug = sanitize_key( $slug );
				if ( in_array( $slug, $known_slugs, true ) ) {
					$services[ $category ][] = $slug;
				}
			}
		}

		// Validate consent mode signals.
		$gcm = array();
		foreach ( (array) $request['gcm'] as $signal => $value ) {
			if ( in_array( $signal, CZCC_Service_Registry::gcm_signals(), true ) && in_array( $value, array( 'granted', 'denied' ), true ) ) {
				$gcm[ $signal ] = $value;
			}
		}
		if ( count( $gcm ) !== count( CZCC_Service_Registry::gcm_signals() ) ) {
			return new WP_Error( 'czcc_invalid_gcm', __( 'Invalid consent mode state.', 'cz-cookie-consent' ), array( 'status' => 400 ) );
		}

		$id = CZCC_Consent_Repository::insert(
			array(
				'uuid'       => (string) $request['uuid'],
				'categories' => $categories,
				'services'   => $services,
				'gcm'        => $gcm,
				'language'   => (string) $request->get_param( 'language' ),
				'revision'   => (string) $request->get_param( 'revision' ),
				'source'     => (string) $request['source'],
			)
		);

		if ( false === $id ) {
			return new WP_Error( 'czcc_db_error', __( 'Could not store consent.', 'cz-cookie-consent' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response( array( 'saved' => true ), 201 );
	}

	/**
	 * Sliding-window rate limit per hashed IP.
	 *
	 * @return bool True when the request is allowed.
	 */
	private static function rate_limit_ok() {
		$ip = CZCC_Consent_Repository::client_ip();
		if ( '' === $ip ) {
			return true;
		}

		$key   = 'czcc_rl_' . md5( $ip );
		$count = (int) get_transient( $key );

		if ( $count >= self::RATE_LIMIT_MAX ) {
			return false;
		}

		set_transient( $key, $count + 1, self::RATE_LIMIT_WINDOW );

		return true;
	}
}
