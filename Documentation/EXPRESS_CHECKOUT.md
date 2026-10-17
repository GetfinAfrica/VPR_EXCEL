# Payer details and Express Checkout

Branch: `feat/express-checkout` (Git does not permit spaces in branch names).

Before a paid search, VPR collects payer name, email, method and optional mobile number. EcoCash, OneMoney and InnBucks require a Zimbabwe mobile number. Zero-fee searches bypass payer details. Validation runs again on the server; payer details and method join the idempotency fingerprint so changed details cannot reuse an existing charge request.

## Methods

- Choose on Paynow / Visa-Mastercard: sends `authname` and `authemail` to the signed hosted checkout before redirecting. The user enters sensitive card information only on Paynow. The generic hosted API does not document a method preselection field; choosing card in VPR does not guarantee Paynow will hide its other enabled methods.
- EcoCash / OneMoney: signed `/interface/remotetransaction` request with `method` and the local mobile number. VPR remains open and shows phone approval instructions, the payment reference and Check Payment Status. No browser redirect is required for express mobile requests.
- InnBucks: uses the same express endpoint, displays the signed authorization code and expiry, and provides the documented InnBucks app deep link. No raw card numbers, wallet PINs, OTPs or saved card tokens are collected.

Each method must be enabled in Paynow for the selected USD/ZWG integration. Unsupported/ambiguous initiation remains in Review and does not issue a report. Retry reuses the same transaction. A signed success with the exact expected amount, reference and integration is still required before certificate issuance. Existing hosted return/recovery and the independent reconciler remain available.

Payer name/email/phone are stored in the protected payment ledger for transaction context. They are not included in checkout JSON, event logs or the admin transaction listing. Session storage retains form details only for recovery in the current tab and clears them after success or an explicit new search. InnBucks authorization data is available only to the bearer-token protected status flow; treat that token and authorization data as private.

## Verification

Run existing payment/HTTP/browser suites and `php tests/express_test.php`. The new suite verifies both currency profiles, each selectable method, correct endpoint/fields/signatures, number normalization, validation failures, contact-bound idempotency, private response fields and verified certificate issuance. Browser tests verify that mobile requests keep the page open and status checks do not create another charge.

Paynow test mobile numbers: `0771111111` success; `0772222222` delayed success; `0773333333` cancellation; `0774444444` insufficient balance. Use the owning merchant email in test mode. These are Paynow's simulated endpoints, not real wallet payments. Other method-specific provider scenarios and live account/currency configuration require separate verification.

References:
- https://developers.paynow.co.zw/docs/paynow/express_checkout_transactions/
- https://developers.paynow.co.zw/docs/paynow/test_mode/

This branch changes payer entry and payment initiation; report display behavior remains as on its parent branch.

## Results on 2026-10-17

245 automated checks passed: 95 existing payment checks, 26 HTTP checks, 110 express/protocol checks and 14 browser-flow checks. Paynow's actual test API accepted EcoCash requests for both supplied profiles and returned signed Paid status with the original expected amounts:

| Profile | Merchant reference | Expected/reported | Result |
| --- | --- | --- | --- |
| USD | VPR-20261017152216-A7013828FEAA3B02 | 1.00 / 1.00 | Paid, search 4 |
| ZWG | VPR-20261017152217-991FB993687AA521 | 2.00 / 2.00 | Paid, search 5 |

A browser EcoCash test collected payer details, remained on VPR with phone instructions, and issued certificate VPR-20261017-000006 after Check Payment Status verified the result. OneMoney and InnBucks are covered by signed protocol fixtures; their actual provider-side scenarios remain to be verified. Card selection uses the previously tested hosted checkout with payer prefill, not direct card-token charges. No live launch or real wallet charge was performed.
