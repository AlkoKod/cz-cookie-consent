# Testovací scénáře

Manuální checklist pro ověření pluginu. Testujte v anonymním okně; stav cookie `czcc_consent` lze mazat v DevTools → Application → Cookies.

## A. Consent Mode základ

| # | Scénář | Očekávání |
|---|---|---|
| A1 | První návštěva, zdroj stránky | V `<head>` je `<script id="czcc-consent-default">` PŘED výstupem GTM4WP / GTM |
| A2 | První návštěva, konzole: `dataLayer` | Obsahuje `consent default` (arguments), event `cookie_consent_default` se `status: "pending"` |
| A3 | Klik „Přijmout vše" | `gtag consent update` vše granted (kromě pravidel), eventy `cookie_consent_accept_all` + `cookie_consent_update`, cookie `czcc_consent` existuje |
| A4 | Klik „Odmítnout volitelné" | update: jen `security_storage` granted (+ `functionality_storage` dle nastavení), eventy `cookie_consent_reject_all` + `cookie_consent_update` |
| A5 | Vlastní volba (jen analytics) | `analytics_storage: granted`, ostatní denied; eventy `cookie_consent_custom` + `cookie_consent_update` |
| A6 | Reload po souhlasu | Banner se nezobrazí; v `<head>` skriptu proběhne okamžitý `consent update`; event `cookie_consent_default` má `status: "stored"` a správné kategorie |
| A7 | Změna volby přes `[czcc_preferences]` odkaz | Modal se otevře, po uložení event `cookie_consent_update` se `source: "update"` |

## B. Expirace a revize

| # | Scénář | Očekávání |
|---|---|---|
| B1 | Nastavit platnost na 1 den, ručně posunout `Expires` cookie / smazat cookie | Banner se zobrazí znovu |
| B2 | Zvýšit „Configuration version" v adminu | Banner se zobrazí znovu i s platnou cookie; po potvrzení má nový záznam v logu novou `config_version` |
| B3 | Revize se liší, návštěvník nereaguje | Head skript stored consent NEaplikuje (vše zůstává denied) |

## C. Server log (REST)

| # | Scénář | Očekávání |
|---|---|---|
| C1 | Každá volba na banneru | Nový řádek v Nastavení → Cookie Consent → Consent log (správný source, kategorie, GCM sloupce) |
| C2 | Přihlášený uživatel | Záznam má `user_id` |
| C3 | IP/UA | V DB jen 64znakové hashe, nikdy surová hodnota |
| C4 | POST s neznámou kategorií/službou | Neznámé hodnoty se zahodí (nezapisují se) |
| C5 | POST s nevalidním `gcm` | HTTP 400 |
| C6 | 21+ POSTů z jedné IP za 10 min | HTTP 429 |
| C7 | CSV export | Stáhne se CSV s BOM, otevře se korektně v Excelu |
| C8 | Delete expired / Delete all | Smaže odpovídající záznamy, jen pro `manage_options`, s nonce |

## D. Iframe blokování

| # | Scénář | Očekávání |
|---|---|---|
| D1 | Vložit YouTube video do příspěvku (oEmbed) | Místo iframe se zobrazí placeholder s textem a tlačítky |
| D2 | „Povolit obsah" | Načte se jen tento iframe, consent stav se nemění |
| D3 | „Povolit vždy" | Načtou se všechny iframe služby, souhlas se propíše do CookieConsent (event `cookie_consent_update`) a do server logu |
| D4 | Přijmout kategorii marketing v banneru | YouTube/FB/IG iframy se odblokují bez reloadu |
| D5 | Odvolat souhlas | Iframy se opět zablokují (bez reloadu) |
| D6 | Google Maps embed | Blokován dle kategorie z nastavení (výchozí functional) |
| D7 | Přepnout YouTube pravidlo na functional | YouTube se odblokuje souhlasem s functional místo marketing |
| D8 | Bricks element Map bez API klíče (starý styl `maps.google.com/maps?…&output=embed`) | Iframe je nahrazen placeholderem (filtr `bricks/frontend/render_element`); po souhlasu s functional se mapa načte na stejné adrese |
| D9 | Ruční markup s holou pb hodnotou v `data-id` (v1.3.0 formát) | Frontend JS ho znormalizuje na `/embed?pb=…`, mapa funguje beze změny markupu |
| D10 | Běžný odkaz `google.com/maps?q=…` bez `output=embed` v iframe | NEobaluje se (není to embed) |

