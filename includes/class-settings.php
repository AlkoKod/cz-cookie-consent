<?php
/**
 * Plugin settings (per-site option).
 *
 * Settings are stored per site even on multisite: banner texts, languages
 * and enabled services typically differ between network sites. The consent
 * log table and the IP-hash salt are the only network-global pieces.
 *
 * @package CZ_Cookie_Consent
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings access + sanitization.
 */
class CZCC_Settings {

	const OPTION = 'czcc_settings';

	/**
	 * Cached settings for the current site.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'consent_duration_days'    => 182,
			'config_version'           => 1,
			'banner_layout'            => 'box inline',
			'banner_position'          => 'bottom left',
			'preferences_layout'       => 'box',
			'show_reject_button'       => true,
			'equal_weight_buttons'     => true,
			'flip_buttons'             => false,
			'disable_page_interaction' => false,
			'hide_from_bots'           => true,
			'functionality_default'    => 'denied',
			'url_passthrough'          => false,
			'ads_data_redaction'       => true,
			'wait_for_update'          => 500,
			'enabled_categories'       => array( 'functional', 'preferences', 'analytics', 'marketing' ),
			'service_overrides'        => array(),
			'custom_services'          => array(),
			'iframe_rules'             => array(
				'youtube'         => array(
					'enabled'  => true,
					'category' => 'marketing',
				),
				'google-maps'     => array(
					'enabled'  => true,
					'category' => 'functional',
				),
				'facebook-embed'  => array(
					'enabled'  => true,
					'category' => 'marketing',
				),
				'instagram-embed' => array(
					'enabled'  => true,
					'category' => 'marketing',
				),
			),
			'auto_wrap_iframes'        => true,
			'load_thumbnails'          => false,
			'texts'                    => CZCC_I18n::default_texts(),
			'privacy_policy_url'       => array(
				'cs' => '',
				'en' => '',
			),
			'gtm4wp_suppress_default'  => true,
			'auto_purge_days'          => 0,
			'debug'                    => false,
			'delete_on_uninstall'      => false,
		);
	}

	/**
	 * Returns merged settings for the current site.
	 *
	 * @return array
	 */
	public static function get() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$defaults = self::defaults();
		$saved    = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		$settings = array_merge( $defaults, $saved );

		// Merge texts per language so new text keys get their defaults.
		$settings['texts'] = self::merge_texts( $defaults['texts'], isset( $saved['texts'] ) && is_array( $saved['texts'] ) ? $saved['texts'] : array() );

		/**
		 * Filters the effective plugin settings.
		 *
		 * @param array $settings Settings for the current site.
		 */
		self::$cache = (array) apply_filters( 'czcc_settings', $settings );

