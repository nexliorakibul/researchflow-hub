# ResearchFlow Hub - Dataset Manager

## Step 13 status

Step 13 implements CRUD for dataset metadata and external links. It intentionally does not upload or store large dataset files because that is outside Version 1 scope.

## Pages

- `modules/datasets/index.php` - owned dataset list with search, filters, sorting, and pagination.
- `modules/datasets/create.php` - add dataset metadata, optionally linked to an owned project.
- `modules/datasets/show.php` - complete dataset metadata and count details.
- `modules/datasets/edit.php` - update an owned dataset record.
- `modules/datasets/delete.php` - CSRF-protected, POST-only deletion.

## Supported metadata

- Project, name, domain, source, and HTTP/HTTPS URL
- Row, column, image, and class counts
- File size label, license, access type, description, status, and notes

Access types are Public, Restricted, Credentialed, Private, and Unknown. Statuses are Identified, Requested, Approved, Downloaded, Preprocessed, Used, and Archived.

## List tools

- Search by dataset name, domain, source, or license.
- Filter by project, access type, status, and domain.
- Sort by newest, oldest, name, or row count.
- Paginate with 10, 25, or 50 records per page.

## Security

- All routes require authentication and restrict records by `user_id`.
- Submitted project IDs must belong to the authenticated user.
- Create, update, and delete require CSRF validation.
- Delete is POST-only and requires confirmation.
- Database operations use prepared statements; sorting uses a fixed allowlist.
- Output is escaped, counts are non-negative whole numbers, and URLs accept only HTTP or HTTPS.

## Step 13 checkpoint

- [x] Create, list, detail, update, and delete operations are implemented.
- [x] Every required metadata field, access type, and status is supported.
- [x] Search, filters, sorting, and pagination are implemented.
- [x] Project and dataset ownership are enforced.
- [x] Dataset navigation is enabled globally and in project workspaces.
- [x] Dataset records store metadata and links only; large file storage was not added.
- [x] Literature Reviews and later modules were not implemented.
