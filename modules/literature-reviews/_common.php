<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/app-layout.php';

function review_page_context(): array
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

function review_request_id(string $source = 'get'): int
{
    $values = $source === 'post' ? $_POST : $_GET;
    $reviewId = filter_var($values['id'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);

    return $reviewId === false ? 0 : (int) $reviewId;
}

function find_owned_review(PDO $connection, int $reviewId, int $userId): ?array
{
    if ($reviewId < 1) {
        return null;
    }

    $statement = $connection->prepare(
        'SELECT lr.id, lr.project_id, lr.paper_id, lr.research_objective,
                lr.dataset_used, lr.dataset_size, lr.methodology, lr.models_used,
                lr.preprocessing, lr.metrics, lr.main_results, lr.key_findings,
                lr.strengths, lr.limitations, lr.future_work, lr.researcher_notes,
                lr.created_at, lr.updated_at, p.title AS project_title,
                pa.title AS paper_title, pa.authors AS paper_authors,
                pa.publication_year AS paper_year
         FROM literature_reviews lr
         INNER JOIN projects p ON p.id = lr.project_id AND p.user_id = lr.user_id
         INNER JOIN papers pa ON pa.id = lr.paper_id AND pa.user_id = lr.user_id
         WHERE lr.id = :id AND lr.user_id = :user_id
         LIMIT 1'
    );
    $statement->execute(['id' => $reviewId, 'user_id' => $userId]);
    $review = $statement->fetch();

    return is_array($review) ? $review : null;
}

function review_projects(PDO $connection, int $userId): array
{
    $statement = $connection->prepare('SELECT id, title FROM projects WHERE user_id = :user_id ORDER BY title ASC, id ASC');
    $statement->execute(['user_id' => $userId]);

    return $statement->fetchAll();
}

function review_papers(PDO $connection, int $userId): array
{
    $statement = $connection->prepare(
        'SELECT id, title, authors, publication_year FROM papers
         WHERE user_id = :user_id ORDER BY title ASC, id ASC'
    );
    $statement->execute(['user_id' => $userId]);

    return $statement->fetchAll();
}

function render_review_not_found(array $user): never
{
    http_response_code(404);
    render_app_page_start('Review not found', $user, 'reviews');
    ?>
    <section class="state-page" aria-labelledby="not-found-title">
        <p class="state-code">404</p><h2 id="not-found-title">Literature review not found</h2>
        <p>The review does not exist or is not available to your account.</p>
        <a class="primary-button" href="<?= e(app_url('modules/literature-reviews/index.php')) ?>">Back to reviews</a>
    </section>
    <?php
    render_app_page_end();
    exit;
}

function review_form_values(array $source): array
{
    return [
        'project_id' => trim(input_string($source, 'project_id')),
        'paper_id' => trim(input_string($source, 'paper_id')),
        'research_objective' => trim(input_string($source, 'research_objective')),
        'dataset_used' => trim(input_string($source, 'dataset_used')),
        'dataset_size' => trim(input_string($source, 'dataset_size')),
        'methodology' => trim(input_string($source, 'methodology')),
        'models_used' => trim(input_string($source, 'models_used')),
        'preprocessing' => trim(input_string($source, 'preprocessing')),
        'metrics' => trim(input_string($source, 'metrics')),
        'main_results' => trim(input_string($source, 'main_results')),
        'key_findings' => trim(input_string($source, 'key_findings')),
        'strengths' => trim(input_string($source, 'strengths')),
        'limitations' => trim(input_string($source, 'limitations')),
        'future_work' => trim(input_string($source, 'future_work')),
        'researcher_notes' => trim(input_string($source, 'researcher_notes')),
    ];
}

function validate_review_values(array $values, PDO $connection, int $userId): array
{
    $errors = [];
    $projectId = filter_var($values['project_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $paperId = filter_var($values['paper_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if ($projectId === false || !user_owns_record($connection, 'projects', (int) $projectId, $userId)) {
        $errors[] = 'Select a project that belongs to your account.';
    }
    if ($paperId === false || !user_owns_record($connection, 'papers', (int) $paperId, $userId)) {
        $errors[] = 'Select a paper that belongs to your account.';
    }

    $shortRules = [
        'dataset_used' => [500, 'Dataset used'],
        'dataset_size' => [100, 'Dataset size'],
        'models_used' => [500, 'Models used'],
        'metrics' => [500, 'Metrics'],
    ];
    foreach ($shortRules as $field => [$maximum, $label]) {
        if ($values[$field] !== '' && !string_length_between($values[$field], 1, $maximum)) {
            $errors[] = $label . ' must not exceed ' . $maximum . ' characters.';
        }
    }

    $textFields = [
        'research_objective' => 'Research objective',
        'methodology' => 'Methodology',
        'preprocessing' => 'Preprocessing',
        'main_results' => 'Main results',
        'key_findings' => 'Key findings',
        'strengths' => 'Strengths',
        'limitations' => 'Limitations',
        'future_work' => 'Future work',
        'researcher_notes' => 'Researcher notes',
    ];
    foreach ($textFields as $field => $label) {
        if ($values[$field] !== '' && !string_length_between($values[$field], 1, 60000)) {
            $errors[] = $label . ' must not exceed 60,000 characters.';
        }
    }

    return $errors;
}

function review_database_values(array $values, int $userId): array
{
    $parameters = ['user_id' => $userId, 'project_id' => (int) $values['project_id'], 'paper_id' => (int) $values['paper_id']];
    foreach (array_keys($values) as $field) {
        if (in_array($field, ['project_id', 'paper_id'], true)) {
            continue;
        }
        $parameters[$field] = $values[$field] !== '' ? $values[$field] : null;
    }

    return $parameters;
}

function render_review_errors(array $errors): void
{
    if ($errors === []) {
        return;
    }
    ?>
    <div class="app-alert app-alert-error" role="alert"><strong>Please correct the following:</strong><ul class="error-list"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
    <?php
}

function render_review_form(array $values, array $projects, array $papers, string $submitLabel): void
{
    ?>
    <?php if ($projects === [] || $papers === []): ?>
        <div class="app-alert app-alert-warning" role="status">A literature review requires both a research project and a saved paper.</div>
    <?php endif; ?>
    <div class="form-grid">
        <div class="form-field">
            <label for="project_id">Research project <span aria-hidden="true">*</span></label>
            <select id="project_id" name="project_id" required><option value="">Select a project</option><?php foreach ($projects as $project): ?><option value="<?= e($project['id']) ?>" <?= $values['project_id'] === (string) $project['id'] ? 'selected' : '' ?>><?= e($project['title']) ?></option><?php endforeach; ?></select>
        </div>
        <div class="form-field">
            <label for="paper_id">Research paper <span aria-hidden="true">*</span></label>
            <select id="paper_id" name="paper_id" required><option value="">Select a paper</option><?php foreach ($papers as $paper): ?><option value="<?= e($paper['id']) ?>" <?= $values['paper_id'] === (string) $paper['id'] ? 'selected' : '' ?>><?= e($paper['title']) ?><?= $paper['publication_year'] ? ' (' . e($paper['publication_year']) . ')' : '' ?></option><?php endforeach; ?></select>
        </div>
        <div class="form-field form-field-wide"><label for="research_objective">Research objective</label><textarea id="research_objective" name="research_objective" rows="4" maxlength="60000"><?= e($values['research_objective']) ?></textarea></div>
        <div class="form-field"><label for="dataset_used">Dataset used</label><input id="dataset_used" name="dataset_used" type="text" value="<?= e($values['dataset_used']) ?>" maxlength="500"></div>
        <div class="form-field"><label for="dataset_size">Dataset size</label><input id="dataset_size" name="dataset_size" type="text" value="<?= e($values['dataset_size']) ?>" maxlength="100" placeholder="12,000 samples"></div>
        <div class="form-field form-field-wide"><label for="methodology">Methodology</label><textarea id="methodology" name="methodology" rows="4" maxlength="60000"><?= e($values['methodology']) ?></textarea></div>
        <div class="form-field"><label for="models_used">Models used</label><input id="models_used" name="models_used" type="text" value="<?= e($values['models_used']) ?>" maxlength="500"></div>
        <div class="form-field"><label for="metrics">Evaluation metrics</label><input id="metrics" name="metrics" type="text" value="<?= e($values['metrics']) ?>" maxlength="500" placeholder="Accuracy, F1, AUROC"></div>
        <div class="form-field form-field-wide"><label for="preprocessing">Preprocessing</label><textarea id="preprocessing" name="preprocessing" rows="4" maxlength="60000"><?= e($values['preprocessing']) ?></textarea></div>
        <div class="form-field form-field-wide"><label for="main_results">Main results</label><textarea id="main_results" name="main_results" rows="4" maxlength="60000"><?= e($values['main_results']) ?></textarea></div>
        <div class="form-field form-field-wide"><label for="key_findings">Key findings</label><textarea id="key_findings" name="key_findings" rows="4" maxlength="60000"><?= e($values['key_findings']) ?></textarea></div>
        <div class="form-field"><label for="strengths">Strengths</label><textarea id="strengths" name="strengths" rows="5" maxlength="60000"><?= e($values['strengths']) ?></textarea></div>
        <div class="form-field"><label for="limitations">Limitations</label><textarea id="limitations" name="limitations" rows="5" maxlength="60000"><?= e($values['limitations']) ?></textarea></div>
        <div class="form-field form-field-wide"><label for="future_work">Future work</label><textarea id="future_work" name="future_work" rows="4" maxlength="60000"><?= e($values['future_work']) ?></textarea></div>
        <div class="form-field form-field-wide"><label for="researcher_notes">Researcher notes</label><textarea id="researcher_notes" name="researcher_notes" rows="5" maxlength="60000"><?= e($values['researcher_notes']) ?></textarea></div>
    </div>
    <div class="form-actions"><button class="primary-button" type="submit" <?= $projects === [] || $papers === [] ? 'disabled' : '' ?>><?= e($submitLabel) ?></button><a class="secondary-button" href="<?= e(app_url('modules/literature-reviews/index.php')) ?>">Cancel</a></div>
    <?php
}

function review_date_label(?string $value, string $fallback = 'Not set'): string
{
    if ($value === null || $value === '') {
        return $fallback;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10));

    return $date instanceof DateTimeImmutable ? $date->format('M j, Y') : $fallback;
}
