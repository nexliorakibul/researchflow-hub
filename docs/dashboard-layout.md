# ResearchFlow Hub - Dashboard and Application Layout

## Step 9 status

Step 9 provides the reusable protected application shell and the complete Version 1 dashboard view. CRUD pages and their navigation routes remain intentionally disabled until their own implementation steps.

## Reusable layout

- `includes/app-layout.php` renders the sidebar, header, flash messages, logout form, main content area, empty states, and footer.
- `assets/css/app.css` provides the academic visual design, dashboard components, responsive grid, collapsible tablet/mobile sidebar, stacked mobile cards, and scrollable tables.
- `assets/js/app.js` controls the sidebar through the hamburger button, backdrop, Escape key, and viewport changes.
- Bootstrap 5 is loaded as the required base UI framework; custom CSS supplies the project-specific design.

## Database-driven dashboard

The dashboard restricts every query by the authenticated `user_id` and displays:

- Counts for projects, resources, papers, datasets, research gaps, experiments, pending tasks, and completed tasks.
- Overall progress calculated as completed tasks / all tasks x 100, or zero when no tasks exist.
- The five most recently created projects, resources, papers, and experiments.
- The next five pending or in-progress tasks ordered by deadline.
- Helpful empty states when the user has no matching records.

## Step 9 checkpoint

- [x] Dashboard values come from MySQL rather than hard-coded sample statistics.
- [x] All dashboard reads are prepared statements restricted by the logged-in user ID.
- [x] Output from stored records is escaped before display.
- [x] Desktop, tablet, and mobile dashboard layouts are supported.
- [x] Logout remains a CSRF-protected POST action.
- [x] No CRUD, profile, password-change, search, chart, or later module was implemented.