## E. GTM4WP

| # | Scénář | Očekávání |
|---|---|---|
| E1 | GTM4WP 2.x aktivní | GTM4WP netiskne vlastní `consent default` blok (filtr); v HTML je jen náš |
| E2 | GTM4WP 1.x aktivní, jeho consent integrace zapnutá | Jeho default blok obsahuje stejné hodnoty jako náš (filtr `gtm4wp_overwrite_consent_mode_flag`) |
| E3 | GTM4WP neaktivní | Plugin funguje, žádné JS chyby; GCM příkazy čekají v dataLayer |
| E4 | Tag Assistant | Consent default PŘED Container Loaded; po souhlasu Consent update |

## F. Multisite

| # | Scénář | Očekávání |
|---|---|---|
| F1 | Network aktivace | Tabulka `wp_czcc_consents` vytvořena jednou; plugin aktivní všude |
| F2 | Souhlas na webu A a B | Záznamy mají správné `blog_id`; site log ukazuje jen vlastní |
| F3 | Network admin → Cookie Consent Log | Vidí záznamy všech webů + sloupec Site; CSV export všech webů jen pro `manage_network_options` |
| F4 | Per-site aktivace na jednom webu sítě | Funguje izolovaně, tabulka sdílená |
| F5 | Odlišné texty/služby na webech | Nastavení se nepřekrývají (per-site option) |

## G. Vícejazyčnost

| # | Scénář | Očekávání |
|---|---|---|
| G1 | Web v češtině | Banner česky (výchozí texty) |
| G2 | Přepnout locale na en_US (nebo WPML/Polylang stránku) | Banner anglicky |
| G3 | Přidat jazyk „de" v adminu, přeložit | Na de stránkách se použijí de texty |
| G4 | Neexistující jazyk | Fallback na en |

## H. Bezpečnost

| # | Scénář | Očekávání |
|---|---|---|
| H1 | Uložení nastavení bez nonce / bez `manage_options` | Odmítnuto |
| H2 | XSS pokus v textech banneru | `wp_kses_post` na vstupu; výstup přes CookieConsent (HTML povoleno jen v popiscích) |
| H3 | SQL injection přes filtry logu | Vše přes `$wpdb->prepare` |
| H4 | Uninstall bez zaškrtnutí „Delete data" | Data zůstávají |
| H5 | Uninstall se zaškrtnutím | Options + řádky webu smazány; tabulka a síťové options dropnuty až když souhlasí všechny weby sítě |

## I. Síťové nastavení (multisite)

| # | Scénář | Očekávání |
|---|---|---|
| I1 | Režim **Off** | Chování jako dřív – weby nezávislé; síťová stránka nastavení existuje, ale neaplikuje se |
| I2 | Režim **Network defaults**, web bez vlastního uložení | Web (admin i frontend banner) používá síťovou konfiguraci; notice „inherits network defaults" |
| I3 | I2 + web uloží vlastní nastavení | Vznikne per-site přepis; notice „uses its own configuration"; síťové změny se na webu už neprojevují |
| I4 | I3 + klik „Reset to network defaults" | Per-site option smazána, web opět dědí; potvrzovací dialog |
| I5 | Režim **Enforce** | Per-site stránka ukazuje jen taby Consent log + Tools s notice; frontend používá síťovou konfiguraci i na webech s dřívějším přepisem |
| I6 | Enforce + přímý POST na czcc_save_settings | Odmítnuto (wp_die), i s platným nonce |
| I7 | Uložení síťové stránky | Jen `manage_network_options` + nonce; redirect zpět na správný tab |
| I8 | Změna režimu na General tabu síťové stránky | Uloží se spolu s nastavením; ostatní taby režim zachovávají (hidden input) |
| I9 | Single-site instalace | `network_mode()` vždy `off`, žádná síťová stránka, nulový dopad |
