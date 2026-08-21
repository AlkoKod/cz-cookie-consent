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
	 * Network admin menu (network-wide consent log).
	 */
	public static function register_network_menu() {
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

	/**
	 * Saves settings (admin-post).
	 */
	public static function handle_save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'cz-cookie-consent' ) );
		}
		check_admin_referer( 'czcc_save_settings' );

		$input = isset( $_POST['czcc'] ) && is_array( $_POST['czcc'] ) ? wp_unslash( $_POST['czcc'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized field-by-field in CZCC_Settings::sanitize().

		CZCC_Settings::update( CZCC_Settings::sanitize( $input ) );

		$tab = isset( $_POST['czcc_tab'] ) ? sanitize_key( wp_unslash( $_POST['czcc_tab'] ) ) : 'general';
		wp_safe_redirect( add_query_arg( array( 'page' => 'czcc-settings', 'tab' => $tab, 'updated' => '1' ), admin_url( 'options-general.php' ) ) );
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

	/**
	 * Settings page router.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tabs = array(
			'general'  => __( 'General', 'cz-cookie-consent' ),
			'services' => __( 'Categories & services', 'cz-cookie-consent' ),
			'texts'    => __( 'Texts', 'cz-cookie-consent' ),
			'iframes'  => __( 'Iframe blocking', 'cz-cookie-consent' ),
			'log'      => __( 'Consent log', 'cz-cookie-consent' ),
			'tools'    => __( 'Tools & debug', 'cz-cookie-consent' ),
		);

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'general';
		}

		echo '<div class="wrap czcc-wrap"><h1>' . esc_html__( 'CZ Cookie Consent', 'cz-cookie-consent' ) . '</h1>';

		if ( ! empty( $_GET['updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'cz-cookie-consent' ) . '</p></div>';
		}
		if ( isset( $_GET['purged'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			/* translators: %d: number of deleted records. */
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( __( '%d consent records deleted.', 'cz-cookie-consent' ), absint( wp_unslash( $_GET['purged'] ) ) ) ) . '</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $slug => $label ) {
			printf(
				'<a href="%s" class="nav-tab%s">%s</a>',
				esc_url( add_query_arg( array( 'page' => 'czcc-settings', 'tab' => $slug ), admin_url( 'options-general.php' ) ) ),
				$tab === $slug ? ' nav-tab-active' : '',
				esc_html( $label )
			);
		}
		echo '</nav>';

		if ( 'log' === $tab ) {
			self::render_log_tab();
		} elseif ( 'tools' === $tab ) {
			self::render_tools_tab();
		} else {
			self::render_settings_form( $tab );
		}

		echo '</div>';
	}

	/**
	 * Renders one settings form tab.
	 *
	 * @param string $tab Tab slug.
	 */
	private static function render_settings_form( $tab ) {
		$settings = CZCC_Settings::get();

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
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="czcc-duration"><?php esc_html_e( 'Consent validity (days)', 'cz-cookie-consent' ); ?></label></th>
				<td>
					<input type="number" min="1" max="730" id="czcc-duration" name="czcc[consent_duration_days]" value="<?php echo esc_attr( (string) $settings['consent_duration_days'] ); ?>">
					<p class="description"><?php esc_html_e( '182 = 6 months, 365 = 12 months. After expiry the banner is shown again.', 'cz-cookie-consent' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="czcc-revision"><?php esc_html_e( 'Configuration version (revision)', 'cz-cookie-consent' ); ?></label></th>
				<td>
					<input type="number" min="1" id="czcc-revision" name="czcc[config_version]" value="<?php echo esc_attr( (string) $settings['config_version'] ); ?>">
					<p class="description"><?php esc_html_e( 'Increase after changing categories/services or texts in a way that requires new consent. The banner is shown again to everyone.', 'cz-cookie-consent' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Banner layout', 'cz-cookie-consent' ); ?></th>
				<td>
					<select name="czcc[banner_layout]">
						<?php foreach ( array( 'box', 'box inline', 'box wide', 'cloud', 'cloud inline', 'bar', 'bar inline' ) as $layout ) : ?>
							<option value="<?php echo esc_attr( $layout ); ?>" <?php selected( $settings['banner_layout'], $layout ); ?>><?php echo esc_html( $layout ); ?></option>
						<?php endforeach; ?>
					</select>
					<select name="czcc[banner_position]">
						<?php foreach ( array( 'bottom left', 'bottom center', 'bottom right', 'middle left', 'middle center', 'middle right', 'top left', 'top center', 'top right' ) as $position ) : ?>
							<option value="<?php echo esc_attr( $position ); ?>" <?php selected( $settings['banner_position'], $position ); ?>><?php echo esc_html( $position ); ?></option>
						<?php endforeach; ?>
					</select>
					<select name="czcc[preferences_layout]">
						<?php foreach ( array( 'box', 'bar', 'bar wide' ) as $layout ) : ?>
							<option value="<?php echo esc_attr( $layout ); ?>" <?php selected( $settings['preferences_layout'], $layout ); ?>><?php echo esc_html( 'preferences: ' . $layout ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Buttons & behavior', 'cz-cookie-consent' ); ?></th>
				<td>
					<label><input type="checkbox" name="czcc[show_reject_button]" value="1" <?php checked( $settings['show_reject_button'] ); ?>> <?php esc_html_e( 'Show "Reject optional" button', 'cz-cookie-consent' ); ?></label><br>
					<label><input type="checkbox" name="czcc[equal_weight_buttons]" value="1" <?php checked( $settings['equal_weight_buttons'] ); ?>> <?php esc_html_e( 'Equal weight accept/reject buttons', 'cz-cookie-consent' ); ?></label><br>
					<label><input type="checkbox" name="czcc[flip_buttons]" value="1" <?php checked( $settings['flip_buttons'] ); ?>> <?php esc_html_e( 'Flip button order', 'cz-cookie-consent' ); ?></label><br>
					<label><input type="checkbox" name="czcc[disable_page_interaction]" value="1" <?php checked( $settings['disable_page_interaction'] ); ?>> <?php esc_html_e( 'Block page interaction until a choice is made', 'cz-cookie-consent' ); ?></label><br>
					<label><input type="checkbox" name="czcc[hide_from_bots]" value="1" <?php checked( $settings['hide_from_bots'] ); ?>> <?php esc_html_e( 'Hide banner from bots/crawlers', 'cz-cookie-consent' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Google Consent Mode', 'cz-cookie-consent' ); ?></th>
				<td>
					<label>
						<?php esc_html_e( 'functionality_storage default:', 'cz-cookie-consent' ); ?>
						<select name="czcc[functionality_default]">
							<option value="denied" <?php selected( $settings['functionality_default'], 'denied' ); ?>>denied</option>
							<option value="granted" <?php selected( $settings['functionality_default'], 'granted' ); ?>>granted</option>
						</select>
					</label>
					<p class="description"><?php esc_html_e( 'Use "granted" only if you treat functional storage as strictly necessary. All other signals default to denied (security_storage is always granted).', 'cz-cookie-consent' ); ?></p>
					<label><?php esc_html_e( 'wait_for_update (ms):', 'cz-cookie-consent' ); ?>
						<input type="number" min="0" max="10000" name="czcc[wait_for_update]" value="<?php echo esc_attr( (string) $settings['wait_for_update'] ); ?>">
					</label><br>
					<label><input type="checkbox" name="czcc[url_passthrough]" value="1" <?php checked( $settings['url_passthrough'] ); ?>> <?php esc_html_e( 'Enable url_passthrough', 'cz-cookie-consent' ); ?></label><br>
					<label><input type="checkbox" name="czcc[ads_data_redaction]" value="1" <?php checked( $settings['ads_data_redaction'] ); ?>> <?php esc_html_e( 'Enable ads_data_redaction', 'cz-cookie-consent' ); ?></label><br>
					<label><input type="checkbox" name="czcc[gtm4wp_suppress_default]" value="1" <?php checked( $settings['gtm4wp_suppress_default'] ); ?>> <?php esc_html_e( 'GTM4WP compatibility: suppress/align its own Consent Mode default block', 'cz-cookie-consent' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Data management', 'cz-cookie-consent' ); ?></th>
				<td>
					<label><?php esc_html_e( 'Auto-purge expired consents after (days, 0 = never):', 'cz-cookie-consent' ); ?>
						<input type="number" min="0" max="3650" name="czcc[auto_purge_days]" value="<?php echo esc_attr( (string) $settings['auto_purge_days'] ); ?>">
					</label><br>
					<label><input type="checkbox" name="czcc[delete_on_uninstall]" value="1" <?php checked( $settings['delete_on_uninstall'] ); ?>> <?php esc_html_e( 'Delete all plugin data on uninstall', 'cz-cookie-consent' ); ?></label><br>
					<label><input type="checkbox" name="czcc[debug]" value="1" <?php checked( $settings['debug'] ); ?>> <?php esc_html_e( 'Debug mode (console logging)', 'cz-cookie-consent' ); ?></label>
				</td>
			</tr>
		</table>
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
		?>
		<h2><?php esc_html_e( 'Enabled categories', 'cz-cookie-consent' ); ?></h2>
		<p>
			<?php foreach ( array( 'functional', 'preferences', 'analytics', 'marketing' ) as $category ) : ?>
				<label class="czcc-inline">
					<input type="checkbox" name="czcc[enabled_categories][]" value="<?php echo esc_attr( $category ); ?>" <?php checked( in_array( $category, (array) $settings['enabled_categories'], true ) ); ?>>
					<?php echo esc_html( $category ); ?>
				</label>
			<?php endforeach; ?>
		</p>
		<p class="description"><?php esc_html_e( 'The "necessary" category is always enabled and cannot be turned off by visitors.', 'cz-cookie-consent' ); ?></p>

		<h2><?php esc_html_e( 'Services', 'cz-cookie-consent' ); ?></h2>
		<table class="widefat striped czcc-services-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Enabled', 'cz-cookie-consent' ); ?></th>
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
						<td>
							<input type="checkbox" name="czcc[service_overrides][<?php echo esc_attr( $slug ); ?>][enabled]" value="1" <?php checked( ! empty( $service['enabled'] ) ); ?> <?php disabled( ! empty( $service['required'] ) ); ?>>
							<?php if ( ! empty( $service['required'] ) ) : ?>
								<input type="hidden" name="czcc[service_overrides][<?php echo esc_attr( $slug ); ?>][enabled]" value="1">
							<?php endif; ?>
						</td>
						<td><strong><?php echo esc_html( $service['name'] ); ?></strong><br><code><?php echo esc_html( $slug ); ?></code></td>
						<td><?php echo esc_html( $service['provider'] ); ?></td>
						<td>
							<select name="czcc[service_overrides][<?php echo esc_attr( $slug ); ?>][category]">
								<?php foreach ( $categories as $category ) : ?>
									<option value="<?php echo esc_attr( $category ); ?>" <?php selected( $service['category'], $category ); ?>><?php echo esc_html( $category ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
						<td><?php echo esc_html( implode( ', ', (array) $service['cookies'] ) ); ?></td>
						<td><?php echo $service['iframe'] ? '<span class="czcc-badge">iframe</span>' : ''; ?><?php echo ! empty( $service['required'] ) ? '<span class="czcc-badge czcc-badge-req">required</span>' : ''; ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Custom services (JSON)', 'cz-cookie-consent' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Add your own services as a JSON object keyed by slug. Fields: name, provider, category, description {lang: text}, cookies [], domains [], gcm [], iframe (bool), embed_url (for iframe services, use {data-id}), default_enabled, required.', 'cz-cookie-consent' ); ?>
		</p>
		<textarea name="czcc[custom_services_json]" rows="8" class="large-text code"><?php echo esc_textarea( $settings['custom_services'] ? wp_json_encode( $settings['custom_services'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : '' ); ?></textarea>
		<?php
	}

	/**
	 * Texts tab.
	 *
	 * @param array $settings Settings.
	 */
	private static function render_texts_tab( array $settings ) {
		$text_keys = CZCC_I18n::text_keys();
		?>
		<p class="description"><?php esc_html_e( 'Banner texts per language. The language shown to a visitor follows the WordPress locale (WPML/Polylang compatible). HTML is allowed in descriptions.', 'cz-cookie-consent' ); ?></p>
		<?php foreach ( (array) $settings['texts'] as $lang => $lang_texts ) : ?>
			<h2 class="czcc-lang-heading">
				<?php echo esc_html( strtoupper( $lang ) ); ?>
				<?php if ( ! in_array( $lang, array( 'cs', 'en' ), true ) ) : ?>
					<label class="czcc-remove-lang"><input type="checkbox" name="czcc[remove_language]" value="<?php echo esc_attr( $lang ); ?>"> <?php esc_html_e( 'remove this language on save', 'cz-cookie-consent' ); ?></label>
				<?php endif; ?>
			</h2>
			<table class="form-table" role="presentation">
				<?php foreach ( $text_keys as $key ) : ?>
					<tr>
						<th scope="row"><label><?php echo esc_html( $key ); ?></label></th>
						<td>
							<?php $value = isset( $lang_texts[ $key ] ) ? $lang_texts[ $key ] : ''; ?>
							<?php if ( in_array( $key, array( 'banner_description', 'preferences_intro', 'iframe_notice' ), true ) || false !== strpos( $key, '_desc' ) ) : ?>
								<textarea name="czcc[texts][<?php echo esc_attr( $lang ); ?>][<?php echo esc_attr( $key ); ?>]" rows="2" class="large-text"><?php echo esc_textarea( $value ); ?></textarea>
							<?php else : ?>
								<input type="text" class="regular-text" name="czcc[texts][<?php echo esc_attr( $lang ); ?>][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>">
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				<tr>
					<th scope="row"><label><?php esc_html_e( 'Privacy policy URL', 'cz-cookie-consent' ); ?></label></th>
					<td>
						<input type="url" class="regular-text" name="czcc[privacy_policy_url][<?php echo esc_attr( $lang ); ?>]" value="<?php echo esc_attr( isset( $settings['privacy_policy_url'][ $lang ] ) ? $settings['privacy_policy_url'][ $lang ] : '' ); ?>">
					</td>
				</tr>
			</table>
		<?php endforeach; ?>

		<h2><?php esc_html_e( 'Add language', 'cz-cookie-consent' ); ?></h2>
		<p>
			<input type="text" maxlength="2" placeholder="de" name="czcc[add_language]" class="small-text">
			<span class="description"><?php esc_html_e( 'Two-letter code. English texts are copied as a starting point.', 'cz-cookie-consent' ); ?></span>
		</p>
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
		?>
		<p>
			<label><input type="checkbox" name="czcc[auto_wrap_iframes]" value="1" <?php checked( $settings['auto_wrap_iframes'] ); ?>> <?php esc_html_e( 'Automatically replace known iframes in content with consent placeholders (YouTube, Google Maps, Facebook, Instagram)', 'cz-cookie-consent' ); ?></label><br>
			<label><input type="checkbox" name="czcc[load_thumbnails]" value="1" <?php checked( $settings['load_thumbnails'] ); ?>> <?php esc_html_e( 'Load video thumbnails before consent (transmits the visitor IP to the provider!)', 'cz-cookie-consent' ); ?></label>
		</p>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Blocked', 'cz-cookie-consent' ); ?></th>
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
						<td><input type="checkbox" name="czcc[iframe_rules][<?php echo esc_attr( $slug ); ?>][enabled]" value="1" <?php checked( ! empty( $rule['enabled'] ) ); ?>></td>
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
			<?php esc_html_e( 'Manual markup: <div data-service="youtube" data-id="VIDEO_ID" data-autoscale></div>. The "Allow content" placeholder is shown until the matching category or service is accepted.', 'cz-cookie-consent' ); ?>
		</p>
		<?php
	}

	/**
	 * Consent log tab.
	 */
	private static function render_log_tab() {
		require_once CZCC_PLUGIN_DIR . 'includes/class-consent-log-table.php';

		$table = new CZCC_Consent_Log_Table( get_current_blog_id() );
		$table->prepare_items();

		$export_url = wp_nonce_url( admin_url( 'admin-post.php?action=czcc_export_csv' ), 'czcc_export_csv' );
		$purge_url  = wp_nonce_url( admin_url( 'admin-post.php?action=czcc_purge_expired' ), 'czcc_purge_expired' );
		$delete_url = wp_nonce_url( admin_url( 'admin-post.php?action=czcc_delete_all' ), 'czcc_delete_all' );

		echo '<p>';
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
		?>
		<h2><?php esc_html_e( 'Status', 'cz-cookie-consent' ); ?></h2>
		<table class="widefat striped czcc-status-table">
			<tbody>
				<tr><td><?php esc_html_e( 'Plugin version', 'cz-cookie-consent' ); ?></td><td><?php echo esc_html( CZCC_VERSION ); ?></td></tr>
				<tr><td><?php esc_html_e( 'DB schema version', 'cz-cookie-consent' ); ?></td><td><?php echo esc_html( (string) get_site_option( CZCC_DB::OPTION_DB_VERSION ) ); ?></td></tr>
				<tr><td><?php esc_html_e( 'Consent table', 'cz-cookie-consent' ); ?></td><td><code><?php echo esc_html( CZCC_DB::table_name() ); ?></code></td></tr>
				<tr><td><?php esc_html_e( 'Records (this site)', 'cz-cookie-consent' ); ?></td><td><?php echo esc_html( (string) CZCC_Consent_Repository::count( get_current_blog_id() ) ); ?></td></tr>
				<tr><td><?php esc_html_e( 'CookieConsent library', 'cz-cookie-consent' ); ?></td><td><?php echo esc_html( CZCC_COOKIECONSENT_VERSION ); ?></td></tr>
				<tr><td><?php esc_html_e( 'iframemanager library', 'cz-cookie-consent' ); ?></td><td><?php echo esc_html( CZCC_IFRAMEMANAGER_VERSION ); ?></td></tr>
				<tr>
					<td>GTM4WP</td>
					<td>
						<?php if ( $gtm4wp ) : ?>
							<?php echo esc_html( sprintf( /* translators: %s: version */ __( 'Active, version %s', 'cz-cookie-consent' ), $gtm4wp ) ); ?>
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

		<h2><?php esc_html_e( 'Current frontend configuration (debug)', 'cz-cookie-consent' ); ?></h2>
		<textarea rows="14" class="large-text code" readonly><?php echo esc_textarea( (string) wp_json_encode( CZCC_Frontend::frontend_config(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></textarea>
		<p class="description"><?php echo esc_html( $settings['debug'] ? __( 'Debug mode is ON: the frontend logs consent events to the browser console.', 'cz-cookie-consent' ) : __( 'Debug mode is OFF.', 'cz-cookie-consent' ) ); ?></p>
		<?php
	}

	/**
	 * Network admin: consent log across all sites.
	 */
	public static function render_network_log() {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return;
		}

		require_once CZCC_PLUGIN_DIR . 'includes/class-consent-log-table.php';

		$table = new CZCC_Consent_Log_Table( null );
		$table->prepare_items();

		$export_url = wp_nonce_url( network_admin_url( 'admin-post.php?action=czcc_export_csv&network=1' ), 'czcc_export_csv' );

		echo '<div class="wrap czcc-wrap"><h1>' . esc_html__( 'Cookie Consent Log (network)', 'cz-cookie-consent' ) . '</h1>';
		echo '<p><a href="' . esc_url( $export_url ) . '" class="button">' . esc_html__( 'Export CSV (all sites)', 'cz-cookie-consent' ) . '</a></p>';
		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="czcc-network-log">';
		$table->search_box( __( 'Search consent ID', 'cz-cookie-consent' ), 'czcc-search' );
		$table->display();
		echo '</form></div>';
	}
}
