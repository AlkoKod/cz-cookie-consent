<?php
/**
 * Activation / deactivation.
 *
 * @package CZ_Cookie_Consent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Activation handler. Works for single site, per-site activation on
 * multisite and network-wide activation (the consent table is global,
 * so it is created exactly once either way).
 */
class CZCC_Activator {

	const CRON_HOOK = 'czcc_daily_maintenance';

	/**
	 * Plugin activation.
	 *
	 * @param bool $network_wide Whether activated network-wide.
	 */
	public static function activate( $network_wide ) {
		CZCC_DB::install();

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Plugin deactivation.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Daily maintenance: purge expired consents per blog according to each
	 * blog's auto_purge_days setting (0 = keep expired records).
	 */
	public static function daily_maintenance() {
		if ( is_multisite() ) {
			$sites = get_sites(
				array(
					'number' => 500,
					'fields' => 'ids',
				)
			);
			foreach ( $sites as $blog_id ) {
				switch_to_blog( $blog_id );
				CZCC_Settings::flush_cache();
				self::purge_current_blog();
				restore_current_blog();
			}
			CZCC_Settings::flush_cache();
		} else {
			self::purge_current_blog();
		}
	}

	/**
	 * Purges expired consents for the current blog if enabled.
	 */
	private static function purge_current_blog() {
		$settings = CZCC_Settings::get();
		$days     = (int) $settings['auto_purge_days'];
		if ( $days > 0 ) {
			CZCC_Consent_Repository::purge_expired( get_current_blog_id(), $days );
		}
	}
}
