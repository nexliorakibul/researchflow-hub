<?php

declare(strict_types=1);

function render_auth_page_start(string $title): void
{
    $applicationName = (string) app_config('name', 'ResearchFlow Hub');
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> | <?= e($applicationName) ?></title>
    <link rel="stylesheet" href="<?= e(app_url('assets/css/auth.css')) ?>">
</head>
<body>
<main class="auth-shell">
    <section class="auth-card" aria-labelledby="page-title">
        <a class="brand" href="<?= e(app_url()) ?>"><?= e($applicationName) ?></a>
        <h1 id="page-title"><?= e($title) ?></h1>
    <?php
}

function render_auth_feedback(array $errors = []): void
{
    foreach (pull_flash_messages() as $message) {
        $type = in_array($message['type'] ?? '', ['success', 'error', 'warning', 'info'], true)
            ? $message['type']
            : 'info';
        ?>
        <div class="alert alert-<?= e($type) ?>" role="<?= in_array($type, ['error', 'warning'], true) ? 'alert' : 'status' ?>">
            <?= e($message['message'] ?? '') ?>
        </div>
        <?php
    }

    if ($errors !== []) {
        ?>
        <div class="alert alert-error" role="alert">
            <p>Please correct the following:</p>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
    }
}

function render_auth_page_end(): void
{
    ?>
    </section>
</main>
</body>
</html>
    <?php
}
