VPR V7 — SQLITE WAL PERFORMANCE UPDATE

This release keeps the existing VPR architecture and permanently enables the SQLite settings
recommended for better concurrent-search performance.

ENABLED ON EVERY DATABASE CONNECTION
- PRAGMA journal_mode = WAL
- PRAGMA synchronous = NORMAL
- PRAGMA busy_timeout = 5000
- PRAGMA foreign_keys = ON
- PRAGMA temp_store = MEMORY

WHY THIS HELPS
WAL (Write-Ahead Logging) allows normal vehicle searches and database writes to coexist with much
less blocking than SQLite's traditional rollback-journal mode.

busy_timeout = 5000 tells SQLite to wait for up to 5 seconds when another request temporarily holds
a database write lock instead of immediately returning a "database is locked" error.

synchronous = NORMAL is the standard WAL performance setting used here to reduce unnecessary disk
synchronisation overhead while retaining normal durability for this application.

NO DATABASE MIGRATION REQUIRED
If you already have a live VPR database:
1. Back up data/vpr.sqlite.
2. Keep your existing data/vpr.sqlite file.
3. Replace the other VPR files with this V7 package.
4. The first database connection will switch the database into WAL mode automatically.

IMPORTANT FILES
Once WAL mode is active, SQLite may create these files beside the database while the site is running:
- vpr.sqlite-wal
- vpr.sqlite-shm

Do not delete those files while the application is active. They are normal SQLite working files.

BACKUPS
The existing VPR Excel backup/import system remains available and is still the recommended portable
backup/migration format for future VPR versions.

All V6 features remain:
- Public vehicle-interest searches
- Mandatory unique registration number
- Brand/model capture
- Optional chassis number
- Ranked interests
- Branded certificates
- Stable certificate numbers
- Historical search and interest snapshots
- Admin search history
- Excel backup/import
- VPR/Diranor branding and contact details
