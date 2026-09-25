# ResearchFlow Hub - Global Search

## Step 23 status

Step 23 implements authenticated, database-driven search across the user's research workspace.

## Search coverage

- Research projects: title, area, and description.
- General resources: title, authors, tags, area, description, notes, and DOI.
- Research papers: title, authors, keywords, venue, area, summary, notes, and DOI.
- Datasets: name, domain, source, description, notes, and license.
- Research gaps: title, type, description, evidence, potential solution, and notes.
- Experiments: experiment name, model name/type, preprocessing, feature engineering, optimizer, and notes.

Resource tags and paper keywords satisfy the required tag search coverage. Tasks and research notes are not part of the PDF-defined Global Search scope.

## Behaviour

- A search field is available in every protected page header and in the sidebar.
- Results from all required modules appear in one newest-first list.
- Every result links to its owned record detail page.
- Empty-query and no-result states are explicit.
- Result lists support 10, 25, or 50 items per page.
- Desktop, tablet, and mobile layouts are supported.

## Security

- Authentication is required.
- Every union branch restricts results by the authenticated `user_id`.
- Search terms and pagination values are passed through prepared PDO statements.
- Result types and destination paths use fixed server-side mappings.
- Database titles, metadata, excerpts, and the submitted query are HTML-escaped.

## Step 23 checkpoint

- [x] All PDF-required searchable record types and fields are covered.
- [x] Authors, resource tags, and paper keywords are searchable.
- [x] Cross-user records are excluded from every result type.
- [x] Search results link to the correct detail pages.
- [x] Pagination and responsive layouts work.
- [x] Profile and later modules were not implemented.
