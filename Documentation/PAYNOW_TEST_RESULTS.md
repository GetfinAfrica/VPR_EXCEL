# Dual-currency checkout verification — 2026-10-17

Repository: `C:\code\VPR_EXCEL`, branch `feat/paynow`. PHP runtime: XAMPP PHP 8.2.12. Development server: loopback port 8088, using the separate `data/vpr.dev.sqlite` database. Existing registry database/configuration preserved.

## Automated regression results

- 95 payment/money/profile/authorization/concurrency checks passed.
- 26 HTTP endpoint, token-access, certificate and admin checks passed.
- 11 browser-flow checks passed, including synchronous popup creation, blocked-popup fallback, ZWG selection, lost-response recovery and retries.
- PHP syntax checks and Git whitespace checks passed.
- Seven live local-server private-path probes returned HTTP 403: database, generated private config (including doubled-slash path), source config, payment helpers, tests, CLI helpers and Git metadata.

These regression suites use isolated fixture databases and fake credentials. Their negative/delayed/concurrent scenarios are local protocol tests, not claims of provider-side cancellation or settlement testing.

## Real Paynow test integrations

Both supplied integrations accepted transaction-specific initiation and returned signatures verified with their respective keys. Credentials were configured as Windows user environment variables, outside Git and browser responses. Environment labels remain `test`; the owner completed Paynow's simulated-payment flow.

| Profile | Integration ID | VPR merchant reference | Expected / reported amount | Signed provider status | Search / certificate |
| --- | --- | --- | --- | --- | --- |
| USD | 26804 | VPR-20261017141704-363E4BF8AA66F85B | 1.00 / 1.00 | Paid | 1 / VPR-20261017-000001 |
| ZiG (ZWG) | 27513 | VPR-20261017141855-7366D12633E99DDE | 2.00 / 2.00 | Paid | 2 / VPR-20261017-000002 |

The application verified these outcomes server-side and both browser tabs displayed the resulting vehicle reports (`TST1234`, Toyota Hilux; `TST5678`, Toyota Prado). No full return/certificate tokens, integration keys or provider poll URLs are recorded here. Currency is bound to the selected integration profile; the classic response has no documented mandatory currency field. Separate provider-side settlement/account currency confirmation remains part of live readiness.

## Production readiness still required

- Configure production hosting secrets and confirm each integration's intended currency, payment methods and account mode with Paynow. Rotate the test/shared keys before using live credentials.
- Verify callback delivery on a reachable HTTPS deployment. Paynow cannot deliver callbacks to loopback; local verification used the saved provider poll URL. Signed callback handling was exercised by the HTTP regression suite.
- Confirm provider callback acknowledgement/retry requirements, run provider-side cancellation/delay scenarios, and arrange the independent reconciler with the hosting scheduler.
- Run the live pilot and reconcile transaction/settlement records before production launch.

This records completed development and test-mode evidence; no live launch is implied.
