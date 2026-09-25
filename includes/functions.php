<?php

declare(strict_types=1);

function app_config(string $key, mixed $default = null): mixed
{
    if (!defined('APP_CONFIG') || !is_array(APP_CONFIG)) {
        return $default;
    }

    return APP_CONFIG[$key] ?? $default;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function input_string(array $source, string $key, string $default = ''): string
{
    $value = $source[$key] ?? $default;

    return is_string($value) ? $value : $default;
}

function query_string(string $key, string $default = ''): string
{
    return input_string($_GET, $key, $default);
}

function post_string(string $key, string $default = ''): string
{
    return input_string($_POST, $key, $default);
}

function app_url(string $path = ''): string
{
    $baseUrl = (string) app_config('base_url', '');
    $path = ltrim($path, '/');

    if ($baseUrl === '') {
        return '/' . $path;
    }

    return $path === '' ? $baseUrl . '/' : $baseUrl . '/' . $path;
}

function redirect(string $location, int $status = 302): void
{
    if ($status < 300 || $status > 399) {
        throw new InvalidArgumentException('Redirect status must be between 300 and 399.');
    }

    if ($location === '' || preg_match('/[\r\n]/', $location) === 1) {
        throw new InvalidArgumentException('Redirect location is invalid.');
    }

    header('Location: ' . $location, true, $status);
    exit;
}

function flash_message(string $type, string $message): void
{
    $allowedTypes = ['success', 'error', 'warning', 'info'];

    if (!in_array($type, $allowedTypes, true)) {
        throw new InvalidArgumentException('Flash message type is invalid.');
    }

    $_SESSION['_flash_messages'][] = [
        'type' => $type,
        'message' => $message,
    ];
}

function pull_flash_messages(): array
{
    $messages = $_SESSION['_flash_messages'] ?? [];
    unset($_SESSION['_flash_messages']);

    return is_array($messages) ? $messages : [];
}
