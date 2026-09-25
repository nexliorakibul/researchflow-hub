<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/app-layout.php';

const GAP_TYPES = ['dataset', 'methodological', 'performance', 'validation', 'explainability', 'multimodal', 'privacy', 'generalization', 'computational', 'clinical_validation', 'other'];
const GAP_PRIORITIES = ['low', 'medium', 'high', 'critical'];
const GAP_STATUSES = ['identified', 'investigating', 'addressed', 'rejected'];

function gap_page_context(): array
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

function gap_request_id(string $source = 'get'): int
{
    $values = $source === 'post' ? $_POST : $_GET;
    $gapId = filter_var($values['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    return $gapId === false ? 0 : (int) $gapId;
}

function find_owned_gap(PDO $connection, int $gapId, int $userId): ?array
{
    if ($gapId < 1) {
        return null;
    }

    $statement = $connection->prepare(
        'SELECT g.id, g.project_id, g.paper_id, g.gap_title, g.gap_type,
                g.description, g.evidence, g.potential_solution, g.priority,
                g.status, g.notes, g.created_at, g.updated_at,
                p.title AS project_title, pa.title AS paper_title,
                pa.authors AS paper_authors, pa.publication_year AS paper_year
         FROM research_gaps g
         INNER JOIN projects p ON p.id = g.project_id AND p.user_id = g.user_id
         LEFT JOIN papers pa ON pa.id = g.paper_id AND pa.user_id = g.user_id
         WHERE g.id = :id AND g.user_id = :user_id LIMIT 1'
    );
    $statement->execute(['id' => $gapId, 'user_id' => $userId]);
    $gap = $statement->fetch();

    return is_array($gap) ? $gap : null;
}

function gap_projects(PDO $connection, int $userId): array
{
    $statement = $connection->prepare('SELECT id, title FROM projects WHERE user_id = :user_id ORDER BY title ASC, id ASC');
    $statement->execute(['user_id' => $userId]);

    return $statement->fetchAll();
}

function gap_papers(PDO $connection, int $userId): array
{
    $statement = $connection->prepare('SELECT id, title, publication_year FROM papers WHERE user_id = :user_id ORDER BY title ASC, id ASC');
    $statement->execute(['user_id' => $userId]);

    return $statement->fetchAll();
}

function render_gap_not_found(array $user): never
{
    http_response_code(404);
    render_app_page_start('Research gap not found', $user, 'gaps');
    ?>
    <section class="state-page" aria-labelledby="not-found-title"><p class="state-code">404</p><h2 id="not-found-title">Research gap not found</h2><p>The gap does not exist or is not available to your account.</p><a class="primary-button" href="<?= e(app_url('modules/research-gaps/index.php')) ?>">Back to research gaps</a></section>
    <?php
    render_app_page_end();
    exit;
}

function gap_form_values(array $source): array
{
    return [
        'project_id' => trim(input_string($source, 'project_id')),
        'paper_id' => trim(input_string($source, 'paper_id')),
        'gap_title' => trim(input_string($source, 'gap_title')),
        'gap_type' => strtolower(trim(input_string($source, 'gap_type', 'other'))),
        'description' => trim(input_string($source, 'description')),
        'evidence' => trim(input_string($source, 'evidence')),
        'potential_solution' => trim(input_string($source, 'potential_solution')),
        'priority' => strtolower(trim(input_string($source, 'priority', 'medium'))),
        'status' => strtolower(trim(input_string($source, 'status', 'identified'))),
        'notes' => trim(input_string($source, 'notes')),
    ];
}

function validate_gap_values(array $values, PDO $connection, int $userId): array
{
    $errors = [];
    if (!string_length_between($values['gap_title'], 1, 255)) {
        $errors[] = 'Gap title is required and must not exceed 255 characters.';
    }
    if (!string_length_between($values['description'], 1, 60000)) {
        $errors[] = 'Description is required and must not exceed 60,000 characters.';
    }
    if (!in_array($values['gap_type'], GAP_TYPES, true)) {
        $errors[] = 'Select a valid gap type.';
    }
    if (!in_array($values['priority'], GAP_PRIORITIES, true)) {
        $errors[] = 'Select a valid priority.';
    }
    if (!in_array($values['status'], GAP_STATUSES, true)) {
        $errors[] = 'Select a valid gap status.';
    }

    foreach (['evidence' => 'Evidence', 'potential_solution' => 'Potential solution', 'notes' => 'Notes'] as $field => $label) {
        if ($values[$field] !== '' && !string_length_between($values[$field], 1, 60000)) {
            $errors[] = $label . ' must not exceed 60,000 characters.';
        }
    }

    $projectId = filter_var($values['project_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($projectId === false || !user_owns_record($connection, 'projects', (int) $projectId, $userId)) {
        $errors[] = 'Select a project that belongs to your account.';
    }

    if ($values['paper_id'] !== '') {
        $paperId = filter_var($values['paper_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($paperId === false || !user_owns_record($connection, 'papers', (int) $paperId, $userId)) {
            $errors[] = 'Select a paper that belongs to your account.';
        }
    }

    return $errors;
}

function gap_database_values(array $values, int $userId): array
{
    return [
        'user_id' => $userId,
        'project_id' => (int) $values['project_id'],
        'paper_id' => $values['paper_id'] !== '' ? (int) $values['paper_id'] : null,
        'gap_title' => $values['gap_title'],
        'gap_type' => $values['gap_type'],
        'description' => $values['description'],
        'evidence' => $values['evidence'] !== '' ? $values['evidence'] : null,
        'potential_solution' => $values['potential_solution'] !== '' ? $values['potential_solution'] : null,
        'priority' => $values['priority'],
        'status' => $values['status'],
        'notes' => $values['notes'] !== '' ? $values['notes'] : null,
    ];
}

function render_gap_errors(array $errors): void
{
    if ($errors === []) {
        return;
    }
    ?>
    <div class="app-alert app-alert-error" role="alert"><strong>Please correct the following:</strong><ul class="error-list"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
    <?php
}

function render_gap_form(array $values, array $projects, array $papers, string $submitLabel): void
{
    ?>
    <?php if ($projects === []): ?><div class="app-alert app-alert-warning" role="status">A research gap requires an existing research project.</div><?php endif; ?>
    <div class="form-grid">
        <div class="form-field form-field-wide"><label for="gap_title">Gap title <span aria-hidden="true">*</span></label><input id="gap_title" name="gap_title" type="text" value="<?= e($values['gap_title']) ?>" maxlength="255" required autofocus></div>
        <div class="form-field"><label for="project_id">Research project <span aria-hidden="true">*</span></label><select id="project_id" name="project_id" required><option value="">Select a project</option><?php foreach ($projects as $project): ?><option value="<?= e($project['id']) ?>" <?= $values['project_id'] === (string) $project['id'] ? 'selected' : '' ?>><?= e($project['title']) ?></option><?php endforeach; ?></select></div>
        <div class="form-field"><label for="paper_id">Related paper</label><select id="paper_id" name="paper_id"><option value="">No related paper</option><?php foreach ($papers as $paper): ?><option value="<?= e($paper['id']) ?>" <?= $values['paper_id'] === (string) $paper['id'] ? 'selected' : '' ?>><?= e($paper['title']) ?><?= $paper['publication_year'] ? ' (' . e($paper['publication_year']) . ')' : '' ?></option><?php endforeach; ?></select></div>
        <div class="form-field"><label for="gap_type">Gap type <span aria-hidden="true">*</span></label><select id="gap_type" name="gap_type" required><?php foreach (GAP_TYPES as $type): ?><option value="<?= e($type) ?>" <?= $values['gap_type'] === $type ? 'selected' : '' ?>><?= e(gap_label($type)) ?></option><?php endforeach; ?></select></div>
        <div class="form-field"><label for="priority">Priority <span aria-hidden="true">*</span></label><select id="priority" name="priority" required><?php foreach (GAP_PRIORITIES as $priority): ?><option value="<?= e($priority) ?>" <?= $values['priority'] === $priority ? 'selected' : '' ?>><?= e(gap_label($priority)) ?></option><?php endforeach; ?></select></div>
        <div class="form-field"><label for="status">Status <span aria-hidden="true">*</span></label><select id="status" name="status" required><?php foreach (GAP_STATUSES as $status): ?><option value="<?= e($status) ?>" <?= $values['status'] === $status ? 'selected' : '' ?>><?= e(gap_label($status)) ?></option><?php endforeach; ?></select></div>
        <div class="form-field form-field-wide"><label for="description">Description <span aria-hidden="true">*</span></label><textarea id="description" name="description" rows="6" maxlength="60000" required><?= e($values['description']) ?></textarea></div>
        <div class="form-field form-field-wide"><label for="evidence">Evidence</label><textarea id="evidence" name="evidence" rows="5" maxlength="60000"><?= e($values['evidence']) ?></textarea></div>
        <div class="form-field form-field-wide"><label for="potential_solution">Potential solution</label><textarea id="potential_solution" name="potential_solution" rows="5" maxlength="60000"><?= e($values['potential_solution']) ?></textarea></div>
        <div class="form-field form-field-wide"><label for="notes">Notes</label><textarea id="notes" name="notes" rows="5" maxlength="60000"><?= e($values['notes']) ?></textarea></div>
    </div>
    <div class="form-actions"><button class="primary-button" type="submit" <?= $projects === [] ? 'disabled' : '' ?>><?= e($submitLabel) ?></button><a class="secondary-button" href="<?= e(app_url('modules/research-gaps/index.php')) ?>">Cancel</a></div>
    <?php
}

function gap_label(?string $value, string $fallback = 'Not specified'): string
{
    if ($value === null || $value === '') {
        return $fallback;
    }

    return ucwords(str_replace('_', ' ', $value));
}

function gap_date_label(?string $value, string $fallback = 'Not set'): string
{
    if ($value === null || $value === '') {
        return $fallback;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10));

    return $date instanceof DateTimeImmutable ? $date->format('M j, Y') : $fallback;
}
