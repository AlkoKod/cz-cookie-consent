# CZ Cookie Consent

WordPress plugin pro cookie lištu s **Google Consent Mode v2**, logováním souhlasů do vlastní DB tabulky, podporou **multisite** a kompatibilitou s **GTM4WP** (1.x i 2.x). Veškeré měřicí/reklamní skripty se spouštějí přes **Google Tag Manager** – plugin je nevkládá, pouze řídí consent stav a dataLayer.

> ⚠️ **Právní upozornění:** Plugin je technický nástroj. Nezaručuje sám o sobě soulad s GDPR/ePrivacy – finální texty, kategorie a právní nastavení musí posoudit právník.

## Použité knihovny a proč

| Knihovna | Verze | Role |
|---|---|---|
| [Orestbida CookieConsent](https://github.com/orestbida/cookieconsent) | 3.1.0 | Banner, kategorie, služby, ukládání souhlasu do cookie |
| [Orestbida iframemanager](https://github.com/orestbida/iframemanager) | 1.3.0 | Blokování iframe (YouTube, Mapy, FB/IG embedy) do souhlasu |

**Doporučení: Orestbida CookieConsent + iframemanager** (nikoliv Klaro):

- CookieConsent v3 má nativní koncept **kategorií i služeb** s per-service toggly – přesně odpovídá zadání.
- `revision` mechanismus řeší re-consent při změně konfigurace.
- Obě knihovny jsou od stejného autora a mají **dokumentovanou vzájemnou integraci** (service `onAccept`/`onReject` ↔ `im.acceptService()`).
- Jsou malé (~24 kB + ~6 kB JS), bez závislostí, MIT licence, aktivně udržované.
- Klaro je robustní, ale jeho model „služeb" je primárně postaven na správě skriptů v pluginu – my skripty řídíme v GTM, takže by většina Klaro funkcionality ležela ladem a chyběl by ekvivalent iframemanageru.

## Architektura

```
cz-cookie-consent/
  cz-cookie-consent.php          – bootstrap, konstanty, hooky
  uninstall.php                  – volitelný úklid dat
  includes/
    class-plugin.php             – orchestrátor (singleton)
    class-activator.php          – aktivace, cron údržba
    class-db.php                 – schéma, dbDelta migrace, verzování
    class-consent-repository.php – zápis/čtení/purge/export souhlasů
    class-settings.php           – nastavení + sanitizace
    class-service-registry.php   – výchozí databáze služeb + custom služby
    class-rest-controller.php    – REST endpoint POST /czcc/v1/consent
    class-frontend.php           – consent default v <head>, assety, iframe wrap
    class-admin.php              – admin UI (taby, log, CSV, nástroje)
    class-consent-log-table.php  – WP_List_Table logu
    class-i18n.php               – textdomain + výchozí texty cs/en
  assets/
    js/frontend.js               – integrace CookieConsent+iframemanager+GCM
    js/admin.js, css/…           – admin drobnosti
    vendor/cookieconsent/        – CookieConsent 3.1.0 (bundlované)
    vendor/iframemanager/        – iframemanager 1.3.0 (bundlované)
  languages/                     – .pot šablona
  docs/                          – GTM návod, testovací scénáře
```

### Tok souhlasu

1. **`wp_head` priorita 0** – inline skript (bez závislostí):
   - vytvoří `dataLayer` + `gtag` stub,
   - pošle `gtag('consent','default', …)` – vše `denied` kromě `security_storage: granted` (`functionality_storage` dle nastavení),
   - volitelně `ads_data_redaction` / `url_passthrough`,
   - **synchronně přečte cookie `czcc_consent`** – vracejícímu se návštěvníkovi ihned pošle `gtag('consent','update', …)` (tagy tedy nikdy nestartují se špatným stavem, ani se nečeká na načtení banner bundle),
   - pushne event **`cookie_consent_default`**.
2. GTM4WP (priorita 1/2/10) vloží dataLayer init a GTM kontejner – **vždy až po našem defaultu**.
3. Po interakci s bannerem (frontend.js): `gtag('consent','update')` → dataLayer event → uložení na server přes REST.

## Databáze souhlasů

**Jedna globální tabulka** `{base_prefix}czcc_consents` se sloupcem `blog_id` (na single-site vždy 1).

Proč globální a ne per-site: jedna migrace pro celou síť, žádné zakládání tabulek při vzniku nového webu, možnost síťového reportu; scoping je vynucen `blog_id` ve **všech** dotazech repozitáře. Nevýhoda (větší tabulka) je řešena indexem na `blog_id` a auto-purge.

| Sloupec | Popis |
|---|---|
| `id` | PK |
| `blog_id` | web v síti |
| `consent_uuid` | anonymní ID souhlasu (consentId z cookie) |
| `user_id` | jen u přihlášených |
| `ip_hash`, `ua_hash` | SHA-256 se site-wide salt – **nikdy surová IP/UA** |
| `categories`, `services` | JSON přijatých kategorií/služeb |
| `gcm_*` (7 sloupců) | ad_storage, analytics_storage, ad_user_data, ad_personalization, functionality_storage, personalization_storage, security_storage |
| `language`, `config_version`, `source` | jazyk, revize konfigurace, akce (`accept_all` / `reject_all` / `custom_save` / `update`) |
| `created_at`, `expires_at` | vytvoření a expirace (dle nastavené platnosti, výchozí 182 dní) |

Schéma je verzované (`czcc_db_version` site option) a migruje se přes `dbDelta()` – při aktivaci i po updatu pluginu.

## Google Consent Mode v2

- Default: `ad_storage`, `analytics_storage`, `ad_user_data`, `ad_personalization`, `personalization_storage` = **denied**; `security_storage` = **granted**; `functionality_storage` dle nastavení (výchozí denied).
- `wait_for_update` konfigurovatelné (výchozí 500 ms).
- Mapping kategorií → signálů (filtrovatelný přes `czcc_gcm_map`):

| Kategorie | Signály |
|---|---|
| necessary | security_storage |
| functional | functionality_storage |
| preferences | personalization_storage |
| analytics | analytics_storage |
| marketing | ad_storage, ad_user_data, ad_personalization |

## Kompatibilita s GTM4WP (ověřeno proti zdrojáku)

| GTM4WP | Chování | Řešení pluginu |
|---|---|---|
| 2.x | dataLayer init na `wp_head` prio **1**, kontejner prio **2** („load early") nebo **10**; consent default blok lze potlačit filtrem | náš default na prio **0** + `add_filter('gtm4wp_consent_mode_default_enabled', '__return_false')` |
| 1.x (≥1.22) | vše na prio 10/2, consent blok bez vypínacího filtru | náš default na prio 0 + filtr `gtm4wp_overwrite_consent_mode_flag` srovná hodnoty GTM4WP defaultu s našimi (nemohou se rozjet) |
| neaktivní | – | plugin funguje samostatně, GTM je nutné vložit jinak |

Obojí lze vypnout checkboxem *GTM4WP compatibility* v nastavení.

## dataLayer eventy

| Event | Kdy |
|---|---|
| `cookie_consent_default` | při každém načtení stránky (z `<head>`, se stavem `pending`/`stored`) |
| `cookie_consent_accept_all` / `cookie_consent_reject_all` / `cookie_consent_custom` | první volba / změna typu volby |
| `cookie_consent_update` | **při každé akci se souhlasem** – doporučený trigger pro tagy |

Objekt `cookie_consent` v každém eventu: `categories[]`, `services{}`, `gcm{}`, `consent_id`, `revision`, `source` + ploché booleany `necessary/functional/preferences/analytics/marketing` (snadné GTM proměnné pro tagy bez podpory Consent Mode, např. Sklik).

Detailní návod na GTM triggery/proměnné: **[docs/gtm-setup.md](docs/gtm-setup.md)**.

## Blokování iframe

- Auto-obalení známých iframe v obsahu (YouTube, Google Maps embed, Facebook plugins, Instagram) na `<div data-service="…" data-id="…">` – lze vypnout.
- Ruční markup: `<div data-service="youtube" data-id="VIDEO_ID" data-autoscale></div>`.
- Každé službě lze v adminu nastavit **kategorii, která ji odblokuje** (YouTube → marketing *nebo* functional atd.).
- Placeholder s textem a tlačítky „Povolit obsah" / „Povolit vždy"; „Povolit vždy" propíše souhlas zpět do CookieConsent (a tedy do GCM + server logu).
- Náhledy videí se ve výchozím stavu **nenačítají** (únik IP na servery třetí strany před souhlasem); lze zapnout.
- Odblokování po souhlasu proběhne **bez reloadu** (service `onAccept` → `im.acceptService()`).

## REST API

`POST /wp-json/czcc/v1/consent` – veřejný endpoint (souhlasy anonymních návštěvníků + cachované stránky ⇒ nelze stavět na nonce), chráněný:
- striktní validací proti známé konfiguraci (kategorie, služby, GCM signály, uuid, jazyk, source),
- rate limitem 20 požadavků / 10 min / IP-hash,
- endpoint umí **pouze přidat záznam do logu** – žádná privilegia.

U přihlášených uživatelů se posílá standardní REST nonce a záznam se přiřadí `user_id`.

## Admin

**Nastavení → Cookie Consent** – taby: General (platnost, revize, layout, GCM, purge), Categories & services (kategorie, zap/vyp služeb, přesuny kategorií, custom služby přes JSON), Texts (texty per jazyk + přidání jazyka; WPML/Polylang přes locale), Iframe blocking, Consent log (filtry, hledání, **CSV export**, mazání expirovaných/vše), Tools & debug (stav, detekce GTM4WP, dump konfigurace).

Na multisite navíc **Network admin → Settings → Cookie Consent Log** (log + CSV napříč weby).

### Síťové nastavení (multisite)

**Network admin → Settings → Cookie Consent** umožňuje spravovat konfiguraci celé sítě z jednoho místa. Tři režimy:

| Režim | Chování |
|---|---|
| **Off** (výchozí) | Každý web se nastavuje samostatně (chování 1.0.x). |
| **Network defaults** | Weby dědí síťovou konfiguraci, dokud si neuloží vlastní. Uložení per-site formuláře vytvoří kompletní přepis; tlačítko *Reset to network defaults* jej smaže a web opět dědí. Stav (dědí / přepisuje) je vidět v notice na stránce nastavení i v tabu Tools. |
| **Enforce** | Všude platí síťová konfigurace; per-site nastavení je zamčené (webu zůstává jen Consent log a Tools) a ukládání je blokované i na úrovni handleru. |

Síťová stránka má stejné taby (General / Categories & services / Texts / Iframe blocking) jako per-site nastavení. Log souhlasů zůstává vždy per-site + síťový přehled.

## Instalace

1. Nahrajte složku do `wp-content/plugins/` (nebo nainstalujte ZIP).
2. Aktivujte (per-site nebo network-wide – obojí podporováno).
3. Projděte **Nastavení → Cookie Consent** (texty, služby, iframe pravidla).
4. V GTM nastavte consent checks dle [docs/gtm-setup.md](docs/gtm-setup.md).
5. V GTM4WP 1.x doporučeně vypněte jeho „Google Consent Mode" integraci (2.x se řeší automaticky; 1.x plugin jistí zarovnáním hodnot).

## Riziková místa (na co si dát pozor)

1. **Timing Consent Mode defaultu vůči GTM4WP** – default musí být před GTM kontejnerem. Řešeno prioritou 0 na `wp_head`; pokud jiný plugin/šablona vkládá GTM ještě dříve (např. natvrdo v `header.php` před `wp_head()`), default se k němu nestihne – takové instalace je nutné upravit.
2. **Rozdíly GTM4WP 1.x vs 2.x** – 2.x je zatím RC; názvy filtrů ověřeny proti masteru (`gtm4wp_consent_mode_default_enabled`, `gtm4wp_overwrite_consent_mode_flag`). Po vydání finální 2.x doporučuji rychlou re-verifikaci.
3. **Full-page cache** – stránky jsou cachované pro všechny návštěvníky stejně; proto se stav souhlasu čte **client-side** z cookie a nikdy se nerendruje do HTML. REST endpoint z téhož důvodu nepoužívá nonce pro anonymy.
4. **Multisite** – souhlasy odděluje `blog_id`; per-site nastavení texty/služby. Cookie platí per doména – na subdoménových/mapovaných sítích je souhlas přirozeně oddělený, na /cesta/ sítích sdílí doménu (cookie path je `/`), tzn. souhlas platí pro celou doménu.
5. **Blokování iframe** – auto-wrap pokrývá iframe embedy; Instagram/Twitter embedy vkládané jako `<blockquote>` + skript auto-wrap nezachytí (skript by stejně měl být řízen přes GTM) – použijte ruční markup.
6. **Sklik a další nástroje bez podpory Consent Mode** – GCM je neblokuje; musí se podmínit triggerem na `cookie_consent.marketing` (viz GTM návod).
7. **Právní limity** – plugin nesbírá důkaz o obsahu lišty v čase souhlasu; uchovává verzi konfigurace (`config_version`), kterou je nutné při každé významné změně **ručně zvýšit** (vynutí re-consent a spáruje log s verzí).

## Testovací scénáře

Viz [docs/testing.md](docs/testing.md) – pokrývá první návštěvu, přijmout/odmítnout/custom, návrat s platným souhlasem, expiraci, změnu revize, iframe flow, GTM4WP 1.x/2.x, multisite a REST validace/rate limit.

## Licence

Plugin: GPL v2 or later. Bundlované knihovny CookieConsent a iframemanager: MIT (© Orest Bida).
