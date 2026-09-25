# ResearchFlow Hub - Research Projects

## Step 10 status

Step 10 implements the Research Projects CRUD module and project workspace. Related resources, papers, datasets, reviews, gaps, experiments, tasks, and notes are counted in the workspace, but their own CRUD pages remain outside this step.

## Pages

- `modules/projects/index.php` - user-owned project list with search, status filter, sorting, and pagination.
- `modules/projects/create.php` - create a project.
- `modules/projects/show.php` - project workspace overview and related-record counts.
- `modules/projects/edit.php` - update an owned project.
- `modules/projects/delete.php` - CSRF-protected, POST-only project deletion.

## Project fields

- Title (required, maximum 200 characters)
- Research area (optional, maximum 150 characters)
- Description (optional)
- Status: Planned, Ongoing, Completed, or Archived
- Start date (optional)
- Target date (optional and never earlier than the start date)

## Security and ownership

- Every page requires an authenticated user.
- All reads, updates, and deletes include the logged-in `user_id`.
- Missing and non-owned project IDs return the same 404 response.
- Create, update, and delete forms require a valid CSRF token.
- Deletion accepts POST only and uses an explicit browser confirmation.
- Database operations use PDO prepared statements; sort SQL uses a fixed allowlist.
- Stored content is escaped before rendering.

Deleting a project follows the schema relationships: project-specific reviews, gaps, experiments, tasks, and notes are deleted. Resources, papers, and datasets remain saved with no assigned project.

## Step 10 checkpoint

- [x] Create, list, view, update, and delete operations work.
- [x] Project title, status, optional fields, and date order are validated server-side.
- [x] Project list search, status filtering, allowed sorting, and 10/25/50 pagination work.
- [x] Project workspace includes Overview, Resources, Papers, Datasets, Literature Review, Research Gaps, Experiments, Tasks, and Notes sections.
- [x] Project progress is calculated from stored task records.
- [x] User ownership, CSRF protection, output escaping, prepared statements, and POST-only deletion are enforced.
- [x] Related module CRUD pages were not implemented in this step.
