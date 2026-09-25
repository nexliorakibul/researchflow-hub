<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/app-layout.php';

const EXPERIMENT_METRICS = [
    'accuracy' => 'Accuracy',
    'precision_score' => 'Precision',
    'recall_score' => 'Recall',
    'f1_score' => 'F1 score',
    'auroc' => 'AUROC',
    'auprc' => 'AUPRC',
    'specificity' => 'Specificity',
];

function experiment_page_context(): array
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

function experiment_request_id(string $source = 'get'): int
{
    $values = $source === 'post' ? $_POST : $_GET;
    $id = filter_var($values['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    return $id === false ? 0 : (int) $id;
}

function find_owned_experiment(PDO $connection, int $experimentId, int $userId): ?array
{
    if ($experimentId < 1) {
        return null;
    }

    $statement = $connection->prepare(
        'SELECT e.*, p.title AS project_title, d.name AS dataset_name
         FROM experiments e
         INNER JOIN projects p ON p.id = e.project_id AND p.user_id = e.user_id
         LEFT JOIN datasets d ON d.id = e.dataset_id AND d.user_id = e.user_id
         WHERE e.id = :id AND e.user_id = :user_id LIMIT 1'
    );
    $statement->execute(['id' => $experimentId, 'user_id' => $userId]);
    $experiment = $statement->fetch();

    return is_array($experiment) ? $experiment : null;
}

function experiment_projects(PDO $connection, int $userId): array
{
    $statement = $connection->prepare('SELECT id, title FROM projects WHERE user_id = :user_id ORDER BY title, id');
    $statement->execute(['user_id' => $userId]);

    return $statement->fetchAll();
}

function experiment_datasets(PDO $connection, int $userId): array
{
    $statement = $connection->prepare('SELECT id, name FROM datasets WHERE user_id = :user_id ORDER BY name, id');
    $statement->execute(['user_id' => $userId]);

    return $statement->fetchAll();
}

function render_experiment_not_found(array $user): never
{
    http_response_code(404);
    render_app_page_start('Experiment not found', $user, 'experiments');
    ?>
    <section class="state-page" aria-labelledby="not-found-title"><p class="state-code">404</p><h2 id="not-found-title">Experiment not found</h2><p>The experiment does not exist or is not available to your account.</p><a class="primary-button" href="<?= e(app_url('modules/experiments/index.php')) ?>">Back to experiments</a></section>
    <?php
    render_app_page_end();
    exit;
}

function experiment_form_values(array $source): array
{
    $fields = [
        'project_id', 'dataset_id', 'experiment_name', 'model_name', 'model_type',
        'preprocessing', 'feature_engineering', 'train_split', 'validation_split',
        'test_split', 'random_seed', 'learning_rate', 'batch_size', 'epochs',
        'optimizer', 'loss_function', 'accuracy', 'precision_score', 'recall_score',
        'f1_score', 'auroc', 'auprc', 'specificity', 'notes', 'experiment_date',
    ];
    $values = [];
    foreach ($fields as $field) {
        $values[$field] = trim(input_string($source, $field));
    }

    return $values;
}

function experiment_optional_float(string $value): ?float
{
    return $value === '' || !is_numeric($value) ? null : (float) $value;
}

function validate_experiment_values(array $values, PDO $connection, int $userId): array
{
    $errors = [];

    if (!string_length_between($values['experiment_name'], 1, 255)) {
        $errors[] = 'Experiment name is required and must not exceed 255 characters.';
    }
    if (!string_length_between($values['model_name'], 1, 255)) {
        $errors[] = 'Model name is required and must not exceed 255 characters.';
    }
    if (!valid_iso_date($values['experiment_date'])) {
        $errors[] = 'Enter a valid experiment date.';
    }

    $projectId = filter_var($values['project_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($projectId === false || !user_owns_record($connection, 'projects', (int) $projectId, $userId)) {
        $errors[] = 'Select a project that belongs to your account.';
    }
    $datasetId = filter_var($values['dataset_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($datasetId === false || !user_owns_record($connection, 'datasets', (int) $datasetId, $userId)) {
        $errors[] = 'Select a dataset that belongs to your account.';
    }

    foreach (['model_type' => 100, 'optimizer' => 100, 'loss_function' => 150] as $field => $maximum) {
        if ($values[$field] !== '' && !string_length_between($values[$field], 1, $maximum)) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . " must not exceed {$maximum} characters.";
        }
    }
    foreach (['preprocessing', 'feature_engineering', 'notes'] as $field) {
        if ($values[$field] !== '' && !string_length_between($values[$field], 1, 60000)) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' must not exceed 60,000 characters.';
        }
    }

    $splitValues = [];
    foreach (['train_split', 'validation_split', 'test_split'] as $field) {
        if ($values[$field] !== '' && !is_numeric($values[$field])) {
            $errors[] = 'Data splits must be numeric percentages.';
            $splitValues[$field] = null;
        } else {
            $splitValues[$field] = experiment_optional_float($values[$field]);
        }
    }
    if (!valid_experiment_splits($splitValues['train_split'], $splitValues['validation_split'], $splitValues['test_split'])) {
        $errors[] = 'Provide all three data splits between 0 and 100, totaling 100, or leave all three blank.';
    }

    if ($values['random_seed'] !== '') {
        $seed = filter_var($values['random_seed'], FILTER_VALIDATE_INT);
        if ($seed === false || $seed < -2147483648 || $seed > 2147483647) {
            $errors[] = 'Random seed must be a valid whole number.';
        }
    }
    if ($values['learning_rate'] !== '' && (!is_numeric($values['learning_rate']) || (float) $values['learning_rate'] <= 0 || (float) $values['learning_rate'] > 9999.99999999)) {
        $errors[] = 'Learning rate must be greater than 0 and no more than 9999.99999999.';
    }
    foreach (['batch_size' => 'Batch size', 'epochs' => 'Epochs'] as $field => $label) {
        if ($values[$field] !== '') {
            $number = filter_var($values[$field], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
            if ($number === false) {
                $errors[] = $label . ' must be a positive whole number.';
            }
        }
    }
    foreach (EXPERIMENT_METRICS as $field => $label) {
        if ($values[$field] !== '' && (!is_numeric($values[$field]) || !valid_metric((float) $values[$field]))) {
            $errors[] = $label . ' must be a number between 0 and 1.';
        }
    }

    return array_values(array_unique($errors));
}

function experiment_database_values(array $values, int $userId): array
{
    $parameters = ['user_id' => $userId];
    foreach ($values as $field => $value) {
        if (in_array($field, ['project_id', 'dataset_id', 'random_seed', 'batch_size', 'epochs'], true)) {
            $parameters[$field] = $value === '' ? null : (int) $value;
        } elseif (in_array($field, ['train_split', 'validation_split', 'test_split', 'learning_rate', ...array_keys(EXPERIMENT_METRICS)], true)) {
            $parameters[$field] = $value === '' ? null : $value;
        } else {
            $parameters[$field] = $value === '' ? null : $value;
        }
    }

    return $parameters;
}

function render_experiment_errors(array $errors): void
{
    if ($errors === []) {
        return;
    }
    ?>
    <div class="app-alert app-alert-error" role="alert"><strong>Please correct the following:</strong><ul class="error-list"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
    <?php
}

function render_experiment_form(array $values, array $projects, array $datasets, string $submitLabel): void
{
    $disabled = $projects === [] || $datasets === [];
    ?>
    <?php if ($projects === []): ?><div class="app-alert app-alert-warning" role="status">An experiment requires an existing research project.</div><?php endif; ?>
    <?php if ($datasets === []): ?><div class="app-alert app-alert-warning" role="status">An experiment requires an existing dataset.</div><?php endif; ?>
    <fieldset class="form-section"><legend>Experiment details</legend><div class="form-grid">
        <div class="form-field form-field-wide"><label for="experiment_name">Experiment name <span aria-hidden="true">*</span></label><input id="experiment_name" name="experiment_name" type="text" value="<?= e($values['experiment_name']) ?>" maxlength="255" required autofocus></div>
        <div class="form-field"><label for="project_id">Research project <span aria-hidden="true">*</span></label><select id="project_id" name="project_id" required><option value="">Select a project</option><?php foreach ($projects as $project): ?><option value="<?= e($project['id']) ?>" <?= $values['project_id'] === (string) $project['id'] ? 'selected' : '' ?>><?= e($project['title']) ?></option><?php endforeach; ?></select></div>
        <div class="form-field"><label for="dataset_id">Dataset <span aria-hidden="true">*</span></label><select id="dataset_id" name="dataset_id" required><option value="">Select a dataset</option><?php foreach ($datasets as $dataset): ?><option value="<?= e($dataset['id']) ?>" <?= $values['dataset_id'] === (string) $dataset['id'] ? 'selected' : '' ?>><?= e($dataset['name']) ?></option><?php endforeach; ?></select></div>
        <div class="form-field"><label for="model_name">Model name <span aria-hidden="true">*</span></label><input id="model_name" name="model_name" type="text" value="<?= e($values['model_name']) ?>" maxlength="255" required></div>
        <div class="form-field"><label for="model_type">Model type</label><input id="model_type" name="model_type" type="text" value="<?= e($values['model_type']) ?>" maxlength="100" placeholder="e.g. Random Forest, CNN"></div>
        <div class="form-field"><label for="experiment_date">Experiment date <span aria-hidden="true">*</span></label><input id="experiment_date" name="experiment_date" type="date" value="<?= e($values['experiment_date']) ?>" required></div>
    </div></fieldset>
    <fieldset class="form-section"><legend>Data preparation</legend><div class="form-grid">
        <div class="form-field form-field-wide"><label for="preprocessing">Preprocessing</label><textarea id="preprocessing" name="preprocessing" rows="4" maxlength="60000"><?= e($values['preprocessing']) ?></textarea></div>
        <div class="form-field form-field-wide"><label for="feature_engineering">Feature engineering</label><textarea id="feature_engineering" name="feature_engineering" rows="4" maxlength="60000"><?= e($values['feature_engineering']) ?></textarea></div>
        <div class="form-field"><label for="train_split">Train split (%)</label><input id="train_split" name="train_split" type="number" min="0" max="100" step="0.01" value="<?= e($values['train_split']) ?>"></div>
        <div class="form-field"><label for="validation_split">Validation split (%)</label><input id="validation_split" name="validation_split" type="number" min="0" max="100" step="0.01" value="<?= e($values['validation_split']) ?>"></div>
        <div class="form-field"><label for="test_split">Test split (%)</label><input id="test_split" name="test_split" type="number" min="0" max="100" step="0.01" value="<?= e($values['test_split']) ?>"></div>
    </div><p class="form-help">Enter all three splits so they total 100, or leave all three blank.</p></fieldset>
    <fieldset class="form-section"><legend>Training configuration</legend><div class="form-grid">
        <div class="form-field"><label for="random_seed">Random seed</label><input id="random_seed" name="random_seed" type="number" step="1" value="<?= e($values['random_seed']) ?>"></div>
        <div class="form-field"><label for="learning_rate">Learning rate</label><input id="learning_rate" name="learning_rate" type="number" min="0.00000001" max="9999.99999999" step="0.00000001" value="<?= e($values['learning_rate']) ?>"></div>
        <div class="form-field"><label for="batch_size">Batch size</label><input id="batch_size" name="batch_size" type="number" min="1" step="1" value="<?= e($values['batch_size']) ?>"></div>
        <div class="form-field"><label for="epochs">Epochs</label><input id="epochs" name="epochs" type="number" min="1" step="1" value="<?= e($values['epochs']) ?>"></div>
        <div class="form-field"><label for="optimizer">Optimizer</label><input id="optimizer" name="optimizer" type="text" value="<?= e($values['optimizer']) ?>" maxlength="100"></div>
        <div class="form-field"><label for="loss_function">Loss function</label><input id="loss_function" name="loss_function" type="text" value="<?= e($values['loss_function']) ?>" maxlength="150"></div>
    </div></fieldset>
    <fieldset class="form-section"><legend>Results</legend><p class="form-help">Metrics are optional. Enter decimal values from 0 to 1.</p><div class="form-grid">
        <?php foreach (EXPERIMENT_METRICS as $field => $label): ?><div class="form-field"><label for="<?= e($field) ?>"><?= e($label) ?></label><input id="<?= e($field) ?>" name="<?= e($field) ?>" type="number" min="0" max="1" step="0.000001" value="<?= e($values[$field]) ?>"></div><?php endforeach; ?>
        <div class="form-field form-field-wide"><label for="notes">Notes</label><textarea id="notes" name="notes" rows="5" maxlength="60000"><?= e($values['notes']) ?></textarea></div>
    </div></fieldset>
    <div class="form-actions"><button class="primary-button" type="submit" <?= $disabled ? 'disabled' : '' ?>><?= e($submitLabel) ?></button><a class="secondary-button" href="<?= e(app_url('modules/experiments/index.php')) ?>">Cancel</a></div>
    <?php
}

function experiment_date_label(?string $value, string $fallback = 'Not set'): string
{
    if ($value === null || $value === '') {
        return $fallback;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10));

    return $date instanceof DateTimeImmutable ? $date->format('M j, Y') : $fallback;
}

function experiment_metric_label(mixed $value): string
{
    return $value === null || $value === '' ? 'Not recorded' : number_format((float) $value * 100, 2) . '%';
}

function experiment_value_label(mixed $value, string $fallback = 'Not set'): string
{
    return $value === null || $value === '' ? $fallback : (string) $value;
}
