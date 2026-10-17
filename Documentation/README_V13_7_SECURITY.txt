VPR V13.7 — HTTPS + SECURITY HARDENING

WHAT CHANGED
- Forces HTTP requests to HTTPS.
- Adds HSTS on HTTPS connections.
- Adds restrictive browser security headers.
- Blocks direct HTTP access to config/database/data/backups/dot-files.
- Disables directory listing and uncommon HTTP methods.
- Strengthens PHP session cookie/session-ID settings.
- Admin/recovery pages are no-cache and noindex.
- Existing login throttling, CSRF protection, password hashing, session regeneration,
  admin audit logging and protected certificate access remain enabled.
- Adds an Admin Login link at the bottom of the public VPR page.
- Fixes the pricing panel so it appears only after admin authentication.
- Keeps V13.6 dynamic USD/ZiG pricing and zero-fee bypass.
- Keeps one-page A4 certificate print/PDF layout.

IMPORTANT ABOUT HTTPS
Website code can FORCE HTTPS but cannot create the SSL/TLS certificate itself.
Your domain/hosting must already have a valid SSL/TLS certificate for https://vpr.co.zw.
If TLS is not active yet, temporarily set VPR_FORCE_HTTPS to false in config.php before deployment,
otherwise HTTP visitors will be redirected to an HTTPS address that the server cannot serve.

UPGRADE
Preserve your live data/vpr.sqlite. Do not overwrite/delete it.
Upload the application files around the existing database.

ADMIN
/admin2000.php

SECURITY
Change the default administrator password before production if it has not already been changed.
Do not place API/payment/email passwords in browser-side JavaScript.
