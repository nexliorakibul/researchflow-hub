<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection, $user] = task_page_context();
$projects = task_projects($connection, $userId);
$values = task_form_values(['project_id' => $_GET['project_id'] ?? '']);
$errors = [];

if (is_post_request()) {
    $values = task_form_values($_POST);
    try {
        require_valid_csrf_token();
    } catch (RuntimeException $exception) {
        $errors[] = 'Your form session expired. Please try again.';
    }
    $errors = array_merge($errors, validate_task_values($values, $connection, $userId));
    if ($errors === []) {
        $statement = $connection->prepare(
            'INSERT INTO tasks (user_id, project_id, title, description, priority, status, start_date, deadline)
             VALUES (:user_id, :project_id, :title, :description, :priority, :status, :start_date, :deadline)'
        );
        $statement->execute(task_database_values($values, $userId));
        $taskId = (int) $connection->lastInsertId();
        flash_message('success', 'Research task created successfully.');
        redirect(app_url('modules/tasks/show.php?id=' . $taskId));
    }
}

render_app_page_start('Add Research Task', $user, 'tasks');
render_app_feedback();
?>
<section class="page-heading page-heading-compact"><div><p class="page-kicker">Research Tasks</p><h2>Add a research task</h2><p>Create a project task with priority, status, and an optional schedule.</p></div></section>
<section class="form-panel"><?php render_task_errors($errors); ?><form method="post" action="<?= e(app_url('modules/tasks/create.php')) ?>" data-task-form><?= csrf_input() ?><?php render_task_form($values, $projects, 'Add task'); ?></form></section>
<script src="<?= e(app_url('assets/js/task-form.js')) ?>" defer></script>
<?php render_app_page_end(); ?>
