<?php

declare(strict_types=1);

function current_user_id(): ?int
{
    $userId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);

    return $userId === false ? null : $userId;
}

function user_is_authenticated(): bool
{
    return current_user_id() !== null;
}

function login_user(int $userId): void
{
    if ($userId < 1) {
        throw new InvalidArgumentException('User ID must be positive.');
    }

    session_regenerate_id(true);
    $_SESSION = [
        'user_id' => $userId,
        'authenticated_at' => time(),
    ];
    rotate_csrf_token();
}

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $cookie = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            [
                'expires' => time() - 42000,
                'path' => $cookie['path'],
                'domain' => $cookie['domain'],
                'secure' => $cookie['secure'],
                'httponly' => $cookie['httponly'],
                'samesite' => $cookie['samesite'] ?? 'Lax',
            ]
        );
    }

    session_destroy();
    session_start();
    session_regenerate_id(true);
    rotate_csrf_token();
}

function require_authenticated_user(): int
{
    $userId = current_user_id();

    if ($userId === null) {
        flash_message('error', 'Please log in to continue.');
        redirect(app_url('login.php'));
    }

    return $userId;
}

function require_guest_user(): void
{
    if (user_is_authenticated()) {
        redirect(app_url('dashboard.php'));
    }
}

function user_owns_record(PDO $connection, string $table, int $recordId, int $userId): bool
{
    $ownedTables = [
        'projects',
        'resources',
        'papers',
        'datasets',
        'literature_reviews',
        'research_gaps',
        'experiments',
        'tasks',
        'notes',
    ];

    if (!in_array($table, $ownedTables, true)) {
        throw new InvalidArgumentException('Ownership table is not allowed.');
    }

    if ($recordId < 1 || $userId < 1) {
        return false;
    }

    $statement = $connection->prepare(
        sprintf('SELECT 1 FROM `%s` WHERE id = :id AND user_id = :user_id LIMIT 1', $table)
    );
    $statement->execute([
        'id' => $recordId,
        'user_id' => $userId,
    ]);

    return $statement->fetchColumn() !== false;
}
