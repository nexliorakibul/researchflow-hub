<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection, $user] = paper_page_context();
$projects = paper_projects($connection, $userId);
$query = scholarly_text_limit(query_string('q'), 200);
$results = [];
$errors = [];

if ($query !== '') {
    try {
        $results = openalex_search_papers($query, 10);
    } catch (ScholarlyApiException $exception) {
        $errors[] = $exception->getMessage();
    }
}

render_app_page_start('Discover Papers', $user, 'papers');
render_app_feedback();
?>
<nav class="breadcrumb-nav" aria-label="Breadcrumb">
    <a href="<?= e(app_url('modules/papers/index.php')) ?>">Research Papers</a><span aria-hidden="true">/</span><span aria-current="page">Discover</span>
</nav>

<section class="page-heading page-heading-compact">
    <div><p class="page-kicker">Version 2</p><h2>Discover with OpenAlex</h2><p>Search scholarly works and import selected metadata into your private library.</p></div>
    <a class="secondary-button" href="<?= e(app_url('modules/papers/import-doi.php')) ?>">Import DOI</a>
</section>

<section class="form-panel api-lookup-panel">
    <?php render_paper_errors($errors); ?>
    <form method="get" action="<?= e(app_url('modules/papers/discover.php')) ?>" class="api-lookup-form" role="search">
        <div class="form-field">
            <label for="openalex-query">Title, topic, author, or keyword</label>
            <input id="openalex-query" name="q" type="search" value="<?= e($query) ?>" minlength="2" maxlength="200" placeholder="e.g. explainable artificial intelligence" required autofocus>
        </div>
        <button class="primary-button" type="submit">Search papers</button>
    </form>
    <p class="module-note">Your search terms are sent to OpenAlex to retrieve public scholarly metadata.</p>
</section>

<?php if ($query !== '' && $errors === []): ?>
    <div class="list-summary"><p><?= e(count($results)) ?> result<?= count($results) === 1 ? '' : 's' ?> from OpenAlex</p></div>
    <?php if ($results === []): ?>
        <section class="dashboard-panel"><?php render_empty_state('No OpenAlex papers matched this search.'); ?></section>
    <?php else: ?>
        <section class="api-results" aria-label="OpenAlex search results">
            <?php foreach ($results as $resultIndex => $paper): ?>
                <article class="dashboard-panel api-result-card">
                    <div class="api-result-heading">
                        <div>
                            <p class="page-kicker"><?= e($paper['publication_year'] ?: 'Year unknown') ?><?= $paper['venue'] !== '' ? ' · ' . e($paper['venue']) : '' ?></p>
                            <h2><?= e($paper['title'] ?: 'Untitled work') ?></h2>
                            <p><?= e($paper['authors'] ?: 'Authors not provided') ?></p>
                        </div>
                        <div class="api-result-badges"><span class="status-badge"><?= e(number_format($paper['cited_by_count'])) ?> citations</span><?php if ($paper['is_open_access']): ?><span class="status-badge status-completed">Open access</span><?php endif; ?></div>
                    </div>
                    <?php if ($paper['summary'] !== ''): ?><p class="api-result-summary"><?= e(scholarly_text_limit($paper['summary'], 420)) ?><?= strlen($paper['summary']) > 420 ? '…' : '' ?></p><?php endif; ?>
                    <form method="post" action="<?= e(app_url('modules/papers/import-openalex.php')) ?>" class="import-options">
                        <?= csrf_input() ?>
                        <input type="hidden" name="work_id" value="<?= e($paper['openalex_id']) ?>">
                        <div class="form-field">
                            <label for="result-project-<?= e($resultIndex) ?>">Project</label>
                            <select id="result-project-<?= e($resultIndex) ?>" name="project_id"><option value="">Unassigned</option><?php foreach ($projects as $project): ?><option value="<?= e($project['id']) ?>"><?= e($project['title']) ?></option><?php endforeach; ?></select>
                        </div>
                        <div class="form-field">
                            <label for="result-status-<?= e($resultIndex) ?>">Reading status</label>
                            <select id="result-status-<?= e($resultIndex) ?>" name="reading_status"><?php foreach (PAPER_READING_STATUSES as $status): ?><option value="<?= e($status) ?>"><?= e(paper_label($status)) ?></option><?php endforeach; ?></select>
                        </div>
                        <button class="primary-button" type="submit">Import</button>
                        <?php if ($paper['url'] !== ''): ?><a class="secondary-button" href="<?= e($paper['url']) ?>" target="_blank" rel="noopener noreferrer">View source</a><?php endif; ?>
                    </form>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
<?php endif; ?>
<?php render_app_page_end(); ?>
