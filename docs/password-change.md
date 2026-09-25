# ResearchFlow Hub - Password Change

## Step 26 status

Step 26 implements secure password changes for the authenticated researcher.

## Included

- Protected Change Password page linked from Account navigation and User Profile.
- Current password verification.
- New password and confirmation fields.
- Required password policy: at least eight characters with uppercase, lowercase, and a number.
- Rejection when the confirmation differs or the new password matches the current password.
- Success and validation feedback without redisplaying password values.

## Security

- A valid authenticated session and CSRF token are required.
- The update targets only the authenticated user's ID.
- The current hash is checked with `password_verify()`.
- The replacement password is stored only through `password_hash()`.
- PDO prepared statements are used for reading and updating the account.
- Session ID and CSRF token are rotated after a successful change.
- The existing authenticated session remains active after rotation.
- Plaintext passwords are never rendered, logged, or stored.

## Step 26 checkpoint

- [x] Correct current credentials and a valid new password complete the change.
- [x] Incorrect current passwords, weak passwords, and mismatched confirmation are rejected.
- [x] CSRF failure prevents the update.
- [x] The old password stops working and the new password works.
- [x] Another user's password hash remains unchanged.
- [x] Responsive Design is verified separately in Step 27.
