---
kind: dependency_management
name: Flat PHP Application with No Third-Party Dependency Manager
category: dependency_management
scope:
    - '**'
source_files:
    - .htaccess
    - .gitignore
    - db.php
    - data_exchange.php
---

## Approach

This is a flat PHP application with **no third-party dependency manager** (no `composer.json`, no `package.json`, no `vendor/` directory, no lockfiles). All runtime dependencies are satisfied by:

1. **PHP core extensions** — the code uses built-in classes such as `ZipArchive` and `SQLite3`; `data_exchange.php` explicitly checks for `ZipArchive` at runtime and throws an exception if it is missing.
2. **A single shared library** — every entry script (`admin2000.php`, `certificate.php`, `index.php`, `domain_check.php`, `data_exchange.php`, `paynow_create.php`, etc.) loads the shared core via `require_once __DIR__ . '/db.php';`. The `db.php` file in turn includes `/config.php` for configuration.
3. **External HTTP APIs** — Paynow USD payments and ZiG API calls are made directly from PHP (e.g. `paynow_create.php`, `zig_api_charge.php`) without any SDK; there is no vendored client library.

## Key Files

- `.htaccess` — contains a `FilesMatch` rule that blocks access to `composer.(json|lock)` among other sensitive files, which indicates the project template or deployment process may have once expected Composer but none is present in this checkout.
- `.gitignore` — ignores only `config.php`, SQLite databases under `data/`, and `.env*` files. No `vendor/`, `node_modules/`, or lockfile patterns are listed.
- `db.php` — the sole shared library; all other scripts depend on it through `require_once`.
- `data_exchange.php` — runtime dependency check: `if (!class_exists('ZipArchive')) throw new Exception('PHP ZipArchive extension is required.');`

## Conventions and Constraints

- **No package manager**: There is no `composer.json`, `composer.lock`, `package.json`, `yarn.lock`, or `Gemfile` anywhere in the repository. Dependencies are pinned implicitly by the PHP version and enabled extensions on the host server.
- **Single-file modules**: Each `.php` file under the root is self-contained except for its one shared dependency on `db.php`. There is no autoloader, namespace-based imports, or PSR-4 structure.
- **Runtime extension validation**: Optional extensions (e.g. `ZipArchive`) are detected at runtime rather than declared in a manifest.
- **Secrets via config file**: Configuration lives in `config.php` (ignored by Git); the `.htaccess` rule also protects it from web access.
- **Composer presence is incidental**: The `.htaccess` deny rule references `composer.(json|lock)`, but these files do not exist in the repo — likely a leftover from a scaffolding template.