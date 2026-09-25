<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once dirname(__DIR__, 2) . '/includes/citations.php';

[$userId, $connection, $user] = paper_page_context();
$paperId = paper_request_id();
$paper = find_owned_paper($connection, $paperId, $userId);

if ($paper === null) {
    render_paper_not_found($user);
}

$keywords = array_values(array_filter(array_map(
    static fn (string $keyword): string => trim($keyword),
    explode(',', (string) ($paper['keywords'] ?? ''))
)));

$relatedStatement = $connection->prepare(
    'SELECT
        (SELECT COUNT(*) FROM literature_reviews WHERE paper_id = :review_paper AND user_id = :review_user) AS reviews,
        (SELECT COUNT(*) FROM research_gaps WHERE paper_id = :gap_paper AND user_id = :gap_user) AS gaps'
);
$relatedStatement->execute([
    'review_paper' => $paperId,
    'review_user' => $userId,
    'gap_paper' => $paperId,
    'gap_user' => $userId,
]);
$related = $relatedStatement->fetch();

if (!is_array($related)) {
    $related = ['reviews' => 0, 'gaps' => 0];
}

$citations = generate_paper_citations($paper);
$missingCitationFields = citation_missing_core_fields($paper);

render_app_page_start('Paper Details', $user, 'papers');
render_app_feedback();
?>
<nav class="breadcrumb-nav" aria-label="Breadcrumb">
    <a href="<?= e(app_url('modules/papers/index.php')) ?>">Research Papers</a><span aria-hidden="true">/</span><span aria-current="page"><?= e($paper['title']) ?></span>
</nav>

<section class="paper-hero">
    <div>
        <div class="project-hero-meta"><span class="status-badge status-<?= e(str_replace('_', '-', $paper['reading_status'])) ?>"><?= e(paper_label($paper['reading_status'])) ?></span><span><?= e($paper['publication_year'] ?: 'Year not set') ?></span></div>
        <h2><?= e($paper['title']) ?></h2>
        <p><?= e($paper['authors'] ?: 'Authors have not been added.') ?></p>
    </div>
    <div class="hero-actions">
        <?php if ($paper['url']): ?><a class="primary-button" href="<?= e($paper['url']) ?>" target="_blank" rel="noopener noreferrer">Open paper</a><?php endif; ?>
        <a class="secondary-button" href="<?= e(app_url('modules/papers/edit.php?id=' . $paperId)) ?>">Edit</a>
        <form method="post" action="<?= e(app_url('modules/papers/delete.php')) ?>" data-confirm="Delete this research paper? Linked literature reviews will also be deleted. This cannot be undone.">
            <?= csrf_input() ?><input type="hidden" name="id" value="<?= e($paperId) ?>"><button class="danger-button" type="submit">Delete</button>
        </form>
    </div>
</section>

