<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection] = dataset_page_context();

if (!is_post_request()) {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

try {
    require_valid_csrf_token();
} catch (RuntimeException $exception) {
    flash_message('error', 'Your form session expired. Please try again.');
    redirect(app_url('modules/datasets/discover.php'));
}

try {
    $dataset = datacite_dataset_by_doi(post_string('doi'));
} catch (ScholarlyApiException $exception) {
    flash_message('error', $exception->getMessage());
    redirect(app_url('modules/datasets/discover.php'));
}

$dataset['project_id'] = trim(post_string('project_id'));
$dataset['status'] = strtolower(trim(post_string('status', 'identified')));
$values = dataset_form_values($dataset);
$errors = validate_dataset_values($values, $connection, $userId);
if ($errors !== []) {
    flash_message('error', implode(' ', $errors));
    redirect(app_url('modules/datasets/discover.php'));
}

$datasetId = create_owned_dataset($connection, $userId, $values);
flash_message('success', 'Dataset imported from DataCite successfully.');
redirect(app_url('modules/datasets/show.php?id=' . $datasetId));
