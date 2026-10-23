# Production security checklist (cPanel / shared hosting)

`.env.example` is a development template. On the server set:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain
LOG_LEVEL=warning
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
SESSION_SAME_SITE=lax
```

Checklist:

- Point the domain document root at `public/` only. `.env`, `storage/`, `vendor/` and the backup folder must not be web-served.
- Serve over HTTPS and force redirect (cPanel "Force HTTPS Redirect"). HSTS is sent automatically on secure requests.
- Run `php artisan config:cache route:cache view:cache` after deploy; never leave `APP_DEBUG=true`.
- Keep `.env` permissions 640 or stricter; use a dedicated MySQL user with only this database.
- Store backups outside the web root and test a restore regularly.
- Change all demo account passwords (12+ characters) and deactivate unused accounts.
- Run `composer audit` and `npm audit` before each release and keep PHP/Laravel patched.

Security headers (`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, a framing/base-uri/form-action CSP) come from `App\Http\Middleware\SecurityHeaders`. A script-restricting CSP is not enabled because views use inline Alpine handlers.
