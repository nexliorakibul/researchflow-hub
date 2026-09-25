<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection, $user] = dataset_page_context();
$datasetId = dataset_request_id();
$dataset = find_owned_dataset($connection, $datasetId, $userId);

if ($dataset === null) {
    render_dataset_not_found($user);
}

render_app_page_start('Dataset Details', $user, 'datasets');
render_app_feedback();
?>
<nav class="breadcrumb-nav" aria-label="Breadcrumb"><a href="<?= e(app_url('modules/datasets/index.php')) ?>">Dataset Manager</a><span aria-hidden="true">/</span><span aria-current="page"><?= e($dataset['name']) ?></span></nav>

<section class="dataset-hero">
    <div><div class="project-hero-meta"><span class="status-badge status-<?= e(str_replace('_', '-', (string) $dataset['status'])) ?>"><?= e(dataset_label($dataset['status'])) ?></span><span><?= e(dataset_label($dataset['access_type'])) ?> access</span></div><h2><?= e($dataset['name']) ?></h2><p><?= e($dataset['description'] ?: 'No dataset description has been added.') ?></p></div>
    <div class="hero-actions">
        <?php if ($dataset['url']): ?><a class="primary-button" href="<?= e($dataset['url']) ?>" target="_blank" rel="noopener noreferrer">Open dataset</a><?php endif; ?>
        <a class="secondary-button" href="<?= e(app_url('modules/datasets/edit.php?id=' . $datasetId)) ?>">Edit</a>
        <form method="post" action="<?= e(app_url('modules/datasets/delete.php')) ?>" data-confirm="Delete this dataset metadata record? This cannot be undone."><?= csrf_input() ?><input type="hidden" name="id" value="<?= e($datasetId) ?>"><button class="danger-button" type="submit">Delete</button></form>
    </div>
</section>

<div class="dataset-detail-grid">
    <section class="dashboard-panel" aria-labelledby="dataset-metadata-title"><div class="panel-heading"><h2 id="dataset-metadata-title">Dataset metadata</h2><p>Source, licensing, and project information</p></div><dl class="detail-list dataset-detail-list">
        <div><dt>Project</dt><dd><?= e($dataset['project_title'] ?: 'Unassigned') ?></dd></div>
        <div><dt>Domain</dt><dd><?= e($dataset['domain'] ?: 'Not set') ?></dd></div>
        <div><dt>Source</dt><dd><?= e($dataset['source'] ?: 'Not set') ?></dd></div>
        <div><dt>License</dt><dd><?= e($dataset['license'] ?: 'Not set') ?></dd></div>
        <div><dt>Access type</dt><dd><?= e(dataset_label($dataset['access_type'])) ?></dd></div>
        <div><dt>Status</dt><dd><?= e(dataset_label($dataset['status'])) ?></dd></div>
        <div><dt>File size</dt><dd><?= e($dataset['file_size'] ?: 'Not set') ?></dd></div>
        <div><dt>Last updated</dt><dd><?= e(dataset_date_label($dataset['updated_at'])) ?></dd></div>
    </dl></section>
    <aside class="dashboard-panel" aria-labelledby="dataset-shape-title"><div class="panel-heading"><h2 id="dataset-shape-title">Dataset shape</h2><p>Saved count metadata</p></div><dl class="dataset-count-list">
        <div><dt>Rows</dt><dd><?= e($dataset['row_count'] !== null ? number_format((int) $dataset['row_count']) : '—') ?></dd></div>
        <div><dt>Columns</dt><dd><?= e($dataset['column_count'] !== null ? number_format((int) $dataset['column_count']) : '—') ?></dd></div>
        <div><dt>Images</dt><dd><?= e($dataset['image_count'] !== null ? number_format((int) $dataset['image_count']) : '—') ?></dd></div>
        <div><dt>Classes</dt><dd><?= e($dataset['class_count'] !== null ? number_format((int) $dataset['class_count']) : '—') ?></dd></div>
    </dl></aside>
</div>

<div class="dataset-text-grid">
    <section class="dashboard-panel text-panel" aria-labelledby="dataset-description-title"><div class="panel-heading"><h2 id="dataset-description-title">Description</h2></div><div class="long-copy"><?= nl2br(e($dataset['description'] ?: 'No description provided.')) ?></div></section>
    <section class="dashboard-panel text-panel" aria-labelledby="dataset-notes-title"><div class="panel-heading"><h2 id="dataset-notes-title">Notes</h2></div><div class="long-copy"><?= nl2br(e($dataset['notes'] ?: 'No notes added.')) ?></div></section>
</div>
<p class="storage-note">ResearchFlow Hub stores dataset metadata and links only. Large dataset file storage is outside Version 1 scope.</p>
<?php render_app_page_end(); ?>
