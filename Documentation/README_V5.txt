VPR V5 — ADMIN VEHICLE SEARCH HISTORY

NEW ADMIN FEATURE
The admin page can search a vehicle by registration number and display its full VPR search history.

For each historical search the admin sees:
- Date and time
- Vehicle brand and model as entered during that search
- Registration number
- Chassis number where supplied
- Result returned for that search
- Unique VPR certificate number
- One-click View Certificate button

CERTIFICATE BEHAVIOUR
The View Certificate button passes the original search ID to certificate.php.
This reproduces the certificate tied to that stored search record and uses the same certificate reference
that the client received for that search.

All previous VPR V4 features remain.

LIVE UPDATE
If you already have VPR records, keep your existing data/vpr.sqlite file and replace the other files.
