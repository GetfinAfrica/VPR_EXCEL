VPR V12.7 — PAYNOW US$1 PAYMENT BEFORE VEHICLE CHECK

Customer flow:
1. Customer enters registration, brand, model and optional chassis.
2. Clicks Check Vehicle — US$1.00.
3. VPR creates a unique Paynow transaction using Integration ID 26804.
4. Paynow opens in a popup window.
5. The main VPR page stays open and checks Paynow status every 2 seconds.
6. Paynow also posts status changes to VPR's result URL.
7. VPR validates Paynow SHA-512 hashes and independently polls the Paynow poll URL.
8. Only Paid / Awaiting Delivery / Delivered with the expected US$1.00 amount releases the vehicle check.
9. VPR generates the historical search record and Vehicle Interest Search Report.
10. The Paynow popup closes and the main VPR page opens the report.

This uses Paynow's documented 3rd Party Integration fields and server-to-server confirmation model.

Files added:
- paynow_create.php
- paynow_result.php
- paynow_status.php
- paynow_return.php

Admin additions:
- Paid searches
- Paid-search revenue
- Recent Paynow transactions and errors

IMPORTANT SECURITY
The integration key is stored in protected config.php and is not exposed to browser JavaScript.
Because the key has been shared in chat, rotate it in Paynow before production launch and replace
VPR_PAYNOW_INTEGRATION_KEY in config.php.

SERVER REQUIREMENT
The hosting account must allow PHP outbound HTTPS connections (cURL preferred, allow_url_fopen fallback).
