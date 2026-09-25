<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection, $user] = gap_page_context();
$gapId = gap_request_id();
$gap = find_owned_gap($connection, $gapId, $userId);
if ($gap === null) {
    render_gap_not_found($user);
}

render_app_page_start('Research Gap Details', $user, 'gaps');
render_app_feedback();
?>
<nav class="breadcrumb-nav" aria-label="Breadcrumb"><a href="<?= e(app_url('modules/research-gaps/index.php')) ?>">Research Gaps</a><span aria-hidden="true">/</span><span aria-current="page"><?= e($gap['gap_title']) ?></span></nav>

<section class="gap-hero">
    <div><div class="project-hero-meta"><span class="priority-badge priority-<?= e($gap['priority']) ?>"><?= e(gap_label($gap['priority'])) ?> priority</span><span class="status-badge status-<?= e(str_replace('_', '-', $gap['status'])) ?>"><?= e(gap_label($gap['status'])) ?></span><span><?= e(gap_label($gap['gap_type'])) ?></span></div><h2><?= e($gap['gap_title']) ?></h2><p><?= e($gap['description']) ?></p></div>
    <div class="hero-actions"><a class="secondary-button" href="<?= e(app_url('modules/research-gaps/edit.php?id=' . $gapId)) ?>">Edit</a><form method="post" action="<?= e(app_url('modules/research-gaps/delete.php')) ?>" data-confirm="Delete this research gap? This cannot be undone."><?= csrf_input() ?><input type="hidden" name="id" value="<?= e($gapId) ?>"><button class="danger-button" type="submit">Delete</button></form></div>
</section>

<section class="dashboard-panel gap-context-panel" aria-labelledby="gap-context-title"><div class="panel-heading"><h2 id="gap-context-title">Research context</h2><p>Project and paper relationships</p></div><dl class="detail-list gap-detail-list">
    <div><dt>Project</dt><dd><a href="<?= e(app_url('modules/projects/show.php?id=' . $gap['project_id'])) ?>"><?= e($gap['project_title']) ?></a></dd></div>
    <div><dt>Related paper</dt><dd><?php if ($gap['paper_id']): ?><a href="<?= e(app_url('modules/papers/show.php?id=' . $gap['paper_id'])) ?>"><?= e($gap['paper_title']) ?></a><?php else: ?>Not linked<?php endif; ?></dd></div>
    <div><dt>Created</dt><dd><?= e(gap_date_label($gap['created_at'])) ?></dd></div>
    <div><dt>Last updated</dt><dd><?= e(gap_date_label($gap['updated_at'])) ?></dd></div>
</dl></section>

<div class="gap-section-grid">
    <section class="dashboard-panel text-panel"><div class="panel-heading"><h2>Description</h2></div><div class="long-copy"><?= nl2br(e($gap['description'])) ?></div></section>
    <section class="dashboard-panel text-panel"><div class="panel-heading"><h2>Evidence</h2></div><div class="long-copy"><?= nl2br(e($gap['evidence'] ?: 'No evidence provided.')) ?></div></section>
    <section class="dashboard-panel text-panel"><div class="panel-heading"><h2>Potential solution</h2></div><div class="long-copy"><?= nl2br(e($gap['potential_solution'] ?: 'No potential solution provided.')) ?></div></section>
    <section class="dashboard-panel text-panel"><div class="panel-heading"><h2>Notes</h2></div><div class="long-copy"><?= nl2br(e($gap['notes'] ?: 'No notes added.')) ?></div></section>
</div>
<?php render_app_page_end(); ?>
