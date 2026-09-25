<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection, $user] = dataset_page_context();

try {
    require_valid_csrf_token();
} catch (RuntimeException $exception) {
    if (!is_post_request()) {
        header('Allow: POST');
        render_http_error(405);
    }
    render_http_error(403);
}

$datasetId = dataset_request_id('post');
$statement = $connection->prepare('DELETE FROM datasets WHERE id = :id AND user_id = :user_id');
$statement->execute(['id' => $datasetId, 'user_id' => $userId]);

if ($statement->rowCount() !== 1) {
    render_dataset_not_found($user);
}

flash_message('success', 'Dataset deleted successfully.');
redirect(app_url('modules/datasets/index.php'));
