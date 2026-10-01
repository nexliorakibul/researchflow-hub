<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection] = paper_page_context();

if (!is_post_request()) {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

try {
    require_valid_csrf_token();
} catch (RuntimeException $exception) {
    flash_message('error', 'Your form session expired. Please try again.');
    redirect(app_url('modules/papers/discover.php'));
}

try {
    $paper = openalex_paper_by_id(post_string('work_id'));
} catch (ScholarlyApiException $exception) {
    flash_message('error', $exception->getMessage());
    redirect(app_url('modules/papers/discover.php'));
}

$paper['project_id'] = trim(post_string('project_id'));
$paper['reading_status'] = strtolower(trim(post_string('reading_status', 'to_read')));
$values = paper_form_values($paper);
$errors = validate_paper_values($values, $connection, $userId);

if ($errors !== []) {
    flash_message('error', implode(' ', $errors));
    redirect(app_url('modules/papers/discover.php'));
}

$paperId = create_owned_paper($connection, $userId, $values);
flash_message('success', 'Paper imported from OpenAlex successfully.');
redirect(app_url('modules/papers/show.php?id=' . $paperId));
