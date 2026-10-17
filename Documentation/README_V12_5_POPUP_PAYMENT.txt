VPR V12.5 — SIMPLE PAYNOW POPUP FLOW

This version returns to the first Paynow payment-link method requested.

CUSTOMER FLOW
1. Enter vehicle details.
2. Click "Check Vehicle — Pay US$1.00".
3. Paynow opens in a popup window instead of taking over the VPR page.
4. Customer completes the US$1 payment.
5. Customer closes the Paynow popup.
6. VPR automatically continues the vehicle search.
7. The VPR Vehicle Interest Search Report is generated.

IMPORTANT LIMITATION OF THE ORIGINAL PAYNOW PAYMENT-LINK METHOD
The supplied Paynow payment link does not provide VPR with a server-to-server success callback tied to
the specific search. Therefore the application can detect that the Paynow popup was opened and closed,
but it cannot independently prove from this link alone that the customer actually completed payment.

This build implements the exact simple popup workflow requested. It should not be represented internally
as cryptographically verified payment confirmation. Automatic verified Paynow settlement requires the
3rd Party Integration callback/polling method.

DEPLOYMENT
Keep your existing data/vpr.sqlite if preserving live records.
Replace the application files with this package.
The public page should show "VPR v12.5 · Popup Payment Mode".
