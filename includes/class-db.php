<?php
/**
 * Database schema and migrations.
 *
 * The consent log lives in ONE global table (wpdb->base_prefix) with a
 * blog_id column. On multisite this keeps installs/migrations trivial
 * (one table for the whole network), keeps reporting across the network
 * possible, and consents are always scoped by blog_id in every query.
 * On single site blog_id is always 1.
 *
 * @package CZ_Cookie_Consent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Schema owner: creates and upgrades the consent table.
 */
class CZCC_DB {

	const OPTION_DB_VERSION = 'czcc_db_version';
	const OPTION_IP_SALT    = 'czcc_ip_salt';

	/**
	 * Fully-qualified consent table name (global, network-wide).
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->base_prefix . 'czcc_consents';
	}

	/**
	 * Creates/updates the schema via dbDelta().
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			blog_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
			consent_uuid VARCHAR(64) NOT NULL,
			user_id BIGINT UNSIGNED NULL DEFAULT NULL,
			ip_hash CHAR(64) NULL DEFAULT NULL,
			ua_hash CHAR(64) NULL DEFAULT NULL,
			categories TEXT NULL,
			services TEXT NULL,
			gcm_ad_storage VARCHAR(8) NOT NULL DEFAULT 'denied',
			gcm_analytics_storage VARCHAR(8) NOT NULL DEFAULT 'denied',
			gcm_ad_user_data VARCHAR(8) NOT NULL DEFAULT 'denied',
			gcm_ad_personalization VARCHAR(8) NOT NULL DEFAULT 'denied',
			gcm_functionality_storage VARCHAR(8) NOT NULL DEFAULT 'denied',
			gcm_personalization_storage VARCHAR(8) NOT NULL DEFAULT 'denied',
			gcm_security_storage VARCHAR(8) NOT NULL DEFAULT 'granted',
			language VARCHAR(10) NOT NULL DEFAULT '',
			config_version VARCHAR(20) NOT NULL DEFAULT '1',
			source VARCHAR(20) NOT NULL DEFAULT 'custom_save',
			created_at DATETIME NOT NULL,
			expires_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY blog_id (blog_id),
			KEY consent_uuid (consent_uuid),
			KEY created_at (created_at),
			KEY expires_at (expires_at)
		) {$charset};";

		dbDelta( $sql );

		update_site_option( self::OPTION_DB_VERSION, CZCC_DB_VERSION );

		// Salt used for IP/UA hashing. Generated once, shared network-wide so
		// hashes of the same visitor match across the network.
		if ( ! get_site_option( self::OPTION_IP_SALT ) ) {
			update_site_option( self::OPTION_IP_SALT, wp_generate_password( 64, true, true ) );
		}
	}

	/**
	 * Runs pending migrations (schema version bumps).
	 */
	public static function maybe_upgrade() {
		if ( (string) get_site_option( self::OPTION_DB_VERSION ) !== (string) CZCC_DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Salt for IP/UA hashing.
	 *
	 * @return string
	 */
	public static function ip_salt() {
		$salt = get_site_option( self::OPTION_IP_SALT );
		if ( ! $salt ) {
			$salt = wp_generate_password( 64, true, true );
			update_site_option( self::OPTION_IP_SALT, $salt );
		}
		return (string) $salt;
	}

	/**
	 * Drops the consent table (uninstall only).
	 */
	public static function drop() {
		global $wpdb;
		$table = self::table_name();
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		delete_site_option( self::OPTION_DB_VERSION );
		delete_site_option( self::OPTION_IP_SALT );
	}
}
