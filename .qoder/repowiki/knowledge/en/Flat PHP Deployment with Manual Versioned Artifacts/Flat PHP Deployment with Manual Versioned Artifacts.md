---
kind: build_system
name: Flat PHP Deployment with Manual Versioned Artifacts
category: build_system
scope:
    - '**'
source_files:
    - install.cmd
    - Latest
    - Checksum
    - db.php
    - .gitignore
---

## Build System Overview

This repository has no traditional build system. It is a flat PHP application deployed directly onto a web server (Apache via `.htaccess`). There are no `Makefile`, `Dockerfile`, CI pipeline, package manifest, or cross-compile toolchain in the repository.

The only "build" artifacts present are:
- A Windows CMD script (`install.cmd`) that downloads and installs the **Qoder IDE** CLI — this is an unrelated third-party installer bundled in the repo, not part of VPR's own packaging.
- Plain-text files `Latest` and `Checksum` used as human-readable markers for the current release.

## Versioning Strategy

Version is tracked manually in two places:
- `Latest` contains `== version: 1.1.73` (the current VPR release).
- The runtime constant `VPR_VERSION` is defined by `config.php` (referenced from `db.php` line 2 via `require_once __DIR__ . '/config.php'`; `config.php` itself is gitignored per `.gitignore` and holds secrets such as DB path, API keys, and `VPR_VERSION`).

The admin console displays the version via `<?=htmlspecialchars(VPR_VERSION)?>` in `admin2000.php` and the public footer echoes it in `index.php`. There is no automated bump step; version changes are manual edits to `config.php` and `Latest`.

## Database Schema Versioning

Schema evolution is handled in-code within `db.php`:
- On first connection, `ensure_v6_schema($pdo)` bootstraps the schema.
- `system_metadata` stores `schema_version` = `12` (see `db.php` line 484 and `data_exchange.php` line 205).
- Backup/restore code validates incoming Excel backups against supported schema versions 1–12 (`Unsupported or invalid VPR backup schema version.`). This is the only migration mechanism — there is no external migration runner.

## Deployment Model

Deployment is file-copy based:
- SQLite database lives at `data/vpr.sqlite` (created on first run).
- Configuration goes in `config.php` (gitignored, never committed).
- Apache configuration is provided via root `.htaccess` and `data/.htaccess`.
- No containerization, no artifact registry, no release pipeline exists in the repo.

## Constraints Observed

- `config.php` is excluded from version control (`.gitignore`); secrets and `VPR_VERSION` are supplied externally at deploy time.
- The `Latest` file is the single source of truth for the human-visible release number; `VPR_VERSION` in `config.php` is the runtime value.
- Schema versioning is enforced at runtime through the `schema_version` metadata row and the range check `if ($version < 1 || $version > 12)` in `data_exchange.php`.
- The bundled `install.cmd` is a Qoder IDE installer (not VPR-related) and performs its own manifest fetch, SHA256 verification, extraction, and delegation to a binary's `install` subcommand.