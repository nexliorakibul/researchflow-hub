<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection, $user] = paper_page_context();
$projects = paper_projects($connection, $userId);
$doi = '';
$preview = null;
$errors = [];

if (is_post_request()) {
    $doi = normalize_doi(post_string('doi'));
    $action = post_string('action', 'lookup');

    try {
        require_valid_csrf_token();
    } catch (RuntimeException $exception) {
        $errors[] = 'Your form session expired. Please try again.';
    }

    if ($errors === []) {
        try {
            $preview = crossref_paper_by_doi($doi);
        } catch (ScholarlyApiException $exception) {
            $errors[] = $exception->getMessage();
        }
    }

    if ($preview !== null && $action === 'import') {
        $preview['project_id'] = trim(post_string('project_id'));
        $preview['reading_status'] = strtolower(trim(post_string('reading_status', 'to_read')));
        $values = paper_form_values($preview);
        $errors = array_merge($errors, validate_paper_values($values, $connection, $userId));

        if ($errors === []) {
            $paperId = create_owned_paper($connection, $userId, $values);
            flash_message('success', 'Paper imported from Crossref successfully.');
            redirect(app_url('modules/papers/show.php?id=' . $paperId));
        }
    }
}

render_app_page_start('Import Paper by DOI', $user, 'papers');
render_app_feedback();
?>
<nav class="breadcrumb-nav" aria-label="Breadcrumb">
    <a href="<?= e(app_url('modules/papers/index.php')) ?>">Research Papers</a><span aria-hidden="true">/</span><span aria-current="page">Import DOI</span>
</nav>

<section class="page-heading page-heading-compact">
    <div><p class="page-kicker">Version 2</p><h2>Import from Crossref</h2><p>Enter a DOI to review trusted publication metadata before saving it.</p></div>
    <a class="secondary-button" href="<?= e(app_url('modules/papers/create.php')) ?>">Add manually</a>
</section>

<section class="form-panel api-lookup-panel">
    <?php render_paper_errors($errors); ?>
    <form method="post" action="<?= e(app_url('modules/papers/import-doi.php')) ?>" class="api-lookup-form">
        <?= csrf_input() ?>
        <div class="form-field">
            <label for="doi-lookup">Digital Object Identifier (DOI)</label>
            <input id="doi-lookup" name="doi" type="text" value="<?= e($doi) ?>" maxlength="255" placeholder="10.1000/example" required autofocus>
        </div>
        <button class="primary-button" type="submit" name="action" value="lookup">Find metadata</button>
    </form>
    <p class="module-note">Metadata is requested server-side from Crossref. You can review it before import.</p>
</section>

<?php if (is_array($preview)): ?>
    <section class="dashboard-panel import-preview" aria-labelledby="import-preview-title">
        <div class="panel-heading"><h2 id="import-preview-title">Import preview</h2><p>Source: Crossref</p></div>
        <dl class="detail-list paper-detail-list">
            <div><dt>Title</dt><dd><?= e($preview['title'] ?: 'Not provided') ?></dd></div>
            <div><dt>Authors</dt><dd><?= e($preview['authors'] ?: 'Not provided') ?></dd></div>
            <div><dt>Year</dt><dd><?= e($preview['publication_year'] ?: 'Not provided') ?></dd></div>
            <div><dt>Venue</dt><dd><?= e($preview['venue'] ?: 'Not provided') ?></dd></div>
            <div><dt>DOI</dt><dd><?= e($preview['doi']) ?></dd></div>
            <div><dt>URL</dt><dd><a href="<?= e($preview['url']) ?>" target="_blank" rel="noopener noreferrer">Open DOI</a></dd></div>
        </dl>
        <form method="post" action="<?= e(app_url('modules/papers/import-doi.php')) ?>" class="import-options">
            <?= csrf_input() ?>
            <input type="hidden" name="doi" value="<?= e($preview['doi']) ?>">
            <div class="form-field">
                <label for="import-project">Research project</label>
                <select id="import-project" name="project_id">
                    <option value="">Unassigned</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?= e($project['id']) ?>"><?= e($project['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field">
                <label for="import-status">Reading status</label>
                <select id="import-status" name="reading_status">
                    <?php foreach (PAPER_READING_STATUSES as $status): ?>
                        <option value="<?= e($status) ?>"><?= e(paper_label($status)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="primary-button" type="submit" name="action" value="import">Import paper</button>
        </form>
    </section>
<?php endif; ?>
<?php render_app_page_end(); ?>
