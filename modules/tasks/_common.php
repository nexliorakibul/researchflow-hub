<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/app-layout.php';

const TASK_PRIORITIES = ['low', 'medium', 'high', 'urgent'];
const TASK_STATUSES = ['pending', 'in_progress', 'completed', 'cancelled'];

function task_page_context(): array
{
    $userId = require_authenticated_user();
    $connection = get_database_connection();
    $statement = $connection->prepare('SELECT name, email FROM users WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $userId]);
    $user = $statement->fetch();

    if (!is_array($user)) {
        logout_user();
        flash_message('error', 'Your account session is no longer valid.');
        redirect(app_url('login.php'));
    }

    return [$userId, $connection, $user];
}

function task_request_id(string $source = 'get'): int
{
    $values = $source === 'post' ? $_POST : $_GET;
    $id = filter_var($values['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    return $id === false ? 0 : (int) $id;
}

function find_owned_task(PDO $connection, int $taskId, int $userId): ?array
{
    if ($taskId < 1) {
        return null;
    }

    $statement = $connection->prepare(
        'SELECT t.id, t.project_id, t.title, t.description, t.priority, t.status,
                t.start_date, t.deadline, t.created_at, t.updated_at,
                p.title AS project_title
         FROM tasks t
         INNER JOIN projects p ON p.id = t.project_id AND p.user_id = t.user_id
         WHERE t.id = :id AND t.user_id = :user_id LIMIT 1'
    );
    $statement->execute(['id' => $taskId, 'user_id' => $userId]);
    $task = $statement->fetch();

    return is_array($task) ? $task : null;
}

function task_projects(PDO $connection, int $userId): array
{
    $statement = $connection->prepare('SELECT id, title FROM projects WHERE user_id = :user_id ORDER BY title, id');
    $statement->execute(['user_id' => $userId]);

    return $statement->fetchAll();
}

function render_task_not_found(array $user): never
{
    http_response_code(404);
    render_app_page_start('Task not found', $user, 'tasks');
    ?>
    <section class="state-page" aria-labelledby="not-found-title"><p class="state-code">404</p><h2 id="not-found-title">Task not found</h2><p>The task does not exist or is not available to your account.</p><a class="primary-button" href="<?= e(app_url('modules/tasks/index.php')) ?>">Back to tasks</a></section>
    <?php
    render_app_page_end();
    exit;
}

function task_form_values(array $source): array
{
    return [
        'project_id' => trim(input_string($source, 'project_id')),
        'title' => trim(input_string($source, 'title')),
        'description' => trim(input_string($source, 'description')),
        'priority' => strtolower(trim(input_string($source, 'priority', 'medium'))),
        'status' => strtolower(trim(input_string($source, 'status', 'pending'))),
        'start_date' => trim(input_string($source, 'start_date')),
        'deadline' => trim(input_string($source, 'deadline')),
    ];
}

function validate_task_values(array $values, PDO $connection, int $userId): array
{
    $errors = [];
    if (!string_length_between($values['title'], 1, 255)) {
        $errors[] = 'Task title is required and must not exceed 255 characters.';
    }
    if ($values['description'] !== '' && !string_length_between($values['description'], 1, 60000)) {
        $errors[] = 'Description must not exceed 60,000 characters.';
    }
    if (!in_array($values['priority'], TASK_PRIORITIES, true)) {
        $errors[] = 'Select a valid task priority.';
    }
    if (!in_array($values['status'], TASK_STATUSES, true)) {
        $errors[] = 'Select a valid task status.';
    }

    $projectId = filter_var($values['project_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($projectId === false || !user_owns_record($connection, 'projects', (int) $projectId, $userId)) {
        $errors[] = 'Select a project that belongs to your account.';
    }

    foreach (['start_date' => 'Start date', 'deadline' => 'Deadline'] as $field => $label) {
        if ($values[$field] !== '' && !valid_iso_date($values[$field])) {
            $errors[] = $label . ' must be a valid date.';
        }
    }
    if (!valid_date_order(
        $values['start_date'] !== '' ? $values['start_date'] : null,
        $values['deadline'] !== '' ? $values['deadline'] : null
    )) {
        $errors[] = 'Deadline cannot be earlier than the start date.';
    }

    return $errors;
}

function task_database_values(array $values, int $userId): array
{
    return [
        'user_id' => $userId,
        'project_id' => (int) $values['project_id'],
        'title' => $values['title'],
        'description' => $values['description'] !== '' ? $values['description'] : null,
        'priority' => $values['priority'],
        'status' => $values['status'],
        'start_date' => $values['start_date'] !== '' ? $values['start_date'] : null,
        'deadline' => $values['deadline'] !== '' ? $values['deadline'] : null,
    ];
}

function render_task_errors(array $errors): void
{
    if ($errors === []) {
        return;
    }
    ?>
    <div class="app-alert app-alert-error" role="alert"><strong>Please correct the following:</strong><ul class="error-list"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
    <?php
}

function render_task_form(array $values, array $projects, string $submitLabel): void
{
    ?>
    <?php if ($projects === []): ?><div class="app-alert app-alert-warning" role="status">A research task requires an existing research project.</div><?php endif; ?>
    <div class="form-grid">
        <div class="form-field form-field-wide"><label for="title">Task title <span aria-hidden="true">*</span></label><input id="title" name="title" type="text" value="<?= e($values['title']) ?>" maxlength="255" required autofocus></div>
        <div class="form-field"><label for="project_id">Research project <span aria-hidden="true">*</span></label><select id="project_id" name="project_id" required><option value="">Select a project</option><?php foreach ($projects as $project): ?><option value="<?= e($project['id']) ?>" <?= $values['project_id'] === (string) $project['id'] ? 'selected' : '' ?>><?= e($project['title']) ?></option><?php endforeach; ?></select></div>
        <div class="form-field"><label for="priority">Priority <span aria-hidden="true">*</span></label><select id="priority" name="priority" required><?php foreach (TASK_PRIORITIES as $priority): ?><option value="<?= e($priority) ?>" <?= $values['priority'] === $priority ? 'selected' : '' ?>><?= e(task_label($priority)) ?></option><?php endforeach; ?></select></div>
        <div class="form-field"><label for="status">Status <span aria-hidden="true">*</span></label><select id="status" name="status" required><?php foreach (TASK_STATUSES as $status): ?><option value="<?= e($status) ?>" <?= $values['status'] === $status ? 'selected' : '' ?>><?= e(task_label($status)) ?></option><?php endforeach; ?></select></div>
        <div class="form-field"><label for="start_date">Start date</label><input id="start_date" name="start_date" type="date" value="<?= e($values['start_date']) ?>"></div>
        <div class="form-field"><label for="deadline">Deadline</label><input id="deadline" name="deadline" type="date" value="<?= e($values['deadline']) ?>"></div>
        <div class="form-field form-field-wide"><label for="description">Description</label><textarea id="description" name="description" rows="7" maxlength="60000"><?= e($values['description']) ?></textarea></div>
    </div>
    <div class="form-actions"><button class="primary-button" type="submit" <?= $projects === [] ? 'disabled' : '' ?>><?= e($submitLabel) ?></button><a class="secondary-button" href="<?= e(app_url('modules/tasks/index.php')) ?>">Cancel</a></div>
    <?php
}

function task_label(?string $value, string $fallback = 'Not set'): string
{
    return $value === null || $value === '' ? $fallback : ucwords(str_replace('_', ' ', $value));
}

function task_date_label(?string $value, string $fallback = 'Not set'): string
{
    if ($value === null || $value === '') {
        return $fallback;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10));

    return $date instanceof DateTimeImmutable ? $date->format('M j, Y') : $fallback;
}

function task_is_overdue(array $task): bool
{
    return !in_array($task['status'], ['completed', 'cancelled'], true)
        && is_string($task['deadline'])
        && $task['deadline'] !== ''
        && $task['deadline'] < date('Y-m-d');
}
