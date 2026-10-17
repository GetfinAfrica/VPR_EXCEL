VPR V12.2 — USD-ONLY PAYNOW ENFORCEMENT

This release builds on V12.1 automatic Paynow confirmation and makes the VPR payment expectation explicit:

SEARCH FEE
US$1.00
CURRENCY
USD ONLY

CHANGES
- Public search button clearly states US$1.00.
- Public page states "USD payment only".
- Payment records now include a currency field.
- Existing databases automatically receive currency='USD' for prior payment rows.
- Paynow initiation description identifies the search as USD-only.
- VPR validates the returned amount against US$1.00.
- If Paynow provides a currency field, VPR validates that it is USD.
- AmountMismatch or CurrencyMismatch states do not unlock the search.
- Admin dashboard now shows the 50 most recent Paynow transactions with:
  * VPR payment reference
  * Paynow reference
  * registration number
  * expected amount/currency
  * received amount/currency
  * payment status
- Paid search revenue continues to count only successful Paynow states.

IMPORTANT
Integration ID 26804 is being treated as a USD-only Paynow integration.
Some Paynow status responses may omit an explicit currency field; where it is omitted, VPR relies on the
merchant integration being USD-only and still enforces the US$1.00 amount.

SECURITY
The Paynow Integration Key remains in protected config.php. Rotate the key before production because it
was shared in chat, then replace VPR_PAYNOW_INTEGRATION_KEY with the new value.

UPGRADE
1. Back up data/vpr.sqlite and download an Excel backup.
2. Keep the existing data/vpr.sqlite file.
3. Replace the application files with V12.2.
4. Test a successful US$1 USD payment.
5. Confirm the payment appears in Recent Paynow Transactions as USD 1.00 and Paid.
