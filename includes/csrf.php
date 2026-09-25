<?php

declare(strict_types=1);

function csrf_token(): string
{
    $token = $_SESSION['_csrf_token'] ?? null;

    if (!is_string($token) || strlen($token) !== 64) {
        $token = bin2hex(random_bytes(32));
        $_SESSION['_csrf_token'] = $token;
    }

    return $token;
}

function csrf_input(): string
{
    return '<input type="hidden" name="_csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf_token(?string $submittedToken): bool
{
    $storedToken = $_SESSION['_csrf_token'] ?? null;

    return is_string($submittedToken)
        && is_string($storedToken)
        && strlen($submittedToken) === 64
        && hash_equals($storedToken, $submittedToken);
}

function require_valid_csrf_token(): void
{
    require_post_request();

    $submittedToken = $_POST['_csrf_token'] ?? null;

    if (!is_string($submittedToken) || !verify_csrf_token($submittedToken)) {
        http_response_code(403);
        throw new RuntimeException('Invalid request token.');
    }
}

function rotate_csrf_token(): string
{
    unset($_SESSION['_csrf_token']);

    return csrf_token();
}
