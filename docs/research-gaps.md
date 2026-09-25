# ResearchFlow Hub - Research Gaps

## Step 16 status

Step 16 implements secure Research Gaps CRUD. Each gap belongs to an owned project and may optionally link to an owned research paper. The statistics dashboard is documented separately as Step 17.

## Pages

- `modules/research-gaps/index.php` - owned gap list with search, filters, sorting, and pagination.
- `modules/research-gaps/create.php` - create a project-linked gap with an optional paper.
- `modules/research-gaps/show.php` - complete gap detail and evidence.
- `modules/research-gaps/edit.php` - update an owned gap.
- `modules/research-gaps/delete.php` - CSRF-protected, POST-only deletion.

## Supported fields

- Project and optional related paper
- Gap title, type, and required description
- Evidence and potential solution
- Priority, status, and notes

Types are Dataset, Methodological, Performance, Validation, Explainability, Multimodal, Privacy, Generalization, Computational, Clinical Validation, and Other. Priorities are Low, Medium, High, and Critical. Statuses are Identified, Investigating, Addressed, and Rejected.

## List tools

- Search title, paper, description, evidence, solution, and notes.
- Filter by project, type, priority, and status.
- Sort by newest, oldest, title, or priority.
- Paginate with 10, 25, or 50 records per page.

## Security

- Authentication and `user_id` ownership checks protect every route.
- Submitted project and paper relationships are ownership-validated.
- Create, update, and delete require valid CSRF tokens.
- Delete is POST-only and requires confirmation.
- Queries use prepared statements; sorting uses a fixed allowlist.
- Displayed content is escaped and required/length validation runs server-side.

## Step 16 checkpoint

- [x] Create, list, detail, update, and delete operations are implemented.
- [x] All PDF-defined types, priorities, and statuses are supported.
- [x] Required gap fields and optional paper linking are implemented.
- [x] Required filters, search, sorting, and pagination are implemented.
- [x] Project, paper, and gap ownership are enforced.
- [x] Navigation is enabled globally, in project workspaces, and from paper details.
- [x] Gap Statistics/Dashboard was kept outside the Step 16 CRUD implementation.
