<?php

declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

$appConfig = require BASE_PATH . '/config/app.php';

if (!is_array($appConfig)) {
    throw new RuntimeException('Application configuration must return an array.');
}

if (!defined('APP_CONFIG')) {
    define('APP_CONFIG', $appConfig);
}

date_default_timezone_set((string) (APP_CONFIG['timezone'] ?? 'UTC'));

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');

require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/error-handler.php';
register_application_error_handlers();
require_once BASE_PATH . '/includes/security.php';
require_once BASE_PATH . '/includes/csrf.php';
require_once BASE_PATH . '/includes/validation.php';
require_once BASE_PATH . '/includes/database.php';
require_once BASE_PATH . '/includes/auth.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off';
    $sessionPath = (string) (APP_CONFIG['session_path'] ?? '');

    if ($sessionPath !== '') {
        if (!is_dir($sessionPath) || !is_writable($sessionPath)) {
            throw new RuntimeException('Configured session path is not writable.');
        }

        session_save_path($sessionPath);
    }

    session_name((string) (APP_CONFIG['session_name'] ?? 'researchflow_session'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

send_security_headers();
