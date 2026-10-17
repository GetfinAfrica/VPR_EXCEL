# UI enhancements

Branch: `ui/enhancements`.

Active checkout and payment-return pages request reconciliation every two seconds while awaiting payment. The shared database claim prevents overlapping Paynow calls, and a two-second server limit applies across tabs for each transaction. Successful, cancelled, failed and expired transactions stop automatic polling. Network failures retry the same reference; no retry initiates another charge. Closing the checkout modal pauses its checks; reopening resumes its existing payment.

After verified success, the screen remains open and displays **Complete**. Selecting it opens `payment_success.php`, where VPR acknowledges the amount, currency, payment method, vehicle registration and transaction references. The user then selects **View certificate**. Neither the modal nor the Paynow return window automatically closes or opens a certificate after success. Refreshing an acknowledgement does not issue another certificate.

The acknowledgement requires the transaction's private return token, Paid status, a verification timestamp and an issued certificate. Invalid access returns 403; unverified or non-successful payments return 409 without a certificate link. Amounts and references come from the database, not browser parameters.

Shared `ui.php` and `assets/ui.css` place headings on the left and proportionate logos on the right across public search, payment modal/status/acknowledgement, certificate, admin login/dashboard/recovery, policy pages and maintenance screens. Related actions share a responsive row and wrap only when necessary on narrow screens. Certificate print controls remain outside the printed report.

Paynow documents polling its returned poll URL using an empty POST and recommends status callbacks as the normal production mechanism: https://developers.paynow.co.zw/docs/paynow/polling_status/. This branch implements the requested two-second active-checkout polling, including localhost where provider callbacks cannot reach the application. Existing callback validation and the unattended CLI reconciler remain available.

Validation: 298 checks passed (95 payment, 32 HTTP, 110 express, 30 scenario, 31 browser-flow). HTTP coverage includes both currencies' pending/paid acknowledgements and invalid-token access. Browser-flow coverage verifies two-second scheduling, no automatic success navigation/window closing for mobile and hosted checkout, explicit Complete navigation, continued pending checks and no duplicate initiation.

Actual Paynow test on 17 October 2026: USD EcoCash for UIX1234 was polled at 16:47:33, :36, :38, :41, :43, :46, :48 and :50 Harare time (two-second waits plus request time). The final signed response was Paid. The modal remained open with Complete; selecting Complete opened VPR's acknowledgement for USD 1.00, and View certificate opened the matching UIX1234 certificate. The paid return page likewise remained on Payment confirmed until Complete. Screens and certificate header were visually checked; the certificate toolbar sits above the report and is hidden in print. No real funds or real card authorisations were used.
