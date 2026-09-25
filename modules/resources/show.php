<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection, $user] = resource_page_context();
$resourceId = resource_request_id();
$resource = find_owned_resource($connection, $resourceId, $userId);

if ($resource === null) {
    render_resource_not_found($user);
}

$tags = array_values(array_filter(array_map(
    static fn (string $tag): string => trim($tag),
    explode(',', (string) ($resource['tags'] ?? ''))
)));

render_app_page_start('Resource Details', $user, 'resources');
render_app_feedback();
?>
<nav class="breadcrumb-nav" aria-label="Breadcrumb">
    <a href="<?= e(app_url('modules/resources/index.php')) ?>">Research Resources</a>
    <span aria-hidden="true">/</span>
    <span aria-current="page"><?= e($resource['title']) ?></span>
</nav>

<section class="resource-hero">
    <div>
        <div class="project-hero-meta">
            <span class="status-badge status-<?= e(str_replace('_', '-', $resource['status'])) ?>"><?= e(resource_label($resource['status'])) ?></span>
            <span><?= e(resource_label($resource['resource_type'])) ?></span>
            <?php if ($resource['is_favorite']): ?>
                <span class="favorite-label"><span aria-hidden="true">&#9733;</span> Favorite</span>
            <?php endif; ?>
        </div>
        <h2><?= e($resource['title']) ?></h2>
        <p><?= e($resource['description'] ?: 'No resource description has been added.') ?></p>
    </div>
    <div class="hero-actions">
        <?php if ($resource['url']): ?>
            <a class="primary-button" href="<?= e($resource['url']) ?>" target="_blank" rel="noopener noreferrer">Open resource</a>
        <?php endif; ?>
        <a class="secondary-button" href="<?= e(app_url('modules/resources/edit.php?id=' . $resourceId)) ?>">Edit</a>
        <form method="post" action="<?= e(app_url('modules/resources/delete.php')) ?>"
              data-confirm="Delete this research resource? This cannot be undone.">
            <?= csrf_input() ?>
            <input type="hidden" name="id" value="<?= e($resourceId) ?>">
            <button class="danger-button" type="submit">Delete</button>
        </form>
    </div>
</section>

<div class="resource-detail-grid">
    <section class="dashboard-panel" aria-labelledby="resource-metadata-title">
        <div class="panel-heading">
            <h2 id="resource-metadata-title">Resource metadata</h2>
            <p>Saved academic and project information</p>
        </div>
        <dl class="detail-list resource-detail-list">
            <div><dt>Project</dt><dd><?= e($resource['project_title'] ?: 'Unassigned') ?></dd></div>
            <div><dt>Research area</dt><dd><?= e($resource['research_area'] ?: 'Not set') ?></dd></div>
            <div><dt>Authors</dt><dd><?= e($resource['authors'] ?: 'Not set') ?></dd></div>
            <div><dt>Publication year</dt><dd><?= e($resource['publication_year'] ?: 'Not set') ?></dd></div>
            <div><dt>DOI</dt><dd><?= e($resource['doi'] ?: 'Not set') ?></dd></div>
            <div><dt>Status</dt><dd><?= e(resource_label($resource['status'])) ?></dd></div>
            <div><dt>Created</dt><dd><?= e(resource_date_label($resource['created_at'])) ?></dd></div>
            <div><dt>Last updated</dt><dd><?= e(resource_date_label($resource['updated_at'])) ?></dd></div>
        </dl>
    </section>

    <aside class="dashboard-panel" aria-labelledby="resource-tags-title">
        <div class="panel-heading">
            <h2 id="resource-tags-title">Tags</h2>
            <p>Keywords saved with this resource</p>
        </div>
        <div class="tag-list">
            <?php if ($tags === []): ?>
                <p class="muted-copy">No tags added.</p>
            <?php else: ?>
                <?php foreach ($tags as $tag): ?><span><?= e($tag) ?></span><?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>
</div>

<div class="resource-text-grid">
    <section class="dashboard-panel text-panel" aria-labelledby="description-title">
        <div class="panel-heading"><h2 id="description-title">Description</h2></div>
        <div class="long-copy"><?= nl2br(e($resource['description'] ?: 'No description provided.')) ?></div>
    </section>
    <section class="dashboard-panel text-panel" aria-labelledby="notes-title">
        <div class="panel-heading"><h2 id="notes-title">Personal notes</h2></div>
        <div class="long-copy"><?= nl2br(e($resource['personal_notes'] ?: 'No personal notes added.')) ?></div>
    </section>
</div>
<?php render_app_page_end(); ?>
