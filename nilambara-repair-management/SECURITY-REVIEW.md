# Security review (2026-10-23)

Scope: Laravel 13 / PHP app in `nilambara-repair-management`. No application is "100% hack-proof"; this lists what was checked and changed.

## Findings

| # | Severity | Status | Item |
|---|----------|--------|------|
| 1 | Low | Fixed | No security headers. Added `SecurityHeaders` middleware (nosniff, frame, referrer, permissions, base CSP, HSTS on HTTPS). |
| 2 | Low | Fixed | Login throttled per username+IP only. Added a 20/min per-IP limit; throttled attempts are audited as `auth.login.throttled`. |
| 3 | Low | Documented | `.env.example` has development values. See `docs/development/production-security-checklist.md`. |
| 4 | Info | Open | No script-restricting CSP, because views use inline Alpine handlers. |
| 5 | Info | Open | `pos_discount` branch in approvals is unreachable dead code. |

No confirmed exploitable vulnerabilities were found. `composer audit` and `npm audit` reported none.

## Verified controls

- Permission checks on routes plus model policies; inactive and denied accounts blocked in `Gate::before`.
- Edit-approval workflow: field whitelists, self-approval blocked, stale snapshot check, idempotency.
- No `{!! !!}` in views; no file uploads; raw SQL uses constants only.
- Backups: path traversal checks, no shell, credentials file, audited downloads.
- Sessions: encrypted, HTTP-only, SameSite=lax. Passwords 12+ chars with breach check in production.
- Login success, failure, throttle and logout are audit-logged.

## Not verifiable here

Server configuration (web root, HTTPS, file permissions, MySQL user rights) and a real backup restore test must be checked on the hosting account.
