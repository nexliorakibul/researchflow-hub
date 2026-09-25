<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection, $user] = experiment_page_context();
$experimentId = experiment_request_id();
$experiment = find_owned_experiment($connection, $experimentId, $userId);
if ($experiment === null) {
    render_experiment_not_found($user);
}

render_app_page_start('Experiment Details', $user, 'experiments');
render_app_feedback();
?>
<nav class="breadcrumb-nav" aria-label="Breadcrumb"><a href="<?= e(app_url('modules/experiments/index.php')) ?>">Experiments</a><span aria-hidden="true">/</span><span aria-current="page"><?= e($experiment['experiment_name']) ?></span></nav>

<section class="experiment-hero">
    <div><div class="project-hero-meta"><span><?= e($experiment['model_type'] ?: 'Model type not set') ?></span><span><?= e(experiment_date_label($experiment['experiment_date'])) ?></span></div><h2><?= e($experiment['experiment_name']) ?></h2><p><?= e($experiment['model_name']) ?></p></div>
    <div class="hero-actions"><a class="secondary-button" href="<?= e(app_url('modules/experiments/edit.php?id=' . $experimentId)) ?>">Edit</a><form method="post" action="<?= e(app_url('modules/experiments/delete.php')) ?>" data-confirm="Delete this experiment? This cannot be undone."><?= csrf_input() ?><input type="hidden" name="id" value="<?= e($experimentId) ?>"><button class="danger-button" type="submit">Delete</button></form></div>
</section>

<section class="dashboard-panel experiment-context-panel" aria-labelledby="experiment-context-title"><div class="panel-heading"><h2 id="experiment-context-title">Research context</h2><p>Project, dataset, and record dates</p></div><dl class="detail-list experiment-detail-list">
    <div><dt>Project</dt><dd><a href="<?= e(app_url('modules/projects/show.php?id=' . $experiment['project_id'])) ?>"><?= e($experiment['project_title']) ?></a></dd></div>
    <div><dt>Dataset</dt><dd><?php if ($experiment['dataset_id']): ?><a href="<?= e(app_url('modules/datasets/show.php?id=' . $experiment['dataset_id'])) ?>"><?= e($experiment['dataset_name']) ?></a><?php else: ?>Dataset unavailable<?php endif; ?></dd></div>
    <div><dt>Experiment date</dt><dd><?= e(experiment_date_label($experiment['experiment_date'])) ?></dd></div>
    <div><dt>Last updated</dt><dd><?= e(experiment_date_label($experiment['updated_at'])) ?></dd></div>
</dl></section>

<section class="dashboard-panel experiment-metrics-panel" aria-labelledby="metrics-title"><div class="panel-heading"><h2 id="metrics-title">Evaluation metrics</h2><p>Displayed as percentages from stored 0–1 values</p></div><dl class="experiment-metric-grid"><?php foreach (EXPERIMENT_METRICS as $field => $label): ?><div><dt><?= e($label) ?></dt><dd><?= e(experiment_metric_label($experiment[$field])) ?></dd></div><?php endforeach; ?></dl></section>

<div class="experiment-section-grid">
    <section class="dashboard-panel"><div class="panel-heading"><h2>Training configuration</h2></div><dl class="detail-list experiment-detail-list"><div><dt>Random seed</dt><dd><?= e(experiment_value_label($experiment['random_seed'])) ?></dd></div><div><dt>Learning rate</dt><dd><?= e(experiment_value_label($experiment['learning_rate'])) ?></dd></div><div><dt>Batch size</dt><dd><?= e(experiment_value_label($experiment['batch_size'])) ?></dd></div><div><dt>Epochs</dt><dd><?= e(experiment_value_label($experiment['epochs'])) ?></dd></div><div><dt>Optimizer</dt><dd><?= e(experiment_value_label($experiment['optimizer'])) ?></dd></div><div><dt>Loss function</dt><dd><?= e(experiment_value_label($experiment['loss_function'])) ?></dd></div></dl></section>
    <section class="dashboard-panel"><div class="panel-heading"><h2>Data splits</h2></div><dl class="detail-list experiment-detail-list"><div><dt>Train</dt><dd><?= $experiment['train_split'] === null ? 'Not set' : e(number_format((float) $experiment['train_split'], 2) . '%') ?></dd></div><div><dt>Validation</dt><dd><?= $experiment['validation_split'] === null ? 'Not set' : e(number_format((float) $experiment['validation_split'], 2) . '%') ?></dd></div><div><dt>Test</dt><dd><?= $experiment['test_split'] === null ? 'Not set' : e(number_format((float) $experiment['test_split'], 2) . '%') ?></dd></div></dl></section>
    <section class="dashboard-panel text-panel"><div class="panel-heading"><h2>Preprocessing</h2></div><div class="long-copy"><?= nl2br(e($experiment['preprocessing'] ?: 'No preprocessing notes added.')) ?></div></section>
    <section class="dashboard-panel text-panel"><div class="panel-heading"><h2>Feature engineering</h2></div><div class="long-copy"><?= nl2br(e($experiment['feature_engineering'] ?: 'No feature engineering notes added.')) ?></div></section>
    <section class="dashboard-panel text-panel experiment-notes-panel"><div class="panel-heading"><h2>Notes</h2></div><div class="long-copy"><?= nl2br(e($experiment['notes'] ?: 'No notes added.')) ?></div></section>
</div>
<?php render_app_page_end(); ?>
