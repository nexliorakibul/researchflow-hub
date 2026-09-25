# ResearchFlow Hub - Research Tasks

## Step 20 status

Step 20 implements secure CRUD for project-based research tasks. Task completion automatically affects the existing project and dashboard progress calculations.

## Included

- Authenticated, user-owned task listing, details, creation, editing, and deletion.
- Required owned project and task title.
- PDF-defined priorities: Low, Medium, High, and Urgent.
- PDF-defined statuses: Pending, In Progress, Completed, and Cancelled.
- Optional description, start date, and deadline.
- Server-side and client-side validation preventing a deadline before its start date.
- Search plus project, priority, and status filters.
- Newest, oldest, title, and deadline sorting.
- Pagination with 10, 25, and 50 item options.
- Table view, overdue indicators, empty states, flash messages, and confirmation dialogs.
- Dashboard upcoming-task links and project workspace integration.

## Security

- Authentication and ownership checks protect every task route.
- Prepared statements are used for all database access.
- Forms use CSRF tokens and server-side validation.
- Output is escaped before rendering.
- Deletion requires POST and a valid CSRF token.

## Step 20 checkpoint

- [x] Task CRUD works with the required fields and defined values.
- [x] Invalid projects, dates, priorities, and statuses are rejected.
- [x] Completing a task updates database-driven project and dashboard progress.
- [x] Filters, sorting, pagination, empty states, and responsive table layouts work.
- [x] Other users' tasks are inaccessible.
- [x] Research Notes and later modules were not implemented.
