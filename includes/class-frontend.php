<?php
/**
 * Frontend: Consent Mode default output, asset loading, iframe wrapping.
 *
 * Timing vs GTM4WP (verified against GTM4WP source):
 *  - GTM4WP 2.x prints its global vars/dataLayer init at wp_head priority 1
 *    and the GTM container at priority 2 ("load early") or 10 (default).
 *  - GTM4WP 1.x prints everything (incl. its optional consent default block)
 *    at wp_head priority 10 (or 2 with "load early").
 *  - This plugin prints the Google Consent Mode default at wp_head
 *    priority 0, i.e. always BEFORE any GTM4WP output.
 *  - On GTM4WP 2.x the duplicate consent default block is suppressed via
 *    the 'gtm4wp_consent_mode_default_enabled' filter.
 *  - On GTM4WP 1.x (no such filter) the 'gtm4wp_overwrite_consent_mode_flag'
 *    filter (since 1.22) forces GTM4WP's own default block to output the
 *    same values as ours, so even if the site admin leaves the GTM4WP
 *    consent integration enabled, the two default commands cannot disagree.
 *
 * @package CZ_Cookie_Consent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Frontend controller.
 */
class CZCC_Frontend {

	/**
	 * Registers hooks.
	 */
	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'print_consent_defaults' ), 0 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

		// GTM4WP 2.x: suppress its own consent default block (we print ours earlier).
		add_filter( 'gtm4wp_consent_mode_default_enabled', array( __CLASS__, 'gtm4wp_suppress_default' ) );
		// GTM4WP 1.x: align its consent default flags with ours.
		add_filter( 'gtm4wp_overwrite_consent_mode_flag', array( __CLASS__, 'gtm4wp_align_flags' ), 10, 2 );

		$settings = CZCC_Settings::get();
		if ( ! empty( $settings['auto_wrap_iframes'] ) ) {
			add_filter( 'the_content', array( __CLASS__, 'wrap_iframes' ), 99 );
			add_filter( 'widget_text_content', array( __CLASS__, 'wrap_iframes' ), 99 );
			add_filter( 'widget_block_content', array( __CLASS__, 'wrap_iframes' ), 99 );
			// Bricks builder renders outside the_content: filter every
			// element's HTML (priority 20 = after Bricks' own callbacks).
			add_filter( 'bricks/frontend/render_element', array( __CLASS__, 'wrap_iframes' ), 20 );
		}

		add_shortcode( 'czcc_preferences', array( __CLASS__, 'preferences_shortcode' ) );
	}

	/**
	 * Google Consent Mode default state derived from settings.
	 *
	 * @return array<string, string>
	 */
	public static function default_gcm_state() {
		$settings = CZCC_Settings::get();

		$state = array(
			'ad_storage'              => 'denied',
			'analytics_storage'       => 'denied',
			'ad_user_data'            => 'denied',
			'ad_personalization'      => 'denied',
			'functionality_storage'   => ( 'granted' === $settings['functionality_default'] ) ? 'granted' : 'denied',
			'personalization_storage' => 'denied',
			'security_storage'        => 'granted',
		);

		/**
		 * Filters the Google Consent Mode default state.
		 *
		 * @param array $state Signal => granted|denied.
		 */
		return (array) apply_filters( 'czcc_gcm_defaults', $state );
	}

	/**
	 * Prints the Consent Mode default + stored-consent re-apply script.
	 *
	 * Runs at wp_head priority 0, before any GTM output. For returning
	 * visitors with a valid consent cookie the stored state is re-applied
	 * synchronously via gtag('consent','update',...) so tags never start
	 * with a wrong state, without waiting for the banner bundle to load.
	 */
	public static function print_consent_defaults() {
		$settings = CZCC_Settings::get();
		$defaults = self::default_gcm_state();

		$default_cmd = $defaults;
		if ( (int) $settings['wait_for_update'] > 0 ) {
			$default_cmd['wait_for_update'] = (int) $settings['wait_for_update'];
		}

		$config = array(
			'defaults'  => $default_cmd,
			'state'     => $defaults,
			'gcmMap'    => CZCC_Service_Registry::gcm_map(),
			'revision'  => (int) $settings['config_version'],
			'cookie'    => 'czcc_consent',
			'funcDef'   => $settings['functionality_default'],
			'redaction' => ! empty( $settings['ads_data_redaction'] ),
			'passthru'  => ! empty( $settings['url_passthrough'] ),
		);

		$json = wp_json_encode( $config );

		// The script must stay dependency-free and synchronous.
		$script = <<<'JS'
(function(cfg){
	window.dataLayer = window.dataLayer || [];
	window.gtag = window.gtag || function(){ dataLayer.push(arguments); };
	gtag('consent', 'default', cfg.defaults);
	if (cfg.redaction) { gtag('set', 'ads_data_redaction', true); }
	if (cfg.passthru) { gtag('set', 'url_passthrough', true); }
	var stored = null;
	try {
		var m = document.cookie.match(/(?:^|;\s*)czcc_consent=([^;]*)/);
		if (m) {
			var c = JSON.parse(decodeURIComponent(m[1]));
			if (c && c.categories && String(c.revision) === String(cfg.revision)) { stored = c; }
		}
	} catch (e) {}
	var state = cfg.state, granted = {};
	if (stored) {
		granted = {};
		for (var cat in cfg.gcmMap) {
			var on = stored.categories.indexOf(cat) > -1;
			for (var i = 0; i < cfg.gcmMap[cat].length; i++) { granted[cfg.gcmMap[cat][i]] = on; }
		}
		state = {};
		for (var sig in cfg.state) { state[sig] = granted[sig] ? 'granted' : 'denied'; }
		state.security_storage = 'granted';
		if (cfg.funcDef === 'granted') { state.functionality_storage = 'granted'; }
		gtag('consent', 'update', state);
	}
	window.CZCC_STATE = { stored: !!stored, state: state, cookie: stored };
	var flags = {};
	for (var cat2 in cfg.gcmMap) { flags[cat2] = stored ? stored.categories.indexOf(cat2) > -1 : (cat2 === 'necessary'); }
	dataLayer.push({
		event: 'cookie_consent_default',
		cookie_consent: {
			status: stored ? 'stored' : 'pending',
			categories: stored ? stored.categories : ['necessary'],
			services: stored && stored.services ? stored.services : {},
			gcm: state,
			consent_id: stored ? stored.consentId : null,
			revision: cfg.revision,
			source: 'page_load',
			necessary: true,
			functional: !!flags.functional,
			preferences: !!flags.preferences,
			analytics: !!flags.analytics,
			marketing: !!flags.marketing
		}
	});
})(%s);
JS;

		echo "\n<!-- CZ Cookie Consent: Google Consent Mode v2 default -->\n";
		echo '<script id="czcc-consent-default">' . sprintf( $script, $json ) . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON built via wp_json_encode, script is static.
	}

	/**
	 * GTM4WP 2.x: suppress its duplicate consent default block.
	 *
	 * @param bool $enabled Whether GTM4WP outputs its own default.
	 * @return bool
	 */
	public static function gtm4wp_suppress_default( $enabled ) {
		$settings = CZCC_Settings::get();
		return empty( $settings['gtm4wp_suppress_default'] ) ? $enabled : false;
	}

	/**
	 * GTM4WP 1.x: force its consent default flags to match ours.
	 *
	 * @param bool   $value Flag value (true = granted).
	 * @param string $flag  GTM4WP option key.
	 * @return bool
	 */
	public static function gtm4wp_align_flags( $value, $flag ) {
		$settings = CZCC_Settings::get();
		if ( empty( $settings['gtm4wp_suppress_default'] ) ) {
			return $value;
		}

		$map = array(
			'integrate-consent-mode-ads'          => false,
			'integrate-consent-mode-ad-user-data' => false,
			'integrate-consent-mode-ad-perso'     => false,
			'integrate-consent-mode-analytics'    => false,
			'integrate-consent-mode-perso'        => false,
			'integrate-consent-mode-func'         => ( 'granted' === $settings['functionality_default'] ),
			'integrate-consent-mode-security'     => true,
		);

		return isset( $map[ $flag ] ) ? $map[ $flag ] : $value;
	}

	/**
	 * Enqueues banner assets and the frontend configuration.
	 */
	public static function enqueue_assets() {
		$settings = CZCC_Settings::get();
		$debug    = ! empty( $settings['debug'] );

		wp_enqueue_style( 'czcc-cookieconsent', CZCC_PLUGIN_URL . 'assets/vendor/cookieconsent/cookieconsent.css', array(), CZCC_COOKIECONSENT_VERSION );
		wp_enqueue_style( 'czcc-frontend', CZCC_PLUGIN_URL . 'assets/css/frontend.css', array( 'czcc-cookieconsent' ), CZCC_VERSION );

		wp_enqueue_script( 'czcc-cookieconsent', CZCC_PLUGIN_URL . 'assets/vendor/cookieconsent/cookieconsent.umd.js', array(), CZCC_COOKIECONSENT_VERSION, true );

		$deps = array( 'czcc-cookieconsent' );

		if ( self::iframe_services_config() ) {
			wp_enqueue_style( 'czcc-iframemanager', CZCC_PLUGIN_URL . 'assets/vendor/iframemanager/iframemanager.css', array(), CZCC_IFRAMEMANAGER_VERSION );
			wp_enqueue_script( 'czcc-iframemanager', CZCC_PLUGIN_URL . 'assets/vendor/iframemanager/iframemanager.js', array(), CZCC_IFRAMEMANAGER_VERSION, true );
			$deps[] = 'czcc-iframemanager';
		}

		wp_enqueue_script( 'czcc-frontend', CZCC_PLUGIN_URL . 'assets/js/frontend.js', $deps, CZCC_VERSION . ( $debug ? '.' . time() : '' ), true );

		wp_add_inline_script( 'czcc-frontend', 'var CZCC_CFG = ' . wp_json_encode( self::frontend_config() ) . ';', 'before' );
	}

	/**
	 * Builds the frontend configuration object.
	 *
	 * @return array
	 */
	public static function frontend_config() {
		$settings = CZCC_Settings::get();
		$lang     = CZCC_I18n::current_language();
		$texts    = CZCC_Settings::texts_for_language( $lang );

		$privacy_urls = (array) $settings['privacy_policy_url'];
		$privacy_url  = isset( $privacy_urls[ $lang ] ) ? $privacy_urls[ $lang ] : ( isset( $privacy_urls['en'] ) ? $privacy_urls['en'] : '' );

		$config = array(
			'lang'          => $lang,
			'texts'         => $texts,
			'privacyUrl'    => $privacy_url,
			'revision'      => (int) $settings['config_version'],
			'durationDays'  => (int) $settings['consent_duration_days'],
			'cookieName'    => 'czcc_consent',
			'gui'           => array(
				'layout'      => $settings['banner_layout'],
				'position'    => $settings['banner_position'],
				'prefLayout'  => $settings['preferences_layout'],
				'equalWeight' => ! empty( $settings['equal_weight_buttons'] ),
				'flip'        => ! empty( $settings['flip_buttons'] ),
			),
			'showReject'    => ! empty( $settings['show_reject_button'] ),
			'pageBlock'     => ! empty( $settings['disable_page_interaction'] ),
			'hideFromBots'  => ! empty( $settings['hide_from_bots'] ),
			'categories'    => self::categories_config( $lang ),
			'gcmMap'        => CZCC_Service_Registry::gcm_map(),
			'gcmDefaults'   => self::default_gcm_state(),
			'funcDefault'   => $settings['functionality_default'],
			'iframe'        => array(
				'services'        => self::iframe_services_config(),
				'loadThumbnails'  => ! empty( $settings['load_thumbnails'] ),
				'texts'           => array(
					'notice'     => isset( $texts['iframe_notice'] ) ? $texts['iframe_notice'] : '',
					'loadBtn'    => isset( $texts['iframe_load_btn'] ) ? $texts['iframe_load_btn'] : '',
					'loadAllBtn' => isset( $texts['iframe_load_all_btn'] ) ? $texts['iframe_load_all_btn'] : '',
				),
			),
			'rest'          => array(
				'url'   => esc_url_raw( rest_url( 'czcc/v1/consent' ) ),
				'nonce' => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : null,
			),
			'debug'         => ! empty( $settings['debug'] ),
		);

		/**
		 * Filters the frontend configuration passed to the banner script.
		 *
		 * @param array $config Frontend config.
		 */
		return (array) apply_filters( 'czcc_frontend_config', $config );
	}

	/**
	 * Category/service structure for the banner.
	 *
	 * Iframe rules override the category of iframe services, so e.g.
	 * YouTube can be moved between marketing and functional in admin.
	 *
	 * @param string $lang Language code.
	 * @return array
	 */
	private static function categories_config( $lang ) {
		$settings = CZCC_Settings::get();
		$enabled  = array_merge( array( 'necessary' ), (array) $settings['enabled_categories'] );
		$rules    = (array) $settings['iframe_rules'];

		$grouped = array();
		foreach ( CZCC_Service_Registry::effective( $settings ) as $slug => $service ) {
			if ( empty( $service['enabled'] ) ) {
				continue;
			}
			$category  = $service['category'];
			$is_iframe = ! empty( $service['iframe'] );

			if ( $is_iframe ) {
				if ( isset( $rules[ $slug ] ) ) {
					if ( empty( $rules[ $slug ]['enabled'] ) ) {
						$is_iframe = false; // Rule disabled: keep as plain service toggle.
					} elseif ( ! empty( $rules[ $slug ]['category'] ) ) {
						$category = $rules[ $slug ]['category'];
					}
				}
			}

			if ( ! in_array( $category, $enabled, true ) ) {
				continue;
			}

			$description = '';
			if ( ! empty( $service['description'] ) && is_array( $service['description'] ) ) {
				if ( isset( $service['description'][ $lang ] ) ) {
					$description = $service['description'][ $lang ];
				} elseif ( isset( $service['description']['en'] ) ) {
					$description = $service['description']['en'];
				}
			}

			$grouped[ $category ][ $slug ] = array(
				'label'    => $service['name'],
				'desc'     => $description,
				'cookies'  => array_values( (array) $service['cookies'] ),
				'iframe'   => $is_iframe,
				'required' => ! empty( $service['required'] ),
			);
		}

		$categories = array();
		foreach ( CZCC_Service_Registry::categories() as $category ) {
			if ( ! in_array( $category, $enabled, true ) ) {
				continue;
			}
			$categories[ $category ] = array(
				'readOnly' => ( 'necessary' === $category ),
				'services' => isset( $grouped[ $category ] ) ? $grouped[ $category ] : array(),
			);
		}

		return $categories;
	}

	/**
	 * Iframe service definitions for iframemanager.
	 *
	 * Cached per request: wrap_iframes() calls this for every rendered
	 * Bricks element.
	 *
	 * @return array<string, array>
	 */
	public static function iframe_services_config() {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}

		$settings = CZCC_Settings::get();
		$rules    = (array) $settings['iframe_rules'];
		$enabled  = array_merge( array( 'necessary' ), (array) $settings['enabled_categories'] );

		$templates = array(
			'youtube'         => array(
				'embedUrl'  => 'https://www.youtube-nocookie.com/embed/{data-id}',
				'thumbnail' => 'https://i3.ytimg.com/vi/{data-id}/hqdefault.jpg',
				'allow'     => 'accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen;',
			),
			'google-maps'     => array(
				// {data-id} is the path+query after "/maps" (e.g.
				// "/embed?pb=..." or "?q=...&output=embed"); legacy bare
				// pb-values in manual markup are normalized in frontend.js.
				'embedUrl'  => 'https://www.google.com/maps{data-id}',
				'thumbnail' => null,
				'allow'     => 'picture-in-picture; fullscreen;',
			),
			'facebook-embed'  => array(
				'embedUrl'  => 'https://www.facebook.com/plugins/{data-id}',
				'thumbnail' => null,
				'allow'     => 'encrypted-media; picture-in-picture; fullscreen;',
			),
			'instagram-embed' => array(
				'embedUrl'  => 'https://www.instagram.com/p/{data-id}/embed/',
				'thumbnail' => null,
				'allow'     => 'encrypted-media; picture-in-picture; fullscreen;',
			),
		);

		$services = array();
		foreach ( CZCC_Service_Registry::effective( $settings ) as $slug => $service ) {
			if ( empty( $service['enabled'] ) || empty( $service['iframe'] ) ) {
				continue;
			}
			$rule = isset( $rules[ $slug ] ) ? $rules[ $slug ] : array( 'enabled' => true, 'category' => $service['category'] );
			if ( empty( $rule['enabled'] ) ) {
				continue;
			}
			$category = ! empty( $rule['category'] ) ? $rule['category'] : $service['category'];
			if ( ! in_array( $category, $enabled, true ) ) {
				continue;
			}

			$template = isset( $templates[ $slug ] ) ? $templates[ $slug ] : array(
				'embedUrl'  => isset( $service['embed_url'] ) ? $service['embed_url'] : '',
				'thumbnail' => null,
				'allow'     => 'fullscreen;',
			);
			if ( empty( $template['embedUrl'] ) ) {
				continue;
			}

			$services[ $slug ] = array(
				'label'     => $service['name'],
				'category'  => $category,
				'embedUrl'  => $template['embedUrl'],
				'thumbnail' => $template['thumbnail'],
				'allow'     => $template['allow'],
			);
		}

		/**
		 * Filters iframe service definitions passed to iframemanager.
		 *
		 * @param array $services Iframe services keyed by slug.
		 */
		$cache = (array) apply_filters( 'czcc_iframe_services', $services );

		return $cache;
	}

	/**
	 * Replaces known third-party iframes with iframemanager placeholders.
	 *
	 * @param string $content HTML content.
	 * @return string
	 */
	public static function wrap_iframes( $content ) {
		if ( false === stripos( (string) $content, '<iframe' ) ) {
			return $content;
		}

		$services = self::iframe_services_config();
		if ( ! $services ) {
			return $content;
		}

		return (string) preg_replace_callback(
			'#<iframe\b[^>]*>\s*</iframe>|<iframe\b[^>]*/>#is',
			function ( $matches ) use ( $services ) {
				return CZCC_Frontend::wrap_single_iframe( $matches[0], $services );
			},
			$content
		);
	}

	/**
	 * Converts one iframe tag into an iframemanager placeholder if it
	 * belongs to a known, enabled iframe service.
	 *
	 * @param string $iframe   Full iframe tag.
	 * @param array  $services Enabled iframe services.
	 * @return string
	 */
	public static function wrap_single_iframe( $iframe, array $services ) {
		if ( ! preg_match( '#\bsrc=["\']([^"\']+)["\']#i', $iframe, $src_match ) ) {
			return $iframe;
		}
		$src = html_entity_decode( $src_match[1], ENT_QUOTES );

		// YouTube.
		if ( isset( $services['youtube'] ) && preg_match( '#^https?://(?:www\.)?youtube(?:-nocookie)?\.com/embed/([A-Za-z0-9_-]{6,20})#i', $src, $m ) ) {
			return self::placeholder_div( 'youtube', $m[1] );
		}

		// Google Maps. The data-id carries everything after "/maps", so one
		// embed template (https://www.google.com/maps{data-id}) covers:
		//  - new style:      google.com/maps/embed?pb=!1m18!...
		//  - Embed API:      google.com/maps/embed/v1/place?key=...&q=...
		//  - old style:      maps.google.com/maps?q=...&output=embed
		//    (used e.g. by the Bricks builder Map element without API key)
		if ( isset( $services['google-maps'] ) && preg_match( '#^https?://(?:www\.|maps\.)?google\.[a-z.]{2,6}/maps(/embed[^\s"\']*|\?[^\s"\']*)$#i', $src, $m ) ) {
			$data_id = $m[1];
			// A bare /maps?query URL is an embed only with output=embed.
			if ( 0 === strpos( $data_id, '?' ) && false === stripos( $data_id, 'output=embed' ) ) {
				return $iframe;
			}
			return self::placeholder_div( 'google-maps', $data_id );
		}

		// Instagram.
		if ( isset( $services['instagram-embed'] ) && preg_match( '#^https?://(?:www\.)?instagram\.com/p/([A-Za-z0-9_-]+)/embed#i', $src, $m ) ) {
			return self::placeholder_div( 'instagram-embed', $m[1] );
		}

		// Facebook plugins (post.php, video.php, page.php, ... incl. query).
		if ( isset( $services['facebook-embed'] ) && preg_match( '#^https?://(?:www\.)?facebook\.com/plugins/([a-z_]+\.php\?.+)$#i', $src, $m ) ) {
			return self::placeholder_div( 'facebook-embed', $m[1] );
		}

		return $iframe;
	}

	/**
	 * Builds the placeholder markup for iframemanager.
	 *
	 * @param string $service Service slug.
	 * @param string $data_id Service data id.
	 * @return string
	 */
	private static function placeholder_div( $service, $data_id ) {
		return sprintf(
			'<div data-service="%s" data-id="%s" data-autoscale></div>',
			esc_attr( $service ),
			esc_attr( $data_id )
		);
	}

	/**
	 * [czcc_preferences] shortcode: element that opens the preferences modal.
	 *
	 * Attributes:
	 *  - text:  label (default: preferences modal title for the current language)
	 *  - style: 'link' (default, underlined text) or 'button' (styled button)
	 *  - class: extra CSS classes, e.g. your theme's button class
	 *
	 * Examples:
	 *  [czcc_preferences]
	 *  [czcc_preferences style="button"]
	 *  [czcc_preferences style="button" text="Změnit nastavení cookies" class="wp-block-button__link"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function preferences_shortcode( $atts ) {
		$lang  = CZCC_I18n::current_language();
		$texts = CZCC_Settings::texts_for_language( $lang );

		$atts = shortcode_atts(
			array(
				'text'  => isset( $texts['preferences_title'] ) ? $texts['preferences_title'] : 'Cookie settings',
				'style' => 'link',
				'class' => '',
			),
			$atts,
			'czcc_preferences'
		);

		$classes = array( 'button' === $atts['style'] ? 'czcc-preferences-button' : 'czcc-preferences-link' );
		foreach ( preg_split( '/\s+/', (string) $atts['class'], -1, PREG_SPLIT_NO_EMPTY ) as $class ) {
			$class = sanitize_html_class( $class );
			if ( '' !== $class ) {
				$classes[] = $class;
			}
		}

		return sprintf(
			'<button type="button" class="%s" data-cc="show-preferencesModal">%s</button>',
			esc_attr( implode( ' ', $classes ) ),
			esc_html( $atts['text'] )
		);
	}
}
