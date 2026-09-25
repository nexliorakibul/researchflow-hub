<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection, $user] = task_page_context();
$taskId = task_request_id();
$task = find_owned_task($connection, $taskId, $userId);
if ($task === null) {
    render_task_not_found($user);
}
$projects = task_projects($connection, $userId);
$values = task_form_values($task);
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
            'UPDATE tasks SET project_id = :project_id, title = :title, description = :description,
                priority = :priority, status = :status, start_date = :start_date, deadline = :deadline
             WHERE id = :id AND user_id = :user_id'
        );
        $parameters = task_database_values($values, $userId);
        $parameters['id'] = $taskId;
        $statement->execute($parameters);
        flash_message('success', 'Research task updated successfully.');
        redirect(app_url('modules/tasks/show.php?id=' . $taskId));
    }
}

render_app_page_start('Edit Research Task', $user, 'tasks');
render_app_feedback();
?>
<section class="page-heading page-heading-compact"><div><p class="page-kicker">Research Tasks</p><h2>Edit research task</h2><p>Update the task, schedule, priority, or completion status.</p></div></section>
<section class="form-panel"><?php render_task_errors($errors); ?><form method="post" action="<?= e(app_url('modules/tasks/edit.php?id=' . $taskId)) ?>" data-task-form><?= csrf_input() ?><?php render_task_form($values, $projects, 'Save changes'); ?></form></section>
<script src="<?= e(app_url('assets/js/task-form.js')) ?>" defer></script>
<?php render_app_page_end(); ?>
