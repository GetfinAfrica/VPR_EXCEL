VPR V12.9 fixes the exact live error:
SQLSTATE[HY000]: General error: 1 no such column: last_error

Cause: the live payments table is from an older build. V12.9 automatically migrates it and adds last_error plus all current payment fields.

UPGRADE:
- KEEP data/vpr.sqlite (do not delete your live records).
- Replace application files with V12.9.
- Log into Admin and click Repair / Verify Payment Database.
- Confirm Repair successful.
- Retry the US$1 Paynow check.
