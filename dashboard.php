<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/app-layout.php';

$userId = require_authenticated_user();
$connection = get_database_connection();

$userStatement = $connection->prepare(
    'SELECT name, email FROM users WHERE id = :id LIMIT 1'
);
$userStatement->execute(['id' => $userId]);
$user = $userStatement->fetch();

if (!is_array($user)) {
    logout_user();
    flash_message('error', 'Your account session is no longer valid.');
    redirect(app_url('login.php'));
}

$countStatement = $connection->prepare(
    'SELECT
        (SELECT COUNT(*) FROM projects WHERE user_id = :projects_user_id) AS projects,
        (SELECT COUNT(*) FROM resources WHERE user_id = :resources_user_id) AS resources,
        (SELECT COUNT(*) FROM papers WHERE user_id = :papers_user_id) AS papers,
        (SELECT COUNT(*) FROM datasets WHERE user_id = :datasets_user_id) AS datasets,
        (SELECT COUNT(*) FROM research_gaps WHERE user_id = :gaps_user_id) AS gaps,
        (SELECT COUNT(*) FROM experiments WHERE user_id = :experiments_user_id) AS experiments,
        (SELECT COUNT(*) FROM tasks WHERE user_id = :pending_user_id AND status = \'pending\') AS pending_tasks,
        (SELECT COUNT(*) FROM tasks WHERE user_id = :completed_user_id AND status = \'completed\') AS completed_tasks,
        (SELECT COUNT(*) FROM tasks WHERE user_id = :total_tasks_user_id) AS total_tasks'
);
$countStatement->execute([
    'projects_user_id' => $userId,
    'resources_user_id' => $userId,
    'papers_user_id' => $userId,
    'datasets_user_id' => $userId,
    'gaps_user_id' => $userId,
    'experiments_user_id' => $userId,
    'pending_user_id' => $userId,
    'completed_user_id' => $userId,
    'total_tasks_user_id' => $userId,
]);
$counts = $countStatement->fetch();

if (!is_array($counts)) {
    throw new RuntimeException('Unable to load dashboard totals.');
}

$recentQueries = [
    'projects' => 'SELECT id, title, research_area, status, created_at
                   FROM projects WHERE user_id = :user_id
                   ORDER BY created_at DESC, id DESC LIMIT 5',
    'resources' => 'SELECT id, title, resource_type, status, created_at
                    FROM resources WHERE user_id = :user_id
                    ORDER BY created_at DESC, id DESC LIMIT 5',
    'papers' => 'SELECT id, title, authors, publication_year, reading_status, created_at
                 FROM papers WHERE user_id = :user_id
                 ORDER BY created_at DESC, id DESC LIMIT 5',
    'experiments' => 'SELECT id, experiment_name, model_name, experiment_date, accuracy, created_at
                      FROM experiments WHERE user_id = :user_id
                      ORDER BY created_at DESC, id DESC LIMIT 5',
];

$recentRecords = [];

foreach ($recentQueries as $key => $sql) {
    $statement = $connection->prepare($sql);
    $statement->execute(['user_id' => $userId]);
    $recentRecords[$key] = $statement->fetchAll();
}

$upcomingTasksStatement = $connection->prepare(
    'SELECT id, title, priority, status, deadline
     FROM tasks
     WHERE user_id = :user_id
       AND status IN (\'pending\', \'in_progress\')
       AND deadline IS NOT NULL
       AND deadline >= CURRENT_DATE
     ORDER BY deadline ASC, id ASC
     LIMIT 5'
);
$upcomingTasksStatement->execute(['user_id' => $userId]);
$upcomingTasks = $upcomingTasksStatement->fetchAll();

$totalTasks = (int) $counts['total_tasks'];
$completedTasks = (int) $counts['completed_tasks'];
$taskProgress = $totalTasks === 0
    ? 0
    : (int) round(($completedTasks / $totalTasks) * 100);

$statistics = [
    ['label' => 'Research Projects', 'value' => (int) $counts['projects']],
    ['label' => 'Resources', 'value' => (int) $counts['resources']],
    ['label' => 'Research Papers', 'value' => (int) $counts['papers']],
    ['label' => 'Datasets', 'value' => (int) $counts['datasets']],
    ['label' => 'Research Gaps', 'value' => (int) $counts['gaps']],
    ['label' => 'Experiments', 'value' => (int) $counts['experiments']],
    ['label' => 'Pending Tasks', 'value' => (int) $counts['pending_tasks']],
    ['label' => 'Completed Tasks', 'value' => $completedTasks],
];

function dashboard_label(?string $value): string
{
    if ($value === null || $value === '') {
        return 'Not specified';
    }

    return ucwords(str_replace('_', ' ', $value));
}

function dashboard_date(?string $value, string $fallback = 'Not dated'): string
{
    if ($value === null || $value === '') {
        return $fallback;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10));

    return $date instanceof DateTimeImmutable ? $date->format('M j, Y') : $fallback;
}

function dashboard_status_class(?string $status): string
{
    $normalized = strtolower(str_replace('_', '-', (string) $status));
    $allowed = [
        'completed', 'reviewed', 'important', 'in-progress', 'reading',
        'ongoing', 'archived', 'cancelled', 'planned', 'pending', 'saved', 'to-read',
    ];

    return in_array($normalized, $allowed, true) ? ' status-' . $normalized : '';
}

render_app_page_start('Dashboard', $user, 'dashboard');
render_app_feedback();
?>
<section class="dashboard-welcome">
    <div>
        <h2>Welcome back, <?= e($user['name']) ?></h2>
        <p>Here is a database-driven overview of your research activity.</p>
    </div>
    <time class="dashboard-date" datetime="<?= e(date('Y-m-d')) ?>"><?= e(date('l, F j, Y')) ?></time>
</section>

<section aria-labelledby="statistics-title">
    <h2 class="visually-hidden" id="statistics-title">Research statistics</h2>
    <div class="statistics-grid">
        <?php foreach ($statistics as $statistic): ?>
            <article class="stat-card">
                <p class="stat-label"><?= e($statistic['label']) ?></p>
                <p class="stat-value"><?= e(number_format($statistic['value'])) ?></p>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="dashboard-panel progress-panel" aria-labelledby="progress-title">
    <div>
        <h2 id="progress-title">Overall task progress</h2>
        <p><?= e($completedTasks) ?> of <?= e($totalTasks) ?> tasks completed</p>
        <div class="progress-track" role="progressbar" aria-label="Overall task progress"
             aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= e($taskProgress) ?>">
            <div class="progress-value" style="width: <?= e($taskProgress) ?>%"></div>
        </div>
    </div>
    <strong class="progress-number"><?= e($taskProgress) ?>%</strong>
</section>

<h2 class="dashboard-section-title">Recent research activity</h2>
<div class="dashboard-grid">
    <section class="dashboard-panel" aria-labelledby="recent-projects-title">
        <div class="panel-heading">
            <h2 id="recent-projects-title">Latest projects</h2>
            <p>Five most recently created projects</p>
        </div>
        <?php if ($recentRecords['projects'] === []): ?>
            <?php render_empty_state('No research projects yet.'); ?>
        <?php else: ?>
            <div class="table-scroll">
                <table class="dashboard-table">
                    <thead><tr><th>Project</th><th>Status</th><th>Added</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentRecords['projects'] as $project): ?>
                        <tr>
                            <td>
                                <a class="record-title record-link" href="<?= e(app_url('modules/projects/show.php?id=' . $project['id'])) ?>"><?= e($project['title']) ?></a>
                                <span class="record-subtitle"><?= e($project['research_area'] ?: 'Research area not set') ?></span>
                            </td>
                            <td><span class="status-badge<?= e(dashboard_status_class($project['status'])) ?>"><?= e(dashboard_label($project['status'])) ?></span></td>
                            <td><?= e(dashboard_date($project['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="dashboard-panel" aria-labelledby="upcoming-tasks-title">
        <div class="panel-heading">
            <h2 id="upcoming-tasks-title">Upcoming tasks</h2>
            <p>Next five active tasks by deadline</p>
        </div>
        <?php if ($upcomingTasks === []): ?>
            <?php render_empty_state('No upcoming tasks with deadlines.'); ?>
        <?php else: ?>
            <div class="table-scroll">
                <table class="dashboard-table">
                    <thead><tr><th>Task</th><th>Priority</th><th>Deadline</th></tr></thead>
                    <tbody>
                    <?php foreach ($upcomingTasks as $task): ?>
                        <tr>
                            <td>
                                <a class="record-title record-link" href="<?= e(app_url('modules/tasks/show.php?id=' . $task['id'])) ?>"><?= e($task['title']) ?></a>
                                <span class="record-subtitle"><?= e(dashboard_label($task['status'])) ?></span>
                            </td>
                            <td><span class="status-badge"><?= e(dashboard_label($task['priority'])) ?></span></td>
                            <td><?= e(dashboard_date($task['deadline'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="dashboard-panel" aria-labelledby="recent-resources-title">
        <div class="panel-heading">
            <h2 id="recent-resources-title">Latest resources</h2>
            <p>Five most recently saved resources</p>
        </div>
        <?php if ($recentRecords['resources'] === []): ?>
            <?php render_empty_state('No research resources yet.'); ?>
        <?php else: ?>
            <div class="table-scroll">
                <table class="dashboard-table">
                    <thead><tr><th>Resource</th><th>Status</th><th>Added</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentRecords['resources'] as $resource): ?>
                        <tr>
                            <td>
                                <a class="record-title record-link" href="<?= e(app_url('modules/resources/show.php?id=' . $resource['id'])) ?>"><?= e($resource['title']) ?></a>
                                <span class="record-subtitle"><?= e(dashboard_label($resource['resource_type'])) ?></span>
                            </td>
                            <td><span class="status-badge<?= e(dashboard_status_class($resource['status'])) ?>"><?= e(dashboard_label($resource['status'])) ?></span></td>
                            <td><?= e(dashboard_date($resource['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="dashboard-panel" aria-labelledby="recent-papers-title">
        <div class="panel-heading">
            <h2 id="recent-papers-title">Latest papers</h2>
            <p>Five most recently added papers</p>
        </div>
        <?php if ($recentRecords['papers'] === []): ?>
            <?php render_empty_state('No research papers yet.'); ?>
        <?php else: ?>
            <div class="table-scroll">
                <table class="dashboard-table">
                    <thead><tr><th>Paper</th><th>Status</th><th>Year</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentRecords['papers'] as $paper): ?>
                        <tr>
                            <td>
                                <a class="record-title record-link" href="<?= e(app_url('modules/papers/show.php?id=' . $paper['id'])) ?>"><?= e($paper['title']) ?></a>
                                <span class="record-subtitle"><?= e($paper['authors'] ?: 'Authors not set') ?></span>
                            </td>
                            <td><span class="status-badge<?= e(dashboard_status_class($paper['reading_status'])) ?>"><?= e(dashboard_label($paper['reading_status'])) ?></span></td>
                            <td><?= e($paper['publication_year'] ?: '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="dashboard-panel" aria-labelledby="recent-experiments-title">
        <div class="panel-heading">
            <h2 id="recent-experiments-title">Latest experiments</h2>
            <p>Five most recently recorded experiments</p>
        </div>
        <?php if ($recentRecords['experiments'] === []): ?>
            <?php render_empty_state('No experiments recorded yet.'); ?>
        <?php else: ?>
            <div class="table-scroll">
                <table class="dashboard-table">
                    <thead><tr><th>Experiment</th><th>Accuracy</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentRecords['experiments'] as $experiment): ?>
                        <tr>
                            <td>
                                <a class="record-title record-link" href="<?= e(app_url('modules/experiments/show.php?id=' . $experiment['id'])) ?>"><?= e($experiment['experiment_name']) ?></a>
                                <span class="record-subtitle"><?= e($experiment['model_name']) ?></span>
                            </td>
                            <td><?= $experiment['accuracy'] === null ? '—' : e(number_format((float) $experiment['accuracy'] * 100, 2) . '%') ?></td>
                            <td><?= e(dashboard_date($experiment['experiment_date'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
<?php render_app_page_end(); ?>