<div class="paper-detail-grid">
    <section class="dashboard-panel" aria-labelledby="paper-metadata-title">
        <div class="panel-heading"><h2 id="paper-metadata-title">Citation-ready metadata</h2><p>Saved publication information</p></div>
        <dl class="detail-list paper-detail-list">
            <div><dt>Project</dt><dd><?= e($paper['project_title'] ?: 'Unassigned') ?></dd></div>
            <div><dt>Research area</dt><dd><?= e($paper['research_area'] ?: 'Not set') ?></dd></div>
            <div><dt>Venue / publisher</dt><dd><?= e($paper['venue'] ?: 'Not set') ?></dd></div>
            <div><dt>Publication year</dt><dd><?= e($paper['publication_year'] ?: 'Not set') ?></dd></div>
            <div><dt>Volume</dt><dd><?= e($paper['volume'] ?: 'Not set') ?></dd></div>
            <div><dt>Issue</dt><dd><?= e($paper['issue'] ?: 'Not set') ?></dd></div>
            <div><dt>Pages</dt><dd><?= e($paper['pages'] ?: 'Not set') ?></dd></div>
            <div><dt>DOI</dt><dd><?= e($paper['doi'] ?: 'Not set') ?></dd></div>
            <div><dt>Reading status</dt><dd><?= e(paper_label($paper['reading_status'])) ?></dd></div>
            <div><dt>Last updated</dt><dd><?= e(paper_date_label($paper['updated_at'])) ?></dd></div>
        </dl>
    </section>
    <aside class="dashboard-panel" aria-labelledby="paper-related-title">
        <div class="panel-heading"><h2 id="paper-related-title">Research connections</h2><p>Records linked to this paper</p></div>
        <dl class="connection-list">
            <div><dt>Literature reviews</dt><dd><a href="<?= e(app_url('modules/literature-reviews/index.php?paper_id=' . $paperId)) ?>"><?= e((int) $related['reviews']) ?></a></dd></div>
            <div><dt>Research gaps</dt><dd><a href="<?= e(app_url('modules/research-gaps/index.php?paper_id=' . $paperId)) ?>"><?= e((int) $related['gaps']) ?></a></dd></div>
        </dl>
        <div class="module-actions"><a href="<?= e(app_url('modules/literature-reviews/create.php?paper_id=' . $paperId . ($paper['project_id'] ? '&project_id=' . $paper['project_id'] : ''))) ?>">Add literature review</a><a href="<?= e(app_url('modules/research-gaps/create.php?paper_id=' . $paperId . ($paper['project_id'] ? '&project_id=' . $paper['project_id'] : ''))) ?>">Add research gap</a><a href="#citation-generator">Generate citation</a></div>
        <p class="module-note">Citations are generated from this paper's saved metadata.</p>
    </aside>
</div>

<section class="dashboard-panel citation-panel" id="citation-generator" data-citation-generator aria-labelledby="citation-generator-title">
    <div class="panel-heading"><h2 id="citation-generator-title">Citation generator</h2><p>Preview and copy IEEE, APA, or Harvard format</p></div>
    <div class="citation-generator-content">
        <?php if ($missingCitationFields !== []): ?><div class="app-alert app-alert-warning citation-warning" role="status">Missing citation metadata is omitted: <?= e(implode(', ', $missingCitationFields)) ?>. Edit the paper to complete it.</div><?php endif; ?>
        <div class="citation-controls">
            <div class="form-field"><label for="citation-style">Citation style</label><select id="citation-style" data-citation-style><option value="ieee" data-citation="<?= e($citations['ieee']) ?>">IEEE</option><option value="apa" data-citation="<?= e($citations['apa']) ?>">APA</option><option value="harvard" data-citation="<?= e($citations['harvard']) ?>">Harvard</option></select></div>
            <button class="primary-button" type="button" data-citation-copy>Copy citation</button>
        </div>
        <label class="citation-preview-label" for="citation-preview">Citation preview</label>
        <textarea class="citation-preview" id="citation-preview" rows="5" readonly data-citation-preview><?= e($citations['ieee']) ?></textarea>
        <p class="citation-copy-status" data-citation-status aria-live="polite"></p>
        <p class="module-note citation-note">Review the generated citation against your course or publisher guidance before submission. Only saved metadata is included.</p>
    </div>
</section>

<section class="dashboard-panel paper-keywords-panel" aria-labelledby="paper-keywords-title">
    <div class="panel-heading"><h2 id="paper-keywords-title">Keywords</h2><p>Terms associated with this paper</p></div>
    <div class="tag-list">
        <?php if ($keywords === []): ?><p class="muted-copy">No keywords added.</p><?php else: ?>
            <?php foreach ($keywords as $keyword): ?><span><?= e($keyword) ?></span><?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<div class="paper-text-grid">
    <section class="dashboard-panel text-panel" aria-labelledby="summary-title"><div class="panel-heading"><h2 id="summary-title">Summary</h2></div><div class="long-copy"><?= nl2br(e($paper['summary'] ?: 'No summary provided.')) ?></div></section>
    <section class="dashboard-panel text-panel" aria-labelledby="paper-notes-title"><div class="panel-heading"><h2 id="paper-notes-title">Personal notes</h2></div><div class="long-copy"><?= nl2br(e($paper['personal_notes'] ?: 'No personal notes added.')) ?></div></section>
</div>
<script src="<?= e(app_url('assets/js/citation-generator.js')) ?>" defer></script>
<?php render_app_page_end(); ?>
