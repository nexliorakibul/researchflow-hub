# ResearchFlow Hub - Application Foundation

## Step 7 status

**PASS** - the shared PHP bootstrap and security helpers were created and verified without implementing pages, authentication flows, or CRUD modules.

## Added foundation

- `config/app.php` - application name, environment, base URL, timezone, and session name.
- `includes/bootstrap.php` - central loader, secure session settings, timezone, error-display policy, and security headers.
- `includes/functions.php` - output escaping, safe redirects, URLs, and flash messages.
- `includes/security.php` - request-method enforcement and common security headers.
- `includes/csrf.php` - secure token generation, hidden form input, verification, enforcement, and rotation.
- `includes/auth.php` - current-user checks and prepared ownership queries for allowed private tables.
- `includes/validation.php` - reusable validation for names, email, passwords, HTTP/HTTPS URLs, years, dates, metrics, and experiment splits.

## Security behaviour

- Sessions use cookies only, strict mode, HTTP-only cookies, and `SameSite=Lax`.
- `RFH_SESSION_PATH` can select an existing writable session directory when the PHP runtime default is unavailable.
- Session cookies become `Secure` automatically under HTTPS.
- Raw PHP errors are not displayed to users.
- User-controlled output can be escaped with `e()` using UTF-8 and quote escaping.
- Redirect values reject header injection.
- Destructive controllers can enforce POST with `require_post_request()`.
- State-changing forms can use `csrf_input()` and `require_valid_csrf_token()`.
- Authentication helpers read only a positive integer `user_id` from the session.
- Ownership checks use a table allowlist and a PDO prepared statement containing both record ID and user ID.
- Security headers disable MIME sniffing, framing by other sites, camera, microphone, and geolocation access.

## Verification result

- All new PHP files pass syntax validation with PHP 8.0.28.
- Bootstrap starts one named secure session without producing output.
- XSS escaping, CSRF success/failure, token rotation, flash-message consumption, and validators pass their checks.
- PDO ownership checking uses the existing verified MySQL connection and returns false for a missing record.
- No login, registration, logout, page template, CRUD, or Step 8 implementation was added.

## Usage rule for future pages

Every application entry point should start with:

```php
require_once __DIR__ . '/includes/bootstrap.php';
```

Nested module pages must adjust the relative path while still loading the same bootstrap exactly once.

## Step 7 checkpoint

- [x] Central bootstrap exists.
- [x] Secure session settings exist.
- [x] CSRF, XSS escaping, POST enforcement, validation, flash, and ownership helpers exist.
- [x] Helpers are reusable and contain no credentials.
- [x] Foundation tests pass.
- [x] No Step 8 feature implementation was started.
