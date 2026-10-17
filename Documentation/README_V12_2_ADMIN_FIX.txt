VPR V12.2 — ADMIN LOGIN FIX

Audit finding:
A V12 legal-wording replacement accidentally changed the SQL alias `priority_rank` into invalid SQL text
(`order of VPR registration_rank`) inside admin2000.php. Because production error display is disabled,
the server returned an empty/blank page after successful admin login.

Fix:
- Restored the internal SQL alias to `priority_rank`.
- Kept the user-facing label as `Order of VPR Registration`.
- Corrected the corresponding PHP array reference.
- Re-ran PHP syntax validation across the package.
- Checked the affected SQL directly against the bundled SQLite database.

UPGRADE:
Keep your existing data/vpr.sqlite file if preserving live data.
Replace admin2000.php (or the other application files) with this corrected package.
