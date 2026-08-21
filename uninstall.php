<?php
/**
 * Uninstall cleanup.
 *
 * Data is only removed when the site (or, on multisite, every site) opted
 * in via the "Delete all plugin data on uninstall" setting.
 *
 * @package CZ_Cookie_Consent
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/includes/class-db.php';

/**
 * Removes per-site options and consent rows if the site opted in.
 *
 * @return bool Whether the site opted in to deletion.
 */
function czcc_uninstall_site() {
	$settings = get_option( 'czcc_settings', array() );
	$delete   = is_array( $settings ) && ! empty( $settings['delete_on_uninstall'] );

	if ( $delete ) {
		global $wpdb;
		$table = $wpdb->base_prefix . 'czcc_consents';
		// Table may already be gone.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE blog_id = %d", get_current_blog_id() ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		}
		delete_option( 'czcc_settings' );
	}

	return $delete;
}

if ( is_multisite() ) {
	$czcc_all_opted_in = true;
	$czcc_site_ids     = get_sites(
		array(
			'number' => 500,
			'fields' => 'ids',
		)
	);
	foreach ( $czcc_site_ids as $czcc_site_id ) {
		switch_to_blog( $czcc_site_id );
		if ( ! czcc_uninstall_site() ) {
			$czcc_all_opted_in = false;
		}
		restore_current_blog();
	}
	// Drop the global table + network options only when every site opted in.
	if ( $czcc_all_opted_in ) {
		CZCC_DB::drop();
		delete_site_option( 'czcc_network_settings' );
		delete_site_option( 'czcc_network_mode' );
	}
} else {
	if ( czcc_uninstall_site() ) {
		CZCC_DB::drop();
		delete_site_option( 'czcc_network_settings' );
		delete_site_option( 'czcc_network_mode' );
	}
}
