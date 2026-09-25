<?php

declare(strict_types=1);

function error_page_content(int $status): array
{
    return match ($status) {
        403 => ['Access forbidden', 'You do not have permission to perform this action.'],
        404 => ['Page not found', 'The requested page could not be found.'],
        405 => ['Method not allowed', 'This action cannot be completed with the requested method.'],
        500 => ['Something went wrong', 'The application encountered an unexpected problem. Please try again.'],
        default => ['Request error', 'The request could not be completed.'],
    };
}

function render_http_error(int $status, ?string $reference = null): never
{
    [$title, $message] = error_page_content($status);
    $applicationName = (string) app_config('name', 'ResearchFlow Hub');
    $authenticated = function_exists('user_is_authenticated') && user_is_authenticated();
    $destination = $authenticated ? app_url('dashboard.php') : app_url('login.php');
    $destinationLabel = $authenticated ? 'Return to dashboard' : 'Go to login';

    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: text/html; charset=UTF-8');
    }
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e((string) $status . ' — ' . $title) ?> | <?= e($applicationName) ?></title>
    <link rel="stylesheet" href="<?= e(app_url('assets/css/auth.css')) ?>">
</head>
<body>
<main class="auth-shell">
    <section class="auth-card error-card" aria-labelledby="error-title">
        <a class="brand" href="<?= e($destination) ?>"><?= e($applicationName) ?></a>
        <p class="error-code" aria-hidden="true"><?= e($status) ?></p>
        <h1 id="error-title"><?= e($title) ?></h1>
        <p class="error-message"><?= e($message) ?></p>
        <?php if ($reference !== null && $reference !== ''): ?>
            <p class="error-reference">Reference: <?= e($reference) ?></p>
        <?php endif; ?>
        <a class="button error-action" href="<?= e($destination) ?>"><?= e($destinationLabel) ?></a>
    </section>
</main>
</body>
</html>
    <?php
    exit;
}

function application_error_reference(): string
{
    try {
        return strtoupper(bin2hex(random_bytes(4)));
    } catch (Throwable $exception) {
        return strtoupper(substr(hash('sha256', uniqid('', true)), 0, 8));
    }
}

function handle_uncaught_application_exception(Throwable $exception): never
{
    $reference = application_error_reference();
    error_log(sprintf(
        '[ResearchFlow Hub %s] Uncaught %s (code %s) in %s:%d: %s',
        $reference,
        $exception::class,
        (string) $exception->getCode(),
        $exception->getFile(),
        $exception->getLine(),
        $exception->getMessage()
    ));

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    render_http_error(500, $reference);
}

function handle_application_error(int $severity, string $message, string $file, int $line): bool
{
    if ((error_reporting() & $severity) === 0) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
}

function handle_fatal_application_error(): void
{
    $error = error_get_last();
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];

    if (!is_array($error) || !in_array($error['type'] ?? null, $fatalTypes, true)) {
        return;
    }

    $reference = application_error_reference();
    error_log(sprintf(
        '[ResearchFlow Hub %s] Fatal error in %s:%d: %s',
        $reference,
        (string) ($error['file'] ?? 'unknown'),
        (int) ($error['line'] ?? 0),
        (string) ($error['message'] ?? 'Unknown fatal error')
    ));

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    render_http_error(500, $reference);
}

function register_application_error_handlers(): void
{
    if (PHP_SAPI !== 'cli' && ob_get_level() === 0) {
        ob_start();
    }

    set_error_handler('handle_application_error');
    set_exception_handler('handle_uncaught_application_exception');
    register_shutdown_function('handle_fatal_application_error');
}
