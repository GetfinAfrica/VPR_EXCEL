VPR V13.2 DOMAIN UPLOAD

USD: US$1.00 using the existing Paynow Integration ID/Key verified flow.
ZiG: ZiG 26.40 using the corrected Paynow popup link.

The separate ZiG API skeleton is included but disabled. The code supplied so far confirms Basic Auth and
POST /api/sandbox/transactions/charges, but not the complete requestBody, real base URL, credentials,
or payment confirmation/status schema. These fields have not been invented.

UPLOAD:
Keep data/vpr.sqlite if preserving live records. Replace the other application files with this package.
Confirm VPR v13.2 after upload. Use Admin > Repair / Verify Payment Database if upgrading an older database.

SECURITY: Rotate the Paynow integration key before production because it was shared in chat.
