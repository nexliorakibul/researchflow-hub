<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$applicationName = (string) app_config('name', 'ResearchFlow Hub');
$primaryUrl = user_is_authenticated() ? app_url('dashboard.php') : app_url('register.php');
$primaryLabel = user_is_authenticated() ? 'Open dashboard' : 'Get started';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="ResearchFlow Hub helps researchers organize projects, resources, literature reviews, research gaps, experiments, tasks, notes, and citations.">
    <title><?= e($applicationName) ?> | Research Management Workspace</title>
    <link rel="stylesheet" href="<?= e(app_url('assets/css/home.css')) ?>">
</head>
<body>
<header class="home-header">
    <a class="home-brand" href="<?= e(app_url()) ?>" aria-label="ResearchFlow Hub home"><span aria-hidden="true">RF</span><?= e($applicationName) ?></a>
    <nav aria-label="Public navigation">
        <?php if (user_is_authenticated()): ?>
            <a href="<?= e(app_url('dashboard.php')) ?>">Dashboard</a>
        <?php else: ?>
            <a href="<?= e(app_url('login.php')) ?>">Sign in</a>
            <a class="nav-action" href="<?= e(app_url('register.php')) ?>">Create account</a>
        <?php endif; ?>
    </nav>
</header>

<main>
    <section class="hero" aria-labelledby="hero-title">
        <div class="hero-copy">
            <p class="eyebrow">Integrated research workspace</p>
            <h1 id="hero-title">Your Complete Research Management Workspace</h1>
            <p class="hero-lead">Organize research projects, manage academic resources, review literature, identify research gaps and track machine learning experiments - all in one place.</p>
            <div class="hero-actions">
                <a class="primary-action" href="<?= e($primaryUrl) ?>"><?= e($primaryLabel) ?></a>
                <?php if (!user_is_authenticated()): ?><a class="secondary-action" href="<?= e(app_url('login.php')) ?>">Sign in</a><?php endif; ?>
            </div>
        </div>
        <div class="hero-preview" aria-label="Research workflow preview">
            <div class="preview-heading"><span>Research workspace</span><strong>Project overview</strong></div>
            <div class="preview-metrics"><div><strong>12</strong><span>Papers</span></div><div><strong>4</strong><span>Datasets</span></div><div><strong>8</strong><span>Experiments</span></div></div>
            <div class="preview-progress"><span>Research progress</span><strong>72%</strong><div><i></i></div></div>
            <div class="preview-list"><span>Literature review</span><span>Research gaps</span><span>Experiment comparison</span></div>
        </div>
    </section>

    <section class="feature-section" aria-labelledby="features-title">
        <p class="eyebrow">One focused system</p>
        <h2 id="features-title">Everything needed for a structured research workflow</h2>
        <div class="feature-grid">
            <article><span aria-hidden="true">01</span><h3>Project workspace</h3><p>Plan projects and connect every research record to the right study.</p></article>
            <article><span aria-hidden="true">02</span><h3>Research library</h3><p>Organize resources, papers, datasets, and structured literature reviews.</p></article>
            <article><span aria-hidden="true">03</span><h3>Gap tracking</h3><p>Capture evidence-backed research gaps, priorities, and potential solutions.</p></article>
            <article><span aria-hidden="true">04</span><h3>Experiment records</h3><p>Store configurations and compare recorded model performance.</p></article>
            <article><span aria-hidden="true">05</span><h3>Tasks and notes</h3><p>Track deadlines, progress, ideas, meetings, and observations.</p></article>
            <article><span aria-hidden="true">06</span><h3>Search and citations</h3><p>Find saved research quickly and generate IEEE, APA, or Harvard citations.</p></article>
        </div>
    </section>

    <section class="workflow-section" aria-labelledby="workflow-title">
        <div><p class="eyebrow">How it works</p><h2 id="workflow-title">Move from research idea to evidence and results</h2></div>
        <ol><li><strong>Create a project</strong><span>Define the topic, area, dates, and status.</span></li><li><strong>Build the evidence base</strong><span>Add resources, papers, datasets, and literature reviews.</span></li><li><strong>Track progress</strong><span>Identify gaps, record experiments, and complete tasks.</span></li></ol>
    </section>
</main>

<footer class="home-footer"><span>&copy; <?= e(date('Y')) ?> <?= e($applicationName) ?></span><span>Built for focused academic research</span></footer>
</body>
</html>
