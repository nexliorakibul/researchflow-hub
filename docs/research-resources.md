# ResearchFlow Hub - General Research Resources

## Step 11 status

Step 11 implements the General Research Resources module. Research Papers and Dataset Manager remain separate later modules; a resource may still be categorized as a paper or dataset when the user only needs a general library entry.

## Pages

- `modules/resources/index.php` - owned resource list with search, filters, sorting, and pagination.
- `modules/resources/create.php` - add a resource, optionally linked to an owned project.
- `modules/resources/show.php` - complete resource detail view.
- `modules/resources/edit.php` - update an owned resource.
- `modules/resources/delete.php` - CSRF-protected, POST-only deletion.

## Supported information

- Project, title, type, research area, authors, publication year
- HTTP/HTTPS URL and DOI
- Description, personal notes, and tags
- Status and favorite flag

Resource types are Research Paper, Dataset, GitHub Repository, Website, Book, Research Tool, Documentation, Pretrained Model, Course, Tutorial, and Other. Statuses are Saved, To Read, Reading, Completed, and Important.

## List tools

- Search by title, author, tags, or DOI.
- Filter by project, type, status, research area, publication year, and favorite state.
- Sort by newest, oldest, title, or publication year.
- Paginate with 10, 25, or 50 records per page.

## Security

- All pages require authentication and restrict reads and writes by `user_id`.
- Submitted project IDs must belong to the authenticated user.
- Create, update, and delete require valid CSRF tokens.
- Delete is POST-only and uses an explicit confirmation dialog.
- SQL values use prepared statements; dynamic sorting uses a fixed allowlist.
- Displayed database content is escaped, and URLs accept only HTTP or HTTPS.

## Step 11 checkpoint

- [x] Create, list, detail, update, and delete operations are implemented.
- [x] All required resource fields, types, statuses, and favorite state are supported.
- [x] Required filters, sorting choices, search, and pagination are implemented.
- [x] Project relationship ownership is validated.
- [x] Resource navigation is enabled globally and inside project workspaces.
- [x] Papers, datasets, and later modules were not implemented.
