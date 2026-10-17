---
kind: configuration_system
name: 'VPR Configuration: PHP Constants + SQLite Settings Table'
category: configuration_system
scope:
    - '**'
source_files:
    - config.php
    - db.php
    - admin2000.php
    - data/vpr.sqlite
---

## Approach

The Vehicle Price Registry is a flat PHP application with no framework. Runtime configuration is split into two layers:

1. **Deployment secrets and environment constants** — defined as `define()` calls in `config.php` (PHP constants such as `VPR_ADMIN_PASSWORD`, `VPR_DB`, `VPR_FORCE_HTTPS`, `VPR_PAYNOW_INTEGRATION_KEY`, `VPR_SEARCH_FEE`, `VPR_ZIG_SEARCH_FEE`, `VPR_PUBLIC_URL`, `VPR_SECURITY_EMAIL`, `VPR_RECOVERY_TOKEN_TTL`, etc.).
2. **Operational settings persisted at runtime** — stored in the SQLite `settings` table (`setting_key` / `setting_value`) and accessed through `vpr_setting()` / `vpr_set_setting()` in `db.php`. The only current use is per-currency pricing keys `price_USD` and `price_ZIG`, written by the admin console.

There is no `.env` file, no YAML/TOML loader, no feature-flag library, and no environment-variable parsing layer. All configuration is resolved at PHP startup via `require_once __DIR__ . '/config.php'` inside `db.php`, which every entry script loads indirectly.

## Key Files

- `config.php` — single source of truth for deployment-time constants (database path, security secrets, Paynow credentials, site identity, session/login timeouts, mail addresses, currency fees).
- `db.php` — the shared core library; it `require_once`s `config.php` on load and exposes the `vpr_setting()` / `vpr_set_setting()` helpers plus the `settings` table schema.
- `admin2000.php` — the only place that writes to the `settings` table (updates `price_USD` / `price_ZIG`).
- `data/vpr.sqlite` — the SQLite database where the `settings` table lives alongside `vehicles`, `searches`, `payments`, `admin_audit`, `admin_recovery`, `login_attempts`, and `system_metadata`.

## Architecture and Conventions

### Two-tier constant model

| Tier | Storage | Access | Examples |
|---|---|---|---|
| Deployment config | `config.php` constants | Directly referenced throughout code | `VPR_DB`, `VPR_FORCE_HTTPS`, `VPR_PAYNOW_INTEGRATION_KEY`, `VPR_ADMIN_SESSION_TIMEOUT`, `VPR_MAX_LOGIN_ATTEMPTS`, `VPR_LOGIN_BLOCK_SECONDS`, `VPR_RECOVERY_TOKEN_TTL`, `VPR_SECURITY_EMAIL`, `VPR_MAIL_FROM`, `VPR_SITE_NAME`, `VPR_VERSION`, `VPR_SITE_TITLE`, `VPR_TAGLINE`, `VPR_SEARCH_CURRENCY`, `VPR_USD_PAYMENT_LINK`, `VPR_ZIG_PAYNOW_LINK`, `VPR_ZIG_API_ENABLED`, `VPR_ZIG_API_BASE_URL`, `VPR_ZIG_API_USERNAME`, `VPR_ZIG_API_PASSWORD`, `VPR_PAYNOW_INIT_URL`, `VPR_PAYNOW_INTEGRATION_ID` |
| Operational settings | SQLite `settings` table | `vpr_setting($pdo, $key, $default)` | `price_USD`, `price_ZIG` |

The `vpr_price()` helper demonstrates the fallback convention: if a setting key is absent from the DB, it falls back to the matching `VPR_*_SEARCH_FEE` constant, then to a hard-coded default (`2.00` for ZIG, `1.00` for USD). This lets operators override prices without editing `config.php`.

### Secret handling

Secrets are plain PHP constants in `config.php`: `VPR_ADMIN_PASSWORD`, `VPR_SECURITY_SECRET`, `VPR_IP_HASH_KEY`, `VPR_PAYNOW_INTEGRATION_KEY`, `VPR_PAYNOW_INTEGRATION_ID`, `VPR_ZIG_API_USERNAME`, `VPR_ZIG_API_PASSWORD`. The file header explicitly warns "IMPORTANT: change this password before going live" and "keep this file private and never publish its contents." Admin passwords are additionally supported from `data/admin_auth.json` (a JSON file containing `password_hash` and `updated_at`), with backward compatibility falling back to the `VPR_ADMIN_PASSWORD` constant.

### Environment detection

- `VPR_PUBLIC_URL` — when set and non-blank, used verbatim as the public base URL; otherwise `vpr_public_base_url()` derives it from `$_SERVER['HTTP_HOST']` prefixed with `https://`.
- `VPR_FORCE_HTTPS` — when true, `security_headers()` issues a 301 redirect to HTTPS before any page content.
- `request_is_https()` trusts `$_SERVER['HTTPS']` and the `X-Forwarded-Proto` proxy header (only the first comma-separated value).

### Security posture baked into config

- `ini_set('display_errors', '0')` and `ini_set('log_errors', '1')` disable error output in production.
- Session security is enforced in `secure_session_start()`: strict mode, cookies-only, no trans_sid, httponly, samesite=Strict, 48-character SID.
- CSRF protection via `csrf_token()` / `verify_csrf()` using a session-stored token.
- Login brute-force protection uses `VPR_MAX_LOGIN_ATTEMPTS` and `VPR_LOGIN_BLOCK_SECONDS` against the `login_attempts` table keyed by HMAC'd client IP (`VPR_IP_HASH_KEY`).
- Admin sessions expire after `VPR_ADMIN_SESSION_TIMEOUT` seconds (default 1800) and are refreshed on each request.
- Recovery tokens have a TTL of `VPR_RECOVERY_TOKEN_TTL` seconds (900 = 15 minutes).

### Schema migrations as part of initialization

`ensure_v6_schema()` runs on every `db()` call and creates/migrates tables including `settings`, `admin_audit`, `admin_recovery`, `login_attempts`, `system_metadata`, and the payment-related columns added in V12.9. It also seeds `system_metadata.meta_key='schema_version'` with `'12'`.

## Conventions and Constraints

- **Single config file**: all deployment-time configuration is centralized in `config.php`; no other PHP file defines these constants.
- **Centralized loading**: `db.php` is the only place that `require_once`s `config.php`; every entry script depends on `db.php` rather than loading `config.php` directly.
- **Constants over variables**: configuration values are exposed as `define()` constants, not global variables or class properties.
- **Runtime overrides via DB**: mutable operational values (currently only search prices) go through `vpr_setting()` / `vpr_set_setting()` backed by the `settings` table, never by editing `config.php`.
- **Fallback chain for prices**: `vpr_setting($pdo, 'price_'.$currency)` → matching `VPR_*_SEARCH_FEE` constant → hard-coded default (`2.00` for ZIG, `1.00` for USD).
- **Secrets stay out of code comments**: the author guidelines forbid quoting secret values from `config.php` in knowledge cards; only key names may be referenced.
- **ZiG integration disabled by default**: `VPR_ZIG_API_ENABLED` is `false`, and the documentation states it stays disabled until provider credentials/schema are supplied.
- **Public URL derivation**: leaving `VPR_PUBLIC_URL` blank (the documented default) causes automatic HTTPS domain discovery from the request host.
- **Security headers applied globally**: `security_headers()` enforces HSTS, CSP, X-Frame-Options, Referrer-Policy, Permissions-Policy, and CORS restrictions whenever HTTPS is detected.