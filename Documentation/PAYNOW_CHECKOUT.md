# Dual-currency Paynow checkout

USD and ZiG (ZWG) use the same transaction-specific browser checkout. The integration profile, not a client-supplied fee or static payment link, chooses the currency. Older `ZIG` form values and `price_ZIG` settings remain readable; new transactions/settings use `ZWG`.

## Configuration and deployment

1. Back up the SQLite database consistently while the application is stopped or with a SQLite-aware backup. Preserve the existing database and ignored `config.php`.
2. Deploy the application changes. Schema migration adds nullable payment verification fields and an event table. It does not infer expected amounts for legacy payments: earlier callbacks may have overwritten them. Legacy unissued payments require manual reconciliation; existing issued certificates remain accessible.
3. Set the variables listed in `paynow.env.example` in the server/hosting environment. The example is documentation only, not a dotenv loader. Use a protected Apache `SetEnv` configuration **outside the document root**, hosting secrets, or process environment. The CLI reconciler must receive the same values. Never put keys in `.htaccess`, the repository, browser code, URLs, or command output.
4. Each profile needs ID, KEY, ENVIRONMENT (`test` or `live`), ENABLED=`1`, and CURRENCY_CONFIRMED=`1`. Confirmation means the merchant has checked that the integration belongs to the intended currency/payment methods. Disabled profiles refuse NEW paid checkouts; existing verified transactions can still settle with the same profile credentials.
5. Restart PHP/Apache after changing its environment. The legacy single-integration constants in ignored `config.php` are no longer used for checkout. Preserve the other existing application configuration. Rotate previously exposed or committed credentials before live deployment.

Paynow uses the same published checkout endpoint for test/live accounts. ENVIRONMENT is an auditable application label, not a switch that can change Paynow's account mode. Verify the mode in Paynow itself. Do not enable live mode until both integrations pass simulated success, delay and cancellation tests, have their currency confirmed in Paynow, and their payment methods are enabled. Do not assume an `Ok` initiation response alone proves the intended currency.

## Protocol decisions

