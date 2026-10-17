VPR MVP V3 — BRANDED RELEASE

KEY CHANGES
- Final VPR logo integrated across public site, admin page and certificate.
- Footer: "A member of Diranor Group" with Diranor Group linked to https://diranor.com.
- Registration number is mandatory and is VPR's primary unique vehicle identifier.
- Vehicle brand and model are mandatory.
- Chassis number is optional.
- Multiple active interests are ranked by VPR registration order: first registered = Priority 1, etc.
- Certificate redesigned as a formal A4 verification document with:
  * VPR branding and border treatment
  * unique certificate reference
  * issue date/time
  * vehicle details
  * result status
  * ranked registered interests
  * watermark
  * formal disclaimer
  * system-generated record line
  * Diranor Group membership footer
- Certificate opens in the browser and can be printed/saved as PDF.

UPDATING AN EXISTING LIVE VPR SITE
1. Back up your current /data/vpr.sqlite file.
2. Upload/replace the PHP files and /assets folder from this package.
3. KEEP your existing /data/vpr.sqlite file if you want to retain your existing records.
4. The new code will use registration number as the main matching key and create a unique registration index.
5. Test with sample registrations before using live records.

FRESH INSTALL
Upload and extract the full package into the domain document root.

PRIVATE ADMIN
/admin2000.php

SECURITY
Change the admin password in config.php.
This remains an MVP. Before giving third-party lenders their own access, add named user accounts,
MFA, audit trails, backups, submission verification and stronger security controls.
