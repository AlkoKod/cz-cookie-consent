<?php
/**
 * Main plugin orchestrator.
 *
 * @package CZ_Cookie_Consent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wires everything together.
 */
class CZCC_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var CZCC_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Returns (and boots) the plugin instance.
	 *
	 * @return CZCC_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	/**
	 * Registers all hooks.
	 */
	private function boot() {
		add_action( 'init', array( 'CZCC_I18n', 'load_textdomain' ) );

		// Schema upgrades after plugin updates (activation hook does not run then).
		add_action( 'admin_init', array( 'CZCC_DB', 'maybe_upgrade' ) );

		add_action( 'rest_api_init', array( 'CZCC_REST_Controller', 'register_routes' ) );

		add_action( CZCC_Activator::CRON_HOOK, array( 'CZCC_Activator', 'daily_maintenance' ) );

		if ( is_admin() ) {
			CZCC_Admin::init();
		} else {
			CZCC_Frontend::init();
		}
	}
}
