# ResearchFlow Hub - Research Papers

## Step 12 status

Step 12 implements the Research Papers module and stores the metadata required by later literature review, research gap, and citation features. It does not implement those later modules or generate citations yet.

## Pages

- `modules/papers/index.php` - owned paper list with search, required filters, sorting, and pagination.
- `modules/papers/create.php` - add a paper, optionally linked to an owned project.
- `modules/papers/show.php` - paper details, citation-ready metadata, keywords, summary, notes, and related-record counts.
- `modules/papers/edit.php` - update an owned paper.
- `modules/papers/delete.php` - CSRF-protected, POST-only deletion.

## Supported metadata

- Project, title, authors, publication year, and venue/publisher
- Volume, issue, pages, DOI, and HTTP/HTTPS paper URL
- Research area, keywords, summary, reading status, and personal notes

Reading statuses are To Read, Reading, Reviewed, Completed, and Important.

## List tools

- Search by title, author, keyword, venue, or DOI.
- Filter by project, publication year, reading status, and research area.
- Sort by newest, oldest, title, or publication year.
- Paginate with 10, 25, or 50 records per page.

## Security

- Every route requires authentication and restricts records by `user_id`.
- Submitted project IDs must belong to the authenticated user.
- Create, update, and delete require CSRF validation.
- Delete is POST-only and requires confirmation.
- Database values use prepared statements; sorting uses a fixed allowlist.
- Displayed values are escaped and paper links accept only HTTP or HTTPS.

## Step 12 checkpoint

- [x] Create, list, detail, update, and delete operations are implemented.
- [x] All required paper fields and reading statuses are supported.
- [x] Required filters, sorting, search, and pagination are implemented.
- [x] Project ownership and paper ownership are enforced.
- [x] Paper navigation is enabled globally, on the dashboard, and in project workspaces.
- [x] Citation metadata is stored without inventing missing values.
- [x] Literature reviews and research gaps are linked, and citation generation is implemented in Step 22.
