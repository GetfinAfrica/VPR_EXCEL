VPR V10 — SECURITY EMAIL ALERTS & ADMIN RECOVERY

This release adds email-based security alerts and one-time admin password recovery while keeping the
site simple and self-contained.

IMPORTANT SECURITY DECISION
VPR does NOT store a webmail password in website code. Hard-coding an email password in PHP would make
that mailbox vulnerable if source code or a backup were ever exposed.

Instead, VPR uses PHP's built-in mail() function. On most cPanel hosting accounts this sends mail through
the hosting server's configured mail transport and does not require the website to know the mailbox password.

SECURITY EMAIL
admin@vpr.co.zw

ALERTS
VPR can send email warnings when:
- repeated failed admin logins are detected
- login attempts are blocked
- a password recovery is requested
- the admin password is changed

ADMIN RECOVERY
The admin login page now includes:
"Forgot / blocked admin password?"

Recovery process:
1. Click the recovery link.
2. VPR generates a cryptographically random one-time token.
3. A recovery link is emailed to admin@vpr.co.zw.
4. The link expires after 15 minutes.
5. The admin chooses a new password.
6. Existing login blocks are cleared.
7. The token becomes unusable after the reset.

PASSWORD STORAGE
After the first recovery/reset, the new admin password is stored only as a secure PHP password hash in:
data/admin_auth.json

The real password is not stored in readable form.

EMAIL DELIVERY TEST
After upload, click "Forgot / blocked admin password?" and request a recovery email.
If no message arrives, the hosting account may have PHP mail() disabled or may require mail routing setup.
The rest of VPR continues to work normally.

ALL V9 SECURITY REMAINS
- SQLite WAL + busy timeout
- CSRF protection
- login rate limiting
- admin session timeout
- security headers
- protected database/config files
- certificate access tokens
- protected IP fingerprints
- admin audit log
- Excel backup/import
