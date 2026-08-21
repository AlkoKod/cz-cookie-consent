/**
 * CZ Cookie Consent – frontend.
 *
 * Wires Orestbida CookieConsent v3 + iframemanager to Google Consent Mode
 * v2, the dataLayer and the REST consent log. The Consent Mode DEFAULT is
 * printed earlier by an inline <head> script (wp_head priority 0); this
 * file only ever sends "update" commands.
 */
(function () {
	'use strict';

	var cfg = window.CZCC_CFG;
	if (!cfg || typeof window.CookieConsent === 'undefined') {
		return;
	}

	var CC = window.CookieConsent;
	var texts = cfg.texts || {};
	var firstConsentHandled = false;

	function log() {
		if (cfg.debug && window.console) {
			console.log.apply(console, ['[CZCC]'].concat([].slice.call(arguments)));
		}
	}

	function gtagSafe() {
		window.dataLayer = window.dataLayer || [];
		window.gtag = window.gtag || function () { dataLayer.push(arguments); };
		return window.gtag;
	}

	/**
	 * Accepted categories -> Google Consent Mode v2 state.
	 */
	function buildGcm(acceptedCategories) {
		var state = {};
		var sig;
		for (sig in cfg.gcmDefaults) {
			state[sig] = 'denied';
		}
		Object.keys(cfg.gcmMap).forEach(function (cat) {
			if (acceptedCategories.indexOf(cat) > -1) {
				cfg.gcmMap[cat].forEach(function (mapped) {
					state[mapped] = 'granted';
				});
			}
		});
		state.security_storage = 'granted';
		if (cfg.funcDefault === 'granted') {
			state.functionality_storage = 'granted';
		}
		return state;
	}

	function categoryFlags(acceptedCategories) {
		return {
			necessary: true,
			functional: acceptedCategories.indexOf('functional') > -1,
			preferences: acceptedCategories.indexOf('preferences') > -1,
			analytics: acceptedCategories.indexOf('analytics') > -1,
			marketing: acceptedCategories.indexOf('marketing') > -1
		};
	}

	function pushEvent(eventName, source, prefs, gcm) {
		var cookie = CC.getCookie();
		var flags = categoryFlags(prefs.acceptedCategories);
		window.dataLayer = window.dataLayer || [];
		dataLayer.push({
			event: eventName,
			cookie_consent: {
				categories: prefs.acceptedCategories,
				services: prefs.acceptedServices,
				gcm: gcm,
				consent_id: cookie.consentId,
				revision: cfg.revision,
				source: source,
				necessary: flags.necessary,
				functional: flags.functional,
				preferences: flags.preferences,
				analytics: flags.analytics,
				marketing: flags.marketing
			}
		});
		log('dataLayer event', eventName, source);
	}

	function sendToServer(source, prefs, gcm) {
		if (!cfg.rest || !cfg.rest.url || !window.fetch) {
			return;
		}
		var cookie = CC.getCookie();
		var headers = { 'Content-Type': 'application/json' };
		if (cfg.rest.nonce) {
			headers['X-WP-Nonce'] = cfg.rest.nonce;
		}
		fetch(cfg.rest.url, {
			method: 'POST',
			headers: headers,
			keepalive: true,
			body: JSON.stringify({
				uuid: cookie.consentId,
				categories: prefs.acceptedCategories,
				services: prefs.acceptedServices,
				gcm: gcm,
				language: cfg.lang,
				revision: String(cfg.revision),
				source: source
			})
		}).then(function (response) {
			log('consent stored on server', response.status);
		}).catch(function (error) {
			log('consent store failed', error);
		});
	}

	/**
	 * Full consent-action handling: gtag update + dataLayer + server log.
	 */
	function handleConsentAction(source) {
		var prefs = CC.getUserPreferences();
		var gcm = buildGcm(prefs.acceptedCategories);

		gtagSafe()('consent', 'update', gcm);

		var specific = 'cookie_consent_custom';
		if (source === 'accept_all') {
			specific = 'cookie_consent_accept_all';
		} else if (source === 'reject_all') {
			specific = 'cookie_consent_reject_all';
		} else if (source === 'update') {
			specific = null;
		}

		if (specific) {
			pushEvent(specific, source, prefs, gcm);
		}
		pushEvent('cookie_consent_update', source, prefs, gcm);

		sendToServer(source, prefs, gcm);
	}

	function sourceFromAcceptType(acceptType) {
		if (acceptType === 'all') {
			return 'accept_all';
		}
		if (acceptType === 'necessary') {
			return 'reject_all';
		}
		return 'custom_save';
	}

	/* ------------------------------------------------------------------ *
	 * iframemanager
	 * ------------------------------------------------------------------ */

	var im = null;
	var iframeServices = (cfg.iframe && cfg.iframe.services) || {};
	var iframeSlugs = Object.keys(iframeServices);

	function setupIframeManager() {
		if (!iframeSlugs.length || typeof window.iframemanager !== 'function') {
			return;
		}

		// google-maps data-id is the path+query after "/maps". Normalize
		// legacy manual markup that carries a bare pb-value (v1.3.0 docs).
		if (iframeServices['google-maps']) {
			document.querySelectorAll('div[data-service="google-maps"]').forEach(function (div) {
				var id = div.getAttribute('data-id') || '';
				if (id && id.charAt(0) !== '/' && id.charAt(0) !== '?') {
					div.setAttribute('data-id', '/embed?pb=' + id);
				}
			});
		}

		im = window.iframemanager();
		var services = {};
		var imTexts = cfg.iframe.texts || {};

		iframeSlugs.forEach(function (slug) {
			var service = iframeServices[slug];
			var languages = {};
			languages[cfg.lang] = {
				notice: (imTexts.notice || '').replace('{service}', service.label),
				loadBtn: imTexts.loadBtn || 'Load',
				loadAllBtn: imTexts.loadAllBtn || "Don't ask again"
			};
			services[slug] = {
				embedUrl: service.embedUrl,
				iframe: { allow: service.allow || 'fullscreen;' },
				languages: languages
			};
			if (cfg.iframe.loadThumbnails && service.thumbnail) {
				services[slug].thumbnailUrl = service.thumbnail;
			}
		});

		im.run({
			currLang: cfg.lang,
			onChange: function (payload) {
				// A visitor clicked "always allow" on a placeholder:
				// propagate the acceptance to CookieConsent (which in turn
				// triggers onChange -> gtag update + server log).
				if (!payload.eventSource || payload.eventSource.type !== 'click') {
					return;
				}
				if (payload.eventSource.action !== 'accept') {
					return;
				}
				var slug = payload.eventSource.service;
				var category = iframeServices[slug] && iframeServices[slug].category;
				if (!category) {
					return;
				}
				var accepted = CC.getUserPreferences().acceptedServices[category] || [];
				CC.acceptService(accepted.concat(payload.changedServices), category);
			},
			services: services
		});
		log('iframemanager running', iframeSlugs);
	}

	/* ------------------------------------------------------------------ *
	 * CookieConsent configuration
	 * ------------------------------------------------------------------ */

	function cookiePatternToMatcher(pattern) {
		if (typeof pattern !== 'string' || !pattern.length) {
			return null;
		}
		if (pattern.indexOf('*') > -1) {
			var prefix = pattern.replace(/\*/g, '');
			return { name: new RegExp('^' + prefix.replace(/[.+?^${}()|[\]\\]/g, '\\$&')) };
		}
		return { name: pattern };
	}

	function buildCategories() {
		var categories = {};

		Object.keys(cfg.categories).forEach(function (cat) {
			var catCfg = cfg.categories[cat];
			var entry = {};

			if (catCfg.readOnly) {
				entry.enabled = true;
				entry.readOnly = true;
			}

			var services = {};
			var autoClear = [];

			Object.keys(catCfg.services || {}).forEach(function (slug) {
				var service = catCfg.services[slug];
				var serviceEntry = { label: service.label };

				var matchers = (service.cookies || [])
					.map(cookiePatternToMatcher)
					.filter(function (matcher) { return matcher !== null; });
				if (matchers.length) {
					serviceEntry.cookies = matchers;
					autoClear = autoClear.concat(matchers);
				}

				if (service.iframe && iframeServices[slug]) {
					serviceEntry.onAccept = function () {
						if (im) { im.acceptService(slug); }
					};
					serviceEntry.onReject = function () {
						if (im) { im.rejectService(slug); }
					};
				}

				services[slug] = serviceEntry;
			});

			if (Object.keys(services).length) {
				entry.services = services;
			}
			if (autoClear.length && !catCfg.readOnly) {
				entry.autoClear = { cookies: autoClear, reloadPage: false };
			}

			categories[cat] = entry;
		});

		return categories;
	}

	function buildTranslations() {
		var footer = '';
		if (cfg.privacyUrl) {
			footer = '<a href="' + cfg.privacyUrl + '">' + (texts.privacy_link_text || 'Privacy policy') + '</a>';
		}

		var consentModal = {
			title: texts.banner_title || '',
			description: (texts.banner_description || '') + ' {{revisionMessage}}',
			revisionMessage: texts.revision_message || '',
			acceptAllBtn: texts.accept_all || 'Accept all',
			showPreferencesBtn: texts.show_preferences || 'Settings',
			footer: footer
		};
		if (cfg.showReject) {
			consentModal.acceptNecessaryBtn = texts.reject_all || 'Reject optional';
		}

		var sections = [{
			title: texts.preferences_title || '',
			description: texts.preferences_intro || ''
		}];

		Object.keys(cfg.categories).forEach(function (cat) {
			sections.push({
				title: texts['cat_' + cat + '_title'] || cat,
				description: texts['cat_' + cat + '_desc'] || '',
				linkedCategory: cat
			});
		});

		var translation = {
			consentModal: consentModal,
			preferencesModal: {
				title: texts.preferences_title || '',
				acceptAllBtn: texts.accept_all || 'Accept all',
				acceptNecessaryBtn: texts.reject_all || 'Reject optional',
				savePreferencesBtn: texts.save_preferences || 'Save preferences',
				closeIconLabel: texts.close || 'Close',
				serviceCounterLabel: 'service|services',
				sections: sections
			}
		};

		var translations = {};
		translations[cfg.lang] = translation;
		return translations;
	}

	/* ------------------------------------------------------------------ *
	 * Run
	 * ------------------------------------------------------------------ */

	setupIframeManager();

	CC.run({
		cookie: {
			name: cfg.cookieName,
			expiresAfterDays: cfg.durationDays,
			sameSite: 'Lax'
		},
		revision: cfg.revision,
		disablePageInteraction: !!cfg.pageBlock,
		hideFromBots: !!cfg.hideFromBots,

		guiOptions: {
			consentModal: {
				layout: cfg.gui.layout,
				position: cfg.gui.position,
				equalWeightButtons: !!cfg.gui.equalWeight,
				flipButtons: !!cfg.gui.flip
			},
			preferencesModal: {
				layout: cfg.gui.prefLayout,
				equalWeightButtons: !!cfg.gui.equalWeight,
				flipButtons: !!cfg.gui.flip
			}
		},

		categories: buildCategories(),

		language: {
			default: cfg.lang,
			translations: buildTranslations()
		},

		onFirstConsent: function (param) {
			firstConsentHandled = true;
			var source = sourceFromAcceptType(CC.getUserPreferences().acceptType);
			log('onFirstConsent', source, param.cookie);
			handleConsentAction(source);
		},

		onConsent: function () {
			// Fires on every page load with valid consent (and right after
			// onFirstConsent). The head script already re-applied the stored
			// state synchronously; here we only re-sync as a safety net for
			// returning visitors. Service onAccept callbacks (iframes) run
			// automatically.
			if (firstConsentHandled) {
				return;
			}
			var prefs = CC.getUserPreferences();
			gtagSafe()('consent', 'update', buildGcm(prefs.acceptedCategories));
			log('onConsent (returning visitor)', prefs.acceptedCategories);
		},

		onChange: function (param) {
			log('onChange', param.changedCategories, param.changedServices);
			handleConsentAction('update');
		}
	});

	log('CookieConsent running', { lang: cfg.lang, revision: cfg.revision });
})();
