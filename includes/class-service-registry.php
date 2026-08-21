<?php
/**
 * Default database of known services/cookies.
 *
 * Every service defines: name, provider, category, description (per lang),
 * typical cookies (prefix match, '*' suffix allowed), domains, Google
 * Consent Mode signals it maps to, whether it is an iframe service,
 * whether it is enabled by default and whether it is required.
 *
 * @package CZ_Cookie_Consent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Service registry.
 */
class CZCC_Service_Registry {

	/**
	 * Valid consent categories, in display order.
	 *
	 * @return string[]
	 */
	public static function categories() {
		return array( 'necessary', 'functional', 'preferences', 'analytics', 'marketing' );
	}

	/**
	 * The seven Google Consent Mode v2 signals.
	 *
	 * @return string[]
	 */
	public static function gcm_signals() {
		return array(
			'ad_storage',
			'analytics_storage',
			'ad_user_data',
			'ad_personalization',
			'functionality_storage',
			'personalization_storage',
			'security_storage',
		);
	}

	/**
	 * Category -> Google Consent Mode signals mapping.
	 *
	 * @return array<string, string[]>
	 */
	public static function gcm_map() {
		$map = array(
			'necessary'   => array( 'security_storage' ),
			'functional'  => array( 'functionality_storage' ),
			'preferences' => array( 'personalization_storage' ),
			'analytics'   => array( 'analytics_storage' ),
			'marketing'   => array( 'ad_storage', 'ad_user_data', 'ad_personalization' ),
		);

		/**
		 * Filters the category -> consent mode signal mapping.
		 *
		 * @param array $map The mapping.
		 */
		return (array) apply_filters( 'czcc_gcm_map', $map );
	}

