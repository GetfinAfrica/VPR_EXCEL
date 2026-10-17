# Local Apache and checkout scenarios

The form has two stages: vehicle details, then currency/payer/method details. Back preserves vehicle input. Paid fields are not required during the vehicle stage or for a server-authorized free search. A review modal belongs to the payment stage. The checkout asset is versioned to avoid stale cached JavaScript after updating either local installation.

In test mode, EcoCash/OneMoney offer an optional scenario selector. It fills the mobile field explicitly; users may also enter a number manually. No number is hard-coded into outgoing requests. Local/international forms are normalized, then sent to Paynow in the documented local format.

| Number | Provider simulation | Passing result |
| --- | --- | --- |
| 0771111111 | Success after about 5 seconds | One verified certificate |
| 0772222222 | Delayed success after about 30 seconds | Pending first; one certificate after verified success |
| 0773333333 | Cancellation after about 30 seconds | Cancelled; no certificate |
| 0774444444 | Immediate insufficient balance | Failed with an insufficient-funds message; no certificate |

Use the integration owner's email in Paynow test mode. The scenario selector is hidden for live profiles and methods without these documented mobile simulations. Selecting a scenario only chooses the submitted number; VPR never manufactures a Paid status from a browser field.

Explicit Paynow initiation errors received from the approved, TLS-verified HTTPS endpoint are recorded as Failed with owned, sanitized error codes/messages. Paynow documents unsigned error payloads; a supplied signature must still verify. Timeouts, malformed replies and invalid signatures remain Review because the outcome is unknown. Terminal failures clear the browser recovery state and prepare a fresh request key only for a deliberate subsequent attempt. Pending/ambiguous transactions retain their references to avoid duplicate charges.

The old unconfirmed rows cannot be relabelled safely without evidence; they remain available for reconciliation. Begin a new search to exercise the corrected failure messages.

## XAMPP configuration

The port 8088 launcher imports Paynow credentials from the Windows user environment. An already-running Apache process does not automatically inherit those values. Run `bin/configure_xampp_paynow.ps1` to copy the test profiles from that environment into `C:\xampp\apache\conf\extra\vpr-paynow-local.conf`, outside the web root and Git. It scopes the variables to the VPR directory, configures the HTTPS subdirectory callback base URL, backs up Apache's main configuration and checks Apache syntax. Restart Apache afterward.

The XAMPP configuration retains its existing HTTPS enforcement. `http://localhost/VPR_EXCEL/` may redirect to `https://localhost/VPR_EXCEL/`. Do not put secrets in `.htaccess` or a committed file. Neither source checkout's ignored `config.php` nor either registry database is overwritten by source synchronization.

Tests: existing payment/HTTP/express suites plus `tests/scenarios_test.php` and `node tests/checkout_test.js`. Live callback delivery still requires a public HTTPS deployment. On localhost, explicitly use Check Payment Status or the reconciler to verify provider changes.

Provider reference: https://developers.paynow.co.zw/docs/paynow/test_mode/

Verified 17 October 2026: 283 automated checks pass. Eight actual Paynow test requests through port 8088 (four scenarios per currency) settled as expected: four Paid with certificates, two Cancelled without certificates, two Failed/InsufficientFunds without certificates. Apache runtime confirms both test profiles loaded after restart. A separate HTTPS matrix through Apache is blocked by the expired XAMPP localhost certificate; the verification client refused it. Renew the local certificate before repeating that HTTPS matrix. No Paynow TLS validation was disabled.
