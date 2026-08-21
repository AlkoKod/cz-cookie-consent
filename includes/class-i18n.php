<?php
/**
 * Internationalization: text domain + default banner texts.
 *
 * Banner texts are stored in settings (editable per language in admin);
 * this class only ships the defaults (cs + en) and resolves the current
 * frontend language from the WordPress locale, which makes the plugin
 * work out of the box with WPML/Polylang (both switch the locale).
 *
 * @package CZ_Cookie_Consent
 */

defined( 'ABSPATH' ) || exit;

/**
 * i18n helper.
 */
class CZCC_I18n {

	/**
	 * Loads the plugin text domain (admin UI strings).
	 */
	public static function load_textdomain() {
		load_plugin_textdomain( 'cz-cookie-consent', false, dirname( plugin_basename( CZCC_PLUGIN_FILE ) ) . '/languages' );
	}

	/**
	 * Two-letter language code for the current request.
	 *
	 * @return string
	 */
	public static function current_language() {
		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		$lang   = strtolower( substr( (string) $locale, 0, 2 ) );

		/**
		 * Filters the language code used to pick banner texts.
		 *
		 * @param string $lang   Two-letter language code.
		 * @param string $locale Full WordPress locale.
		 */
		return (string) apply_filters( 'czcc_current_language', $lang ? $lang : 'en', $locale );
	}

	/**
	 * Default banner texts for all shipped languages.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function default_texts() {
		return array(
			'cs' => array(
				'banner_title'          => 'Vážíme si vašeho soukromí',
				'banner_description'    => 'Tento web používá cookies k zajištění základních funkcí a se souhlasem také k měření návštěvnosti a personalizaci obsahu či reklamy. Svůj souhlas můžete kdykoli změnit v nastavení cookies.',
				'accept_all'            => 'Přijmout vše',
				'reject_all'            => 'Odmítnout volitelné',
				'show_preferences'      => 'Nastavení',
				'save_preferences'      => 'Uložit volby',
				'close'                 => 'Zavřít',
				'revision_message'      => 'Upravili jsme zásady používání cookies, prosíme o potvrzení vašich voleb.',
				'preferences_title'     => 'Nastavení cookies',
				'preferences_intro'     => 'Zde si můžete nastavit, které kategorie cookies nám dovolíte používat. Nezbytné cookies jsou nutné pro fungování webu a nelze je vypnout.',
				'privacy_link_text'     => 'Zásady zpracování osobních údajů',
				'cat_necessary_title'   => 'Nezbytné',
				'cat_necessary_desc'    => 'Cookies nutné pro základní fungování webu (např. bezpečnost, uložení souhlasu). Nelze je vypnout.',
				'cat_functional_title'  => 'Funkční',
				'cat_functional_desc'   => 'Umožňují rozšířené funkce webu, například vložené mapy nebo videa.',
				'cat_preferences_title' => 'Preferenční',
				'cat_preferences_desc'  => 'Umožňují webu zapamatovat si vaše volby (např. jazyk nebo region) a přizpůsobit obsah.',
				'cat_analytics_title'   => 'Analytické',
				'cat_analytics_desc'    => 'Pomáhají nám pochopit, jak web používáte. Data jsou zpracovávána souhrnně a anonymizovaně.',
				'cat_marketing_title'   => 'Marketingové',
				'cat_marketing_desc'    => 'Používají se pro zobrazování relevantní reklamy a měření její účinnosti (Google Ads, Sklik, Facebook…).',
				'iframe_notice'         => 'Tento obsah je hostován třetí stranou. Jeho zobrazením souhlasíte s podmínkami služby {service}.',
				'iframe_load_btn'       => 'Povolit obsah',
				'iframe_load_all_btn'   => 'Povolit vždy',
			),
			'en' => array(
				'banner_title'          => 'We value your privacy',
				'banner_description'    => 'This website uses cookies to provide essential functionality and, with your consent, to measure traffic and personalize content or ads. You can change your consent at any time in the cookie settings.',
				'accept_all'            => 'Accept all',
				'reject_all'            => 'Reject optional',
				'show_preferences'      => 'Settings',
				'save_preferences'      => 'Save preferences',
				'close'                 => 'Close',
				'revision_message'      => 'Our cookie policy has changed, please confirm your choices again.',
				'preferences_title'     => 'Cookie preferences',
				'preferences_intro'     => 'Here you can choose which cookie categories you allow us to use. Necessary cookies are required for the website to work and cannot be disabled.',
				'privacy_link_text'     => 'Privacy policy',
				'cat_necessary_title'   => 'Necessary',
				'cat_necessary_desc'    => 'Cookies required for the basic operation of the website (e.g. security, storing your consent). They cannot be disabled.',
				'cat_functional_title'  => 'Functional',
				'cat_functional_desc'   => 'Enable enhanced functionality such as embedded maps or videos.',
				'cat_preferences_title' => 'Preferences',
				'cat_preferences_desc'  => 'Allow the website to remember your choices (e.g. language or region) and personalize content.',
				'cat_analytics_title'   => 'Analytics',
				'cat_analytics_desc'    => 'Help us understand how the website is used. Data is processed in aggregate and anonymized form.',
				'cat_marketing_title'   => 'Marketing',
				'cat_marketing_desc'    => 'Used to show relevant ads and measure their performance (Google Ads, Sklik, Facebook…).',
				'iframe_notice'         => 'This content is hosted by a third party. By showing it you accept the terms of service of {service}.',
				'iframe_load_btn'       => 'Load content',
				'iframe_load_all_btn'   => 'Always allow',
			),
		);
	}

	/**
	 * Keys every language text set consists of (used by admin + sanitizer).
	 *
	 * @return string[]
	 */
	public static function text_keys() {
		return array_keys( self::default_texts()['en'] );
	}
}
