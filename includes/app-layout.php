<?php

declare(strict_types=1);

function render_app_page_start(string $title, array $user, string $activePage = ''): void
{
    $applicationName = (string) app_config('name', 'ResearchFlow Hub');
    $navigationGroups = [
        'Workspace' => [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => app_url('dashboard.php'), 'icon' => 'grid'],
            ['key' => 'search', 'label' => 'Global Search', 'href' => app_url('search.php'), 'icon' => 'search'],
            ['key' => 'projects', 'label' => 'Research Projects', 'href' => app_url('modules/projects/index.php'), 'icon' => 'folder'],
        ],
        'Research Library' => [
            ['key' => 'resources', 'label' => 'Resources', 'href' => app_url('modules/resources/index.php'), 'icon' => 'bookmark'],
            ['key' => 'papers', 'label' => 'Papers', 'href' => app_url('modules/papers/index.php'), 'icon' => 'file'],
            ['key' => 'datasets', 'label' => 'Datasets', 'href' => app_url('modules/datasets/index.php'), 'icon' => 'database'],
            ['key' => 'reviews', 'label' => 'Literature Reviews', 'href' => app_url('modules/literature-reviews/index.php'), 'icon' => 'journal'],
            ['key' => 'review-matrix', 'label' => 'Review Matrix', 'href' => app_url('modules/literature-reviews/matrix.php'), 'icon' => 'grid'],
        ],
        'Research Progress' => [
            ['key' => 'gaps', 'label' => 'Research Gaps', 'href' => app_url('modules/research-gaps/index.php'), 'icon' => 'lightbulb'],
            ['key' => 'gap-statistics', 'label' => 'Gap Statistics', 'href' => app_url('modules/research-gaps/statistics.php'), 'icon' => 'grid'],
            ['key' => 'experiments', 'label' => 'Experiments', 'href' => app_url('modules/experiments/index.php'), 'icon' => 'beaker'],
            ['key' => 'experiment-comparison', 'label' => 'Experiment Comparison', 'href' => app_url('modules/experiments/compare.php'), 'icon' => 'grid'],
            ['key' => 'tasks', 'label' => 'Tasks', 'href' => app_url('modules/tasks/index.php'), 'icon' => 'check'],
            ['key' => 'notes', 'label' => 'Notes', 'href' => app_url('modules/notes/index.php'), 'icon' => 'note'],
        ],
        'Account' => [
            ['key' => 'profile', 'label' => 'User Profile', 'href' => app_url('profile.php'), 'icon' => 'user'],
            ['key' => 'password', 'label' => 'Change Password', 'href' => app_url('change-password.php'), 'icon' => 'lock'],
        ],
    ];
    $userName = trim((string) ($user['name'] ?? 'Researcher'));
    $userEmail = trim((string) ($user['email'] ?? ''));
    $initial = function_exists('mb_substr')
        ? mb_strtoupper(mb_substr($userName, 0, 1, 'UTF-8'), 'UTF-8')
        : strtoupper(substr($userName, 0, 1));
    $headerQuery = $activePage === 'search' && isset($_GET['q']) && is_string($_GET['q'])
        ? trim($_GET['q'])
        : '';
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> | <?= e($applicationName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= e(app_url('assets/css/app.css')) ?>">
</head>
<body class="app-body">
<div class="app-shell">
    <aside class="app-sidebar" id="app-sidebar" aria-label="Main navigation">
        <div class="sidebar-brand">
            <span class="brand-mark" aria-hidden="true">RF</span>
            <span>
                <strong><?= e($applicationName) ?></strong>
                <small>Research workspace</small>
            </span>
        </div>

        <nav class="sidebar-navigation">
            <?php foreach ($navigationGroups as $groupLabel => $items): ?>
                <div class="navigation-group">
                    <p class="navigation-label"><?= e($groupLabel) ?></p>
                    <?php foreach ($items as $item): ?>
                        <?php if (is_string($item['href'])): ?>
                            <a class="navigation-link<?= $activePage === $item['key'] ? ' is-active' : '' ?>"
                               href="<?= e($item['href']) ?>"
                               <?= $activePage === $item['key'] ? 'aria-current="page"' : '' ?>>
                                <span class="navigation-icon icon-<?= e($item['icon']) ?>" aria-hidden="true"></span>
                                <span><?= e($item['label']) ?></span>
                            </a>
                        <?php else: ?>
                            <span class="navigation-link is-disabled" aria-disabled="true"
                                  title="This module will be enabled in a later step">
                                <span class="navigation-icon icon-<?= e($item['icon']) ?>" aria-hidden="true"></span>
                                <span><?= e($item['label']) ?></span>
                            </span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-user">
            <span class="user-avatar" aria-hidden="true"><?= e($initial !== '' ? $initial : 'R') ?></span>
            <span class="user-details">
                <strong><?= e($userName) ?></strong>
                <small><?= e($userEmail) ?></small>
            </span>
        </div>
    </aside>

    <button class="sidebar-overlay" type="button" data-sidebar-close aria-label="Close navigation"></button>

    <div class="app-content">
        <header class="app-header">
            <div class="header-title-group">
                <button class="menu-button" type="button" data-sidebar-toggle
                        aria-controls="app-sidebar" aria-expanded="false">
                    <span aria-hidden="true"></span>
                    <span class="visually-hidden">Open navigation</span>
                </button>
                <div>
                    <p class="page-eyebrow">ResearchFlow Hub</p>
                    <h1><?= e($title) ?></h1>
                </div>
            </div>

            <form class="header-search" method="get" action="<?= e(app_url('search.php')) ?>" role="search">
                <label class="visually-hidden" for="header-global-search">Search all research records</label>
                <input id="header-global-search" name="q" type="search" value="<?= e($headerQuery) ?>" maxlength="100" placeholder="Search workspace" required>
                <button type="submit">Search</button>
            </form>

            <form method="post" action="<?= e(app_url('logout.php')) ?>">
                <?= csrf_input() ?>
                <button class="logout-button" type="submit">Log out</button>
            </form>
        </header>

        <main class="app-main">
    <?php
}

function render_app_feedback(): void
{
    foreach (pull_flash_messages() as $message) {
        $type = in_array($message['type'] ?? '', ['success', 'error', 'warning', 'info'], true)
            ? (string) $message['type']
            : 'info';
        ?>
        <div class="app-alert app-alert-<?= e($type) ?>" role="<?= in_array($type, ['error', 'warning'], true) ? 'alert' : 'status' ?>">
            <?= e($message['message'] ?? '') ?>
        </div>
        <?php
    }
}

function render_empty_state(string $message): void
{
    ?>
    <div class="empty-state">
        <span aria-hidden="true">&#9675;</span>
        <p><?= e($message) ?></p>
    </div>
    <?php
}

function render_app_page_end(): void
{
    ?>
        </main>
        <footer class="app-footer">
            <span>&copy; <?= e(date('Y')) ?> ResearchFlow Hub</span>
            <span>Built for focused academic research</span>
        </footer>
    </div>
</div>
<script src="<?= e(app_url('assets/js/app.js')) ?>" defer></script>
</body>
</html>
    <?php
}
