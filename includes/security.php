<?php

declare(strict_types=1);

function request_method(): string
{
    return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
}

function is_post_request(): bool
{
    return request_method() === 'POST';
}

function require_post_request(): void
{
    if (is_post_request()) {
        return;
    }

    if (!headers_sent()) {
        header('Allow: POST');
    }

    http_response_code(405);
    throw new RuntimeException('POST request required.');
}

function send_security_headers(): void
{
    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}
