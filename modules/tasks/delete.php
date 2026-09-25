<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection, $user] = task_page_context();
try {
    require_valid_csrf_token();
} catch (RuntimeException $exception) {
    if (!is_post_request()) {
        header('Allow: POST');
        render_http_error(405);
    }
    render_http_error(403);
}

$taskId = task_request_id('post');
$statement = $connection->prepare('DELETE FROM tasks WHERE id = :id AND user_id = :user_id');
$statement->execute(['id' => $taskId, 'user_id' => $userId]);
if ($statement->rowCount() !== 1) {
    render_task_not_found($user);
}

flash_message('success', 'Research task deleted successfully.');
redirect(app_url('modules/tasks/index.php'));
