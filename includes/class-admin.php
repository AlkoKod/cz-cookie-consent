<?php
/**
 * Admin UI: settings tabs, consent log, CSV export, tools.
 *
 * @package CZ_Cookie_Consent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin controller.
 */
class CZCC_Admin {

	/**
	 * Registers hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'network_admin_menu', array( __CLASS__, 'register_network_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

		add_action( 'admin_post_czcc_save_settings', array( __CLASS__, 'handle_save_settings' ) );
		add_action( 'admin_post_czcc_save_network_settings', array( __CLASS__, 'handle_save_network_settings' ) );
		add_action( 'admin_post_czcc_reset_site_settings', array( __CLASS__, 'handle_reset_site_settings' ) );
		add_action( 'admin_post_czcc_export_csv', array( __CLASS__, 'handle_export_csv' ) );
		add_action( 'admin_post_czcc_purge_expired', array( __CLASS__, 'handle_purge_expired' ) );
		add_action( 'admin_post_czcc_delete_all', array( __CLASS__, 'handle_delete_all' ) );
	}

	/**
	 * Site admin menu.
	 */
	public static function register_menu() {
		add_options_page(
			__( 'Cookie Consent', 'cz-cookie-consent' ),
			__( 'Cookie Consent', 'cz-cookie-consent' ),
			'manage_options',
			'czcc-settings',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Network admin menu (network settings + network-wide consent log).
	 */
	public static function register_network_menu() {
		add_submenu_page(
			'settings.php',
			__( 'Cookie Consent', 'cz-cookie-consent' ),
			__( 'Cookie Consent', 'cz-cookie-consent' ),
			'manage_network_options',
			'czcc-network-settings',
			array( __CLASS__, 'render_network_settings_page' )
		);
		add_submenu_page(
			'settings.php',
			__( 'Cookie Consent Log', 'cz-cookie-consent' ),
			__( 'Cookie Consent Log', 'cz-cookie-consent' ),
			'manage_network_options',
			'czcc-network-log',
			array( __CLASS__, 'render_network_log' )
		);
	}

	/**
	 * Admin assets on our screens only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_assets( $hook ) {
		if ( false === strpos( (string) $hook, 'czcc' ) ) {
			return;
		}
		wp_enqueue_style( 'czcc-admin', CZCC_PLUGIN_URL . 'assets/css/admin.css', array(), CZCC_VERSION );
		wp_enqueue_script( 'czcc-admin', CZCC_PLUGIN_URL . 'assets/js/admin.js', array(), CZCC_VERSION, true );
	}

	/* ------------------------------------------------------------------ *
	 * admin-post handlers
	 * ------------------------------------------------------------------ */

	/**
	 * Saves settings (admin-post).
	 */
	public static function handle_save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'cz-cookie-consent' ) );
		}
		if ( 'enforce' === CZCC_Settings::network_mode() ) {
			wp_die( esc_html__( 'Cookie consent settings are enforced network-wide and cannot be changed per site.', 'cz-cookie-consent' ) );
		}
		check_admin_referer( 'czcc_save_settings' );

		$input = isset( $_POST['czcc'] ) && is_array( $_POST['czcc'] ) ? wp_unslash( $_POST['czcc'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized field-by-field in CZCC_Settings::sanitize().

		CZCC_Settings::update( CZCC_Settings::sanitize( $input ) );

		$tab = isset( $_POST['czcc_tab'] ) ? sanitize_key( wp_unslash( $_POST['czcc_tab'] ) ) : 'general';
		wp_safe_redirect( add_query_arg( array( 'page' => 'czcc-settings', 'tab' => $tab, 'updated' => '1' ), admin_url( 'options-general.php' ) ) );
		exit;
	}

	/**
	 * Saves network settings + mode (admin-post).
	 */
	public static function handle_save_network_settings() {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'cz-cookie-consent' ) );
		}
		check_admin_referer( 'czcc_save_network_settings' );

		$input = isset( $_POST['czcc'] ) && is_array( $_POST['czcc'] ) ? wp_unslash( $_POST['czcc'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized field-by-field in CZCC_Settings::sanitize().

		CZCC_Settings::update_network( CZCC_Settings::sanitize( $input, CZCC_Settings::network_settings() ) );

		$mode = isset( $_POST['czcc_network_mode'] ) ? sanitize_key( wp_unslash( $_POST['czcc_network_mode'] ) ) : 'off';
		if ( ! in_array( $mode, array( 'off', 'defaults', 'enforce' ), true ) ) {
			$mode = 'off';
		}
		CZCC_Settings::update_network_mode( $mode );

		$tab = isset( $_POST['czcc_tab'] ) ? sanitize_key( wp_unslash( $_POST['czcc_tab'] ) ) : 'general';
		wp_safe_redirect( add_query_arg( array( 'page' => 'czcc-network-settings', 'tab' => $tab, 'updated' => '1' ), network_admin_url( 'settings.php' ) ) );
		exit;
	}

	/**
	 * Deletes the per-site override so the site inherits network defaults
	 * again (admin-post).
	 */
	public static function handle_reset_site_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'cz-cookie-consent' ) );
		}
		check_admin_referer( 'czcc_reset_site_settings' );

		CZCC_Settings::delete_site_settings();

		wp_safe_redirect( add_query_arg( array( 'page' => 'czcc-settings', 'tab' => 'general', 'czcc_reset' => '1' ), admin_url( 'options-general.php' ) ) );
		exit;
	}

	/**
	 * CSV export (admin-post).
	 */
	public static function handle_export_csv() {
		$network = ! empty( $_GET['network'] );

		if ( $network ) {
			if ( ! current_user_can( 'manage_network_options' ) ) {
				wp_die( esc_html__( 'Insufficient permissions.', 'cz-cookie-consent' ) );
			}
		} elseif ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'cz-cookie-consent' ) );
		}
		check_admin_referer( 'czcc_export_csv' );

		CZCC_Consent_Repository::export_csv( $network ? null : get_current_blog_id() );
		exit;
	}

	/**
	 * Purge expired consents (admin-post).
	 */
	public static function handle_purge_expired() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'cz-cookie-consent' ) );
		}
		check_admin_referer( 'czcc_purge_expired' );

		$deleted = CZCC_Consent_Repository::purge_expired( get_current_blog_id(), 0 );

		wp_safe_redirect( add_query_arg( array( 'page' => 'czcc-settings', 'tab' => 'log', 'purged' => (int) $deleted ), admin_url( 'options-general.php' ) ) );
		exit;
	}

	/**
	 * Delete the whole consent log of this site (admin-post).
	 */
	public static function handle_delete_all() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'cz-cookie-consent' ) );
		}
		check_admin_referer( 'czcc_delete_all' );

		$deleted = CZCC_Consent_Repository::delete_all( get_current_blog_id() );

		wp_safe_redirect( add_query_arg( array( 'page' => 'czcc-settings', 'tab' => 'log', 'purged' => (int) $deleted ), admin_url( 'options-general.php' ) ) );
		exit;
	}

	/* ------------------------------------------------------------------ *
	 * Shared UI helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Page header with title, version and quick links.
	 *
	 * @param string $subtitle Optional subtitle (e.g. "network settings").
	 */
	private static function render_header( $subtitle = '' ) {
		?>
		<div class="czcc-header">
			<div class="czcc-header-title">
				<span class="czcc-logo" aria-hidden="true">🍪</span>
				<h1>
					<?php esc_html_e( 'CZ Cookie Consent', 'cz-cookie-consent' ); ?>
					<?php if ( $subtitle ) : ?>
						<span class="czcc-subtitle"><?php echo esc_html( $subtitle ); ?></span>
					<?php endif; ?>
				</h1>
				<span class="czcc-version">v<?php echo esc_html( CZCC_VERSION ); ?></span>
			</div>
			<div class="czcc-header-links">
				<a href="https://github.com/AlkoKod/cz-cookie-consent" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'GitHub', 'cz-cookie-consent' ); ?></a>
				<a href="https://github.com/AlkoKod/cz-cookie-consent/blob/main/docs/gtm-setup.md" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'GTM guide', 'cz-cookie-consent' ); ?></a>
			</div>
		</div>
		<?php
	}

	/**
	 * Toggle switch bound to a checkbox input.
	 *
	 * @param string $name     Input name attribute.
	 * @param bool   $checked  Current state.
	 * @param string $label    Visible label (plain text).
	 * @param bool   $disabled Disable the input.
	 * @param string $value    Submitted value (default "1").
	 * @return string
	 */
	private static function toggle( $name, $checked, $label = '', $disabled = false, $value = '1' ) {
		$html  = '<label class="czcc-toggle">';
		$html .= sprintf(
			'<input type="checkbox" name="%s" value="%s"%s%s>',
			esc_attr( $name ),
			esc_attr( $value ),
			checked( (bool) $checked, true, false ),
			disabled( (bool) $disabled, true, false )
		);
		$html .= '<span class="czcc-toggle-slider" aria-hidden="true"></span>';
		if ( '' !== $label ) {
			$html .= '<span class="czcc-toggle-label">' . esc_html( $label ) . '</span>';
		}
		$html .= '</label>';
		return $html;
	}

	/**
	 * Opens a settings card.
	 *
	 * @param string $title Card heading.
	 * @param string $desc  Optional short description.
	 */
	private static function card_open( $title, $desc = '' ) {
		echo '<div class="czcc-card">';
		echo '<h2>' . esc_html( $title ) . '</h2>';
		if ( '' !== $desc ) {
			echo '<p class="czcc-card-desc">' . esc_html( $desc ) . '</p>';
		}
	}

	/**
	 * Closes a settings card.
	 */
	private static function card_close() {
		echo '</div>';
	}

	/* ------------------------------------------------------------------ *
	 * Site settings page
	 * ------------------------------------------------------------------ */

	/**
	 * Settings page router.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$mode = CZCC_Settings::network_mode();

		$tabs = array(
			'general'  => __( 'General', 'cz-cookie-consent' ),
			'services' => __( 'Categories & services', 'cz-cookie-consent' ),
			'texts'    => __( 'Texts', 'cz-cookie-consent' ),
			'iframes'  => __( 'Iframe blocking', 'cz-cookie-consent' ),
			'log'      => __( 'Consent log', 'cz-cookie-consent' ),
			'tools'    => __( 'Tools & debug', 'cz-cookie-consent' ),
		);
		$default_tab = 'general';

		// Enforced network configuration: only data/status tabs remain.
		if ( 'enforce' === $mode ) {
			unset( $tabs['general'], $tabs['services'], $tabs['texts'], $tabs['iframes'] );
			$default_tab = 'log';
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : $default_tab; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = $default_tab;
		}

		echo '<div class="wrap czcc-wrap">';
		self::render_header();

		if ( ! empty( $_GET['updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'cz-cookie-consent' ) . '</p></div>';
		}
		if ( ! empty( $_GET['czcc_reset'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Site configuration removed. This site now inherits the network defaults.', 'cz-cookie-consent' ) . '</p></div>';
		}
		if ( isset( $_GET['purged'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			/* translators: %d: number of deleted records. */
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( __( '%d consent records deleted.', 'cz-cookie-consent' ), absint( wp_unslash( $_GET['purged'] ) ) ) ) . '</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		if ( 'enforce' === $mode ) {
			echo '<div class="notice notice-info"><p>' . esc_html__( 'Cookie consent settings are enforced network-wide by the network administrator. Only the consent log and tools are available here.', 'cz-cookie-consent' ) . '</p></div>';
		}

		echo '<nav class="nav-tab-wrapper czcc-tabs">';
		foreach ( $tabs as $slug => $label ) {
			printf(
				'<a href="%s" class="nav-tab%s">%s</a>',
				esc_url( add_query_arg( array( 'page' => 'czcc-settings', 'tab' => $slug ), admin_url( 'options-general.php' ) ) ),
				$tab === $slug ? ' nav-tab-active' : '',
				esc_html( $label )
			);
		}
		echo '</nav>';

		echo '<div class="czcc-body">';
		if ( 'log' === $tab ) {
			self::render_log_tab();
		} elseif ( 'tools' === $tab ) {
			self::render_tools_tab();
		} else {
			self::render_settings_form( $tab );
		}
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Renders one settings form tab.
	 *
	 * @param string $tab Tab slug.
	 */
	private static function render_settings_form( $tab ) {
		$settings = CZCC_Settings::get();

		if ( 'defaults' === CZCC_Settings::network_mode() ) {
			if ( CZCC_Settings::site_has_override() ) {
				$reset_url = wp_nonce_url( admin_url( 'admin-post.php?action=czcc_reset_site_settings' ), 'czcc_reset_site_settings' );
				echo '<div class="notice notice-info inline"><p>';
				esc_html_e( 'This site uses its own configuration and ignores the network defaults.', 'cz-cookie-consent' );
				echo ' <a href="' . esc_url( $reset_url ) . '" class="czcc-confirm" data-confirm="' . esc_attr__( 'Discard this site\'s configuration and inherit the network defaults?', 'cz-cookie-consent' ) . '">' . esc_html__( 'Reset to network defaults', 'cz-cookie-consent' ) . '</a>';
				echo '</p></div>';
			} else {
				echo '<div class="notice notice-info inline"><p>' . esc_html__( 'This site inherits the network defaults. Saving this form creates a site-specific configuration that overrides them.', 'cz-cookie-consent' ) . '</p></div>';
			}
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="czcc_save_settings">';
		echo '<input type="hidden" name="czcc_tab" value="' . esc_attr( $tab ) . '">';
		wp_nonce_field( 'czcc_save_settings' );

		// Re-submit all persisted structures so a save from one tab does not
		// wipe the others: every tab renders only its own fields, therefore
		// hidden state carries the rest.
		self::render_hidden_state( $settings, $tab );

		if ( 'general' === $tab ) {
			self::render_general_tab( $settings );
		} elseif ( 'services' === $tab ) {
			self::render_services_tab( $settings );
		} elseif ( 'texts' === $tab ) {
			self::render_texts_tab( $settings );
		} elseif ( 'iframes' === $tab ) {
			self::render_iframes_tab( $settings );
		}

		submit_button( __( 'Save settings', 'cz-cookie-consent' ) );
		echo '</form>';
	}

	/**
	 * Hidden inputs carrying the state of the tabs not being edited.
	 *
	 * @param array  $settings Current settings.
	 * @param string $tab      Active tab.
	 */
	private static function render_hidden_state( array $settings, $tab ) {
		$general_keys = array( 'consent_duration_days', 'config_version', 'banner_layout', 'banner_position', 'preferences_layout', 'wait_for_update', 'functionality_default', 'auto_purge_days' );
		$general_flags = array( 'show_reject_button', 'equal_weight_buttons', 'flip_buttons', 'disable_page_interaction', 'hide_from_bots', 'url_passthrough', 'ads_data_redaction', 'gtm4wp_suppress_default', 'debug', 'delete_on_uninstall' );

		if ( 'general' !== $tab ) {
			foreach ( $general_keys as $key ) {
				printf( '<input type="hidden" name="czcc[%s]" value="%s">', esc_attr( $key ), esc_attr( (string) $settings[ $key ] ) );
			}
			foreach ( $general_flags as $key ) {
				if ( ! empty( $settings[ $key ] ) ) {
					printf( '<input type="hidden" name="czcc[%s]" value="1">', esc_attr( $key ) );
				}
			}
		}

		if ( 'services' !== $tab ) {
			foreach ( (array) $settings['enabled_categories'] as $category ) {
				printf( '<input type="hidden" name="czcc[enabled_categories][]" value="%s">', esc_attr( $category ) );
			}
			foreach ( (array) $settings['service_overrides'] as $slug => $override ) {
				if ( ! empty( $override['enabled'] ) ) {
					printf( '<input type="hidden" name="czcc[service_overrides][%s][enabled]" value="1">', esc_attr( $slug ) );
				}
				if ( ! empty( $override['category'] ) ) {
					printf( '<input type="hidden" name="czcc[service_overrides][%1$s][category]" value="%2$s">', esc_attr( $slug ), esc_attr( $override['category'] ) );
				}
			}
			printf( '<input type="hidden" name="czcc[custom_services_json]" value="%s">', esc_attr( $settings['custom_services'] ? wp_json_encode( $settings['custom_services'] ) : '' ) );
		}

		if ( 'iframes' !== $tab ) {
			foreach ( (array) $settings['iframe_rules'] as $slug => $rule ) {
				if ( ! empty( $rule['enabled'] ) ) {
					printf( '<input type="hidden" name="czcc[iframe_rules][%s][enabled]" value="1">', esc_attr( $slug ) );
				}
				printf( '<input type="hidden" name="czcc[iframe_rules][%1$s][category]" value="%2$s">', esc_attr( $slug ), esc_attr( $rule['category'] ) );
			}
			if ( ! empty( $settings['auto_wrap_iframes'] ) ) {
				echo '<input type="hidden" name="czcc[auto_wrap_iframes]" value="1">';
			}
			if ( ! empty( $settings['load_thumbnails'] ) ) {
				echo '<input type="hidden" name="czcc[load_thumbnails]" value="1">';
			}
		}

		if ( 'texts' !== $tab ) {
			foreach ( (array) $settings['texts'] as $lang => $lang_texts ) {
				foreach ( (array) $lang_texts as $key => $value ) {
					printf( '<input type="hidden" name="czcc[texts][%1$s][%2$s]" value="%3$s">', esc_attr( $lang ), esc_attr( $key ), esc_attr( $value ) );
				}
			}
			foreach ( (array) $settings['privacy_policy_url'] as $lang => $url ) {
				printf( '<input type="hidden" name="czcc[privacy_policy_url][%1$s]" value="%2$s">', esc_attr( $lang ), esc_attr( $url ) );
			}
		}
	}

	/**
	 * General tab.
	 *
	 * @param array $settings Settings.
	 */
	private static function render_general_tab( array $settings ) {
		?>
		<div class="czcc-cards">

			<div class="czcc-card">
				<h2><?php esc_html_e( 'Consent', 'cz-cookie-consent' ); ?></h2>
				<div class="czcc-field">
					<label for="czcc-duration"><?php esc_html_e( 'Consent validity (days)', 'cz-cookie-consent' ); ?></label>
					<input type="number" min="1" max="730" id="czcc-duration" name="czcc[consent_duration_days]" value="<?php echo esc_attr( (string) $settings['consent_duration_days'] ); ?>">
					<p class="description"><?php esc_html_e( '182 = 6 months, 365 = 12 months. After expiry the banner is shown again.', 'cz-cookie-consent' ); ?></p>
				</div>
				<div class="czcc-field">
					<label for="czcc-revision"><?php esc_html_e( 'Configuration version (revision)', 'cz-cookie-consent' ); ?></label>
					<input type="number" min="1" id="czcc-revision" name="czcc[config_version]" value="<?php echo esc_attr( (string) $settings['config_version'] ); ?>">
					<p class="description"><?php esc_html_e( 'Increase after changing categories/services or texts in a way that requires new consent. The banner is shown again to everyone.', 'cz-cookie-consent' ); ?></p>
				</div>
			</div>

			<div class="czcc-card">
				<h2><?php esc_html_e( 'Banner appearance', 'cz-cookie-consent' ); ?></h2>
				<div class="czcc-field-row">
					<div class="czcc-field">
						<label for="czcc-layout"><?php esc_html_e( 'Banner layout', 'cz-cookie-consent' ); ?></label>
						<select id="czcc-layout" name="czcc[banner_layout]">
							<?php foreach ( array( 'box', 'box inline', 'box wide', 'cloud', 'cloud inline', 'bar', 'bar inline' ) as $layout ) : ?>
								<option value="<?php echo esc_attr( $layout ); ?>" <?php selected( $settings['banner_layout'], $layout ); ?>><?php echo esc_html( $layout ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="czcc-field">
						<label for="czcc-position"><?php esc_html_e( 'Banner position', 'cz-cookie-consent' ); ?></label>
						<select id="czcc-position" name="czcc[banner_position]">
							<?php foreach ( array( 'bottom left', 'bottom center', 'bottom right', 'middle left', 'middle center', 'middle right', 'top left', 'top center', 'top right' ) as $position ) : ?>
								<option value="<?php echo esc_attr( $position ); ?>" <?php selected( $settings['banner_position'], $position ); ?>><?php echo esc_html( $position ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="czcc-field">
						<label for="czcc-pref-layout"><?php esc_html_e( 'Preferences layout', 'cz-cookie-consent' ); ?></label>
						<select id="czcc-pref-layout" name="czcc[preferences_layout]">
							<?php foreach ( array( 'box', 'bar', 'bar wide' ) as $layout ) : ?>
								<option value="<?php echo esc_attr( $layout ); ?>" <?php selected( $settings['preferences_layout'], $layout ); ?>><?php echo esc_html( $layout ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
				<div class="czcc-toggle-group">
					<?php
					echo self::toggle( 'czcc[show_reject_button]', $settings['show_reject_button'], __( 'Show "Reject optional" button', 'cz-cookie-consent' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built and escaped in toggle().
					echo self::toggle( 'czcc[equal_weight_buttons]', $settings['equal_weight_buttons'], __( 'Equal weight accept/reject buttons', 'cz-cookie-consent' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo self::toggle( 'czcc[flip_buttons]', $settings['flip_buttons'], __( 'Flip button order', 'cz-cookie-consent' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo self::toggle( 'czcc[disable_page_interaction]', $settings['disable_page_interaction'], __( 'Block page interaction until a choice is made', 'cz-cookie-consent' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo self::toggle( 'czcc[hide_from_bots]', $settings['hide_from_bots'], __( 'Hide banner from bots/crawlers', 'cz-cookie-consent' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				</div>
			</div>

			<div class="czcc-card">
				<h2><?php esc_html_e( 'Google Consent Mode', 'cz-cookie-consent' ); ?></h2>
				<div class="czcc-field-row">
					<div class="czcc-field">
						<label for="czcc-func-default"><?php esc_html_e( 'functionality_storage default', 'cz-cookie-consent' ); ?></label>
						<select id="czcc-func-default" name="czcc[functionality_default]">
							<option value="denied" <?php selected( $settings['functionality_default'], 'denied' ); ?>>denied</option>
							<option value="granted" <?php selected( $settings['functionality_default'], 'granted' ); ?>>granted</option>
						</select>
					</div>
					<div class="czcc-field">
						<label for="czcc-wait"><?php esc_html_e( 'wait_for_update (ms)', 'cz-cookie-consent' ); ?></label>
						<input type="number" min="0" max="10000" id="czcc-wait" name="czcc[wait_for_update]" value="<?php echo esc_attr( (string) $settings['wait_for_update'] ); ?>">
					</div>
				</div>
				<p class="description"><?php esc_html_e( 'Use "granted" only if you treat functional storage as strictly necessary. All other signals default to denied (security_storage is always granted).', 'cz-cookie-consent' ); ?></p>
				<div class="czcc-toggle-group">
					<?php
					echo self::toggle( 'czcc[url_passthrough]', $settings['url_passthrough'], __( 'Enable url_passthrough', 'cz-cookie-consent' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo self::toggle( 'czcc[ads_data_redaction]', $settings['ads_data_redaction'], __( 'Enable ads_data_redaction', 'cz-cookie-consent' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo self::toggle( 'czcc[gtm4wp_suppress_default]', $settings['gtm4wp_suppress_default'], __( 'GTM4WP compatibility: suppress/align its own Consent Mode default block', 'cz-cookie-consent' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				</div>
			</div>

			<div class="czcc-card">
				<h2><?php esc_html_e( 'Data management', 'cz-cookie-consent' ); ?></h2>
				<div class="czcc-field">
					<label for="czcc-purge"><?php esc_html_e( 'Auto-purge expired consents after (days, 0 = never)', 'cz-cookie-consent' ); ?></label>
					<input type="number" min="0" max="3650" id="czcc-purge" name="czcc[auto_purge_days]" value="<?php echo esc_attr( (string) $settings['auto_purge_days'] ); ?>">
				</div>
				<div class="czcc-toggle-group">
					<?php
					echo self::toggle( 'czcc[delete_on_uninstall]', $settings['delete_on_uninstall'], __( 'Delete all plugin data on uninstall', 'cz-cookie-consent' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo self::toggle( 'czcc[debug]', $settings['debug'], __( 'Debug mode (console logging)', 'cz-cookie-consent' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				</div>
			</div>

		</div>
		<?php
	}

	/**
	 * Categories & services tab.
	 *
	 * @param array $settings Settings.
	 */
	private static function render_services_tab( array $settings ) {
		$categories = CZCC_Service_Registry::categories();
		$services   = CZCC_Service_Registry::effective( $settings );

		self::card_open( __( 'Enabled categories', 'cz-cookie-consent' ), __( 'The "necessary" category is always enabled and cannot be turned off by visitors.', 'cz-cookie-consent' ) );
		?>
		<div class="czcc-toggle-group czcc-toggle-group-inline">
			<?php foreach ( array( 'functional', 'preferences', 'analytics', 'marketing' ) as $category ) : ?>
				<?php echo self::toggle( 'czcc[enabled_categories][]', in_array( $category, (array) $settings['enabled_categories'], true ), $category, false, $category ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endforeach; ?>
		</div>
		<?php
		self::card_close();

		self::card_open( __( 'Services', 'cz-cookie-consent' ), __( 'Enable the services actually used on this website and check their category. Disabled services are not offered in the banner.', 'cz-cookie-consent' ) );
		?>
		<table class="widefat striped czcc-services-table">
			<thead>
				<tr>
					<th class="czcc-col-toggle"><?php esc_html_e( 'Enabled', 'cz-cookie-consent' ); ?></th>
					<th><?php esc_html_e( 'Service', 'cz-cookie-consent' ); ?></th>
					<th><?php esc_html_e( 'Provider', 'cz-cookie-consent' ); ?></th>
					<th><?php esc_html_e( 'Category', 'cz-cookie-consent' ); ?></th>
					<th><?php esc_html_e( 'Cookies', 'cz-cookie-consent' ); ?></th>
					<th><?php esc_html_e( 'Type', 'cz-cookie-consent' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $services as $slug => $service ) : ?>
					<tr>
						<td class="czcc-col-toggle">
							<?php echo self::toggle( 'czcc[service_overrides][' . $slug . '][enabled]', ! empty( $service['enabled'] ), '', ! empty( $service['required'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php if ( ! empty( $service['required'] ) ) : ?>
								<input type="hidden" name="czcc[service_overrides][<?php echo esc_attr( $slug ); ?>][enabled]" value="1">
							<?php endif; ?>
						</td>
						<td><strong><?php echo esc_html( $service['name'] ); ?></strong><br><code><?php echo esc_html( $slug ); ?></code></td>
						<td><span class="czcc-provider"><?php echo esc_html( $service['provider'] ); ?></span></td>
						<td>
							<select name="czcc[service_overrides][<?php echo esc_attr( $slug ); ?>][category]">
								<?php foreach ( $categories as $category ) : ?>
									<option value="<?php echo esc_attr( $category ); ?>" <?php selected( $service['category'], $category ); ?>><?php echo esc_html( $category ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
						<td class="czcc-col-cookies"><?php echo esc_html( implode( ', ', (array) $service['cookies'] ) ); ?></td>
						<td><?php echo $service['iframe'] ? '<span class="czcc-badge">iframe</span>' : ''; ?><?php echo ! empty( $service['required'] ) ? '<span class="czcc-badge czcc-badge-req">required</span>' : ''; ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		self::card_close();

		self::card_open( __( 'Custom services (JSON)', 'cz-cookie-consent' ), __( 'Add your own services as a JSON object keyed by slug. Fields: name, provider, category, description {lang: text}, cookies [], domains [], gcm [], iframe (bool), embed_url (for iframe services, use {data-id}), default_enabled, required.', 'cz-cookie-consent' ) );
		?>
		<textarea name="czcc[custom_services_json]" rows="8" class="large-text code"><?php echo esc_textarea( $settings['custom_services'] ? wp_json_encode( $settings['custom_services'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : '' ); ?></textarea>
		<?php
		self::card_close();
	}

	/**
	 * Texts tab.
	 *
	 * @param array $settings Settings.
	 */
	private static function render_texts_tab( array $settings ) {
		$text_keys = CZCC_I18n::text_keys();
		$first     = true;
		?>
		<p class="czcc-tab-intro"><?php esc_html_e( 'Banner texts per language. The language shown to a visitor follows the WordPress locale (WPML/Polylang compatible). HTML is allowed in descriptions.', 'cz-cookie-consent' ); ?></p>

		<?php foreach ( (array) $settings['texts'] as $lang => $lang_texts ) : ?>
			<details class="czcc-lang" <?php echo $first ? 'open' : ''; ?>>
				<summary>
					<span class="czcc-lang-chip"><?php echo esc_html( strtoupper( $lang ) ); ?></span>
					<span class="czcc-lang-title"><?php echo esc_html( isset( $lang_texts['banner_title'] ) ? $lang_texts['banner_title'] : '' ); ?></span>
					<?php if ( ! in_array( $lang, array( 'cs', 'en' ), true ) ) : ?>
						<label class="czcc-remove-lang" onclick="event.stopPropagation()"><input type="checkbox" name="czcc[remove_language]" value="<?php echo esc_attr( $lang ); ?>"> <?php esc_html_e( 'remove on save', 'cz-cookie-consent' ); ?></label>
					<?php endif; ?>
				</summary>
				<div class="czcc-lang-fields">
					<?php foreach ( $text_keys as $key ) : ?>
						<?php $value = isset( $lang_texts[ $key ] ) ? $lang_texts[ $key ] : ''; ?>
						<div class="czcc-field">
							<label><?php echo esc_html( $key ); ?></label>
							<?php if ( in_array( $key, array( 'banner_description', 'preferences_intro', 'iframe_notice' ), true ) || false !== strpos( $key, '_desc' ) ) : ?>
								<textarea name="czcc[texts][<?php echo esc_attr( $lang ); ?>][<?php echo esc_attr( $key ); ?>]" rows="2" class="large-text"><?php echo esc_textarea( $value ); ?></textarea>
							<?php else : ?>
								<input type="text" class="regular-text" name="czcc[texts][<?php echo esc_attr( $lang ); ?>][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>">
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
					<div class="czcc-field">
						<label><?php esc_html_e( 'Privacy policy URL', 'cz-cookie-consent' ); ?></label>
						<input type="url" class="regular-text" name="czcc[privacy_policy_url][<?php echo esc_attr( $lang ); ?>]" value="<?php echo esc_attr( isset( $settings['privacy_policy_url'][ $lang ] ) ? $settings['privacy_policy_url'][ $lang ] : '' ); ?>">
					</div>
				</div>
			</details>
			<?php $first = false; ?>
		<?php endforeach; ?>

		<?php self::card_open( __( 'Add language', 'cz-cookie-consent' ) ); ?>
		<div class="czcc-field czcc-field-inline">
			<input type="text" maxlength="2" placeholder="de" name="czcc[add_language]" class="small-text">
			<span class="description"><?php esc_html_e( 'Two-letter code. English texts are copied as a starting point.', 'cz-cookie-consent' ); ?></span>
		</div>
		<?php self::card_close(); ?>
		<?php
	}

	/**
	 * Iframe blocking tab.
	 *
	 * @param array $settings Settings.
	 */
	private static function render_iframes_tab( array $settings ) {
		$services   = CZCC_Service_Registry::effective( $settings );
		$categories = CZCC_Service_Registry::categories();

		self::card_open( __( 'Behavior', 'cz-cookie-consent' ) );
		?>
		<div class="czcc-toggle-group">
			<?php
			echo self::toggle( 'czcc[auto_wrap_iframes]', $settings['auto_wrap_iframes'], __( 'Automatically replace known iframes in content with consent placeholders (YouTube, Google Maps, Facebook, Instagram)', 'cz-cookie-consent' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo self::toggle( 'czcc[load_thumbnails]', $settings['load_thumbnails'], __( 'Load video thumbnails before consent (transmits the visitor IP to the provider!)', 'cz-cookie-consent' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</div>
		<?php
		self::card_close();

		self::card_open( __( 'Iframe services', 'cz-cookie-consent' ), __( 'Blocked services show an "Allow content" placeholder until the selected category (or the individual service) is accepted.', 'cz-cookie-consent' ) );
		?>
		<table class="widefat striped czcc-services-table">
			<thead>
				<tr>
					<th class="czcc-col-toggle"><?php esc_html_e( 'Blocked', 'cz-cookie-consent' ); ?></th>
					<th><?php esc_html_e( 'Service', 'cz-cookie-consent' ); ?></th>
					<th><?php esc_html_e( 'Unblocked by category', 'cz-cookie-consent' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $services as $slug => $service ) :
					if ( empty( $service['iframe'] ) ) {
						continue;
					}
					$rule = isset( $settings['iframe_rules'][ $slug ] ) ? $settings['iframe_rules'][ $slug ] : array(
						'enabled'  => true,
						'category' => $service['category'],
					);
					?>
					<tr>
						<td class="czcc-col-toggle"><?php echo self::toggle( 'czcc[iframe_rules][' . $slug . '][enabled]', ! empty( $rule['enabled'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
						<td><strong><?php echo esc_html( $service['name'] ); ?></strong> <code><?php echo esc_html( $slug ); ?></code></td>
						<td>
							<select name="czcc[iframe_rules][<?php echo esc_attr( $slug ); ?>][category]">
								<?php foreach ( $categories as $category ) : ?>
									<option value="<?php echo esc_attr( $category ); ?>" <?php selected( $rule['category'], $category ); ?>><?php echo esc_html( $category ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p class="description">
			<?php esc_html_e( 'Manual markup: <div data-service="youtube" data-id="VIDEO_ID" data-autoscale></div>.', 'cz-cookie-consent' ); ?>
		</p>
		<?php
		self::card_close();
	}

	/**
	 * Consent stats row.
	 *
	 * @param int|null $blog_id Blog scope; null = network-wide.
	 */
	private static function render_stats( $blog_id ) {
		$stats  = CZCC_Consent_Repository::stats( $blog_id );
		$source = $stats['by_source'];

		$items = array(
			array( __( 'Total consents', 'cz-cookie-consent' ), $stats['total'], '' ),
			array( __( 'Last 30 days', 'cz-cookie-consent' ), $stats['recent30'], '' ),
			array( __( 'Accept all', 'cz-cookie-consent' ), isset( $source['accept_all'] ) ? $source['accept_all'] : 0, 'granted' ),
			array( __( 'Reject all', 'cz-cookie-consent' ), isset( $source['reject_all'] ) ? $source['reject_all'] : 0, 'denied' ),
			array( __( 'Custom', 'cz-cookie-consent' ), isset( $source['custom_save'] ) ? $source['custom_save'] : 0, '' ),
			array( __( 'Updates', 'cz-cookie-consent' ), isset( $source['update'] ) ? $source['update'] : 0, '' ),
		);

		echo '<div class="czcc-stats">';
		foreach ( $items as $item ) {
			printf(
				'<div class="czcc-stat czcc-stat-%s"><span class="czcc-stat-value">%s</span><span class="czcc-stat-label">%s</span></div>',
				esc_attr( $item[2] ? $item[2] : 'neutral' ),
				esc_html( number_format_i18n( (int) $item[1] ) ),
				esc_html( $item[0] )
			);
		}
		echo '</div>';
	}

	/**
	 * Consent log tab.
	 */
	private static function render_log_tab() {
		require_once CZCC_PLUGIN_DIR . 'includes/class-consent-log-table.php';

		self::render_stats( get_current_blog_id() );

		$table = new CZCC_Consent_Log_Table( get_current_blog_id() );
		$table->prepare_items();

		$export_url = wp_nonce_url( admin_url( 'admin-post.php?action=czcc_export_csv' ), 'czcc_export_csv' );
		$purge_url  = wp_nonce_url( admin_url( 'admin-post.php?action=czcc_purge_expired' ), 'czcc_purge_expired' );
		$delete_url = wp_nonce_url( admin_url( 'admin-post.php?action=czcc_delete_all' ), 'czcc_delete_all' );

		echo '<p class="czcc-actions">';
		echo '<a href="' . esc_url( $export_url ) . '" class="button">' . esc_html__( 'Export CSV', 'cz-cookie-consent' ) . '</a> ';
		echo '<a href="' . esc_url( $purge_url ) . '" class="button">' . esc_html__( 'Delete expired', 'cz-cookie-consent' ) . '</a> ';
		echo '<a href="' . esc_url( $delete_url ) . '" class="button button-link-delete czcc-confirm" data-confirm="' . esc_attr__( 'Really delete ALL consent records of this site?', 'cz-cookie-consent' ) . '">' . esc_html__( 'Delete all', 'cz-cookie-consent' ) . '</a>';
		echo '</p>';

		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="czcc-settings"><input type="hidden" name="tab" value="log">';
		$table->search_box( __( 'Search consent ID', 'cz-cookie-consent' ), 'czcc-search' );
		$table->display();
		echo '</form>';
	}

	/**
	 * Tools & debug tab.
	 */
	private static function render_tools_tab() {
		$settings = CZCC_Settings::get();
		$gtm4wp   = defined( 'GTM4WP_VERSION' ) ? GTM4WP_VERSION : null;

		self::card_open( __( 'Status', 'cz-cookie-consent' ) );
		?>
		<table class="widefat striped czcc-status-table">
			<tbody>
				<tr><td><?php esc_html_e( 'Plugin version', 'cz-cookie-consent' ); ?></td><td><?php echo esc_html( CZCC_VERSION ); ?></td></tr>
				<tr><td><?php esc_html_e( 'DB schema version', 'cz-cookie-consent' ); ?></td><td><?php echo esc_html( (string) get_site_option( CZCC_DB::OPTION_DB_VERSION ) ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Consent table', 'cz-cookie-consent' ); ?></td><td><code><?php echo esc_html( CZCC_DB::table_name() ); ?></code></td></tr>
				<tr><td><?php esc_html_e( 'Records (this site)', 'cz-cookie-consent' ); ?></td><td><?php echo esc_html( (string) CZCC_Consent_Repository::count( get_current_blog_id() ) ); ?></td></tr>
				<?php if ( is_multisite() ) : ?>
					<tr>
						<td><?php esc_html_e( 'Network configuration mode', 'cz-cookie-consent' ); ?></td>
						<td>
							<code><?php echo esc_html( CZCC_Settings::network_mode() ); ?></code>
							<?php if ( 'defaults' === CZCC_Settings::network_mode() ) : ?>
								— <?php echo esc_html( CZCC_Settings::site_has_override() ? __( 'this site uses its own configuration', 'cz-cookie-consent' ) : __( 'this site inherits the network defaults', 'cz-cookie-consent' ) ); ?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endif; ?>
				<tr><td><?php esc_html_e( 'CookieConsent library', 'cz-cookie-consent' ); ?></td><td><?php echo esc_html( CZCC_COOKIECONSENT_VERSION ); ?></td></tr>
				<tr><td><?php esc_html_e( 'iframemanager library', 'cz-cookie-consent' ); ?></td><td><?php echo esc_html( CZCC_IFRAMEMANAGER_VERSION ); ?></td></tr>
				<tr>
					<td>GTM4WP</td>
					<td>
						<?php if ( $gtm4wp ) : ?>
							<span class="czcc-badge czcc-badge-ok"><?php echo esc_html( sprintf( /* translators: %s: version */ __( 'Active, version %s', 'cz-cookie-consent' ), $gtm4wp ) ); ?></span>
							<?php if ( 0 === strpos( (string) $gtm4wp, '1.' ) ) : ?>
								<p class="description"><?php esc_html_e( 'GTM4WP 1.x detected: keep "Google Consent Mode" integration in GTM4WP disabled, or leave it enabled — this plugin aligns its flags either way. GTM4WP 2.x is handled fully automatically.', 'cz-cookie-consent' ); ?></p>
							<?php endif; ?>
						<?php else : ?>
							<?php esc_html_e( 'Not active (the plugin works standalone; GTM must then be inserted by other means).', 'cz-cookie-consent' ); ?>
						<?php endif; ?>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
		self::card_close();

		self::card_open( __( 'Current frontend configuration (debug)', 'cz-cookie-consent' ) );
		?>
		<textarea rows="14" class="large-text code" readonly><?php echo esc_textarea( (string) wp_json_encode( CZCC_Frontend::frontend_config(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></textarea>
		<p class="description"><?php echo esc_html( $settings['debug'] ? __( 'Debug mode is ON: the frontend logs consent events to the browser console.', 'cz-cookie-consent' ) : __( 'Debug mode is OFF.', 'cz-cookie-consent' ) ); ?></p>
		<?php
		self::card_close();
	}

	/* ------------------------------------------------------------------ *
	 * Network admin pages
	 * ------------------------------------------------------------------ */

	/**
	 * Network admin: network-wide configuration page.
	 */
	public static function render_network_settings_page() {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return;
		}

		$tabs = array(
			'general'  => __( 'General', 'cz-cookie-consent' ),
			'services' => __( 'Categories & services', 'cz-cookie-consent' ),
			'texts'    => __( 'Texts', 'cz-cookie-consent' ),
			'iframes'  => __( 'Iframe blocking', 'cz-cookie-consent' ),
		);

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'general';
		}

		echo '<div class="wrap czcc-wrap">';
		self::render_header( __( 'network settings', 'cz-cookie-consent' ) );

		if ( ! empty( $_GET['updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Network settings saved.', 'cz-cookie-consent' ) . '</p></div>';
		}

		echo '<nav class="nav-tab-wrapper czcc-tabs">';
		foreach ( $tabs as $slug => $label ) {
			printf(
				'<a href="%s" class="nav-tab%s">%s</a>',
				esc_url( add_query_arg( array( 'page' => 'czcc-network-settings', 'tab' => $slug ), network_admin_url( 'settings.php' ) ) ),
				$tab === $slug ? ' nav-tab-active' : '',
				esc_html( $label )
			);
		}
		echo '</nav>';

		echo '<div class="czcc-body">';
		self::render_network_settings_form( $tab );
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Network settings form for one tab.
	 *
	 * @param string $tab Tab slug.
	 */
	private static function render_network_settings_form( $tab ) {
		$settings = CZCC_Settings::network_settings();
		$mode     = CZCC_Settings::network_mode();

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="czcc_save_network_settings">';
		echo '<input type="hidden" name="czcc_tab" value="' . esc_attr( $tab ) . '">';
		wp_nonce_field( 'czcc_save_network_settings' );

		if ( 'general' === $tab ) {
			$modes = array(
				'off'      => array(
					__( 'Off', 'cz-cookie-consent' ),
					__( 'Every site configures the plugin independently.', 'cz-cookie-consent' ),
				),
				'defaults' => array(
					__( 'Network defaults', 'cz-cookie-consent' ),
					__( 'Sites inherit this configuration until they save their own; a site can reset back to it.', 'cz-cookie-consent' ),
				),
				'enforce'  => array(
					__( 'Enforce', 'cz-cookie-consent' ),
					__( 'This configuration applies everywhere, per-site settings are locked.', 'cz-cookie-consent' ),
				),
			);
			self::card_open( __( 'Network mode', 'cz-cookie-consent' ) );
			echo '<div class="czcc-mode-options">';
			foreach ( $modes as $value => $labels ) {
				printf(
					'<label class="czcc-mode-option%s"><input type="radio" name="czcc_network_mode" value="%s"%s><span class="czcc-mode-name">%s</span><span class="czcc-mode-desc">%s</span></label>',
					$mode === $value ? ' is-active' : '',
					esc_attr( $value ),
					checked( $mode, $value, false ),
					esc_html( $labels[0] ),
					esc_html( $labels[1] )
				);
			}
			echo '</div>';
			self::card_close();
		} else {
			echo '<input type="hidden" name="czcc_network_mode" value="' . esc_attr( $mode ) . '">';
		}

		self::render_hidden_state( $settings, $tab );

		if ( 'general' === $tab ) {
			self::render_general_tab( $settings );
		} elseif ( 'services' === $tab ) {
			self::render_services_tab( $settings );
		} elseif ( 'texts' === $tab ) {
			self::render_texts_tab( $settings );
		} elseif ( 'iframes' === $tab ) {
			self::render_iframes_tab( $settings );
		}

		submit_button( __( 'Save network settings', 'cz-cookie-consent' ) );
		echo '</form>';
	}

	/**
	 * Network admin: consent log across all sites.
	 */
	public static function render_network_log() {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return;
		}

		require_once CZCC_PLUGIN_DIR . 'includes/class-consent-log-table.php';

		echo '<div class="wrap czcc-wrap">';
		self::render_header( __( 'network log', 'cz-cookie-consent' ) );

		self::render_stats( null );

		$table = new CZCC_Consent_Log_Table( null );
		$table->prepare_items();

		$export_url = wp_nonce_url( network_admin_url( 'admin-post.php?action=czcc_export_csv&network=1' ), 'czcc_export_csv' );

		echo '<div class="czcc-body">';
		echo '<p class="czcc-actions"><a href="' . esc_url( $export_url ) . '" class="button">' . esc_html__( 'Export CSV (all sites)', 'cz-cookie-consent' ) . '</a></p>';
		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="czcc-network-log">';
		$table->search_box( __( 'Search consent ID', 'cz-cookie-consent' ), 'czcc-search' );
		$table->display();
		echo '</form>';
		echo '</div></div>';
	}
}
