# ResearchFlow Hub - User Profile

## Step 25 status

Step 25 implements a protected profile page where the authenticated researcher can view and update their own account information.

## Included

- View and update full name and login email.
- View and update institution, research interests, and bio.
- Account creation and last-updated dates in the profile summary.
- Account navigation link and updated sidebar identity after a successful save.
- Responsive desktop, tablet, and mobile profile layout.
- Clear validation errors, success feedback, and cancel/reset navigation.

## Validation and security

- Authentication is required to view or submit the profile.
- Updates always target the authenticated session user ID.
- Full name must contain 2 to 100 characters.
- Email must be valid, unique, and no longer than 190 characters.
- Institution is optional and limited to 150 characters.
- CSRF protection is required for updates.
- Database operations use PDO prepared statements.
- Submitted and stored values are escaped before display.
- Malformed non-scalar form values are handled safely.

## Step 25 checkpoint

- [x] All five required profile fields can be viewed and updated.
- [x] Duplicate and invalid emails are rejected without changing stored data.
- [x] Only the authenticated user's record can be updated.
- [x] CSRF and output escaping are enforced.
- [x] The page remains usable on desktop, tablet, and mobile.
- [x] Password Change is handled separately in Step 26.
