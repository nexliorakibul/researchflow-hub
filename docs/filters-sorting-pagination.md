# ResearchFlow Hub - Filters, Sorting, and Pagination

## Step 24 status

Step 24 verifies the database-driven list controls required by the project scope. Existing module controls were retained and audited rather than duplicated.

## Required filter coverage

- Resources: project, type, status, research area, publication year, and favorite state.
- Papers: project, publication year, reading status, and research area.
- Experiments: project, dataset, model, and experiment date.
- Research gaps: project, gap type, priority, and status.
- Literature review matrix: search, project, publication year, dataset, and model.

Projects, datasets, literature reviews, tasks, and notes also provide relevant module-specific filters and search controls.

## Sorting coverage

- Newest and oldest sorting is available across record lists.
- Title A-Z and Z-A sorting is available where records have a title or name.
- Publication-year sorting is available for resources, papers, and literature reviews.
- Deadline sorting is available for tasks.
- Accuracy and F1 sorting is available for experiments.

Sort choices use fixed server-side allowlists; submitted values are never inserted into SQL unless they match a defined option.

## Pagination behaviour

- Lists default to 10 records per page.
- Users can select 10, 25, or 50 records per page.
- Invalid page sizes fall back to 10 and invalid page numbers fall back to page 1.
- Requested pages beyond the available range are clamped to the last available page.
- Filter, search, sort, and page-size selections are preserved in Previous and Next links.

## Security and ownership

- All list pages require authentication.
- Every list and count query is restricted by the authenticated user's ID.
- Filter values use prepared-statement parameters.
- Sort expressions come only from server-defined allowlists.
- Output values and generated pagination URLs are HTML-escaped.

## Step 24 checkpoint

- [x] All PDF-required filters are present.
- [x] All required sort modes are present where applicable.
- [x] Long lists use a default page size of 10 with 10, 25, and 50 options.
- [x] Pagination preserves active controls and handles invalid values safely.
- [x] User ownership remains enforced in list and count queries.
- [x] User Profile and later modules were not implemented.
