# ResearchFlow Hub - Literature Reviews

## Step 14 status

Step 14 implements structured literature review CRUD. Every review belongs to an authenticated user and links one owned project with one owned research paper. The comparison matrix is documented separately as Step 15.

## Pages

- `modules/literature-reviews/index.php` - owned review list with search, filters, sorting, and pagination.
- `modules/literature-reviews/create.php` - create a review linked to an owned project and paper.
- `modules/literature-reviews/show.php` - complete structured review detail.
- `modules/literature-reviews/edit.php` - update an owned review and its relationships.
- `modules/literature-reviews/delete.php` - CSRF-protected, POST-only deletion.

## Supported review fields

- Research objective
- Dataset used and dataset size
- Methodology, models used, and preprocessing
- Evaluation metrics and main results
- Key findings, strengths, and limitations
- Future work and researcher notes

## List tools

- Search paper titles/authors and review evidence fields.
- Filter by project, paper, and paper publication year.
- Sort by newest, oldest, paper title, or paper year.
- Paginate with 10, 25, or 50 records per page.

## Security

- All routes require authentication and restrict reviews by `user_id`.
- Submitted project and paper IDs must both belong to the authenticated user.
- Create, update, and delete require CSRF validation.
- Delete is POST-only and requires confirmation.
- SQL uses prepared statements; dynamic sorting uses a fixed allowlist.
- All displayed user content is escaped and field lengths are validated server-side.

## Step 14 checkpoint

- [x] Create, list, detail, update, and delete operations are implemented.
- [x] Every review field required by the PDF is supported.
- [x] Project, paper, and review ownership are enforced.
- [x] Search, filters, sorting, and pagination are implemented.
- [x] Review navigation is enabled globally, in projects, and from paper details.
- [x] Literature Review Matrix was kept outside the Step 14 CRUD implementation.
