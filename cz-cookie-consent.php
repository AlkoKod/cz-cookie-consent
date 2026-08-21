<?php
/**
 * CZ Cookie Consent
 *
 * @package           CZ_Cookie_Consent
 * @author            Innovative Business s.r.o.
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       CZ Cookie Consent
 * Plugin URI:        https://github.com/AlkoKod/cz-cookie-consent
 * Description:       Cookie consent banner s Google Consent Mode v2, logováním souhlasů, podporou multisite a kompatibilitou s GTM4WP. Postaveno na knihovnách Orestbida CookieConsent v3 a iframemanager.
 * Version:           1.3.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Innovative Business s.r.o.
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cz-cookie-consent
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'CZCC_VERSION', '1.3.0' );
define( 'CZCC_DB_VERSION', '1' );
define( 'CZCC_PLUGIN_FILE', __FILE__ );
define( 'CZCC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CZCC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Bundled library versions (assets/vendor).
define( 'CZCC_COOKIECONSENT_VERSION', '3.1.0' );
define( 'CZCC_IFRAMEMANAGER_VERSION', '1.3.0' );

require_once CZCC_PLUGIN_DIR . 'includes/class-i18n.php';
require_once CZCC_PLUGIN_DIR . 'includes/class-db.php';
require_once CZCC_PLUGIN_DIR . 'includes/class-settings.php';
require_once CZCC_PLUGIN_DIR . 'includes/class-service-registry.php';
require_once CZCC_PLUGIN_DIR . 'includes/class-consent-repository.php';
require_once CZCC_PLUGIN_DIR . 'includes/class-rest-controller.php';
require_once CZCC_PLUGIN_DIR . 'includes/class-frontend.php';
require_once CZCC_PLUGIN_DIR . 'includes/class-admin.php';
require_once CZCC_PLUGIN_DIR . 'includes/class-activator.php';
require_once CZCC_PLUGIN_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'CZCC_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CZCC_Activator', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'CZCC_Plugin', 'instance' ) );
