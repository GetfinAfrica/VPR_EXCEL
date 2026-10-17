VPR MVP V2 — Vehicle Pledge Registry

CHANGES IN V2
- Chassis number is optional.
- Registration number is also optional on the public search.
- Vehicle brand is now a separate REQUIRED dropdown.
- Vehicle model is REQUIRED and remains free text.
- Model examples are shown: Fortuner, Hilux, Prado, NV350.
- Major global and Chinese vehicle brands are included, including Foton, BAW, GWM, Haval, FAW, JAC, Chery, Geely, BYD, Changan, Dongfeng, DFSK, JMC, MG, Maxus, GAC, Jetour, Omoda and Jaecoo.
- The database keeps the fuller/longer model description when the same vehicle is searched again.
- Existing search statistics and admin functions remain.
- Existing V1 databases are automatically upgraded when the new db.php is used.

IMPORTANT FOR AN EXISTING LIVE SITE
If you already have data you want to keep:
1. Make a backup copy of your current data/vpr.sqlite.
2. Upload the new PHP files from this package.
3. KEEP your existing data/vpr.sqlite rather than replacing it with the blank V2 database.
4. On the first page load, db.php will automatically upgrade the old database and add the brand field.
5. Existing old records will initially have brand "Other"; future searches can improve those records.

FOR A FRESH INSTALL
Upload/extract the whole package. The included data/vpr.sqlite is already V2-ready.

MATCHING RULE
VPR checks a specific vehicle using:
- Chassis number if supplied, otherwise
- Registration number if supplied.

If neither identifier is entered, the brand/model search is still stored for database-building purposes,
but the site does NOT issue a no-interest clearance because it cannot uniquely identify the vehicle.

ADMIN INTERESTS
To register an interest, brand, model and interested party are required, plus at least one unique identifier:
chassis number OR registration number.

SECURITY
Change the admin password in config.php before live use.
