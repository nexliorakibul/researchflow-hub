<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/app-layout.php';

const DATASET_ACCESS_TYPES = ['public', 'restricted', 'credentialed', 'private', 'unknown'];
const DATASET_STATUSES = ['identified', 'requested', 'approved', 'downloaded', 'preprocessed', 'used', 'archived'];

function dataset_page_context(): array
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

function dataset_request_id(string $source = 'get'): int
{
    $values = $source === 'post' ? $_POST : $_GET;
    $datasetId = filter_var($values['id'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);

    return $datasetId === false ? 0 : (int) $datasetId;
}

function find_owned_dataset(PDO $connection, int $datasetId, int $userId): ?array
{
    if ($datasetId < 1) {
        return null;
    }

    $statement = $connection->prepare(
        'SELECT d.id, d.project_id, d.name, d.domain, d.source, d.url,
                d.row_count, d.column_count, d.image_count, d.class_count,
                d.file_size, d.license, d.access_type, d.description,
                d.status, d.notes, d.created_at, d.updated_at,
                p.title AS project_title
         FROM datasets d
         LEFT JOIN projects p ON p.id = d.project_id AND p.user_id = d.user_id
         WHERE d.id = :id AND d.user_id = :user_id
         LIMIT 1'
    );
    $statement->execute(['id' => $datasetId, 'user_id' => $userId]);
    $dataset = $statement->fetch();

    return is_array($dataset) ? $dataset : null;
}

function dataset_projects(PDO $connection, int $userId): array
{
    $statement = $connection->prepare(
        'SELECT id, title FROM projects WHERE user_id = :user_id ORDER BY title ASC, id ASC'
    );
    $statement->execute(['user_id' => $userId]);

    return $statement->fetchAll();
}

function render_dataset_not_found(array $user): never
{
    http_response_code(404);
    render_app_page_start('Dataset not found', $user, 'datasets');
    ?>
    <section class="state-page" aria-labelledby="not-found-title">
        <p class="state-code">404</p>
        <h2 id="not-found-title">Dataset not found</h2>
        <p>The dataset does not exist or is not available to your account.</p>
        <a class="primary-button" href="<?= e(app_url('modules/datasets/index.php')) ?>">Back to datasets</a>
    </section>
    <?php
    render_app_page_end();
    exit;
}

function dataset_form_values(array $source): array
{
    return [
        'project_id' => trim(input_string($source, 'project_id')),
        'name' => trim(input_string($source, 'name')),
        'domain' => trim(input_string($source, 'domain')),
        'source' => trim(input_string($source, 'source')),
        'url' => trim(input_string($source, 'url')),
        'row_count' => trim(input_string($source, 'row_count')),
        'column_count' => trim(input_string($source, 'column_count')),
        'image_count' => trim(input_string($source, 'image_count')),
        'class_count' => trim(input_string($source, 'class_count')),
        'file_size' => trim(input_string($source, 'file_size')),
        'license' => trim(input_string($source, 'license')),
        'access_type' => strtolower(trim(input_string($source, 'access_type', 'unknown'))),
        'description' => trim(input_string($source, 'description')),
        'status' => strtolower(trim(input_string($source, 'status', 'identified'))),
        'notes' => trim(input_string($source, 'notes')),
    ];
}

function valid_dataset_count(string $value, int $maximum): bool
{
    if ($value === '') {
        return true;
    }

    return ctype_digit($value)
        && filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0, 'max_range' => $maximum],
        ]) !== false;
}