		return self::$cache;
	}

	/**
	 * Persists settings and clears the cache.
	 *
	 * @param array $settings Sanitized settings.
	 */
	public static function update( array $settings ) {
		update_option( self::OPTION, $settings );
		self::$cache = null;
	}

	/**
	 * Clears the per-request cache (must be called around switch_to_blog()).
	 */
	public static function flush_cache() {
		self::$cache = null;
	}

	/**
	 * Merges saved texts over defaults, keeping custom languages.
	 *
	 * @param array $defaults Default texts per language.
	 * @param array $saved    Saved texts per language.
	 * @return array
	 */
	private static function merge_texts( array $defaults, array $saved ) {
		$texts = $defaults;
		foreach ( $saved as $lang => $lang_texts ) {
			if ( ! is_array( $lang_texts ) ) {
				continue;
			}
			$base           = isset( $texts[ $lang ] ) ? $texts[ $lang ] : ( isset( $defaults['en'] ) ? $defaults['en'] : array() );
			$texts[ $lang ] = array_merge( $base, $lang_texts );
		}
		return $texts;
	}

	/**
	 * Texts for a language, falling back to English, then Czech.
	 *
	 * @param string $lang Two-letter language code.
	 * @return array<string, string>
	 */
	public static function texts_for_language( $lang ) {
		$settings = self::get();
		$texts    = $settings['texts'];

		if ( isset( $texts[ $lang ] ) ) {
			return $texts[ $lang ];
		}
		if ( isset( $texts['en'] ) ) {
			return $texts['en'];
		}
		return reset( $texts );
	}

	/**
	 * Sanitizes a settings array coming from the admin form.
	 *
	 * @param array $input Raw input.
	 * @return array Sanitized settings.
	 */
	public static function sanitize( array $input ) {
		$defaults = self::defaults();
		$current  = self::get();
		$clean    = $current;

		$clean['consent_duration_days'] = isset( $input['consent_duration_days'] ) ? max( 1, min( 730, (int) $input['consent_duration_days'] ) ) : $defaults['consent_duration_days'];
		$clean['config_version']        = isset( $input['config_version'] ) ? max( 1, (int) $input['config_version'] ) : $defaults['config_version'];
		$clean['wait_for_update']       = isset( $input['wait_for_update'] ) ? max( 0, min( 10000, (int) $input['wait_for_update'] ) ) : $defaults['wait_for_update'];
		$clean['auto_purge_days']       = isset( $input['auto_purge_days'] ) ? max( 0, min( 3650, (int) $input['auto_purge_days'] ) ) : 0;

		$layouts                 = array( 'box', 'box inline', 'box wide', 'cloud', 'cloud inline', 'bar', 'bar inline' );
		$positions               = array( 'bottom left', 'bottom center', 'bottom right', 'middle left', 'middle center', 'middle right', 'top left', 'top center', 'top right' );
		$pref_layouts            = array( 'box', 'bar', 'bar wide' );
		$clean['banner_layout']  = ( isset( $input['banner_layout'] ) && in_array( $input['banner_layout'], $layouts, true ) ) ? $input['banner_layout'] : $defaults['banner_layout'];
		$clean['banner_position'] = ( isset( $input['banner_position'] ) && in_array( $input['banner_position'], $positions, true ) ) ? $input['banner_position'] : $defaults['banner_position'];
		$clean['preferences_layout'] = ( isset( $input['preferences_layout'] ) && in_array( $input['preferences_layout'], $pref_layouts, true ) ) ? $input['preferences_layout'] : $defaults['preferences_layout'];

		foreach ( array( 'show_reject_button', 'equal_weight_buttons', 'flip_buttons', 'disable_page_interaction', 'hide_from_bots', 'url_passthrough', 'ads_data_redaction', 'auto_wrap_iframes', 'load_thumbnails', 'gtm4wp_suppress_default', 'debug', 'delete_on_uninstall' ) as $flag ) {
			$clean[ $flag ] = ! empty( $input[ $flag ] );
		}

		$clean['functionality_default'] = ( isset( $input['functionality_default'] ) && 'granted' === $input['functionality_default'] ) ? 'granted' : 'denied';

		// Categories: necessary is implicit and always on.
		$valid_categories            = array_diff( CZCC_Service_Registry::categories(), array( 'necessary' ) );
		$clean['enabled_categories'] = array();
		if ( isset( $input['enabled_categories'] ) && is_array( $input['enabled_categories'] ) ) {
			foreach ( $input['enabled_categories'] as $cat ) {
				if ( in_array( $cat, $valid_categories, true ) ) {
					$clean['enabled_categories'][] = $cat;
				}
			}
		}

		// Service overrides.
		$clean['service_overrides'] = array();
		if ( isset( $input['service_overrides'] ) && is_array( $input['service_overrides'] ) ) {
			$all_categories = CZCC_Service_Registry::categories();
			foreach ( $input['service_overrides'] as $slug => $override ) {
				$slug = sanitize_key( $slug );
				if ( '' === $slug || ! is_array( $override ) ) {
					continue;
				}
				$entry = array( 'enabled' => ! empty( $override['enabled'] ) );
				if ( ! empty( $override['category'] ) && in_array( $override['category'], $all_categories, true ) ) {
					$entry['category'] = $override['category'];
				}
				$clean['service_overrides'][ $slug ] = $entry;
			}
		}

		// Custom services (JSON blob from admin; input is already unslashed).
		if ( isset( $input['custom_services_json'] ) && '' !== trim( (string) $input['custom_services_json'] ) ) {
			$decoded = json_decode( (string) $input['custom_services_json'], true );
			if ( is_array( $decoded ) ) {
				$clean['custom_services'] = self::sanitize_custom_services( $decoded );
			}
			// Invalid JSON: keep the previous value instead of wiping it.
		} elseif ( array_key_exists( 'custom_services_json', $input ) ) {
			$clean['custom_services'] = array();
		}

		// Iframe rules.
		if ( isset( $input['iframe_rules'] ) && is_array( $input['iframe_rules'] ) ) {
			$clean['iframe_rules'] = array();
			$all_categories        = CZCC_Service_Registry::categories();
			foreach ( $input['iframe_rules'] as $slug => $rule ) {
				$slug = sanitize_key( $slug );
				if ( '' === $slug || ! is_array( $rule ) ) {
					continue;
				}
				$category = ( ! empty( $rule['category'] ) && in_array( $rule['category'], $all_categories, true ) ) ? $rule['category'] : 'marketing';
				$clean['iframe_rules'][ $slug ] = array(
					'enabled'  => ! empty( $rule['enabled'] ),
					'category' => $category,
				);
			}
		}

		// Texts per language.
		if ( isset( $input['texts'] ) && is_array( $input['texts'] ) ) {
			$clean['texts'] = $current['texts'];
			$text_keys      = CZCC_I18n::text_keys();
			foreach ( $input['texts'] as $lang => $lang_texts ) {
				$lang = strtolower( preg_replace( '/[^a-z]/', '', substr( (string) $lang, 0, 2 ) ) );
				if ( '' === $lang || ! is_array( $lang_texts ) ) {
					continue;
				}
				foreach ( $text_keys as $key ) {
					if ( isset( $lang_texts[ $key ] ) ) {
						$clean['texts'][ $lang ][ $key ] = wp_kses_post( (string) $lang_texts[ $key ] );
					}
				}
			}
		}

		// Removing a language.
		if ( ! empty( $input['remove_language'] ) ) {
			$remove = strtolower( preg_replace( '/[^a-z]/', '', (string) $input['remove_language'] ) );
			if ( $remove && ! in_array( $remove, array( 'cs', 'en' ), true ) ) {
				unset( $clean['texts'][ $remove ], $clean['privacy_policy_url'][ $remove ] );
			}
		}

		// Adding a language: copy English defaults as a starting point.
		if ( ! empty( $input['add_language'] ) ) {
			$new = strtolower( preg_replace( '/[^a-z]/', '', substr( (string) $input['add_language'], 0, 2 ) ) );
			if ( $new && ! isset( $clean['texts'][ $new ] ) ) {
				$clean['texts'][ $new ] = CZCC_I18n::default_texts()['en'];
			}
		}

		// Privacy policy URLs per language.
		if ( isset( $input['privacy_policy_url'] ) && is_array( $input['privacy_policy_url'] ) ) {
			$clean['privacy_policy_url'] = array();
			foreach ( $input['privacy_policy_url'] as $lang => $url ) {
				$lang = strtolower( preg_replace( '/[^a-z]/', '', substr( (string) $lang, 0, 2 ) ) );
				if ( $lang ) {
					$clean['privacy_policy_url'][ $lang ] = esc_url_raw( (string) $url );
				}
			}
		}

		return $clean;
	}

	/**
	 * Sanitizes the custom services structure.
	 *
	 * @param array $services Raw decoded JSON.
	 * @return array
	 */
	private static function sanitize_custom_services( array $services ) {
		$clean      = array();
		$categories = CZCC_Service_Registry::categories();
		$signals    = CZCC_Service_Registry::gcm_signals();

		foreach ( $services as $slug => $service ) {
			$slug = sanitize_key( is_string( $slug ) ? $slug : '' );
			if ( '' === $slug || ! is_array( $service ) ) {
				continue;
			}

			$entry = array(
				'name'            => isset( $service['name'] ) ? sanitize_text_field( (string) $service['name'] ) : $slug,
				'provider'        => isset( $service['provider'] ) ? sanitize_text_field( (string) $service['provider'] ) : '',
				'category'        => ( isset( $service['category'] ) && in_array( $service['category'], $categories, true ) ) ? $service['category'] : 'marketing',
				'description'     => array(),
				'cookies'         => array(),
				'domains'         => array(),
				'gcm'             => array(),
				'iframe'          => ! empty( $service['iframe'] ),
				'default_enabled' => ! isset( $service['default_enabled'] ) || ! empty( $service['default_enabled'] ),
				'required'        => ! empty( $service['required'] ),
			);

			if ( isset( $service['description'] ) && is_array( $service['description'] ) ) {
				foreach ( $service['description'] as $lang => $text ) {
					$lang = strtolower( preg_replace( '/[^a-z]/', '', substr( (string) $lang, 0, 2 ) ) );
					if ( $lang ) {
						$entry['description'][ $lang ] = sanitize_text_field( (string) $text );
					}
				}
			}
			foreach ( array( 'cookies', 'domains' ) as $list_key ) {
				if ( isset( $service[ $list_key ] ) && is_array( $service[ $list_key ] ) ) {
					foreach ( $service[ $list_key ] as $item ) {
						$item = sanitize_text_field( (string) $item );
						if ( '' !== $item ) {
							$entry[ $list_key ][] = $item;
						}
					}
				}
			}
			if ( isset( $service['gcm'] ) && is_array( $service['gcm'] ) ) {
				foreach ( $service['gcm'] as $signal ) {
					if ( in_array( $signal, $signals, true ) ) {
						$entry['gcm'][] = $signal;
					}
				}
			}
			if ( $entry['iframe'] && isset( $service['embed_url'] ) ) {
				$entry['embed_url'] = esc_url_raw( (string) $service['embed_url'] );
			}

			$clean[ $slug ] = $entry;
		}

		return $clean;
	}
}
