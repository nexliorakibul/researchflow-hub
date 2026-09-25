# ResearchFlow Hub - Research Notes

## Step 21 status

Step 21 implements secure CRUD for project-linked research notes.

## Included

- Authenticated, user-owned note listing, details, creation, editing, and deletion.
- Required owned project, title, content, and note type.
- PDF-defined types: General, Idea, Meeting, Experiment, Paper, Dataset, and Important.
- Database-generated creation and update dates.
- Search across note title, content, and project.
- Project and note-type filters.
- Newest, oldest, and title sorting.
- Pagination with 10, 25, and 50 item options.
- Responsive card listing, detail page, empty states, flash messages, and confirmation dialogs.
- Sidebar and project workspace integration.

## Security

- Authentication and ownership checks protect every note route.
- Prepared statements are used for all database access.
- Forms use CSRF tokens and authoritative server-side validation.
- Note content and other database output are escaped before rendering.
- Deletion requires POST and a valid CSRF token.

## Step 21 checkpoint

- [x] Note CRUD works with all required fields and defined note types.
- [x] Invalid projects, blank content, and invalid note types are rejected.
- [x] Search, filters, sorting, pagination, and responsive layouts work.
- [x] Creation dates are database-generated and displayed.
- [x] Other users' notes are inaccessible.
- [x] Citation Generator and later modules were not implemented.
