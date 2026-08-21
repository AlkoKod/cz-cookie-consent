# Nastavení Google Tag Manageru pro CZ Cookie Consent

Plugin nastavuje **Google Consent Mode v2** a plní **dataLayer**. Tagy se spouštějí výhradně v GTM. Tento návod popisuje doporučené nastavení kontejneru.

## 1. Zapněte přehled souhlasu v GTM

*Admin → Container Settings → zaškrtnout „Enable consent overview"*.

V přehledu (ikona štítu u seznamu tagů) pak uvidíte, který tag má jaké consent požadavky.

## 2. Jak plugin komunikuje s GTM

### Consent Mode (vestavěné chování Google tagů)

- Před načtením GTM: `gtag('consent','default', …)` – vše denied kromě `security_storage`.
- Vracející se návštěvník se souhlasem: okamžitě `gtag('consent','update', …)` (stále před GTM).
- Po interakci s lištou: `gtag('consent','update', …)`.

Google tagy (GA4, Google Ads) na to reagují **automaticky** – není potřeba je blokovat triggerem; stačí v nastavení tagu ponechat *Built-in consent checks*.

### dataLayer eventy

| Event | Kdy | Použití |
|---|---|---|
| `cookie_consent_default` | každé načtení stránky (ihned v `<head>`) | stav `pending`/`stored`; obsahuje aktuální kategorie |
| `cookie_consent_update` | **každá akce se souhlasem** (první volba, změna, „Povolit vždy" na iframe) | hlavní trigger pro spouštění tagů po souhlasu |
| `cookie_consent_accept_all` / `cookie_consent_reject_all` / `cookie_consent_custom` | typ konkrétní volby | měření chování lišty |

Objekt `cookie_consent` (ve všech eventech):

```json
{
  "categories": ["necessary", "analytics"],
  "services": { "analytics": ["google-analytics-4"] },
  "gcm": { "ad_storage": "denied", "analytics_storage": "granted", "…": "…" },
  "consent_id": "abc123…",
  "revision": 1,
  "source": "custom_save",
  "necessary": true,
  "functional": false,
  "preferences": false,
  "analytics": true,
  "marketing": false
}
```

## 3. Doporučené proměnné (Data Layer Variables)

Vytvořte proměnné typu *Data Layer Variable* (verze 2):

| Název proměnné | Data Layer Variable Name |
|---|---|
| `DLV - consent.marketing` | `cookie_consent.marketing` |
| `DLV - consent.analytics` | `cookie_consent.analytics` |
| `DLV - consent.functional` | `cookie_consent.functional` |
| `DLV - consent.preferences` | `cookie_consent.preferences` |
| `DLV - consent.categories` | `cookie_consent.categories` |
| `DLV - consent.id` | `cookie_consent.consent_id` |
| `DLV - consent.source` | `cookie_consent.source` |

## 4. Doporučené triggery

1. **`Consent update`** – Custom Event: `cookie_consent_update`.
2. **`Consent – marketing granted`** – Custom Event: `cookie_consent_update|cookie_consent_default` (zaškrtnout „Use regex matching") + podmínka `DLV - consent.marketing` *equals* `true`.
3. **`Consent – analytics granted`** – stejně s `DLV - consent.analytics`.

> Trigger na **oba** eventy (`default` i `update`) zajistí, že se tag spustí jak u vracejícího se návštěvníka (stav už v `cookie_consent_default`), tak hned po udělení souhlasu bez reloadu.

## 5. Nastavení tagů

### GA4 / Google Ads (podporují Consent Mode)

- Trigger: normální (All Pages / eventy).
- Consent: *Built-in consent checks* – tag sám respektuje `analytics_storage` / `ad_storage`.
- Volitelně přísnější režim: *Additional consent checks → Require additional consent for tag to fire* a vybrat příslušné signály – pak tag do souhlasu nevystřelí vůbec (žádné cookieless pingy).

### Sklik, Heureka, Leady apod. (bez podpory Consent Mode)

Tyto tagy Consent Mode ignorují – **musí** být blokované triggerem:

- Trigger: `Consent – marketing granted` (bod 4.2).
- V *Additional consent checks* navíc nastavte `ad_storage` (dokumentační efekt v Consent Overview).

### Šablony třetích stran s consent polem

Řada komunitních šablon (např. Facebook Pixel od facebookarchive/duracelltomi) má vlastní pole „consent granted" – nastavte na `{{DLV - consent.marketing}}`.

## 6. Ověření (Tag Assistant + Consent Mode debug)

1. GTM Preview → načíst web v anonymním okně.
2. V Tag Assistant zkontrolovat pořadí: **Consent (default)** musí být PŘED `Container Loaded`.
3. Záložka *Consent* u eventu ukazuje stav on-page default / update.
4. Odsouhlasit lištu → přijde `cookie_consent_update` + Consent update; consent-gated tagy se spustí bez reloadu.
5. Zkontrolovat v Network, že GA4 requesty obsahují `gcs=G111` (po souhlasu) vs `gcs=G100` (denied).

## 7. GTM4WP poznámky

- **2.x**: nic nenastavujte – plugin automaticky potlačí consent default blok GTM4WP (filtr `gtm4wp_consent_mode_default_enabled`).
- **1.x**: v nastavení GTM4WP (Integration → Consent mode) doporučujeme integraci **vypnout**. Pokud zůstane zapnutá, plugin její hodnoty srovná se svými přes filtr `gtm4wp_overwrite_consent_mode_flag`, takže se defaulty nerozjedou.
- GTM4WP volbu **„container code placement"** můžete nechat na výchozí hodnotě; náš default běží na `wp_head` prioritě 0, tedy vždy dřív.
