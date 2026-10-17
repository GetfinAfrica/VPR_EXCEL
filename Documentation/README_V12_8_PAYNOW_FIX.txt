VPR V12.8 — PAYNOW JSON / INITIATION FIX

This release fixes the browser error:
Failed to execute 'json' on 'Response': Unexpected end of JSON input

ROOT CAUSE HANDLING
The browser was expecting JSON from paynow_create.php. If PHP failed before producing output, the
browser received an empty body and Response.json() failed.

FIXES
1. paynow_create.php now ALWAYS returns JSON, including:
   - PHP exceptions
   - SQLite/database errors
   - Paynow connection failures
   - empty Paynow responses
   - invalid Paynow responses
2. A PHP shutdown handler catches fatal server errors and converts them to JSON.
3. The browser now reads the raw response first and only parses JSON when content exists.
4. The browser reports a useful VPR payment error instead of "Unexpected end of JSON input".
5. Existing/live SQLite databases are checked and upgraded for required payment columns.
6. paynow_create.php verifies that the payments table exists before attempting initiation.
7. Paynow empty responses are explicitly detected.
8. Admin now includes Payment System Health showing PDO SQLite, cURL, OpenSSL and fallback capability.

EXPECTED CUSTOMER FLOW
Enter vehicle -> Check Vehicle US$1 -> popup -> Paynow -> confirmed payment -> VPR search report.

If initiation still fails after this build, the user-visible error will now identify the actual backend
failure instead of returning an empty JSON body.

SECURITY NOTE
Rotate the Paynow Integration Key before production launch because the current key was shared in chat.
