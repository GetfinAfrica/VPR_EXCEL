VPR V13.1 — ZiG AMOUNT FIX

Fixed ZiG amount from 1.00 to 26.40.

Cause:
The original supplied Paynow Payment Link contained amount=1.00 inside its encoded q parameter.
Paynow therefore correctly opened the payment page for ZiG 1.00.

Fix:
The same Paynow Payment Link payload has been rebuilt with amount=26.40.
VPR now displays ZiG 26.40 and opens Paynow with amount=26.40.

USD remains US$1.00 using the Integration ID / Integration Key flow.

ZiG Paynow link used by this build:
https://www.paynow.co.zw/Payment/Link/?q=c2VhcmNoPWFsZHJpZGdlQGRpcmFub3IuY29tJmFtb3VudD0yNi40MCZyZWZlcmVuY2U9Jmw9MQ%3D%3D

Security limitation remains:
A generic Paynow Payment Link does not provide VPR with a transaction-specific poll URL/callback,
so popup closure alone is not treated as verified payment.
