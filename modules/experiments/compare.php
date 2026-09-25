<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection, $user] = experiment_page_context();

$rawSelection = $_GET['experiments'] ?? [];
if (!is_array($rawSelection)) {
    $rawSelection = [];
}
$selectedIds = [];
foreach ($rawSelection as $value) {
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id !== false) {
        $selectedIds[(int) $id] = (int) $id;
    }
}

$statement = $connection->prepare(
    'SELECT e.id, e.experiment_name, e.model_name, e.model_type, e.experiment_date,
            e.accuracy, e.precision_score, e.recall_score, e.f1_score, e.auroc,
            p.title AS project_title, d.name AS dataset_name
     FROM experiments e
     INNER JOIN projects p ON p.id = e.project_id AND p.user_id = e.user_id
     LEFT JOIN datasets d ON d.id = e.dataset_id AND d.user_id = e.user_id
     WHERE e.user_id = :user_id
     ORDER BY e.experiment_date DESC, e.id DESC'
);
$statement->execute(['user_id' => $userId]);
$availableExperiments = $statement->fetchAll();

$selectedExperiments = [];
foreach ($availableExperiments as $experiment) {
    if (isset($selectedIds[(int) $experiment['id']])) {
        $selectedExperiments[] = $experiment;
    }
}

$chartDefinitions = [
    'accuracy' => ['title' => 'Accuracy', 'canvas_id' => 'accuracy-comparison-chart'],
    'f1_score' => ['title' => 'F1 score', 'canvas_id' => 'f1-comparison-chart'],
    'auroc' => ['title' => 'AUROC', 'canvas_id' => 'auroc-comparison-chart'],
];

render_app_page_start('Experiment Comparison', $user, 'experiment-comparison');
render_app_feedback();
?>
<nav class="breadcrumb-nav" aria-label="Breadcrumb"><a href="<?= e(app_url('modules/experiments/index.php')) ?>">Experiments</a><span aria-hidden="true">/</span><span aria-current="page">Comparison</span></nav>

<section class="page-heading"><div><p class="page-kicker">Experiment analysis</p><h2>Compare experiments</h2><p>Compare saved model results. Charts use only metrics recorded in your experiment data.</p></div><a class="secondary-button" href="<?= e(app_url('modules/experiments/index.php')) ?>">View experiments</a></section>

<section class="dashboard-panel comparison-selection-panel" aria-labelledby="comparison-selection-title">
    <div class="panel-heading"><h2 id="comparison-selection-title">Select experiments</h2><p>Choose at least two experiment records.</p></div>
    <?php if ($availableExperiments === []): ?>
        <?php render_empty_state('No experiments are available to compare.'); ?>
        <div class="comparison-empty-action"><a class="primary-button" href="<?= e(app_url('modules/experiments/create.php')) ?>">Add first experiment</a></div>
    <?php else: ?>
        <form method="get" action="<?= e(app_url('modules/experiments/compare.php')) ?>">
            <div class="comparison-selection-list">
                <?php foreach ($availableExperiments as $experiment): ?>
                    <label class="comparison-option"><input type="checkbox" name="experiments[]" value="<?= e($experiment['id']) ?>" <?= isset($selectedIds[(int) $experiment['id']]) ? 'checked' : '' ?>><span><strong><?= e($experiment['experiment_name']) ?></strong><small><?= e($experiment['model_name']) ?> · <?= e($experiment['dataset_name'] ?: 'Dataset unavailable') ?> · <?= e(experiment_date_label($experiment['experiment_date'])) ?></small></span></label>
                <?php endforeach; ?>
            </div>
            <div class="form-actions"><button class="primary-button" type="submit">Compare selected</button><a class="text-button" href="<?= e(app_url('modules/experiments/compare.php')) ?>">Clear selection</a></div>
        </form>
    <?php endif; ?>
</section>

<?php if ($selectedExperiments !== [] && count($selectedExperiments) < 2): ?>
    <div class="app-alert app-alert-warning" role="status">Select at least two experiments to create a comparison.</div>
<?php elseif (count($selectedExperiments) >= 2): ?>
    <section class="dashboard-panel comparison-table-panel" aria-labelledby="comparison-table-title">
        <div class="panel-heading"><h2 id="comparison-table-title">Metric comparison</h2><p><?= e(number_format(count($selectedExperiments))) ?> selected experiments</p></div>
        <div class="table-scroll"><table class="dashboard-table comparison-table"><thead><tr><th>Experiment</th><th>Model</th><th>Dataset</th><th>Accuracy</th><th>Precision</th><th>Recall</th><th>F1</th><th>AUROC</th></tr></thead><tbody>
            <?php foreach ($selectedExperiments as $experiment): ?><tr>
                <td><a class="record-title record-link" href="<?= e(app_url('modules/experiments/show.php?id=' . $experiment['id'])) ?>"><?= e($experiment['experiment_name']) ?></a><span class="record-subtitle"><?= e($experiment['project_title']) ?> · <?= e(experiment_date_label($experiment['experiment_date'])) ?></span></td>
                <td><span class="record-title"><?= e($experiment['model_name']) ?></span><span class="record-subtitle"><?= e($experiment['model_type'] ?: 'Type not set') ?></span></td>
                <td><?= e($experiment['dataset_name'] ?: 'Dataset unavailable') ?></td>
                <td><?= e(experiment_metric_label($experiment['accuracy'])) ?></td>
                <td><?= e(experiment_metric_label($experiment['precision_score'])) ?></td>
                <td><?= e(experiment_metric_label($experiment['recall_score'])) ?></td>
                <td><?= e(experiment_metric_label($experiment['f1_score'])) ?></td>
                <td><?= e(experiment_metric_label($experiment['auroc'])) ?></td>
            </tr><?php endforeach; ?>
        </tbody></table></div>
    </section>

    <section class="comparison-chart-grid" aria-label="Experiment metric charts">
        <?php foreach ($chartDefinitions as $field => $chart): ?>
            <?php
            $labels = [];
            $values = [];
            $hasValues = false;
            foreach ($selectedExperiments as $experiment) {
                $labels[] = $experiment['experiment_name'] . ' — ' . $experiment['model_name'];
                $value = $experiment[$field] === null ? null : round((float) $experiment[$field] * 100, 4);
                $values[] = $value;
                $hasValues = $hasValues || $value !== null;
            }
            ?>
            <article class="dashboard-panel comparison-chart-panel" aria-labelledby="<?= e($chart['canvas_id']) ?>-title">
                <div class="panel-heading"><h2 id="<?= e($chart['canvas_id']) ?>-title"><?= e($chart['title']) ?></h2><p>Recorded percentage by experiment</p></div>
                <?php if ($hasValues): ?>
                    <div class="comparison-chart-wrap"><canvas id="<?= e($chart['canvas_id']) ?>" data-experiment-chart data-metric="<?= e($chart['title']) ?>" data-labels="<?= e(json_encode($labels, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) ?>" data-values="<?= e(json_encode($values, JSON_THROW_ON_ERROR)) ?>" role="img" aria-label="Bar chart comparing <?= e($chart['title']) ?> across selected experiments"></canvas></div>
                    <p class="chart-fallback" data-chart-fallback hidden>The chart could not be loaded. The same values are available in the comparison table.</p>
                <?php else: ?>
                    <?php render_empty_state('No ' . $chart['title'] . ' values are recorded for the selected experiments.'); ?>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script src="<?= e(app_url('assets/js/experiment-comparison.js')) ?>" defer></script>
<?php render_app_page_end(); ?>
