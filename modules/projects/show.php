<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection, $user] = project_page_context();
$projectId = project_request_id();
$project = find_owned_project($connection, $projectId, $userId);

if ($project === null) {
    render_project_not_found($user);
}

$summaryStatement = $connection->prepare(
    'SELECT
        (SELECT COUNT(*) FROM resources WHERE project_id = :resources_project AND user_id = :resources_user) AS resources,
        (SELECT COUNT(*) FROM papers WHERE project_id = :papers_project AND user_id = :papers_user) AS papers,
        (SELECT COUNT(*) FROM datasets WHERE project_id = :datasets_project AND user_id = :datasets_user) AS datasets,
        (SELECT COUNT(*) FROM literature_reviews WHERE project_id = :reviews_project AND user_id = :reviews_user) AS reviews,
        (SELECT COUNT(*) FROM research_gaps WHERE project_id = :gaps_project AND user_id = :gaps_user) AS gaps,
        (SELECT COUNT(*) FROM experiments WHERE project_id = :experiments_project AND user_id = :experiments_user) AS experiments,
        (SELECT COUNT(*) FROM tasks WHERE project_id = :tasks_project AND user_id = :tasks_user) AS tasks,
        (SELECT COUNT(*) FROM tasks WHERE project_id = :completed_project AND user_id = :completed_user AND status = \'completed\') AS completed_tasks,
        (SELECT COUNT(*) FROM notes WHERE project_id = :notes_project AND user_id = :notes_user) AS notes'
);
$summaryStatement->execute([
    'resources_project' => $projectId,
    'resources_user' => $userId,
    'papers_project' => $projectId,
    'papers_user' => $userId,
    'datasets_project' => $projectId,
    'datasets_user' => $userId,
    'reviews_project' => $projectId,
    'reviews_user' => $userId,
    'gaps_project' => $projectId,
    'gaps_user' => $userId,
    'experiments_project' => $projectId,
    'experiments_user' => $userId,
    'tasks_project' => $projectId,
    'tasks_user' => $userId,
    'completed_project' => $projectId,
    'completed_user' => $userId,
    'notes_project' => $projectId,
    'notes_user' => $userId,
]);
$summary = $summaryStatement->fetch();

if (!is_array($summary)) {
    throw new RuntimeException('Unable to load project workspace totals.');
}

$totalTasks = (int) $summary['tasks'];
$completedTasks = (int) $summary['completed_tasks'];
$progress = $totalTasks === 0 ? 0 : (int) round(($completedTasks / $totalTasks) * 100);
$workspaceSections = [
    ['label' => 'Overview', 'count' => null, 'state' => 'active', 'href' => null],
    ['label' => 'Resources', 'count' => (int) $summary['resources'], 'state' => 'enabled', 'href' => app_url('modules/resources/index.php?project_id=' . $projectId)],
    ['label' => 'Papers', 'count' => (int) $summary['papers'], 'state' => 'enabled', 'href' => app_url('modules/papers/index.php?project_id=' . $projectId)],
    ['label' => 'Datasets', 'count' => (int) $summary['datasets'], 'state' => 'enabled', 'href' => app_url('modules/datasets/index.php?project_id=' . $projectId)],
    ['label' => 'Literature Review', 'count' => (int) $summary['reviews'], 'state' => 'enabled', 'href' => app_url('modules/literature-reviews/index.php?project_id=' . $projectId)],
    ['label' => 'Research Gaps', 'count' => (int) $summary['gaps'], 'state' => 'enabled', 'href' => app_url('modules/research-gaps/index.php?project_id=' . $projectId)],
    ['label' => 'Experiments', 'count' => (int) $summary['experiments'], 'state' => 'enabled', 'href' => app_url('modules/experiments/index.php?project_id=' . $projectId)],
    ['label' => 'Tasks', 'count' => $totalTasks, 'state' => 'enabled', 'href' => app_url('modules/tasks/index.php?project_id=' . $projectId)],
    ['label' => 'Notes', 'count' => (int) $summary['notes'], 'state' => 'enabled', 'href' => app_url('modules/notes/index.php?project_id=' . $projectId)],
];

render_app_page_start('Project Workspace', $user, 'projects');
render_app_feedback();
?>
<nav class="breadcrumb-nav" aria-label="Breadcrumb">
    <a href="<?= e(app_url('modules/projects/index.php')) ?>">Research Projects</a>
    <span aria-hidden="true">/</span>
    <span aria-current="page"><?= e($project['title']) ?></span>
</nav>

<section class="project-hero">
    <div>
        <div class="project-hero-meta">
            <span class="status-badge status-<?= e(str_replace('_', '-', $project['status'])) ?>">
                <?= e(project_status_label($project['status'])) ?>
            </span>
            <span><?= e($project['research_area'] ?: 'General research') ?></span>
        </div>
        <h2><?= e($project['title']) ?></h2>
        <p><?= e($project['description'] ?: 'No project description has been added.') ?></p>
    </div>
    <div class="hero-actions">
        <a class="secondary-button" href="<?= e(app_url('modules/projects/edit.php?id=' . $projectId)) ?>">Edit project</a>
        <form method="post" action="<?= e(app_url('modules/projects/delete.php')) ?>"
              data-confirm="Delete this project? Reviews, gaps, experiments, tasks, and notes for it will also be deleted. Linked resources, papers, and datasets will remain unassigned. This cannot be undone.">
            <?= csrf_input() ?>
            <input type="hidden" name="id" value="<?= e($projectId) ?>">
            <button class="danger-button" type="submit">Delete project</button>
        </form>
    </div>
</section>

<div class="workspace-tabs" aria-label="Project workspace sections">
    <?php foreach ($workspaceSections as $section): ?>
        <?php if (is_string($section['href'])): ?>
            <a class="workspace-tab" href="<?= e($section['href']) ?>">
                <?= e($section['label']) ?><small><?= e($section['count']) ?></small>
            </a>
        <?php else: ?>
            <span class="workspace-tab<?= $section['state'] === 'active' ? ' is-active' : ' is-disabled' ?>"
                  <?= $section['state'] === 'active' ? 'aria-current="page"' : 'aria-disabled="true"' ?>>
                <?= e($section['label']) ?>
                <?php if ($section['count'] !== null): ?><small><?= e($section['count']) ?></small><?php endif; ?>
            </span>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<div class="workspace-grid">
    <section class="dashboard-panel workspace-overview" aria-labelledby="overview-title">
        <div class="panel-heading">
            <h2 id="overview-title">Project overview</h2>
            <p>Core project details and research timeline</p>
        </div>
        <dl class="detail-list">
            <div><dt>Research area</dt><dd><?= e($project['research_area'] ?: 'Not set') ?></dd></div>
            <div><dt>Status</dt><dd><?= e(project_status_label($project['status'])) ?></dd></div>
            <div><dt>Start date</dt><dd><?= e(project_date_label($project['start_date'])) ?></dd></div>
            <div><dt>Target date</dt><dd><?= e(project_date_label($project['target_date'])) ?></dd></div>
            <div><dt>Created</dt><dd><?= e(project_date_label($project['created_at'])) ?></dd></div>
            <div><dt>Last updated</dt><dd><?= e(project_date_label($project['updated_at'])) ?></dd></div>
        </dl>
    </section>

    <section class="dashboard-panel project-progress-panel" aria-labelledby="project-progress-title">
        <div class="panel-heading">
            <h2 id="project-progress-title">Task progress</h2>
            <p>Completed tasks within this project</p>
        </div>
        <div class="project-progress-content">
            <strong><?= e($progress) ?>%</strong>
            <p><?= e($completedTasks) ?> of <?= e($totalTasks) ?> tasks completed</p>
            <div class="progress-track" role="progressbar" aria-label="Project task progress"
                 aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= e($progress) ?>">
                <div class="progress-value" style="width: <?= e($progress) ?>%"></div>
            </div>
        </div>
    </section>
</div>

<section class="dashboard-panel workspace-modules" aria-labelledby="workspace-modules-title">
    <div class="panel-heading">
        <h2 id="workspace-modules-title">Workspace sections</h2>
        <p>Related record counts; module pages will be enabled in their implementation steps.</p>
    </div>
    <div class="workspace-module-grid">
        <?php foreach (array_slice($workspaceSections, 1) as $section): ?>
            <article class="workspace-module-card<?= is_string($section['href']) ? ' is-enabled' : '' ?>">
                <p><?= e($section['label']) ?></p>
                <strong><?= e($section['count']) ?></strong>
                <?php if (is_string($section['href'])): ?>
                    <a href="<?= e($section['href']) ?>">View records</a>
                <?php else: ?>
                    <span>Records</span>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php render_app_page_end(); ?>
