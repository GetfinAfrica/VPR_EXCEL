VPR V12.6 — PAYNOW COMPLETELY REMOVED

Public flow:
1. Enter vehicle details.
2. Click Check Vehicle.
3. VPR processes the search immediately.
4. The VPR Vehicle Interest Search Report is generated.

Removed:
- Paynow links and popup
- Paynow API integration
- Paynow callbacks and polling
- payment gateway diagnostics
- payment transaction history
- paid-search revenue cards
- Paynow configuration and PHP files
- payment backup/import code

Retained:
- VPR branding
- strict AAA1111 registration format
- brand/model requirements and optional chassis number
- active-interest checking
- Order of VPR Registration
- historical search records
- certificates/search reports
- admin dashboard
- daily/weekly/monthly/total search analytics
- Excel/PDF traffic reports
- Excel registry backup/import
- Terms of Use, Privacy Policy and correction/dispute process
- admin security/recovery/audit controls
- SQLite WAL + 5-second busy timeout

Upgrade:
Keep your existing data/vpr.sqlite if preserving live records.
Replace the other files with this package.
Delete any old Paynow PHP files left on the server.
