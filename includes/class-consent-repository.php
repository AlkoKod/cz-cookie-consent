<?php
/**
 * Consent log repository.
 *
 * @package CZ_Cookie_Consent
 */

defined( 'ABSPATH' ) || exit;

/**
 * All reads/writes of the consent table go through this class.
 */
class CZCC_Consent_Repository {

	/**
	 * Valid consent sources.
	 *
	 * @return string[]
	 */
	public static function sources() {
		return array( 'accept_all', 'reject_all', 'custom_save', 'update' );
	}

	/**
	 * Hashes a value with the network salt (never store raw IP/UA).
	 *
	 * @param string $value Raw value.
	 * @return string sha256 hex digest.
	 */
	public static function hash( $value ) {
		return hash( 'sha256', (string) $value . CZCC_DB::ip_salt() );
	}

	/**
	 * Client IP (best effort, proxy aware only via filter).
	 *
	 * @return string
	 */
	public static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		/**
		 * Filters the client IP used for hashing (e.g. behind a trusted proxy
		 * read X-Forwarded-For here — only do that if the proxy is trusted).
		 *
		 * @param string $ip Detected IP address.
		 */
		return (string) apply_filters( 'czcc_client_ip', $ip );
	}

	/**
	 * Inserts one consent record.
	 *
	 * @param array $data {
	 *     Consent data.
	 *
	 *     @type string $uuid       Consent UUID (from the consent cookie).
	 *     @type array  $categories Accepted categories.
	 *     @type array  $services   Accepted services per category.
	 *     @type array  $gcm        Google Consent Mode signal => granted|denied.
	 *     @type string $language   Two-letter language.
	 *     @type string $revision   Config version.
	 *     @type string $source     accept_all|reject_all|custom_save|update.
	 * }
	 * @return int|false Insert ID or false.
	 */
	public static function insert( array $data ) {
		global $wpdb;

		$settings = CZCC_Settings::get();
		$days     = (int) $settings['consent_duration_days'];
		$now      = current_time( 'mysql', true );
		$expires  = gmdate( 'Y-m-d H:i:s', time() + $days * DAY_IN_SECONDS );

		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 500 ) : '';
		$ip = self::client_ip();

		$gcm = isset( $data['gcm'] ) && is_array( $data['gcm'] ) ? $data['gcm'] : array();

		$row = array(
			'blog_id'                     => get_current_blog_id(),
			'consent_uuid'                => substr( (string) $data['uuid'], 0, 64 ),
			'user_id'                     => get_current_user_id() ? get_current_user_id() : null,
			'ip_hash'                     => $ip ? self::hash( $ip ) : null,
			'ua_hash'                     => $ua ? self::hash( $ua ) : null,
			'categories'                  => wp_json_encode( array_values( (array) $data['categories'] ) ),
			'services'                    => wp_json_encode( (array) $data['services'] ),
			'gcm_ad_storage'              => self::gcm_value( $gcm, 'ad_storage' ),
			'gcm_analytics_storage'       => self::gcm_value( $gcm, 'analytics_storage' ),
			'gcm_ad_user_data'            => self::gcm_value( $gcm, 'ad_user_data' ),
			'gcm_ad_personalization'      => self::gcm_value( $gcm, 'ad_personalization' ),
			'gcm_functionality_storage'   => self::gcm_value( $gcm, 'functionality_storage' ),
			'gcm_personalization_storage' => self::gcm_value( $gcm, 'personalization_storage' ),
			'gcm_security_storage'        => self::gcm_value( $gcm, 'security_storage', 'granted' ),
			'language'                    => substr( (string) $data['language'], 0, 10 ),
			'config_version'              => substr( (string) $data['revision'], 0, 20 ),
			'source'                      => in_array( $data['source'], self::sources(), true ) ? $data['source'] : 'custom_save',
			'created_at'                  => $now,
			'expires_at'                  => $expires,
		);

		$result = $wpdb->insert( CZCC_DB::table_name(), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Normalizes one consent mode signal value.
	 *
	 * @param array  $gcm      Signal map.
	 * @param string $signal   Signal name.
	 * @param string $fallback Default value.
	 * @return string granted|denied
	 */
	private static function gcm_value( array $gcm, $signal, $fallback = 'denied' ) {
		if ( isset( $gcm[ $signal ] ) && in_array( $gcm[ $signal ], array( 'granted', 'denied' ), true ) ) {
			return $gcm[ $signal ];
		}
		return $fallback;
	}

	/**
	 * Queries consent records.
	 *
	 * @param array $args {
	 *     @type int|null $blog_id  Blog scope; null = all blogs (network admin).
	 *     @type string   $search   consent_uuid search.
	 *     @type string   $source   Source filter.
	 *     @type int      $per_page Rows per page.
	 *     @type int      $paged    Page number (1-based).
	 * }
	 * @return array{rows: array, total: int}
	 */
	public static function query( array $args ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'blog_id'  => get_current_blog_id(),
				'search'   => '',
				'source'   => '',
				'per_page' => 50,
				'paged'    => 1,
			)
		);

		$table  = CZCC_DB::table_name();
		$where  = array( '1=1' );
		$params = array();

		if ( null !== $args['blog_id'] ) {
			$where[]  = 'blog_id = %d';
			$params[] = (int) $args['blog_id'];
		}
		if ( '' !== $args['search'] ) {
			$where[]  = 'consent_uuid LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}
		if ( '' !== $args['source'] && in_array( $args['source'], self::sources(), true ) ) {
			$where[]  = 'source = %s';
			$params[] = $args['source'];
		}

		$where_sql = implode( ' AND ', $where );
		$per_page  = max( 1, min( 500, (int) $args['per_page'] ) );
		$offset    = ( max( 1, (int) $args['paged'] ) - 1 ) * $per_page;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$total_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$total     = (int) $wpdb->get_var( $params ? $wpdb->prepare( $total_sql, $params ) : $total_sql );

		$rows_sql    = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
		$rows_params = array_merge( $params, array( $per_page, $offset ) );
		$rows        = $wpdb->get_results( $wpdb->prepare( $rows_sql, $rows_params ), ARRAY_A );
		// phpcs:enable

		return array(
			'rows'  => is_array( $rows ) ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * Deletes expired records (optionally only those expired > $grace_days ago).
	 *
	 * @param int|null $blog_id    Blog scope; null = all blogs.
	 * @param int      $grace_days Days after expiry to keep records.
	 * @return int Deleted rows.
	 */
	public static function purge_expired( $blog_id = null, $grace_days = 0 ) {
		global $wpdb;

		$table  = CZCC_DB::table_name();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - max( 0, (int) $grace_days ) * DAY_IN_SECONDS );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		if ( null === $blog_id ) {
			$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE expires_at < %s", $cutoff ) );
		} else {
			$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE blog_id = %d AND expires_at < %s", (int) $blog_id, $cutoff ) );
		}
		// phpcs:enable

		return (int) $deleted;
	}

	/**
	 * Deletes all records for a blog (or all blogs when null).
	 *
	 * @param int|null $blog_id Blog scope.
	 * @return int Deleted rows.
	 */
	public static function delete_all( $blog_id = null ) {
		global $wpdb;

		$table = CZCC_DB::table_name();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		if ( null === $blog_id ) {
			return (int) $wpdb->query( "DELETE FROM {$table}" );
		}
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE blog_id = %d", (int) $blog_id ) );
		// phpcs:enable
	}

	/**
	 * Streams a CSV export of the consent log.
	 *
	 * @param int|null $blog_id Blog scope; null = all blogs.
	 */
	public static function export_csv( $blog_id ) {
		global $wpdb;

		$table = CZCC_DB::table_name();

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=consent-log-' . gmdate( 'Ymd-His' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		// UTF-8 BOM so Excel opens the file correctly.
		fwrite( $out, "\xEF\xBB\xBF" );

		$headers = array(
			'id', 'blog_id', 'consent_uuid', 'user_id', 'ip_hash', 'ua_hash', 'categories', 'services',
			'ad_storage', 'analytics_storage', 'ad_user_data', 'ad_personalization',
			'functionality_storage', 'personalization_storage', 'security_storage',
			'language', 'config_version', 'source', 'created_at', 'expires_at',
		);
		fputcsv( $out, $headers );

		$last_id = 0;
		do {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
			if ( null === $blog_id ) {
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE id > %d ORDER BY id ASC LIMIT 1000", $last_id ), ARRAY_A );
			} else {
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE blog_id = %d AND id > %d ORDER BY id ASC LIMIT 1000", (int) $blog_id, $last_id ), ARRAY_A );
			}
			// phpcs:enable

			foreach ( (array) $rows as $row ) {
				$last_id = (int) $row['id'];
				fputcsv(
					$out,
					array(
						$row['id'],
						$row['blog_id'],
						$row['consent_uuid'],
						$row['user_id'],
						$row['ip_hash'],
						$row['ua_hash'],
						$row['categories'],
						$row['services'],
						$row['gcm_ad_storage'],
						$row['gcm_analytics_storage'],
						$row['gcm_ad_user_data'],
						$row['gcm_ad_personalization'],
						$row['gcm_functionality_storage'],
						$row['gcm_personalization_storage'],
						$row['gcm_security_storage'],
						$row['language'],
						$row['config_version'],
						$row['source'],
						$row['created_at'],
						$row['expires_at'],
					)
				);
			}
		} while ( ! empty( $rows ) );

		fclose( $out );
	}

	/**
	 * Aggregate stats for the admin overview.
	 *
	 * @param int|null $blog_id Blog scope; null = all blogs.
	 * @return array{total:int, by_source:array<string,int>, recent30:int}
	 */
	public static function stats( $blog_id = null ) {
		global $wpdb;

		$table = CZCC_DB::table_name();
		$where = ( null === $blog_id ) ? '1=1' : $wpdb->prepare( 'blog_id = %d', (int) $blog_id );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- $where is prepared above.
		$rows = $wpdb->get_results( "SELECT source, COUNT(*) AS cnt FROM {$table} WHERE {$where} GROUP BY source", ARRAY_A );

		$by_source = array();
		$total     = 0;
		foreach ( (array) $rows as $row ) {
			$by_source[ $row['source'] ] = (int) $row['cnt'];
			$total                      += (int) $row['cnt'];
		}

		$since    = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );
		$recent30 = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where} AND created_at >= %s", $since ) );
		// phpcs:enable

		return array(
			'total'     => $total,
			'by_source' => $by_source,
			'recent30'  => $recent30,
		);
	}

	/**
	 * Total row count for a blog.
	 *
	 * @param int|null $blog_id Blog scope; null = all.
	 * @return int
	 */
	public static function count( $blog_id = null ) {
		global $wpdb;
		$table = CZCC_DB::table_name();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		if ( null === $blog_id ) {
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		}
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE blog_id = %d", (int) $blog_id ) );
		// phpcs:enable
	}
}
