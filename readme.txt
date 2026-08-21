=== CZ Cookie Consent ===
Contributors: innovativebusiness
Tags: cookie consent, gdpr, google consent mode, gtm, multisite
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Cookie consent banner with Google Consent Mode v2, consent logging, multisite support and GTM4WP compatibility.

== Description ==

CZ Cookie Consent is a cookie consent plugin built on the Orestbida CookieConsent v3 and iframemanager libraries.

* Google Consent Mode v2: default state printed at wp_head priority 0, before any GTM output; stored consent re-applied synchronously for returning visitors.
* GTM4WP compatible (1.x and 2.x) - the plugin suppresses/aligns GTM4WP's own consent default block.
* All marketing/analytics tags are managed in Google Tag Manager; the plugin only manages consent state, dataLayer events and iframe blocking.
* Consent logging into a custom database table (works on multisite with a single global table scoped by blog_id). No raw IP addresses are stored - only salted hashes.
* Categories: necessary (always on), functional, preferences, analytics, marketing.
* Built-in service database: Google (GTM, GA4, Ads, Conversion Linker, Fonts, YouTube, Maps, reCAPTCHA), Meta (Pixel, Ads, Facebook/Instagram embeds), Seznam.cz (Sklik, retargeting, Zbozi.cz), LinkedIn, TikTok, Hotjar, Microsoft Clarity, Heureka.
* Iframe blocking with placeholders (YouTube, Google Maps, Facebook, Instagram) including automatic wrapping of embeds in content.
* Multilingual texts editable in admin (Czech + English shipped, more languages can be added; WPML/Polylang compatible via locale).
* Consent log admin with filtering, CSV export and purging; network-wide log on multisite.
* REST endpoint with strict validation and rate limiting.

Legal note: this plugin is a technical tool and does not by itself guarantee GDPR/ePrivacy compliance. Have the final texts and configuration reviewed by a lawyer.

== Installation ==

1. Upload the plugin folder to /wp-content/plugins/ and activate it (per-site or network-wide).
2. Go to Settings -> Cookie Consent and configure texts, services and iframe rules.
3. Configure consent checks in Google Tag Manager (see docs/gtm-setup.md in the plugin folder).

== Frequently Asked Questions ==

= Does it work without GTM4WP? =

Yes. The plugin manages Consent Mode and the dataLayer regardless of how GTM is inserted. GTM must then be inserted by other means.

= Where are consents stored? =

In a custom global table (base_prefix + czcc_consents) with a blog_id column. IP addresses and user agents are stored only as salted SHA-256 hashes.

== Changelog ==

= 1.1.0 =
* Network-wide configuration on multisite: new Network Admin -> Settings -> Cookie Consent page.
* Two network modes: "Network defaults" (sites inherit until they save their own configuration, with one-click reset back) and "Enforce" (network settings apply everywhere, per-site settings are locked).
* Tools tab now shows the network mode and whether the site inherits or overrides.

= 1.0.0 =
* Initial release.