	/**
	 * Default services database.
	 *
	 * @return array<string, array>
	 */
	public static function defaults() {
		$services = array(
			// ---- Google -------------------------------------------------.
			'google-tag-manager'       => array(
				'name'            => 'Google Tag Manager',
				'provider'        => 'Google',
				'category'        => 'necessary',
				'description'     => array(
					'cs' => 'Správce měřicích kódů. Sám o sobě neukládá marketingové cookies, pouze řídí ostatní nástroje podle uděleného souhlasu.',
					'en' => 'Tag management system. Does not set marketing cookies by itself; it only controls other tools according to the given consent.',
				),
				'cookies'         => array(),
				'domains'         => array( 'googletagmanager.com' ),
				'gcm'             => array(),
				'iframe'          => false,
				'default_enabled' => true,
				'required'        => true,
			),
			'google-analytics-4'       => array(
				'name'            => 'Google Analytics 4',
				'provider'        => 'Google',
				'category'        => 'analytics',
				'description'     => array(
					'cs' => 'Měření návštěvnosti a chování uživatelů na webu.',
					'en' => 'Traffic and on-site behavior measurement.',
				),
				'cookies'         => array( '_ga', '_ga_*', '_gid', '_gat' ),
				'domains'         => array( 'google-analytics.com', 'analytics.google.com', 'googletagmanager.com' ),
				'gcm'             => array( 'analytics_storage' ),
				'iframe'          => false,
				'default_enabled' => true,
				'required'        => false,
			),
			'google-ads'               => array(
				'name'            => 'Google Ads',
				'provider'        => 'Google',
				'category'        => 'marketing',
				'description'     => array(
					'cs' => 'Měření konverzí a remarketing v síti Google Ads.',
					'en' => 'Conversion measurement and remarketing in the Google Ads network.',
				),
				'cookies'         => array( '_gcl_au', '_gcl_aw', '_gcl_dc', 'IDE', 'test_cookie' ),
				'domains'         => array( 'googleadservices.com', 'doubleclick.net', 'google.com', 'google.cz' ),
				'gcm'             => array( 'ad_storage', 'ad_user_data', 'ad_personalization' ),
				'iframe'          => false,
				'default_enabled' => true,
				'required'        => false,
			),
			'google-conversion-linker' => array(
				'name'            => 'Google Ads Conversion Linker',
				'provider'        => 'Google',
				'category'        => 'marketing',
				'description'     => array(
					'cs' => 'Ukládá informace o kliknutí na reklamu (GCLID) pro správné měření konverzí.',
					'en' => 'Stores ad click information (GCLID) for accurate conversion measurement.',
				),
				'cookies'         => array( '_gcl_au', '_gcl_aw', '_gcl_gb' ),
				'domains'         => array( 'google.com' ),
				'gcm'             => array( 'ad_storage' ),
				'iframe'          => false,
				'default_enabled' => true,
				'required'        => false,
			),
			'google-fonts'             => array(
				'name'            => 'Google Fonts',
				'provider'        => 'Google',
				'category'        => 'functional',
				'description'     => array(
					'cs' => 'Načítání webových fontů ze serverů Google. Neukládá cookies, ale přenáší IP adresu na servery Google.',
					'en' => 'Loads web fonts from Google servers. Sets no cookies but transmits the IP address to Google.',
				),
				'cookies'         => array(),
				'domains'         => array( 'fonts.googleapis.com', 'fonts.gstatic.com' ),
				'gcm'             => array( 'functionality_storage' ),
				'iframe'          => false,
				'default_enabled' => false,
				'required'        => false,
			),
			'youtube'                  => array(
				'name'            => 'YouTube',
				'provider'        => 'Google',
				'category'        => 'marketing',
				'description'     => array(
					'cs' => 'Vložená videa YouTube. Do udělení souhlasu jsou nahrazena zástupným obsahem.',
					'en' => 'Embedded YouTube videos. Replaced by a placeholder until consent is given.',
				),
				'cookies'         => array( 'VISITOR_INFO1_LIVE', 'YSC', 'PREF' ),
				'domains'         => array( 'youtube.com', 'youtube-nocookie.com', 'ytimg.com' ),
				'gcm'             => array( 'ad_storage' ),
				'iframe'          => true,
				'default_enabled' => true,
				'required'        => false,
			),
			'google-maps'              => array(
				'name'            => 'Google Maps',
				'provider'        => 'Google',
				'category'        => 'functional',
				'description'     => array(
					'cs' => 'Vložené mapy Google. Do udělení souhlasu jsou nahrazeny zástupným obsahem.',
					'en' => 'Embedded Google Maps. Replaced by a placeholder until consent is given.',
				),
				'cookies'         => array( 'NID', 'CONSENT', 'AEC' ),
				'domains'         => array( 'google.com', 'maps.google.com', 'maps.googleapis.com' ),
				'gcm'             => array( 'functionality_storage' ),
				'iframe'          => true,
				'default_enabled' => true,
				'required'        => false,
			),
			'recaptcha'                => array(
				'name'            => 'Google reCAPTCHA',
				'provider'        => 'Google',
				'category'        => 'necessary',
				'description'     => array(
					'cs' => 'Ochrana formulářů proti spamu a zneužití (bezpečnostní služba).',
					'en' => 'Protects forms against spam and abuse (security service).',
				),
				'cookies'         => array( '_GRECAPTCHA' ),
				'domains'         => array( 'google.com', 'gstatic.com', 'recaptcha.net' ),
				'gcm'             => array( 'security_storage' ),
				'iframe'          => false,
				'default_enabled' => false,
				'required'        => false,
			),
			// ---- Meta ---------------------------------------------------.
			'facebook-pixel'           => array(
				'name'            => 'Facebook Pixel',
				'provider'        => 'Meta',
				'category'        => 'marketing',
				'description'     => array(
					'cs' => 'Měření konverzí a remarketing v síti Meta (Facebook/Instagram).',
					'en' => 'Conversion measurement and remarketing in the Meta network (Facebook/Instagram).',
				),
				'cookies'         => array( '_fbp', '_fbc', 'fr' ),
				'domains'         => array( 'facebook.com', 'facebook.net', 'connect.facebook.net' ),
				'gcm'             => array( 'ad_storage', 'ad_user_data', 'ad_personalization' ),
				'iframe'          => false,
				'default_enabled' => true,
				'required'        => false,
			),
			'meta-ads'                 => array(
				'name'            => 'Meta Ads',
				'provider'        => 'Meta',
				'category'        => 'marketing',
				'description'     => array(
					'cs' => 'Reklamní systém Meta – cílení a vyhodnocování reklam.',
					'en' => 'Meta advertising system – ad targeting and evaluation.',
				),
				'cookies'         => array( 'fr', 'datr', 'sb' ),
				'domains'         => array( 'facebook.com', 'instagram.com' ),
				'gcm'             => array( 'ad_storage', 'ad_user_data', 'ad_personalization' ),
				'iframe'          => false,
				'default_enabled' => false,
				'required'        => false,
			),
			'facebook-embed'           => array(
				'name'            => 'Facebook embed',
				'provider'        => 'Meta',
				'category'        => 'marketing',
				'description'     => array(
					'cs' => 'Vložené příspěvky a videa z Facebooku. Do udělení souhlasu jsou nahrazeny zástupným obsahem.',
					'en' => 'Embedded Facebook posts and videos. Replaced by a placeholder until consent is given.',
				),
				'cookies'         => array( 'fr', 'datr' ),
				'domains'         => array( 'facebook.com' ),
				'gcm'             => array( 'ad_storage' ),
				'iframe'          => true,
				'default_enabled' => true,
				'required'        => false,
			),
			'instagram-embed'          => array(
				'name'            => 'Instagram embed',
				'provider'        => 'Meta',
				'category'        => 'marketing',
				'description'     => array(
					'cs' => 'Vložené příspěvky z Instagramu. Do udělení souhlasu jsou nahrazeny zástupným obsahem.',
					'en' => 'Embedded Instagram posts. Replaced by a placeholder until consent is given.',
				),
				'cookies'         => array( 'ig_did', 'csrftoken', 'mid' ),
				'domains'         => array( 'instagram.com' ),
				'gcm'             => array( 'ad_storage' ),
				'iframe'          => true,
				'default_enabled' => true,
				'required'        => false,
			),
			// ---- Seznam.cz ----------------------------------------------.
			'sklik'                    => array(
				'name'            => 'Sklik',
				'provider'        => 'Seznam.cz',
				'category'        => 'marketing',
				'description'     => array(
					'cs' => 'Měření konverzí a retargeting reklamní sítě Sklik.',
					'en' => 'Conversion measurement and retargeting for the Sklik ad network.',
				),
				'cookies'         => array( 'sid', 'udid', 'retargeting' ),
				'domains'         => array( 'seznam.cz', 'imedia.cz', 'sklik.cz' ),
				'gcm'             => array( 'ad_storage', 'ad_personalization' ),
				'iframe'          => false,
				'default_enabled' => true,
				'required'        => false,
			),
			'seznam-retargeting'       => array(
				'name'            => 'Seznam retargeting',
				'provider'        => 'Seznam.cz',
				'category'        => 'marketing',
				'description'     => array(
					'cs' => 'Retargetingový kód Seznam.cz pro cílení reklamy na návštěvníky webu.',
					'en' => 'Seznam.cz retargeting code for targeting ads at website visitors.',
				),
				'cookies'         => array( 'sid', 'retargeting' ),
				'domains'         => array( 'seznam.cz', 'imedia.cz' ),
				'gcm'             => array( 'ad_storage', 'ad_personalization' ),
				'iframe'          => false,
				'default_enabled' => false,
				'required'        => false,
			),
			'zbozi-cz'                 => array(
				'name'            => 'Zboží.cz konverze',
				'provider'        => 'Seznam.cz',
				'category'        => 'marketing',
				'description'     => array(
					'cs' => 'Měření konverzí srovnávače Zboží.cz (relevantní pro e-shopy).',
					'en' => 'Conversion measurement for the Zboží.cz price comparison site (relevant for e-shops).',
				),
				'cookies'         => array( 'sid' ),
				'domains'         => array( 'zbozi.cz', 'seznam.cz' ),
				'gcm'             => array( 'ad_storage' ),
				'iframe'          => false,
				'default_enabled' => false,
				'required'        => false,
			),
			// ---- Ostatní ------------------------------------------------.
			'linkedin-insight'         => array(
				'name'            => 'LinkedIn Insight Tag',
				'provider'        => 'LinkedIn',
				'category'        => 'marketing',
				'description'     => array(
					'cs' => 'Měření konverzí a retargeting LinkedIn.',
					'en' => 'LinkedIn conversion tracking and retargeting.',
				),
				'cookies'         => array( 'li_sugr', 'bcookie', 'lidc', 'UserMatchHistory' ),
				'domains'         => array( 'linkedin.com', 'licdn.com' ),
				'gcm'             => array( 'ad_storage', 'ad_user_data' ),
				'iframe'          => false,
				'default_enabled' => false,
				'required'        => false,
			),
			'tiktok-pixel'             => array(
				'name'            => 'TikTok Pixel',
				'provider'        => 'TikTok',
				'category'        => 'marketing',
				'description'     => array(
					'cs' => 'Měření konverzí a remarketing TikTok.',
					'en' => 'TikTok conversion measurement and remarketing.',
				),
				'cookies'         => array( '_ttp', 'tt_appInfo', 'tt_sessionId' ),
				'domains'         => array( 'tiktok.com', 'analytics.tiktok.com' ),
				'gcm'             => array( 'ad_storage', 'ad_user_data' ),
				'iframe'          => false,
				'default_enabled' => false,
				'required'        => false,
			),
			'hotjar'                   => array(
				'name'            => 'Hotjar',
				'provider'        => 'Hotjar',
				'category'        => 'analytics',
				'description'     => array(
					'cs' => 'Analýza chování uživatelů (heatmapy, nahrávky relací).',
					'en' => 'User behavior analytics (heatmaps, session recordings).',
				),
				'cookies'         => array( '_hj*' ),
				'domains'         => array( 'hotjar.com', 'hotjar.io' ),
				'gcm'             => array( 'analytics_storage' ),
				'iframe'          => false,
				'default_enabled' => false,
				'required'        => false,
			),
			'ms-clarity'               => array(
				'name'            => 'Microsoft Clarity',
				'provider'        => 'Microsoft',
				'category'        => 'analytics',
				'description'     => array(
					'cs' => 'Analýza chování uživatelů (heatmapy, nahrávky relací).',
					'en' => 'User behavior analytics (heatmaps, session recordings).',
				),
				'cookies'         => array( '_clck', '_clsk', 'CLID', 'MUID' ),
				'domains'         => array( 'clarity.ms', 'bing.com' ),
				'gcm'             => array( 'analytics_storage' ),
				'iframe'          => false,
				'default_enabled' => false,
				'required'        => false,
			),
			'heureka'                  => array(
				'name'            => 'Heureka – Ověřeno zákazníky',
				'provider'        => 'Heureka',
				'category'        => 'marketing',
				'description'     => array(
					'cs' => 'Měření konverzí Heureka a služba Ověřeno zákazníky (relevantní pro e-shopy).',
					'en' => 'Heureka conversion measurement and the "Verified by customers" service (relevant for e-shops).',
				),
				'cookies'         => array( 'heureka_*' ),
				'domains'         => array( 'heureka.cz', 'heureka.sk' ),
				'gcm'             => array( 'ad_storage' ),
				'iframe'          => false,
				'default_enabled' => false,
				'required'        => false,
			),
		);

		/**
		 * Filters the default service database. Add or modify services here.
		 *
		 * @param array $services Service definitions keyed by slug.
		 */
		return (array) apply_filters( 'czcc_default_services', $services );
	}

