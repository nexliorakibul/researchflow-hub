<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection, $user] = task_page_context();
$taskId = task_request_id();
$task = find_owned_task($connection, $taskId, $userId);
if ($task === null) {
    render_task_not_found($user);
}

render_app_page_start('Research Task Details', $user, 'tasks');
render_app_feedback();
?>
<nav class="breadcrumb-nav" aria-label="Breadcrumb"><a href="<?= e(app_url('modules/tasks/index.php')) ?>">Research Tasks</a><span aria-hidden="true">/</span><span aria-current="page"><?= e($task['title']) ?></span></nav>

<section class="task-hero">
    <div><div class="project-hero-meta"><span class="priority-badge priority-<?= e($task['priority']) ?>"><?= e(task_label($task['priority'])) ?> priority</span><span class="status-badge status-<?= e(str_replace('_', '-', $task['status'])) ?>"><?= e(task_label($task['status'])) ?></span><?php if (task_is_overdue($task)): ?><span class="task-overdue-label">Overdue</span><?php endif; ?></div><h2><?= e($task['title']) ?></h2><p><?= e($task['description'] ?: 'No task description has been added.') ?></p></div>
    <div class="hero-actions"><a class="secondary-button" href="<?= e(app_url('modules/tasks/edit.php?id=' . $taskId)) ?>">Edit</a><form method="post" action="<?= e(app_url('modules/tasks/delete.php')) ?>" data-confirm="Delete this research task? This cannot be undone."><?= csrf_input() ?><input type="hidden" name="id" value="<?= e($taskId) ?>"><button class="danger-button" type="submit">Delete</button></form></div>
</section>

<div class="task-detail-grid">
    <section class="dashboard-panel" aria-labelledby="task-context-title"><div class="panel-heading"><h2 id="task-context-title">Task details</h2><p>Project, schedule, and record information</p></div><dl class="detail-list task-detail-list">
        <div><dt>Project</dt><dd><a href="<?= e(app_url('modules/projects/show.php?id=' . $task['project_id'])) ?>"><?= e($task['project_title']) ?></a></dd></div>
        <div><dt>Priority</dt><dd><?= e(task_label($task['priority'])) ?></dd></div>
        <div><dt>Status</dt><dd><?= e(task_label($task['status'])) ?></dd></div>
        <div><dt>Start date</dt><dd><?= e(task_date_label($task['start_date'])) ?></dd></div>
        <div><dt>Deadline</dt><dd class="<?= task_is_overdue($task) ? 'task-overdue-text' : '' ?>"><?= e(task_date_label($task['deadline'])) ?></dd></div>
        <div><dt>Last updated</dt><dd><?= e(task_date_label($task['updated_at'])) ?></dd></div>
    </dl></section>
    <section class="dashboard-panel text-panel"><div class="panel-heading"><h2>Description</h2></div><div class="long-copy"><?= nl2br(e($task['description'] ?: 'No description added.')) ?></div></section>
</div>
<?php render_app_page_end(); ?>
