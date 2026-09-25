# ResearchFlow Hub - Experiment Comparison

## Step 19 status

Step 19 implements a read-only comparison generated from the authenticated user's saved experiment records. It does not train models, predict results, or store duplicate comparison data.

## Page

- `modules/experiments/compare.php` - experiment selection, accessible comparison table, and database-driven charts.

## Comparison fields

- Experiment and project.
- Model name and model type.
- Dataset.
- Accuracy, precision, recall, F1 score, and AUROC.

Metrics remain optional. Recorded values are stored from 0 to 1 and displayed as percentages; missing values display as not recorded.

## Charts

Chart.js displays separate bar charts for:

- Accuracy.
- F1 score.
- AUROC.

Each chart uses the selected experiments' stored database values. The comparison table remains available if Chart.js cannot load, and a clear empty state replaces any chart whose metric has no recorded values.

## Security

- Authentication is required.
- The prepared query is restricted by the authenticated `user_id`.
- Submitted identifiers are validated as positive integers and matched only against owned results.
- Database content, JSON chart data, and HTML output are escaped.
- The page is read-only and does not create or modify experiment records.

## Step 19 checkpoint

- [x] Users can select and compare multiple owned experiments.
- [x] The table includes model, dataset, accuracy, precision, recall, F1, and AUROC.
- [x] Accuracy, F1, and AUROC charts are database-driven.
- [x] Missing metrics and empty experiment collections have explicit states.
- [x] Other users' experiments are excluded.
- [x] Desktop, tablet, and mobile layouts are supported.
- [x] Research Tasks and later modules were not implemented.