	/**
	 * Effective service list: defaults + admin overrides + custom services.
	 *
	 * @param array $settings Plugin settings.
	 * @return array<string, array>
	 */
	public static function effective( array $settings ) {
		$services = self::defaults();

		// Custom services defined in admin (validated on save).
		if ( ! empty( $settings['custom_services'] ) && is_array( $settings['custom_services'] ) ) {
			foreach ( $settings['custom_services'] as $slug => $service ) {
				$services[ $slug ] = wp_parse_args(
					$service,
					array(
						'name'            => $slug,
						'provider'        => '',
						'category'        => 'marketing',
						'description'     => array(),
						'cookies'         => array(),
						'domains'         => array(),
						'gcm'             => array(),
						'iframe'          => false,
						'default_enabled' => true,
						'required'        => false,
					)
				);
			}
		}

		// Per-service admin overrides: enabled flag + category override.
		$overrides = isset( $settings['service_overrides'] ) && is_array( $settings['service_overrides'] ) ? $settings['service_overrides'] : array();
		foreach ( $services as $slug => &$service ) {
			$service['enabled'] = ! empty( $service['default_enabled'] );
			if ( isset( $overrides[ $slug ]['enabled'] ) ) {
				$service['enabled'] = (bool) $overrides[ $slug ]['enabled'];
			}
			if ( ! empty( $overrides[ $slug ]['category'] ) && in_array( $overrides[ $slug ]['category'], self::categories(), true ) ) {
				$service['category'] = $overrides[ $slug ]['category'];
			}
			if ( ! empty( $service['required'] ) ) {
				$service['enabled'] = true;
			}
		}
		unset( $service );

		/**
		 * Filters the effective service list after admin overrides.
		 *
		 * @param array $services Services keyed by slug.
		 * @param array $settings Plugin settings.
		 */
		return (array) apply_filters( 'czcc_services', $services, $settings );
	}

	/**
	 * Enabled services grouped by category.
	 *
	 * @param array $settings Plugin settings.
	 * @return array<string, array<string, array>>
	 */
	public static function enabled_by_category( array $settings ) {
		$grouped = array();
		foreach ( self::effective( $settings ) as $slug => $service ) {
			if ( empty( $service['enabled'] ) ) {
				continue;
			}
			$cat = isset( $service['category'] ) ? $service['category'] : 'marketing';
			if ( ! in_array( $cat, self::categories(), true ) ) {
				$cat = 'marketing';
			}
			$grouped[ $cat ][ $slug ] = $service;
		}
		return $grouped;
	}

	/**
	 * All known service slugs (for REST validation).
	 *
	 * @param array $settings Plugin settings.
	 * @return string[]
	 */
	public static function known_slugs( array $settings ) {
		return array_keys( self::effective( $settings ) );
	}
}