- [Initiation](https://developers.paynow.co.zw/docs/paynow/initiate_transaction/): form-encoded POST, unique reference, two-decimal amount, profile ID, per-transaction return/result URLs and status `Message`. The classic API does not document a currency request field. This implementation therefore uses separate, explicitly confirmed currency profiles and sends no invented currency parameter.
- [Signature validation](https://developers.paynow.co.zw/docs/paynow/validating_hash/): concatenate URL-decoded values in received order, excluding hash; append the selected integration key; uppercase SHA-512. Duplicate/ambiguous fields and non-scalar inputs are rejected. Tests include Paynow's published hash vector.
- [Statuses](https://developers.paynow.co.zw/docs/paynow/status_update/): `Paid`, `Awaiting Delivery`, and `Delivered` mean payment made; the latter two may remain in suspense under BuySafe. They authorize this digital report after reference, amount and profile verification. They do not imply settled funds. Reconcile provider settlement separately.
- The classic status payload does not document a currency field. A valid signature with the persisted currency-specific profile binds currency; if a currency or integration ID field is supplied, it must exactly match. Missing amount, reference, provider reference or status never authorizes a report.
- [Polling](https://developers.paynow.co.zw/docs/paynow/polling_status/): empty POST to the saved, verified initiation poll URL. Only HTTPS `www.paynow.co.zw`, port 443, and the expected endpoint paths are allowed. No redirects and no requests to callback-supplied URLs. Browser timers read VPR's stored state; the explicit Check Status button requests reconciliation. Poll claims rate-limit concurrent provider requests.
- Valid callbacks are processed atomically and acknowledged with HTTP 200 and `OK`. Invalid signatures return 403, processing failures 503 and a sanitized error code in the event log. The published shopping-cart pages do not specify acknowledgement text or a retry schedule. Confirm these with Paynow for production; recovery deliberately does not depend on provider retries.

## State machine

| State | Meaning / allowed outcome |
| --- | --- |
| Created | Local record persisted before contacting Paynow. |
| Initiated | Signed checkout and poll URLs stored. |
| Pending | Signed provider Created/Sent status, no report. |
| Paid | Signed successful status and exact expected amount/reference/profile verified. Search and snapshot created in the same write transaction. Remains Paid on stale messages; conflicting later statuses raise reconciliation flags. |
| Review | Unknown provider status, ambiguous initiation or reconciliation required. A subsequent valid pending/success/cancellation message can resolve it. |
| Cancelled | Signed cancellation. Never automatically converted into Paid; conflicting late success is flagged. |
| Failed / Expired | Reserved terminal operational states; never inferred from a network timeout. Conflicts require manual review. |

The original currency, integration identity, vehicle details, expected amount and return token are never overwritten by provider messages. Provider amount/currency/status are stored separately. `BEGIN IMMEDIATE` serializes callback/poll issuance before reads; duplicate messages reuse the same search. Search issuance from a public form is permitted only after a fresh, transactional server-side zero-price check. Admin price changes affect new transactions only and are audited.

Initiation retries reuse the request key, including after lost responses and page reloads in the same browser tab/session. Changed vehicle details on an existing request are rejected. An initiation timeout is retained for reconciliation, never blindly retried as another charge. A signed late callback can still settle it. Lost initiation URLs require manual Paynow lookup; do not copy an unverified poll URL from a customer.

## Operations

Run with the same profile environment as the web process:

```powershell
C:\xampp\php\php.exe C:\code\VPR_EXCEL\bin\reconcile_payments.php --limit=25
C:\xampp\php\php.exe C:\code\VPR_EXCEL\bin\reconcile_payments.php --reference=VPR-...
```

Schedule the bounded reconciler using your hosting scheduler or Windows Task Scheduler independently of customer browser activity. It returns nonzero for unresolved errors or missing verified poll URLs. Do not silently mark old pending payments expired; first reconcile with Paynow. Review event history and admin transaction tables against Paynow transaction/settlement exports using merchant reference, provider reference, expected/reported amount, currency and integration ID. Revenue excludes flagged transactions and never mixes currencies. Legacy payments are excluded from new verified-revenue totals.

`payment_events` contains only merchant reference, event source, owned outcome codes and timestamps. Keys, full return tokens, provider URLs and arbitrary response/error text are excluded. Protect HTTP logs containing certificate/payment query strings using hosting log redaction/access controls. Existing Excel backup functionality is unchanged and is not a complete payment-ledger backup; use SQLite-aware backups for the ledger/settings/events.

## Test commands and current evidence

For a local application session, configure the test variables in the current process or Windows user environment, then run:

```powershell
powershell -File C:\code\VPR_EXCEL\bin\start_dev.ps1
```

Open `http://127.0.0.1:8088`. The launcher creates a consistent SQLite snapshot as `data/vpr.dev.sqlite`, creates ignored private `data/vpr.dev.config.php`, and uses the loopback-only server router to block private paths. Existing registry data and production transport settings are preserved. The launcher accepts only profiles labelled test; Paynow's account mode still must be checked separately. CLI tools may select this development database using `VPR_CONFIG_FILE` pointed at that generated file. Apache ignores this CLI-only override and continues using its existing `config.php`.

The browser submits the displayed fee as a quote only. The server checks it against the current authoritative price before initiation; a changed quote requires the customer to reload and confirm rather than silently charging a new fee.

```powershell
C:\xampp\php\php.exe tests\payments_test.php
C:\xampp\php\php.exe tests\http_test.php
node tests\checkout_test.js
```

The PHP suites create isolated temporary SQLite fixtures, use fake integration credentials, and never touch the application's database or send provider charges. Tests cover currency/profile selection, money validation, mismatches, missing fields, direct issuance bypass, retry/idempotency, terminal outcomes, concurrent callbacks, token access, admin rendering and certificates. Browser-flow tests cover synchronous popup creation, popup blocking fallback and reload recovery.

On 2026-10-17, both supplied test integration IDs (USD 26804 and ZWG 27513) accepted real test API initiation and returned valid signed checkout/poll URLs. Both owner-completed simulated payments were subsequently verified as Paid and issued one certificate each; see [test results](PAYNOW_TEST_RESULTS.md). [Paynow test mode](https://developers.paynow.co.zw/docs/paynow/test_mode/) requires the owning merchant account to log in, then select `TESTING: Faked Success`. Guest accounts cannot complete it. Localhost callbacks cannot be delivered from Paynow; use a reachable HTTPS test deployment or explicitly reconcile the saved transaction after simulated payment. Keep provider-side completion/currency verification results in the release checklist before launch.
