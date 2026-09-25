<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/app-layout.php';

const PROJECT_STATUSES = ['planned', 'ongoing', 'completed', 'archived'];

function project_page_context(): array
{
    $userId = require_authenticated_user();
    $connection = get_database_connection();
    $statement = $connection->prepare(
        'SELECT name, email FROM users WHERE id = :id LIMIT 1'
    );
    $statement->execute(['id' => $userId]);
    $user = $statement->fetch();

    if (!is_array($user)) {
        logout_user();
        flash_message('error', 'Your account session is no longer valid.');
        redirect(app_url('login.php'));
    }

    return [$userId, $connection, $user];
}

function project_request_id(string $source = 'get'): int
{
    $values = $source === 'post' ? $_POST : $_GET;
    $projectId = filter_var($values['id'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);

    return $projectId === false ? 0 : (int) $projectId;
}

function find_owned_project(PDO $connection, int $projectId, int $userId): ?array
{
    if ($projectId < 1) {
        return null;
    }

    $statement = $connection->prepare(
        'SELECT id, title, research_area, description, status, start_date, target_date,
                created_at, updated_at
         FROM projects
         WHERE id = :id AND user_id = :user_id
         LIMIT 1'
    );
    $statement->execute([
        'id' => $projectId,
        'user_id' => $userId,
    ]);
    $project = $statement->fetch();

    return is_array($project) ? $project : null;
}

function render_project_not_found(array $user): never
{
    http_response_code(404);
    render_app_page_start('Project not found', $user, 'projects');
    ?>
    <section class="state-page" aria-labelledby="not-found-title">
        <p class="state-code">404</p>
        <h2 id="not-found-title">Project not found</h2>
        <p>The project does not exist or is not available to your account.</p>
        <a class="primary-button" href="<?= e(app_url('modules/projects/index.php')) ?>">Back to projects</a>
    </section>
    <?php
    render_app_page_end();
    exit;
}

function project_form_values(array $source): array
{
    return [
        'title' => trim(input_string($source, 'title')),
        'research_area' => trim(input_string($source, 'research_area')),
        'description' => trim(input_string($source, 'description')),
        'status' => strtolower(trim(input_string($source, 'status', 'planned'))),
        'start_date' => trim(input_string($source, 'start_date')),
        'target_date' => trim(input_string($source, 'target_date')),
    ];
}

function validate_project_values(array $values): array
{
    $errors = [];

    if (!string_length_between($values['title'], 1, 200)) {
        $errors[] = 'Project title is required and must not exceed 200 characters.';
    }

    if ($values['research_area'] !== '' && !string_length_between($values['research_area'], 1, 150)) {
        $errors[] = 'Research area must not exceed 150 characters.';
    }

    if (!in_array($values['status'], PROJECT_STATUSES, true)) {
        $errors[] = 'Select a valid project status.';
    }

    foreach (['start_date' => 'Start date', 'target_date' => 'Target date'] as $field => $label) {
        if ($values[$field] !== '' && !valid_iso_date($values[$field])) {
            $errors[] = $label . ' must be a valid date.';
        }
    }

    if (!valid_date_order(
        $values['start_date'] !== '' ? $values['start_date'] : null,
        $values['target_date'] !== '' ? $values['target_date'] : null
    )) {
        $errors[] = 'Target date cannot be earlier than the start date.';
    }

    return $errors;
}

function render_project_errors(array $errors): void
{
    if ($errors === []) {
        return;
    }
    ?>
    <div class="app-alert app-alert-error" role="alert">
        <strong>Please correct the following:</strong>
        <ul class="error-list">
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
}

function render_project_form(array $values, string $submitLabel): void
{
    ?>
    <div class="form-grid">
        <div class="form-field form-field-wide">
            <label for="title">Project title <span aria-hidden="true">*</span></label>
            <input id="title" name="title" type="text" value="<?= e($values['title']) ?>"
                   maxlength="200" required autofocus>
        </div>

        <div class="form-field">
            <label for="research_area">Research area</label>
            <input id="research_area" name="research_area" type="text"
                   value="<?= e($values['research_area']) ?>" maxlength="150">
        </div>

        <div class="form-field">
            <label for="status">Status <span aria-hidden="true">*</span></label>
            <select id="status" name="status" required>
                <?php foreach (PROJECT_STATUSES as $status): ?>
                    <option value="<?= e($status) ?>" <?= $values['status'] === $status ? 'selected' : '' ?>>
                        <?= e(ucfirst($status)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-field">
            <label for="start_date">Start date</label>
            <input id="start_date" name="start_date" type="date" value="<?= e($values['start_date']) ?>">
        </div>

        <div class="form-field">
            <label for="target_date">Target date</label>
            <input id="target_date" name="target_date" type="date" value="<?= e($values['target_date']) ?>">
        </div>

        <div class="form-field form-field-wide">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="7"><?= e($values['description']) ?></textarea>
        </div>
    </div>

    <div class="form-actions">
        <button class="primary-button" type="submit"><?= e($submitLabel) ?></button>
        <a class="secondary-button" href="<?= e(app_url('modules/projects/index.php')) ?>">Cancel</a>
    </div>
    <?php
}

function project_status_label(string $status): string
{
    return ucwords(str_replace('_', ' ', $status));
}

function project_date_label(?string $date, string $fallback = 'Not set'): string
{
    if ($date === null || $date === '') {
        return $fallback;
    }

    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', substr($date, 0, 10));

    return $parsed instanceof DateTimeImmutable ? $parsed->format('M j, Y') : $fallback;
}
