<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/app-layout.php';

const PAPER_READING_STATUSES = ['to_read', 'reading', 'reviewed', 'completed', 'important'];

function paper_page_context(): array
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

function paper_request_id(string $source = 'get'): int
{
    $values = $source === 'post' ? $_POST : $_GET;
    $paperId = filter_var($values['id'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);

    return $paperId === false ? 0 : (int) $paperId;
}

function find_owned_paper(PDO $connection, int $paperId, int $userId): ?array
{
    if ($paperId < 1) {
        return null;
    }

    $statement = $connection->prepare(
        'SELECT p.id, p.project_id, p.title, p.authors, p.publication_year,
                p.venue, p.volume, p.issue, p.pages, p.doi, p.url,
                p.research_area, p.keywords, p.summary, p.reading_status,
                p.personal_notes, p.created_at, p.updated_at,
                pr.title AS project_title
         FROM papers p
         LEFT JOIN projects pr ON pr.id = p.project_id AND pr.user_id = p.user_id
         WHERE p.id = :id AND p.user_id = :user_id
         LIMIT 1'
    );
    $statement->execute(['id' => $paperId, 'user_id' => $userId]);
    $paper = $statement->fetch();

    return is_array($paper) ? $paper : null;
}

function paper_projects(PDO $connection, int $userId): array
{
    $statement = $connection->prepare(
        'SELECT id, title FROM projects WHERE user_id = :user_id ORDER BY title ASC, id ASC'
    );
    $statement->execute(['user_id' => $userId]);

    return $statement->fetchAll();
}

function render_paper_not_found(array $user): never
{
    http_response_code(404);
    render_app_page_start('Paper not found', $user, 'papers');
    ?>
    <section class="state-page" aria-labelledby="not-found-title">
        <p class="state-code">404</p>
        <h2 id="not-found-title">Paper not found</h2>
        <p>The paper does not exist or is not available to your account.</p>
        <a class="primary-button" href="<?= e(app_url('modules/papers/index.php')) ?>">Back to papers</a>
    </section>
    <?php
    render_app_page_end();
    exit;
}

function paper_form_values(array $source): array
{
    return [
        'project_id' => trim(input_string($source, 'project_id')),
        'title' => trim(input_string($source, 'title')),
        'authors' => trim(input_string($source, 'authors')),
        'publication_year' => trim(input_string($source, 'publication_year')),
        'venue' => trim(input_string($source, 'venue')),
        'volume' => trim(input_string($source, 'volume')),
        'issue' => trim(input_string($source, 'issue')),
        'pages' => trim(input_string($source, 'pages')),
        'doi' => trim(input_string($source, 'doi')),
        'url' => trim(input_string($source, 'url')),
        'research_area' => trim(input_string($source, 'research_area')),
        'keywords' => trim(input_string($source, 'keywords')),
        'summary' => trim(input_string($source, 'summary')),
        'reading_status' => strtolower(trim(input_string($source, 'reading_status', 'to_read'))),
        'personal_notes' => trim(input_string($source, 'personal_notes')),
    ];
}

function validate_paper_values(array $values, PDO $connection, int $userId): array
{
    $errors = [];

    if (!string_length_between($values['title'], 1, 500)) {
        $errors[] = 'Paper title is required and must not exceed 500 characters.';
    }

    $lengthRules = [
        'authors' => [1000, 'Authors'],
        'venue' => [255, 'Venue'],
        'volume' => [50, 'Volume'],
        'issue' => [50, 'Issue'],
        'pages' => [50, 'Pages'],
        'doi' => [255, 'DOI'],
        'research_area' => [150, 'Research area'],
        'keywords' => [1000, 'Keywords'],
    ];

    foreach ($lengthRules as $field => [$maximum, $label]) {
        if ($values[$field] !== '' && !string_length_between($values[$field], 1, $maximum)) {
            $errors[] = $label . ' must not exceed ' . $maximum . ' characters.';
        }
    }

    if (!in_array($values['reading_status'], PAPER_READING_STATUSES, true)) {
        $errors[] = 'Select a valid reading status.';
    }

    if ($values['publication_year'] !== '') {
        $year = filter_var($values['publication_year'], FILTER_VALIDATE_INT);

        if ($year === false || !valid_year((int) $year)) {
            $errors[] = 'Publication year must contain four valid digits.';
        }
    }

    if ($values['url'] !== '') {
        if (!string_length_between($values['url'], 1, 1000)) {
            $errors[] = 'URL must not exceed 1000 characters.';
        } elseif (!valid_http_url($values['url'])) {
            $errors[] = 'URL must be a valid HTTP or HTTPS address.';
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

function render_paper_errors(array $errors): void
{
    if ($errors === []) {
        return;
    }
    ?>
    <div class="app-alert app-alert-error" role="alert">
        <strong>Please correct the following:</strong>
        <ul class="error-list">
            <?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
        </ul>
    </div>
    <?php
}

function render_paper_form(array $values, array $projects, string $submitLabel): void
{
    ?>
    <div class="form-grid">
        <div class="form-field form-field-wide">
            <label for="title">Paper title <span aria-hidden="true">*</span></label>
            <input id="title" name="title" type="text" value="<?= e($values['title']) ?>" maxlength="500" required autofocus>
        </div>
        <div class="form-field">
            <label for="project_id">Research project</label>
            <select id="project_id" name="project_id">
                <option value="">Unassigned</option>
                <?php foreach ($projects as $project): ?>
                    <option value="<?= e($project['id']) ?>" <?= $values['project_id'] === (string) $project['id'] ? 'selected' : '' ?>><?= e($project['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-field">
            <label for="reading_status">Reading status <span aria-hidden="true">*</span></label>
            <select id="reading_status" name="reading_status" required>
                <?php foreach (PAPER_READING_STATUSES as $status): ?>
                    <option value="<?= e($status) ?>" <?= $values['reading_status'] === $status ? 'selected' : '' ?>><?= e(paper_label($status)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-field form-field-wide">
            <label for="authors">Authors</label>
            <input id="authors" name="authors" type="text" value="<?= e($values['authors']) ?>" maxlength="1000" placeholder="Author One, Author Two">
        </div>
        <div class="form-field">
            <label for="publication_year">Publication year</label>
            <input id="publication_year" name="publication_year" type="number" value="<?= e($values['publication_year']) ?>" min="1000" max="9999" inputmode="numeric">
        </div>
        <div class="form-field">
            <label for="venue">Venue / publisher</label>
            <input id="venue" name="venue" type="text" value="<?= e($values['venue']) ?>" maxlength="255">
        </div>
        <div class="form-field">
            <label for="volume">Volume</label>
            <input id="volume" name="volume" type="text" value="<?= e($values['volume']) ?>" maxlength="50">
        </div>
        <div class="form-field">
            <label for="issue">Issue</label>
            <input id="issue" name="issue" type="text" value="<?= e($values['issue']) ?>" maxlength="50">
        </div>
        <div class="form-field">
            <label for="pages">Pages</label>
            <input id="pages" name="pages" type="text" value="<?= e($values['pages']) ?>" maxlength="50" placeholder="101-115">
        </div>
        <div class="form-field">
            <label for="doi">DOI</label>
            <input id="doi" name="doi" type="text" value="<?= e($values['doi']) ?>" maxlength="255" placeholder="10.xxxx/example">
        </div>
        <div class="form-field form-field-wide">
            <label for="url">Paper URL</label>
            <input id="url" name="url" type="url" value="<?= e($values['url']) ?>" maxlength="1000" placeholder="https://example.com/paper">
        </div>
        <div class="form-field">
            <label for="research_area">Research area</label>
            <input id="research_area" name="research_area" type="text" value="<?= e($values['research_area']) ?>" maxlength="150">
        </div>
        <div class="form-field">
            <label for="keywords">Keywords</label>
            <input id="keywords" name="keywords" type="text" value="<?= e($values['keywords']) ?>" maxlength="1000" placeholder="machine learning, security">
        </div>
        <div class="form-field form-field-wide">
            <label for="summary">Summary</label>
            <textarea id="summary" name="summary" rows="6"><?= e($values['summary']) ?></textarea>
        </div>
        <div class="form-field form-field-wide">
            <label for="personal_notes">Personal notes</label>
            <textarea id="personal_notes" name="personal_notes" rows="5"><?= e($values['personal_notes']) ?></textarea>
        </div>
    </div>
    <div class="form-actions">
        <button class="primary-button" type="submit"><?= e($submitLabel) ?></button>
        <a class="secondary-button" href="<?= e(app_url('modules/papers/index.php')) ?>">Cancel</a>
    </div>
    <?php
}

function paper_label(?string $value, string $fallback = 'Not specified'): string
{
    if ($value === null || $value === '') {
        return $fallback;
    }

    return ucwords(str_replace('_', ' ', $value));
}

function paper_date_label(?string $value, string $fallback = 'Not set'): string
{
    if ($value === null || $value === '') {
        return $fallback;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', substr($value, 0, 10));

    return $date instanceof DateTimeImmutable ? $date->format('M j, Y') : $fallback;
}
