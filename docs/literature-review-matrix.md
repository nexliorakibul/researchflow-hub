# ResearchFlow Hub - Literature Review Matrix

## Step 15 status

Step 15 implements a database-driven comparison matrix generated from the authenticated user's stored literature reviews. It introduces no duplicate matrix storage and does not modify review evidence.

## Page

- `modules/literature-reviews/matrix.php` - comparison table with search, required filters, sorting, and pagination.

## Matrix columns

- Paper
- Dataset
- Method
- Model
- Result
- Limitation

The Paper cell also shows its project, authors, and publication year and links to the full review.

## Search and filters

- Search paper titles/authors, datasets, methods, models, results, and limitations.
- Filter by project, paper publication year, exact stored dataset, and exact stored model value.
- Sort by paper title, publication year, or review creation date.
- Paginate with 10 records by default and 10, 25, or 50 per-page choices.

## Security

- Authentication is required.
- Every query is restricted by the current `user_id`.
- All filter values use prepared statements.
- Dynamic sorting uses a fixed server-side allowlist.
- Every displayed database value is escaped.

## Step 15 checkpoint

- [x] Matrix rows are generated from stored literature reviews.
- [x] Paper, Dataset, Method, Model, Result, and Limitation columns are present.
- [x] Search and project/year/dataset/model filters are implemented.
- [x] Sorting and pagination are implemented.
- [x] Empty and no-match states are provided.
- [x] Other users' review evidence is not accessible.
- [x] Research Gaps and later modules were not implemented.
