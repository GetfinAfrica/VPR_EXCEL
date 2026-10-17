VPR V9 — PLUG-AND-PLAY SECURITY

This version keeps the security improvements from V8 but removes anything that requires you to install,
configure or understand SSL/TLS certificates before the site can work.

You can upload this version to your current cPanel website in the same way as the earlier VPR versions.

SECURITY FEATURES INCLUDED IN THE CODE

1. Browser protection headers
   - Content-Security-Policy
   - X-Frame-Options DENY
   - X-Content-Type-Options nosniff
   - Referrer-Policy
   - Permissions-Policy

2. Admin session protection
   - HttpOnly cookies
   - SameSite=Strict
   - Strict PHP session mode
   - Session ID regenerated after login
   - 30-minute inactivity timeout
   - Secure cookie automatically used only when the site is already running on HTTPS

3. Login attack protection
   - 5 failed login attempts maximum per protected IP fingerprint
   - 15-minute temporary block
   - Login successes/failures/blocks are logged

4. CSRF protection
   - Database-changing admin actions require a random security token
   - Excel import is also protected

5. Certificate protection
   - Client certificate links contain a long random access token
   - Guessing a certificate/search number alone is not enough to access the certificate
   - Logged-in admin users can still open historical certificates

6. Visitor privacy
   - New searches store a protected HMAC fingerprint instead of the visitor's raw IP address

7. Input protection
   - Registration-number validation
   - Length limits on vehicle fields
   - PDO prepared statements continue to protect database queries

8. Private file protection
   - Database/configuration files are blocked from web access
   - /data has its own deny rule
   - Directory listing disabled
   - Dot-files blocked

9. Production error protection
   - PHP errors are not shown to public visitors
   - Errors can still be recorded by the hosting environment

10. Admin audit trail
    - Login activity
    - Added interests
    - Cleared interests
    - Excel exports
    - Excel imports

11. Performance protection retained
    - SQLite WAL mode
    - 5-second busy timeout
    - synchronous=NORMAL
    - temp_store=MEMORY
    - indexes retained

12. Data portability retained
    - Excel backup
    - Excel restore/import
    - Stable certificate numbers
    - Search history
    - Historical interest snapshots

UPGRADE YOUR CURRENT VPR SITE
1. Download an Excel backup from your current admin area.
2. Make a copy of data/vpr.sqlite.
3. KEEP your existing data/vpr.sqlite file.
4. Replace the other files with this V9 package.
5. Make sure .htaccess is also uploaded.
6. Test a normal public search and admin login.

NO CERTIFICATE INSTALLATION REQUIRED
V9 does not force HTTPS and does not require you to install or configure certificates.
If your hosting company already provides HTTPS automatically, VPR will benefit from it automatically,
but the site will not depend on that setup in order to operate.
