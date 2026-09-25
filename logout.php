<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

require_authenticated_user();

try {
    require_valid_csrf_token();
} catch (RuntimeException $exception) {
    if (!is_post_request()) {
        header('Allow: POST');
        render_http_error(405);
    }

    render_http_error(403);
}

logout_user();
flash_message('success', 'You have been logged out.');
redirect(app_url('login.php'));
