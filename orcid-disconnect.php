<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$userId = require_authenticated_user();
if (!is_post_request()) {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

try {
    require_valid_csrf_token();
} catch (RuntimeException $exception) {
    flash_message('error', 'Your form session expired. Please try again.');
    redirect(app_url('profile.php'));
}

$statement = get_database_connection()->prepare('UPDATE users SET orcid_id = NULL WHERE id = :id');
$statement->execute(['id' => $userId]);
flash_message('success', 'ORCID has been disconnected from your account.');
redirect(app_url('profile.php'));
