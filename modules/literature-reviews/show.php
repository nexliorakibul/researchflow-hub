<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection, $user] = review_page_context();
$reviewId = review_request_id();
$review = find_owned_review($connection, $reviewId, $userId);
if ($review === null) {
    render_review_not_found($user);
}

$sections = [
    'Research objective' => $review['research_objective'],
    'Methodology' => $review['methodology'],
    'Preprocessing' => $review['preprocessing'],
    'Main results' => $review['main_results'],
    'Key findings' => $review['key_findings'],
    'Strengths' => $review['strengths'],
    'Limitations' => $review['limitations'],
    'Future work' => $review['future_work'],
    'Researcher notes' => $review['researcher_notes'],
];

render_app_page_start('Literature Review Details', $user, 'reviews');
render_app_feedback();
?>
<nav class="breadcrumb-nav" aria-label="Breadcrumb"><a href="<?= e(app_url('modules/literature-reviews/index.php')) ?>">Literature Reviews</a><span aria-hidden="true">/</span><span aria-current="page"><?= e($review['paper_title']) ?></span></nav>

<section class="review-hero">
    <div><div class="project-hero-meta"><a href="<?= e(app_url('modules/projects/show.php?id=' . $review['project_id'])) ?>"><?= e($review['project_title']) ?></a><span><?= e($review['paper_year'] ?: 'Year not set') ?></span></div><h2><?= e($review['paper_title']) ?></h2><p><?= e($review['paper_authors'] ?: 'Authors have not been added to this paper.') ?></p></div>
    <div class="hero-actions"><a class="primary-button" href="<?= e(app_url('modules/papers/show.php?id=' . $review['paper_id'])) ?>">View paper</a><a class="secondary-button" href="<?= e(app_url('modules/literature-reviews/matrix.php?project_id=' . $review['project_id'])) ?>">View matrix</a><a class="secondary-button" href="<?= e(app_url('modules/literature-reviews/edit.php?id=' . $reviewId)) ?>">Edit</a><form method="post" action="<?= e(app_url('modules/literature-reviews/delete.php')) ?>" data-confirm="Delete this literature review? This cannot be undone."><?= csrf_input() ?><input type="hidden" name="id" value="<?= e($reviewId) ?>"><button class="danger-button" type="submit">Delete</button></form></div>
</section>

<section class="dashboard-panel review-evidence-panel" aria-labelledby="review-evidence-title"><div class="panel-heading"><h2 id="review-evidence-title">Evidence summary</h2><p>Structured fields used by the literature review matrix</p></div><dl class="detail-list review-detail-list">
    <div><dt>Dataset used</dt><dd><?= e($review['dataset_used'] ?: 'Not set') ?></dd></div>
    <div><dt>Dataset size</dt><dd><?= e($review['dataset_size'] ?: 'Not set') ?></dd></div>
    <div><dt>Models used</dt><dd><?= e($review['models_used'] ?: 'Not set') ?></dd></div>
    <div><dt>Metrics</dt><dd><?= e($review['metrics'] ?: 'Not set') ?></dd></div>
    <div><dt>Created</dt><dd><?= e(review_date_label($review['created_at'])) ?></dd></div>
    <div><dt>Last updated</dt><dd><?= e(review_date_label($review['updated_at'])) ?></dd></div>
</dl></section>

<div class="review-section-grid">
    <?php foreach ($sections as $label => $content): ?>
        <section class="dashboard-panel text-panel"><div class="panel-heading"><h2><?= e($label) ?></h2></div><div class="long-copy"><?= nl2br(e($content ?: 'Not provided.')) ?></div></section>
    <?php endforeach; ?>
</div>
<?php render_app_page_end(); ?>
