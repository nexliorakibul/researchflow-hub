<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection, $user] = task_page_context();
$projects = task_projects($connection, $userId);

$search = trim(query_string('q'));
$projectId = filter_var($_GET['project_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$priority = strtolower(trim(query_string('priority')));
$status = strtolower(trim(query_string('status')));
$sort = strtolower(trim(query_string('sort', 'newest')));
$perPage = filter_var($_GET['per_page'] ?? 10, FILTER_VALIDATE_INT);
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);

$search = function_exists('mb_substr') ? mb_substr($search, 0, 100, 'UTF-8') : substr($search, 0, 100);
$projectId = $projectId === false ? null : (int) $projectId;
if (!in_array($priority, TASK_PRIORITIES, true)) {
    $priority = '';
}
if (!in_array($status, TASK_STATUSES, true)) {
    $status = '';
}
$sortOptions = [
    'newest' => ['label' => 'Newest first', 'sql' => 't.created_at DESC, t.id DESC'],
    'oldest' => ['label' => 'Oldest first', 'sql' => 't.created_at ASC, t.id ASC'],
    'title_asc' => ['label' => 'Title A-Z', 'sql' => 't.title ASC, t.id DESC'],
    'title_desc' => ['label' => 'Title Z-A', 'sql' => 't.title DESC, t.id DESC'],
    'deadline_asc' => ['label' => 'Deadline soonest', 'sql' => 't.deadline IS NULL ASC, t.deadline ASC, t.id DESC'],
    'deadline_desc' => ['label' => 'Deadline latest', 'sql' => 't.deadline IS NULL ASC, t.deadline DESC, t.id DESC'],
];
if (!array_key_exists($sort, $sortOptions)) {
    $sort = 'newest';
}
if (!in_array($perPage, [10, 25, 50], true)) {
    $perPage = 10;
}
$page = is_int($page) && $page > 0 ? $page : 1;

$where = ['t.user_id = :user_id'];
$parameters = ['user_id' => $userId];
$integerParameters = ['user_id'];
if ($projectId !== null) {
    $where[] = 't.project_id = :project_id';
    $parameters['project_id'] = $projectId;
    $integerParameters[] = 'project_id';
}
if ($priority !== '') {
    $where[] = 't.priority = :priority';
    $parameters['priority'] = $priority;
}
if ($status !== '') {
    $where[] = 't.status = :status';
    $parameters['status'] = $status;
}
if ($search !== '') {
    $where[] = '(t.title LIKE :search_title OR t.description LIKE :search_description OR p.title LIKE :search_project)';
    $pattern = '%' . $search . '%';
    $parameters['search_title'] = $pattern;
    $parameters['search_description'] = $pattern;
    $parameters['search_project'] = $pattern;
}

$whereSql = implode(' AND ', $where);
$fromSql = ' FROM tasks t INNER JOIN projects p ON p.id = t.project_id AND p.user_id = t.user_id ';
$countStatement = $connection->prepare('SELECT COUNT(*)' . $fromSql . 'WHERE ' . $whereSql);
$countStatement->execute($parameters);
$totalTasks = (int) $countStatement->fetchColumn();
$totalPages = max(1, (int) ceil($totalTasks / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$listStatement = $connection->prepare(
    'SELECT t.id, t.title, t.description, t.priority, t.status, t.start_date, t.deadline,
            t.created_at, p.title AS project_title'
    . $fromSql . 'WHERE ' . $whereSql . ' ORDER BY ' . $sortOptions[$sort]['sql'] . ' LIMIT :limit OFFSET :offset'
);
foreach ($parameters as $key => $value) {
    $listStatement->bindValue(':' . $key, $value, in_array($key, $integerParameters, true) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$listStatement->bindValue(':limit', $perPage, PDO::PARAM_INT);
$listStatement->bindValue(':offset', $offset, PDO::PARAM_INT);
$listStatement->execute();
$tasks = $listStatement->fetchAll();

function tasks_page_url(int $targetPage): string
{
    $query = $_GET;
    $query['page'] = $targetPage;

    return app_url('modules/tasks/index.php') . '?' . http_build_query($query);
}

$hasFilters = $search !== '' || $projectId !== null || $priority !== '' || $status !== '';
render_app_page_start('Research Tasks', $user, 'tasks');
render_app_feedback();
?>
<section class="page-heading"><div><p class="page-kicker">Research progress</p><h2>Research tasks</h2><p>Plan project work, track priorities and deadlines, and update completion status.</p></div><a class="primary-button" href="<?= e(app_url('modules/tasks/create.php')) ?>">Add task</a></section>

<form class="filter-bar task-filter-bar" method="get" action="<?= e(app_url('modules/tasks/index.php')) ?>">
    <div class="filter-field filter-search"><label for="task-search">Search</label><input id="task-search" name="q" type="search" value="<?= e($search) ?>" maxlength="100" placeholder="Task, description, project"></div>
    <div class="filter-field"><label for="task-project">Project</label><select id="task-project" name="project_id"><option value="">All projects</option><?php foreach ($projects as $project): ?><option value="<?= e($project['id']) ?>" <?= $projectId === (int) $project['id'] ? 'selected' : '' ?>><?= e($project['title']) ?></option><?php endforeach; ?></select></div>
    <div class="filter-field"><label for="task-priority">Priority</label><select id="task-priority" name="priority"><option value="">All priorities</option><?php foreach (TASK_PRIORITIES as $taskPriority): ?><option value="<?= e($taskPriority) ?>" <?= $priority === $taskPriority ? 'selected' : '' ?>><?= e(task_label($taskPriority)) ?></option><?php endforeach; ?></select></div>
    <div class="filter-field"><label for="task-status">Status</label><select id="task-status" name="status"><option value="">All statuses</option><?php foreach (TASK_STATUSES as $taskStatus): ?><option value="<?= e($taskStatus) ?>" <?= $status === $taskStatus ? 'selected' : '' ?>><?= e(task_label($taskStatus)) ?></option><?php endforeach; ?></select></div>
    <div class="filter-field"><label for="task-sort">Sort</label><select id="task-sort" name="sort"><?php foreach ($sortOptions as $sortValue => $option): ?><option value="<?= e($sortValue) ?>" <?= $sort === $sortValue ? 'selected' : '' ?>><?= e($option['label']) ?></option><?php endforeach; ?></select></div>
    <div class="filter-field filter-small"><label for="task-page-size">Per page</label><select id="task-page-size" name="per_page"><?php foreach ([10, 25, 50] as $size): ?><option value="<?= e($size) ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= e($size) ?></option><?php endforeach; ?></select></div>
    <div class="filter-actions"><button class="secondary-button" type="submit">Apply</button><a class="text-button" href="<?= e(app_url('modules/tasks/index.php')) ?>">Reset</a></div>
</form>

<div class="list-summary"><p><?= e(number_format($totalTasks)) ?> task<?= $totalTasks === 1 ? '' : 's' ?></p></div>
<?php if ($tasks === []): ?>
    <section class="dashboard-panel"><?php render_empty_state($hasFilters ? 'No tasks match the selected filters.' : 'No research tasks yet. Add your first project task.'); ?></section>
<?php else: ?>
    <section class="dashboard-panel task-list-panel"><div class="table-scroll"><table class="dashboard-table task-table"><thead><tr><th>Task</th><th>Project</th><th>Priority</th><th>Status</th><th>Start</th><th>Deadline</th><th>Actions</th></tr></thead><tbody>
        <?php foreach ($tasks as $task): ?><tr>
            <td><a class="record-title record-link" href="<?= e(app_url('modules/tasks/show.php?id=' . $task['id'])) ?>"><?= e($task['title']) ?></a><span class="record-subtitle"><?= e($task['description'] ?: 'No description') ?></span></td>
            <td><?= e($task['project_title']) ?></td>
            <td><span class="priority-badge priority-<?= e($task['priority']) ?>"><?= e(task_label($task['priority'])) ?></span></td>
            <td><span class="status-badge status-<?= e(str_replace('_', '-', $task['status'])) ?>"><?= e(task_label($task['status'])) ?></span></td>
            <td><?= e(task_date_label($task['start_date'])) ?></td>
            <td class="<?= task_is_overdue($task) ? 'task-overdue-text' : '' ?>"><?= e(task_date_label($task['deadline'])) ?><?php if (task_is_overdue($task)): ?><span class="record-subtitle">Overdue</span><?php endif; ?></td>
            <td><div class="table-actions"><a href="<?= e(app_url('modules/tasks/show.php?id=' . $task['id'])) ?>">View</a><a href="<?= e(app_url('modules/tasks/edit.php?id=' . $task['id'])) ?>">Edit</a><form method="post" action="<?= e(app_url('modules/tasks/delete.php')) ?>" data-confirm="Delete this research task? This cannot be undone."><?= csrf_input() ?><input type="hidden" name="id" value="<?= e($task['id']) ?>"><button type="submit">Delete</button></form></div></td>
        </tr><?php endforeach; ?>
    </tbody></table></div></section>
    <?php if ($totalPages > 1): ?><nav class="pagination-nav" aria-label="Research task pages"><?php if ($page > 1): ?><a href="<?= e(tasks_page_url($page - 1)) ?>">Previous</a><?php endif; ?><span>Page <?= e($page) ?> of <?= e($totalPages) ?></span><?php if ($page < $totalPages): ?><a href="<?= e(tasks_page_url($page + 1)) ?>">Next</a><?php endif; ?></nav><?php endif; ?>
<?php endif; ?>
<?php render_app_page_end(); ?>
