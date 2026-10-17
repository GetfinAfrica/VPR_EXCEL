VPR V6 — EXCEL BACKUP, RESTORE & MIGRATION

NEW ADMIN DATA MANAGEMENT
The private VPR admin dashboard now includes a Data Backup & Migration section.

DOWNLOAD EXCEL BACKUP
One click creates a complete .xlsx file containing:
1. Metadata
2. Vehicles
3. Interests
4. Searches
5. SearchInterests (the exact interest snapshot attached to each certificate)

The workbook is designed for:
- Normal backups
- Moving VPR data to a new hosting environment
- Importing old VPR information into a future upgraded version
- Audit/review in Microsoft Excel

UPLOAD / RESTORE
The admin can upload a VPR .xlsx backup. The importer MERGES the workbook with the current database:
- Vehicles are matched by registration number
- Existing vehicles are enriched rather than blindly duplicated
- Interests are checked for duplicates before insertion
- Searches are matched by certificate number
- Certificate numbers remain stable across migrations
- Historical certificate interest snapshots are restored

CERTIFICATE STABILITY
V6 stores the certificate number in the database as a permanent field. This allows future migrations
without changing old certificate numbers even if internal database IDs differ after import.

HISTORICAL CERTIFICATE SNAPSHOTS
For new searches, VPR now stores a snapshot of the exact interested parties and ranking that existed
when the client performed the search. Therefore, viewing that certificate later reproduces the original
search result even if interests are subsequently added or cleared.

Older searches created before V6 did not have a frozen interest snapshot. Those legacy searches use the
best available historical data until they are migrated from a backup containing snapshot data.

SERVER REQUIREMENT
Excel backup/import uses PHP ZipArchive and SimpleXML. Most cPanel PHP installations include these.
If export/import reports that ZipArchive is unavailable, enable the PHP zip extension in cPanel or ask
the hosting provider to enable it.

SAFE UPDATE OF AN EXISTING SITE
1. Download a copy of your current data/vpr.sqlite before updating.
2. Keep the existing data/vpr.sqlite file.
3. Replace the remaining VPR files with this package.
4. Log into admin and confirm the Data Backup & Migration panel appears.
5. Download an Excel backup immediately and keep it safely.
