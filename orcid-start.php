<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (!orcid_oauth_ready()) {
    flash_message('error', 'ORCID sign-in is not configured on this server yet.');
    redirect(app_url(user_is_authenticated() ? 'profile.php' : 'login.php'));
}

$state = bin2hex(random_bytes(24));
$_SESSION['_orcid_oauth'] = [
    'state' => $state,
    'mode' => user_is_authenticated() ? 'connect' : 'login',
    'created_at' => time(),
];

redirect(orcid_authorization_url($state));
