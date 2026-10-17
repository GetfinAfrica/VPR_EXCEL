VPR MVP — Vehicle Pledge Registry

WHAT THIS PACKAGE IS
A very small PHP + SQLite website designed for low-cost shared hosting.
It does not require you to manage a server or set up MySQL.

FILES
- index.php              Public vehicle check page.
- admin2000.php          Private admin page.
- config.php             Change admin password here.
- db.php                 Database connection/helper functions.
- data/vpr.sqlite        Database file.
- .htaccess              Blocks direct public access to database/config on Apache hosting.

ADMIN FEATURES
- Password-protected private page.
- Add vehicle interests.
- Clear an interest.
- Searches today.
- Total searches.
- Total vehicle records.
- Active interests.
- Searches per day for the last 30 days.

PUBLIC FEATURES
- Chassis number, vehicle model and registration number.
- Every search is stored.
- First search automatically creates a vehicle record.
- Shows whether an ACTIVE interest is registered.
- Shows interested party where an interest exists.
- Print/Save as PDF clearance result through the browser.

UPLOAD / INSTALL
1. Buy/connect your domain, e.g. vpr.co.zw.
2. Use ordinary shared web hosting/cPanel that supports PHP 8+ and PDO SQLite.
3. Upload all files/folders to public_html (or the domain's document root).
4. Make sure the /data folder and vpr.sqlite are writable by PHP.
   Typical hosting permissions:
   - data folder: 755 or 775
   - vpr.sqlite: 664 or 666 only if required by your host
5. Open config.php and CHANGE:
   VPR_ADMIN_PASSWORD
6. Visit:
   https://your-domain/
7. Private admin:
   https://your-domain/admin2000.php

IMPORTANT
A domain by itself is only an address. The PHP code still needs ordinary web hosting behind the domain.
This package is designed specifically so you do NOT need to rent or administer a VPS/server.

SECURITY
This is an MVP, not a banking-grade registry.
Do not rely on the secrecy of admin2000.php alone. Keep the password enabled.
Before banks/lenders are allowed to log in themselves, upgrade to individual user accounts, audit logs,
multi-factor authentication, stronger verification of interest submissions, and regular backups.

LEGAL / WORDING
The public result intentionally says:
"No registered interest found on VPR"
rather than stating that the vehicle is legally free of all encumbrances.
