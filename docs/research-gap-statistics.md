# ResearchFlow Hub - Research Gap Statistics

## Step 17 status

Step 17 implements the database-driven Research Gap Dashboard and Statistics page. It reads only the authenticated user's stored gaps and does not fabricate or hard-code results.

## Page

- `modules/research-gaps/statistics.php` - summary cards, accessible distribution table, and Chart.js gap-type chart.

## Statistics

- **Total gaps:** all owned gap records.
- **High priority:** records whose priority is exactly `high`.
- **Addressed:** records whose status is exactly `addressed`.
- **Open gaps:** records whose status is `identified` or `investigating`.
- **Distribution by gap type:** counts for every PDF-defined gap type.

The page shows an explicit empty state when no gaps exist. The accessible table contains the same distribution values as the doughnut chart and remains usable if Chart.js cannot load.

## Security and data integrity

- Authentication is required.
- Summary and distribution queries are restricted by `user_id` and use prepared statements.
- Chart labels come from the fixed gap-type allowlist.
- Chart values come from aggregate database counts.
- The data passed to JavaScript is safely JSON-encoded and HTML-escaped.

## Step 17 checkpoint

- [x] Total, high-priority, addressed, and open cards are database-driven.
- [x] Open is clearly defined as Identified plus Investigating.
- [x] Chart.js displays the distribution by gap type.
- [x] An accessible distribution table and chart failure fallback are present.
- [x] Empty state and responsive layouts are implemented.
- [x] Other users' gap data is excluded.
- [x] Experiments and later modules were not implemented.
