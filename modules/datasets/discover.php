<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection, $user] = dataset_page_context();
$projects = dataset_projects($connection, $userId);
$query = scholarly_text_limit(trim(query_string('q')), 200);
$results = [];
$errors = [];

if ($query !== '') {
    try {
        $results = datacite_search_datasets($query, 12);
    } catch (ScholarlyApiException $exception) {
        $errors[] = $exception->getMessage();
    }
}

render_app_page_start('Discover Datasets', $user, 'datasets');
render_app_feedback();
?>
<nav class="breadcrumb-nav" aria-label="Breadcrumb"><a href="<?= e(app_url('modules/datasets/index.php')) ?>">Dataset Manager</a><span aria-hidden="true">/</span><span aria-current="page">Discover</span></nav>
<section class="page-heading page-heading-compact"><div><p class="page-kicker">Version 3</p><h2>Search DataCite datasets</h2><p>Find public dataset metadata by topic, title, creator, or DOI and import it into your workspace.</p></div><a class="secondary-button" href="<?= e(app_url('modules/datasets/create.php')) ?>">Add manually</a></section>

<section class="form-panel api-lookup-panel">
    <?php render_dataset_errors($errors); ?>
    <form method="get" action="<?= e(app_url('modules/datasets/discover.php')) ?>" class="api-lookup-form" role="search">
        <div class="form-field"><label for="datacite-query">Dataset search</label><input id="datacite-query" name="q" type="search" value="<?= e($query) ?>" minlength="2" maxlength="200" placeholder="climate change, medical imaging, 10.xxxx/..." required autofocus></div>
        <button class="primary-button" type="submit">Search DataCite</button>
    </form>
    <p class="module-note">Only public, findable dataset DOI metadata is requested from DataCite.</p>
</section>

<?php if ($query !== '' && $errors === []): ?>
    <div class="list-summary"><p><?= e(number_format(count($results))) ?> result<?= count($results) === 1 ? '' : 's' ?> shown</p></div>
    <?php if ($results === []): ?>
        <section class="dashboard-panel"><?php render_empty_state('No DataCite datasets matched your search.'); ?></section>
    <?php else: ?>
        <section class="api-results" aria-label="DataCite dataset results">
            <?php foreach ($results as $index => $dataset): ?>
                <article class="dashboard-panel api-result-card">
                    <div class="api-result-heading"><div><p class="page-kicker">DataCite dataset</p><h2><?= e($dataset['name']) ?></h2><p><?= e(implode(' · ', array_filter([$dataset['creators'], $dataset['publication_year'], $dataset['source']]))) ?></p></div><div class="api-result-badges"><?php if ($dataset['license'] !== ''): ?><span class="status-badge status-completed"><?= e($dataset['license']) ?></span><?php endif; ?></div></div>
                    <?php if ($dataset['description'] !== ''): ?><p class="api-result-summary"><?= e($dataset['description']) ?></p><?php endif; ?>
                    <form method="post" action="<?= e(app_url('modules/datasets/import-datacite.php')) ?>" class="import-options">
                        <?= csrf_input() ?><input type="hidden" name="doi" value="<?= e($dataset['datacite_doi']) ?>">
                        <div class="form-field"><label for="dataset-project-<?= e((string) $index) ?>">Research project</label><select id="dataset-project-<?= e((string) $index) ?>" name="project_id"><option value="">Unassigned</option><?php foreach ($projects as $project): ?><option value="<?= e($project['id']) ?>"><?= e($project['title']) ?></option><?php endforeach; ?></select></div>
                        <div class="form-field"><label for="dataset-status-<?= e((string) $index) ?>">Status</label><select id="dataset-status-<?= e((string) $index) ?>" name="status"><?php foreach (DATASET_STATUSES as $status): ?><option value="<?= e($status) ?>"><?= e(dataset_label($status)) ?></option><?php endforeach; ?></select></div>
                        <button class="primary-button" type="submit">Import dataset</button>
                        <a class="secondary-button" href="<?= e($dataset['url']) ?>" target="_blank" rel="noopener noreferrer">Open DOI</a>
                    </form>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
<?php endif; ?>
<?php render_app_page_end(); ?>
