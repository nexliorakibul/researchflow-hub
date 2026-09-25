# Experiments

Step 18 implements secure CRUD for ML/DL experiment records.

## Included

- Authenticated, user-owned experiment listing, details, creation, editing, and deletion.
- Required project, dataset, experiment name, model name, and experiment date.
- Optional preprocessing, feature engineering, data splits, seed, training configuration, notes, and metrics.
- Server-side validation for owned relationships, ISO dates, positive training values, complete splits totaling 100, and metrics from 0 to 1.
- Search plus project, dataset, model, and date filters.
- Newest, oldest, title, accuracy, and F1 sorting.
- Pagination with 10, 25, and 50 item options.
- Percentage display for stored metric values.
- CSRF protection, prepared statements, escaped output, ownership checks, confirmation prompts, and POST-only deletion.
- Dashboard, sidebar, and project workspace links.

## Checkpoint

Experiment CRUD is complete. Experiment comparison and charts are implemented separately in Step 19.
