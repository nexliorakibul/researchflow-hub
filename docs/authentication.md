# ResearchFlow Hub - Authentication

## Step 8 status

The Version 1 authentication flow includes registration, login, POST-only logout, protected sessions, and a minimal protected dashboard checkpoint. The complete dashboard module is intentionally not implemented in this step.

## Pages

- `register.php` - creates a user after server-side validation and CSRF verification.
- `login.php` - verifies the saved password hash and starts an authenticated session.
- `logout.php` - accepts only authenticated POST requests with a valid CSRF token.
- `dashboard.php` - minimal protected checkpoint that verifies the session against the database.

## Registration rules

- Full name is required and must contain 2-100 characters.
- Email is required, normalized to lowercase, validated, and unique.
- Password requires at least 8 characters, including uppercase, lowercase, and a number.
- Password confirmation must match.
- Passwords are stored only through `password_hash()`.
- Duplicate-email responses do not expose database details.

## Login and session rules

- Login errors do not reveal whether an email exists.
- Passwords are checked with `password_verify()` and rehashed when PHP recommends it.
- Successful login regenerates the session ID before storing the user ID.
- The CSRF token rotates after login and logout.
- Private pages require a valid positive user ID and confirm that the user still exists.
- Logout clears session data, expires the session cookie, destroys the old session, and creates a clean guest session.

## Verification result

- Registration creates a database user with a non-plaintext password hash.
- Duplicate registration is rejected.
- Invalid login is rejected with a generic message.
- Valid login redirects to the protected checkpoint.
- Unauthenticated dashboard access redirects to login.
- Logout through GET is rejected with HTTP 405.
- Logout through POST with the session's CSRF token succeeds.
- The old authenticated session cannot reopen the protected page after logout.
- Test users and temporary HTTP/session files are removed after testing.

## Step 8 checkpoint

- [x] Registration, login, logout, and protected sessions are implemented.
- [x] Password hashing, prepared statements, CSRF, output escaping, session regeneration, and server validation are applied.
- [x] Responsive authentication pages and useful feedback are present.
- [x] No complete dashboard, profile, password-change, or CRUD module was started.
