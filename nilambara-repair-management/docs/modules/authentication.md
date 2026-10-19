# Authentication

The application retains Laravel Fortify and Laravel's session guard; no second authentication scaffold or public registration route is introduced.

## Sign-in flow

- Staff sign in with a normalized, case-insensitive username and password.
- Fortify applies the existing per-username-and-IP limit of five attempts per minute.
- Passwords use Laravel's `hashed` cast and are never logged or returned in account views.
- Successful authentication regenerates the session ID. Logout invalidates the session and regenerates its CSRF token.
- CSRF validation remains provided by the `web` middleware.
- The public registration, password-reset, email-verification, and two-factor/passkey endpoints remain disabled. Administrator password reset is available from user management.
- Login success, failure, and logout are audit events. Successful login updates `users.last_login_at`.

The additive migration preserves existing email values and derives a unique username from each existing email's local part. New employee accounts may omit email; email is not used for authentication. Duplicate normalized usernames are rejected by both validation and the unique database index.

## Disabled accounts and sessions

Each account has an `is_active` flag, and linked employee records have their own employment state. Fortify refuses login when either is inactive. The `active.account` middleware checks every authenticated web request, logs out an inactive user, invalidates the session, and regenerates the CSRF token. Administrative disablement and employee deactivation also delete that user's stored database sessions. A disabled account therefore cannot continue using a session created before the change.

## Initial administrator

After migrations and `RoleSeeder`, use the private console bootstrap:

```powershell
php artisan app:create-user
```

It requests a display name, username, optional email, and a confirmed password (minimum 12 characters, with mixed case, letters, and numbers). The first administrator is linked to an employee record and assigned all current permissions. If an existing unassigned account needs to be promoted, use `php artisan app:assign-initial-admin <username>` and confirm the grant. Both commands refuse to bootstrap when an active administrator already exists.

## Development server note

On this Windows/XAMPP workspace, use PHP 8.5 and load the repository's extension config before starting Artisan. Laravel's development server creates a child PHP process; `--no-reload` ensures that child inherits `PHPRC`, including `pdo_mysql`:

```powershell
$env:PHPRC = (Resolve-Path ..\.tools\php.ini).Path
php artisan serve --host=127.0.0.1 --port=8000 --no-reload
```

Restart the server after changing `.env` because `--no-reload` disables automatic restarts.
