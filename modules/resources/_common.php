<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/app-layout.php';

const RESOURCE_TYPES = [
    'research_paper',
    'dataset',
    'github_repository',
    'website',
    'book',
    'research_tool',
    'documentation',
    'pretrained_model',
    'course',
    'tutorial',
    'other',
];

const RESOURCE_STATUSES = ['saved', 'to_read', 'reading', 'completed', 'important'];

function resource_page_context(): array
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

function resource_request_id(string $source = 'get'): int
{
    $values = $source === 'post' ? $_POST : $_GET;
    $resourceId = filter_var($values['id'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);

    return $resourceId === false ? 0 : (int) $resourceId;
}

function find_owned_resource(PDO $connection, int $resourceId, int $userId): ?array
{
    if ($resourceId < 1) {
        return null;
    }

    $statement = $connection->prepare(
        'SELECT r.id, r.project_id, r.title, r.resource_type, r.research_area,
                r.authors, r.publication_year, r.url, r.doi, r.description,
                r.personal_notes, r.tags, r.status, r.is_favorite,
                r.created_at, r.updated_at, p.title AS project_title
         FROM resources r
         LEFT JOIN projects p ON p.id = r.project_id AND p.user_id = r.user_id
         WHERE r.id = :id AND r.user_id = :user_id
         LIMIT 1'
    );
    $statement->execute([
        'id' => $resourceId,
        'user_id' => $userId,
    ]);
    $resource = $statement->fetch();

    return is_array($resource) ? $resource : null;
}

function resource_projects(PDO $connection, int $userId): array
{
    $statement = $connection->prepare(
        'SELECT id, title FROM projects
         WHERE user_id = :user_id
         ORDER BY title ASC, id ASC'
    );
    $statement->execute(['user_id' => $userId]);

    return $statement->fetchAll();
}

function render_resource_not_found(array $user): never
{
    http_response_code(404);
    render_app_page_start('Resource not found', $user, 'resources');
    ?>
    <section class="state-page" aria-labelledby="not-found-title">
        <p class="state-code">404</p>
        <h2 id="not-found-title">Resource not found</h2>
        <p>The resource does not exist or is not available to your account.</p>
        <a class="primary-button" href="<?= e(app_url('modules/resources/index.php')) ?>">Back to resources</a>
    </section>
    <?php
    render_app_page_end();
    exit;
}

function resource_form_values(array $source): array
{
    return [
        'project_id' => trim(input_string($source, 'project_id')),
        'title' => trim(input_string($source, 'title')),
        'resource_type' => strtolower(trim(input_string($source, 'resource_type', 'website'))),
        'research_area' => trim(input_string($source, 'research_area')),
        'authors' => trim(input_string($source, 'authors')),
        'publication_year' => trim(input_string($source, 'publication_year')),
        'url' => trim(input_string($source, 'url')),
        'doi' => trim(input_string($source, 'doi')),
        'description' => trim(input_string($source, 'description')),
        'personal_notes' => trim(input_string($source, 'personal_notes')),
        'tags' => trim(input_string($source, 'tags')),
        'status' => strtolower(trim(input_string($source, 'status', 'saved'))),
        'is_favorite' => input_string($source, 'is_favorite') === '1',
    ];
}

function validate_resource_values(
    array $values,
    PDO $connection,
    int $userId
): array {
    $errors = [];

    if (!string_length_between($values['title'], 1, 255)) {
        $errors[] = 'Resource title is required and must not exceed 255 characters.';
    }

    if (!in_array($values['resource_type'], RESOURCE_TYPES, true)) {
        $errors[] = 'Select a valid resource type.';
    }

    if (!in_array($values['status'], RESOURCE_STATUSES, true)) {
        $errors[] = 'Select a valid resource status.';
    }

    $lengthRules = [
        'research_area' => [150, 'Research area'],
        'authors' => [500, 'Authors'],
        'doi' => [255, 'DOI'],
        'tags' => [500, 'Tags'],
    ];

    foreach ($lengthRules as $field => [$maximum, $label]) {
        if ($values[$field] !== '' && !string_length_between($values[$field], 1, $maximum)) {
            $errors[] = $label . ' must not exceed ' . $maximum . ' characters.';
        }
    }

    if ($values['url'] !== '') {
        if (!string_length_between($values['url'], 1, 1000)) {
            $errors[] = 'URL must not exceed 1000 characters.';
        } elseif (!valid_http_url($values['url'])) {
            $errors[] = 'URL must be a valid HTTP or HTTPS address.';
        }
    }

    if ($values['publication_year'] !== '') {
        $year = filter_var($values['publication_year'], FILTER_VALIDATE_INT);

        if ($year === false || !valid_year((int) $year)) {
            $errors[] = 'Publication year must contain four valid digits.';
        }
    }

    if ($values['project_id'] !== '') {
        $projectId = filter_var($values['project_id'], FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($projectId === false || !user_owns_record($connection, 'projects', (int) $projectId, $userId)) {
            $errors[] = 'Select a project that belongs to your account.';
        }
    }

    return $errors;
}

function render_resource_errors(array $errors): void
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

function render_resource_form(
    array $values,
    array $projects,
    string $submitLabel
): void {
    ?>
    <div class="form-grid">
        <div class="form-field form-field-wide">
            <label for="title">Resource title <span aria-hidden="true">*</span></label>
            <input id="title" name="title" type="text" value="<?= e($values['title']) ?>"
                   maxlength="255" required autofocus>
        </div>

        <div class="form-field">
            <label for="project_id">Research project</label>
            <select id="project_id" name="project_id">
                <option value="">Unassigned</option>
                <?php foreach ($projects as $project): ?>
                    <option value="<?= e($project['id']) ?>" <?= $values['project_id'] === (string) $project['id'] ? 'selected' : '' ?>>
                        <?= e($project['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-field">
            <label for="resource_type">Resource type <span aria-hidden="true">*</span></label>
            <select id="resource_type" name="resource_type" required>
                <?php foreach (RESOURCE_TYPES as $type): ?>
                    <option value="<?= e($type) ?>" <?= $values['resource_type'] === $type ? 'selected' : '' ?>>
                        <?= e(resource_label($type)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-field">
            <label for="research_area">Research area</label>
            <input id="research_area" name="research_area" type="text"
                   value="<?= e($values['research_area']) ?>" maxlength="150">
        </div>

        <div class="form-field">
            <label for="authors">Authors</label>
            <input id="authors" name="authors" type="text" value="<?= e($values['authors']) ?>" maxlength="500">
        </div>

        <div class="form-field">
            <label for="publication_year">Publication year</label>
            <input id="publication_year" name="publication_year" type="number"
                   value="<?= e($values['publication_year']) ?>" min="1000" max="9999" inputmode="numeric">
        </div>

        <div class="form-field">
            <label for="status">Status <span aria-hidden="true">*</span></label>
            <select id="status" name="status" required>
                <?php foreach (RESOURCE_STATUSES as $status): ?>
                    <option value="<?= e($status) ?>" <?= $values['status'] === $status ? 'selected' : '' ?>>
                        <?= e(resource_label($status)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-field form-field-wide">
            <label for="url">URL</label>
            <input id="url" name="url" type="url" value="<?= e($values['url']) ?>"
                   maxlength="1000" placeholder="https://example.com/resource">
        </div>

        <div class="form-field">
            <label for="doi">DOI</label>
            <input id="doi" name="doi" type="text" value="<?= e($values['doi']) ?>" maxlength="255">
        </div>

        <div class="form-field">
            <label for="tags">Tags</label>
            <input id="tags" name="tags" type="text" value="<?= e($values['tags']) ?>"
                   maxlength="500" placeholder="machine learning, survey">
        </div>

        <div class="form-field form-field-wide">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="5"><?= e($values['description']) ?></textarea>
        </div>

        <div class="form-field form-field-wide">
            <label for="personal_notes">Personal notes</label>
            <textarea id="personal_notes" name="personal_notes" rows="5"><?= e($values['personal_notes']) ?></textarea>
        </div>

        <div class="form-field form-field-wide checkbox-field">
            <input type="hidden" name="is_favorite" value="0">
            <input id="is_favorite" name="is_favorite" type="checkbox" value="1" <?= $values['is_favorite'] ? 'checked' : '' ?>>
            <label for="is_favorite">Mark as a favorite resource</label>
        </div>
    </div>

    <div class="form-actions">
        <button class="primary-button" type="submit"><?= e($submitLabel) ?></button>
        <a class="secondary-button" href="<?= e(app_url('modules/resources/index.php')) ?>">Cancel</a>
    </div>
    <?php
}

function resource_label(?string $value, string $fallback = 'Not specified'): string
{
    if ($value === null || $value === '') {
        return $fallback;
    }

    return ucwords(str_replace('_', ' ', $value));
}

function resource_date_label(?string $value, string $fallback = 'Not set'): string
{
    if ($value === null || $value === '') {
        return $fallback;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10));

    return $date instanceof DateTimeImmutable ? $date->format('M j, Y') : $fallback;
}
