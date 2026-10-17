---
kind: error_handling
name: 'Flat PHP Error Handling: die/exit, Throwable, register_shutdown_function, and JSON error envelopes'
category: error_handling
scope:
    - '**'
source_files:
    - db.php
    - paynow_create.php
    - certificate.php
    - admin2000.php
    - data_exchange.php
    - paynow_result.php
---

## Approach

VPR is a flat PHP application with no framework, so error handling is implemented through three complementary mechanisms:

1. **`die()` / `exit()` with `http_response_code()`** — used in page entry points for immediate termination on invalid input or authorization failures.
2. **PHP `Throwable` (catch of `Throwable`, not just `Exception`)** — used inside shared library functions (`db.php`) and Paynow integration scripts to propagate recoverable business/network errors up to callers.
3. **`register_shutdown_function` + `error_get_last()`** — a last-resort fatal-error handler that converts E_ERROR/E_PARSE/etc. into a JSON response rather than leaking raw PHP stack traces.

There is no global exception handler, no custom exception hierarchy, no sentinel error constants, and no middleware layer. The pattern is procedural: each script owns its own try/catch boundaries.

## Key Files

- `db.php` — the single shared core library; sets PDO to `ERRMODE_EXCEPTION`, catches `Throwable` in read helpers (`vpr_setting`, `audit_admin`, `checkpoint_db`), and throws `Exception` from network calls (`paynow_http_post`, `paynow_poll_status`).
- `paynow_create.php` — defines a local `vpr_json_exit()` helper and a `register_shutdown_function` that sanitizes fatal errors into a uniform `{ok,message,technical}` JSON envelope.
- `certificate.php`, `admin2000.php`, `data_exchange.php`, `paynow_result.php`, `paynow_return.php`, `paynow_status.php` — page-level scripts that terminate early via `http_response_code()` + `exit()`.

## Architecture & Conventions

### Database errors are swallowed at the boundary
In `db.php`, read-only helpers wrap their work in `try { ... } catch (Throwable $e) { return $default; }` (see `vpr_setting`, line 6–10). This means a missing table or corrupt DB does not crash the request — it returns a fallback value. Write helpers (`vpr_set_setting`, `audit_admin`) use empty `catch (Throwable $e) {}` blocks so audit logging never aborts the caller.

PDO itself is configured globally in `db()`: `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION` (line 28), so all SQL errors surface as `PDOException` (a subclass of `Throwable`).

### Network / gateway errors throw `Exception`
The Paynow HTTP layer in `db.php` throws descriptive `Exception`s:
- `"Unable to connect to Paynow: <curl_error>"` (line 308)
- `"Paynow returned HTTP <code>."` (line 311)
- `"The server cannot make outbound Paynow requests. Enable PHP cURL or allow_url_fopen."` (line 318)
- `"Unable to connect to Paynow from this hosting server."` (line 335)
- `"Missing Paynow poll URL."` (line 340)
- `"Paynow status signature could not be verified."` (line 344)

Callers in `paynow_create.php` catch `Throwable` around the Paynow call and translate it into a 502 JSON response with the message stored back into the `payments.last_error` column.

### Page-level validation uses `http_response_code()` + `exit()`
Public pages validate inputs before any database access and exit immediately:
- `certificate.php`: 404 for missing ID / missing row, 403 for wrong access token.
- `admin2000.php`: 403 for invalid CSRF/security token.
- `data_exchange.php`: 403 for unauthenticated admin, 400 for missing/too-large upload.
- `paynow_result.php`: 400 for missing reference, 403 for bad signature / mismatched ref.

These are hard exits — no further processing occurs after them.

### JSON API has a dedicated error envelope
`paynow_create.php` defines `vpr_json_exit($ok, $message='', array $extra=[], $http=200)` which:
- Sets `Content-Type: application/json; charset=UTF-8`
- Clears output buffers
- Emits `{ ok, message, ...extra }`
- Calls `exit`

It is used for every structured error path (405, 400, 502, 500).

### Fatal error safety net
`paynow_create.php` registers a shutdown function that checks `error_get_last()` against `[E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR]`. If matched, it emits a sanitized JSON body with `ok=false`, a generic `"VPR payment service encountered a server error."` message, and a `technical` field containing only the basename of the file plus line number and message — deliberately stripping the full path.

### Transaction rollback convention
`finalize_vpr_search_from_payment()` wraps its transactional block in `try { ... } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }` — rethrowing the original exception after ensuring rollback.

## Conventions Observed

- Input-validation failures → `http_response_code(<code>)` followed immediately by `exit('<human message>')`.
- Business-rule violations inside shared code → `throw new Exception('<descriptive message>')`.
- Unrecoverable runtime faults → caught by `register_shutdown_function` in JSON endpoints, producing a sanitized 500 JSON response.
- Read-side DB failures in `db.php` → silently return defaults (no propagation).
- Audit/logging DB failures → silently ignored (`catch (Throwable $e) {}`).
- Payment-gateway failures → logged into `payments.last_error` and surfaced to the caller as a 502 JSON response.
- No custom exception classes, no error-code constants, no centralized error logger, no `set_error_handler`.
- All user-facing messages are plain strings; there is no i18n or error-code enumeration.