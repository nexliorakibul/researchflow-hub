<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/app-layout.php';

const NOTE_TYPES = ['general', 'idea', 'meeting', 'experiment', 'paper', 'dataset', 'important'];

function note_page_context(): array
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

function note_request_id(string $source = 'get'): int
{
    $values = $source === 'post' ? $_POST : $_GET;
    $id = filter_var($values['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    return $id === false ? 0 : (int) $id;
}

function find_owned_note(PDO $connection, int $noteId, int $userId): ?array
{
    if ($noteId < 1) {
        return null;
    }

    $statement = $connection->prepare(
        'SELECT n.id, n.project_id, n.title, n.content, n.note_type,
                n.created_at, n.updated_at, p.title AS project_title
         FROM notes n
         INNER JOIN projects p ON p.id = n.project_id AND p.user_id = n.user_id
         WHERE n.id = :id AND n.user_id = :user_id LIMIT 1'
    );
    $statement->execute(['id' => $noteId, 'user_id' => $userId]);
    $note = $statement->fetch();

    return is_array($note) ? $note : null;
}

function note_projects(PDO $connection, int $userId): array
{
    $statement = $connection->prepare('SELECT id, title FROM projects WHERE user_id = :user_id ORDER BY title, id');
    $statement->execute(['user_id' => $userId]);

    return $statement->fetchAll();
}

function render_note_not_found(array $user): never
{
    http_response_code(404);
    render_app_page_start('Note not found', $user, 'notes');
    ?>
    <section class="state-page" aria-labelledby="not-found-title"><p class="state-code">404</p><h2 id="not-found-title">Note not found</h2><p>The note does not exist or is not available to your account.</p><a class="primary-button" href="<?= e(app_url('modules/notes/index.php')) ?>">Back to notes</a></section>
    <?php
    render_app_page_end();
    exit;
}

function note_form_values(array $source): array
{
    return [
        'project_id' => trim(input_string($source, 'project_id')),
        'title' => trim(input_string($source, 'title')),
        'content' => trim(input_string($source, 'content')),
        'note_type' => strtolower(trim(input_string($source, 'note_type', 'general'))),
    ];
}

function validate_note_values(array $values, PDO $connection, int $userId): array
{
    $errors = [];
    if (!string_length_between($values['title'], 1, 255)) {
        $errors[] = 'Note title is required and must not exceed 255 characters.';
    }
    if (!string_length_between($values['content'], 1, 60000)) {
        $errors[] = 'Note content is required and must not exceed 60,000 characters.';
    }
    if (!in_array($values['note_type'], NOTE_TYPES, true)) {
        $errors[] = 'Select a valid note type.';
    }

    $projectId = filter_var($values['project_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($projectId === false || !user_owns_record($connection, 'projects', (int) $projectId, $userId)) {
        $errors[] = 'Select a project that belongs to your account.';
    }

    return $errors;
}

function note_database_values(array $values, int $userId): array
{
    return [
        'user_id' => $userId,
        'project_id' => (int) $values['project_id'],
        'title' => $values['title'],
        'content' => $values['content'],
        'note_type' => $values['note_type'],
    ];
}

function render_note_errors(array $errors): void
{
    if ($errors === []) {
        return;
    }
    ?>
    <div class="app-alert app-alert-error" role="alert"><strong>Please correct the following:</strong><ul class="error-list"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
    <?php
}

function render_note_form(array $values, array $projects, string $submitLabel): void
{
    ?>
    <?php if ($projects === []): ?><div class="app-alert app-alert-warning" role="status">A research note requires an existing research project.</div><?php endif; ?>
    <div class="form-grid">
        <div class="form-field form-field-wide"><label for="title">Note title <span aria-hidden="true">*</span></label><input id="title" name="title" type="text" value="<?= e($values['title']) ?>" maxlength="255" required autofocus></div>
        <div class="form-field"><label for="project_id">Research project <span aria-hidden="true">*</span></label><select id="project_id" name="project_id" required><option value="">Select a project</option><?php foreach ($projects as $project): ?><option value="<?= e($project['id']) ?>" <?= $values['project_id'] === (string) $project['id'] ? 'selected' : '' ?>><?= e($project['title']) ?></option><?php endforeach; ?></select></div>
        <div class="form-field"><label for="note_type">Note type <span aria-hidden="true">*</span></label><select id="note_type" name="note_type" required><?php foreach (NOTE_TYPES as $type): ?><option value="<?= e($type) ?>" <?= $values['note_type'] === $type ? 'selected' : '' ?>><?= e(note_label($type)) ?></option><?php endforeach; ?></select></div>
        <div class="form-field form-field-wide"><label for="content">Content <span aria-hidden="true">*</span></label><textarea id="content" name="content" rows="12" maxlength="60000" required><?= e($values['content']) ?></textarea></div>
    </div>
    <div class="form-actions"><button class="primary-button" type="submit" <?= $projects === [] ? 'disabled' : '' ?>><?= e($submitLabel) ?></button><a class="secondary-button" href="<?= e(app_url('modules/notes/index.php')) ?>">Cancel</a></div>
    <?php
}

function note_label(?string $value, string $fallback = 'Not set'): string
{
    return $value === null || $value === '' ? $fallback : ucwords(str_replace('_', ' ', $value));
}

function note_date_label(?string $value, string $fallback = 'Not set'): string
{
    if ($value === null || $value === '') {
        return $fallback;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10));

    return $date instanceof DateTimeImmutable ? $date->format('M j, Y') : $fallback;
}
