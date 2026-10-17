VPR V12.2 — FULL FUNCTIONAL AUDIT & FIX

This package was audited across the public search flow, Paynow payment flow, database logic,
certificate generation, admin dashboard, interest ranking, analytics, security controls and backup/import.

Key fixes made during the audit:
1. Paynow responses are handled case-insensitively while preserving hash validation.
2. Signed Paynow callback reference must match the VPR transaction reference.
3. Polled Paynow status must also match the same transaction reference.
4. Payment finalisation is atomically claimed to prevent callback/return concurrency from creating duplicate searches.
5. Merchant payment references now use stronger random entropy.
6. Customer return URLs use a separate strong random access token.
7. Payment backup data is now included in the Excel backup/import migration workbook.
8. The VPR certificate title is corrected to "VPR Vehicle Interest Search Report".
9. Interest display uses "Order of VPR Registration", not legal priority language.
10. Interest numbers preserve their original chronological registration order even when an earlier interest is cleared.
11. Certificate logic accepts both current and legacy "no interest" result wording.
12. Existing SQLite WAL, 5-second busy timeout, security, recovery, analytics and USD-only Paynow controls are retained.

Hosting requirements that cannot be fully tested inside ChatGPT's local PHP runtime:
- PDO SQLite extension
- PHP cURL or allow_url_fopen for outbound Paynow HTTPS requests
- ZipArchive and SimpleXML for Excel backup/import
- PHP mail() routing for security/recovery emails

These should be verified in cPanel. PHP syntax for the package is checked separately.