function validate_dataset_values(array $values, PDO $connection, int $userId): array
{
    $errors = [];

    if (!string_length_between($values['name'], 1, 255)) {
        $errors[] = 'Dataset name is required and must not exceed 255 characters.';
    }

    $lengthRules = [
        'domain' => [150, 'Domain'],
        'source' => [255, 'Source'],
        'file_size' => [100, 'File size'],
        'license' => [255, 'License'],
    ];

    foreach ($lengthRules as $field => [$maximum, $label]) {
        if ($values[$field] !== '' && !string_length_between($values[$field], 1, $maximum)) {
            $errors[] = $label . ' must not exceed ' . $maximum . ' characters.';
        }
    }

    if (!in_array($values['access_type'], DATASET_ACCESS_TYPES, true)) {
        $errors[] = 'Select a valid access type.';
    }

    if (!in_array($values['status'], DATASET_STATUSES, true)) {
        $errors[] = 'Select a valid dataset status.';
    }

    if ($values['url'] !== '') {
        if (!string_length_between($values['url'], 1, 1000)) {
            $errors[] = 'URL must not exceed 1000 characters.';
        } elseif (!valid_http_url($values['url'])) {
            $errors[] = 'URL must be a valid HTTP or HTTPS address.';
        }
    }

    $countRules = [
        'row_count' => [PHP_INT_MAX, 'Row count'],
        'column_count' => [2147483647, 'Column count'],
        'image_count' => [PHP_INT_MAX, 'Image count'],
        'class_count' => [2147483647, 'Class count'],
    ];

    foreach ($countRules as $field => [$maximum, $label]) {
        if (!valid_dataset_count($values[$field], $maximum)) {
            $errors[] = $label . ' must be a non-negative whole number.';
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

function dataset_database_values(array $values, int $userId): array
{
    return [
        'user_id' => $userId,
        'project_id' => $values['project_id'] !== '' ? (int) $values['project_id'] : null,
        'name' => $values['name'],
        'domain' => $values['domain'] !== '' ? $values['domain'] : null,
        'source' => $values['source'] !== '' ? $values['source'] : null,
        'url' => $values['url'] !== '' ? $values['url'] : null,
        'row_count' => $values['row_count'] !== '' ? (int) $values['row_count'] : null,
        'column_count' => $values['column_count'] !== '' ? (int) $values['column_count'] : null,
        'image_count' => $values['image_count'] !== '' ? (int) $values['image_count'] : null,
        'class_count' => $values['class_count'] !== '' ? (int) $values['class_count'] : null,
        'file_size' => $values['file_size'] !== '' ? $values['file_size'] : null,
        'license' => $values['license'] !== '' ? $values['license'] : null,
        'access_type' => $values['access_type'],
        'description' => $values['description'] !== '' ? $values['description'] : null,
        'status' => $values['status'],
        'notes' => $values['notes'] !== '' ? $values['notes'] : null,
    ];
}

function render_dataset_errors(array $errors): void
{
    if ($errors === []) {
        return;
    }
    ?>
    <div class="app-alert app-alert-error" role="alert">
        <strong>Please correct the following:</strong>
        <ul class="error-list"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
    </div>
    <?php
}

function render_dataset_form(array $values, array $projects, string $submitLabel): void
{
    ?>
    <div class="form-grid">
        <div class="form-field form-field-wide">
            <label for="name">Dataset name <span aria-hidden="true">*</span></label>
            <input id="name" name="name" type="text" value="<?= e($values['name']) ?>" maxlength="255" required autofocus>
        </div>
        <div class="form-field">
            <label for="project_id">Research project</label>
            <select id="project_id" name="project_id">
                <option value="">Unassigned</option>
                <?php foreach ($projects as $project): ?><option value="<?= e($project['id']) ?>" <?= $values['project_id'] === (string) $project['id'] ? 'selected' : '' ?>><?= e($project['title']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="form-field"><label for="domain">Domain</label><input id="domain" name="domain" type="text" value="<?= e($values['domain']) ?>" maxlength="150" placeholder="Computer vision"></div>
        <div class="form-field"><label for="source">Source</label><input id="source" name="source" type="text" value="<?= e($values['source']) ?>" maxlength="255" placeholder="Kaggle, UCI, organization"></div>
        <div class="form-field"><label for="license">License</label><input id="license" name="license" type="text" value="<?= e($values['license']) ?>" maxlength="255" placeholder="CC BY 4.0"></div>
        <div class="form-field">
            <label for="access_type">Access type <span aria-hidden="true">*</span></label>
            <select id="access_type" name="access_type" required><?php foreach (DATASET_ACCESS_TYPES as $accessType): ?><option value="<?= e($accessType) ?>" <?= $values['access_type'] === $accessType ? 'selected' : '' ?>><?= e(dataset_label($accessType)) ?></option><?php endforeach; ?></select>
        </div>
        <div class="form-field">
            <label for="status">Status <span aria-hidden="true">*</span></label>
            <select id="status" name="status" required><?php foreach (DATASET_STATUSES as $status): ?><option value="<?= e($status) ?>" <?= $values['status'] === $status ? 'selected' : '' ?>><?= e(dataset_label($status)) ?></option><?php endforeach; ?></select>
        </div>
        <div class="form-field form-field-wide"><label for="url">Dataset URL</label><input id="url" name="url" type="url" value="<?= e($values['url']) ?>" maxlength="1000" placeholder="https://example.com/dataset"></div>
        <div class="form-field"><label for="row_count">Row count</label><input id="row_count" name="row_count" type="number" value="<?= e($values['row_count']) ?>" min="0" step="1" inputmode="numeric"></div>
        <div class="form-field"><label for="column_count">Column count</label><input id="column_count" name="column_count" type="number" value="<?= e($values['column_count']) ?>" min="0" step="1" inputmode="numeric"></div>
        <div class="form-field"><label for="image_count">Image count</label><input id="image_count" name="image_count" type="number" value="<?= e($values['image_count']) ?>" min="0" step="1" inputmode="numeric"></div>
        <div class="form-field"><label for="class_count">Class count</label><input id="class_count" name="class_count" type="number" value="<?= e($values['class_count']) ?>" min="0" step="1" inputmode="numeric"></div>
        <div class="form-field"><label for="file_size">File size</label><input id="file_size" name="file_size" type="text" value="<?= e($values['file_size']) ?>" maxlength="100" placeholder="2.4 GB"></div>
        <div class="form-field form-field-wide"><label for="description">Description</label><textarea id="description" name="description" rows="6"><?= e($values['description']) ?></textarea></div>
        <div class="form-field form-field-wide"><label for="notes">Notes</label><textarea id="notes" name="notes" rows="5"><?= e($values['notes']) ?></textarea></div>
    </div>
    <div class="form-actions"><button class="primary-button" type="submit"><?= e($submitLabel) ?></button><a class="secondary-button" href="<?= e(app_url('modules/datasets/index.php')) ?>">Cancel</a></div>
    <?php
}

function dataset_label(?string $value, string $fallback = 'Not specified'): string
{
    if ($value === null || $value === '') {
        return $fallback;
    }

    return ucwords(str_replace('_', ' ', $value));
}

function dataset_date_label(?string $value, string $fallback = 'Not set'): string
{
    if ($value === null || $value === '') {
        return $fallback;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10));

    return $date instanceof DateTimeImmutable ? $date->format('M j, Y') : $fallback;
}
