<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection, $user] = experiment_page_context();
try {
    require_valid_csrf_token();
} catch (RuntimeException $exception) {
    if (!is_post_request()) {
        header('Allow: POST');
        render_http_error(405);
    }
    render_http_error(403);
}

$experimentId = experiment_request_id('post');
$statement = $connection->prepare('DELETE FROM experiments WHERE id = :id AND user_id = :user_id');
$statement->execute(['id' => $experimentId, 'user_id' => $userId]);
if ($statement->rowCount() !== 1) {
    render_experiment_not_found($user);
}

flash_message('success', 'Experiment deleted successfully.');
redirect(app_url('modules/experiments/index.php'));
