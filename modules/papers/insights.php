<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection, $user] = paper_page_context();
$paperId = paper_request_id();
$paper = find_owned_paper($connection, $paperId, $userId);

if ($paper === null) {
    render_paper_not_found($user);
}

$insights = null;
$openAccess = [];
$errors = [];

try {
    $insights = scholarly_paper_insights($paper);
} catch (ScholarlyApiException $exception) {
    $errors[] = $exception->getMessage();
}

if (valid_doi((string) ($paper['doi'] ?? ''))) {
    try {
        $openAccess = unpaywall_open_access((string) $paper['doi']);
    } catch (ScholarlyApiException $exception) {
        $openAccess = [];
    }
}

$details = is_array($insights['details'] ?? null) ? $insights['details'] : [];
$provider = (string) ($insights['provider'] ?? 'Scholarly provider');
if ($openAccess === [] && ($details['pdf_url'] ?? '') !== '') {
    $openAccess = [
        'is_open_access' => true,
        'status' => 'Semantic Scholar',
        'license' => '',
        'version' => '',
        'pdf_url' => (string) $details['pdf_url'],
        'landing_url' => (string) ($details['url'] ?? ''),
    ];
}

function render_scholarly_cards(array $items, string $emptyMessage): void
{
    if ($items === []) {
        render_empty_state($emptyMessage);
        return;
    }
    ?>
    <div class="scholarly-card-grid">
        <?php foreach ($items as $item): ?>
            <article class="scholarly-card">
                <div class="scholarly-card-meta">
                    <span><?= e(($item['year'] ?? '') ?: 'Year unavailable') ?></span>
                    <span><?= e(number_format((int) ($item['citation_count'] ?? 0))) ?> citations</span>
                </div>
                <h3><?= e($item['title'] ?? 'Untitled paper') ?></h3>
                <p><?= e(($item['authors'] ?? '') ?: 'Authors unavailable') ?></p>
                <div class="card-actions">
                    <?php if (($item['pdf_url'] ?? '') !== ''): ?><a href="<?= e($item['pdf_url']) ?>" target="_blank" rel="noopener noreferrer">Open PDF</a><?php endif; ?>
                    <?php if (($item['url'] ?? '') !== ''): ?><a href="<?= e($item['url']) ?>" target="_blank" rel="noopener noreferrer">Semantic Scholar</a><?php endif; ?>
                    <?php if (($item['doi'] ?? '') !== ''): ?><a href="<?= e('https://doi.org/' . $item['doi']) ?>" target="_blank" rel="noopener noreferrer">DOI</a><?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <?php
}

$githubSearch = 'https://github.com/search?' . http_build_query([
    'q' => '"' . (string) $paper['title'] . '"',
    'type' => 'repositories',
]);

render_app_page_start('Scholarly Insights', $user, 'papers');
render_app_feedback();
?>
<nav class="breadcrumb-nav" aria-label="Breadcrumb">
    <a href="<?= e(app_url('modules/papers/index.php')) ?>">Research Papers</a><span aria-hidden="true">/</span>
    <a href="<?= e(app_url('modules/papers/show.php?id=' . $paperId)) ?>"><?= e($paper['title']) ?></a><span aria-hidden="true">/</span>
    <span aria-current="page">Scholarly insights</span>
</nav>

<section class="page-heading">
    <div><p class="page-kicker">Version 3</p><h2>Scholarly insights</h2><p>Citations, references, related research, open-access locations, and code discovery.</p></div>
    <div class="hero-actions">
        <?php if ($paper['code_url']): ?><a class="primary-button" href="<?= e($paper['code_url']) ?>" target="_blank" rel="noopener noreferrer">Open saved code</a><?php endif; ?>
        <a class="secondary-button" href="<?= e($githubSearch) ?>" target="_blank" rel="noopener noreferrer">Find code on GitHub</a>
        <a class="secondary-button" href="<?= e(app_url('modules/papers/show.php?id=' . $paperId)) ?>">Back to paper</a>
    </div>
</section>

<?php if ($errors !== []): ?>
    <div class="app-alert app-alert-error" role="alert"><?= e(implode(' ', $errors)) ?></div>
<?php else: ?>
    <?php if (($insights['provider_notice'] ?? '') !== ''): ?><div class="app-alert app-alert-info" role="status"><?= e($insights['provider_notice']) ?></div><?php endif; ?>
    <section class="insight-stat-grid" aria-label="Paper impact summary">
        <article class="stat-card"><span>Citations</span><strong><?= e(number_format((int) ($details['citation_count'] ?? 0))) ?></strong><small><?= e($provider) ?></small></article>
        <article class="stat-card"><span>Influential</span><strong><?= e(number_format((int) ($details['influential_citation_count'] ?? 0))) ?></strong><small>Influential citations</small></article>
        <article class="stat-card"><span>References</span><strong><?= e(number_format((int) ($details['reference_count'] ?? 0))) ?></strong><small>Bibliography links</small></article>
        <article class="stat-card"><span>Open access</span><strong><?= !empty($openAccess['is_open_access']) ? 'Yes' : 'Not found' ?></strong><small><?= e(($openAccess['status'] ?? '') ?: 'Live lookup') ?></small></article>
    </section>

    <section class="dashboard-panel open-access-panel" aria-labelledby="open-access-title">
        <div class="panel-heading"><h2 id="open-access-title">Open-access finder</h2><p>Legal public locations reported by Unpaywall or Semantic Scholar</p></div>
        <div class="open-access-content">
            <?php if (!empty($openAccess['is_open_access'])): ?>
                <div><strong>Open-access copy found</strong><p><?= e(implode(' · ', array_filter([$openAccess['status'] ?? '', $openAccess['license'] ?? '', $openAccess['version'] ?? '']))) ?></p></div>
                <div class="hero-actions"><?php if (($openAccess['pdf_url'] ?? '') !== ''): ?><a class="primary-button" href="<?= e($openAccess['pdf_url']) ?>" target="_blank" rel="noopener noreferrer">Open PDF</a><?php endif; ?><?php if (($openAccess['landing_url'] ?? '') !== ''): ?><a class="secondary-button" href="<?= e($openAccess['landing_url']) ?>" target="_blank" rel="noopener noreferrer">Open source page</a><?php endif; ?></div>
            <?php else: ?>
                <p class="muted-copy">No verified open-access location was returned. This does not mean that no legal copy exists.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="dashboard-panel scholarly-section" aria-labelledby="related-title"><div class="panel-heading"><h2 id="related-title">Related papers</h2><p>Recommendations based on the selected paper</p></div><?php render_scholarly_cards($insights['recommendations'] ?? [], 'No related-paper recommendations were returned.'); ?></section>
    <section class="dashboard-panel scholarly-section" aria-labelledby="citations-title"><div class="panel-heading"><h2 id="citations-title">Selected citing papers</h2><p>Papers that cite this work</p></div><?php render_scholarly_cards($insights['citations'] ?? [], 'No citing papers were returned.'); ?></section>
    <section class="dashboard-panel scholarly-section" aria-labelledby="references-title"><div class="panel-heading"><h2 id="references-title">References</h2><p>Selected works referenced by this paper</p></div><?php render_scholarly_cards($insights['references'] ?? [], 'No references were returned.'); ?></section>
<?php endif; ?>
<?php render_app_page_end(); ?>
