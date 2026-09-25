<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection, $user] = note_page_context();
$noteId = note_request_id();
$note = find_owned_note($connection, $noteId, $userId);
if ($note === null) {
    render_note_not_found($user);
}

render_app_page_start('Research Note Details', $user, 'notes');
render_app_feedback();
?>
<nav class="breadcrumb-nav" aria-label="Breadcrumb"><a href="<?= e(app_url('modules/notes/index.php')) ?>">Research Notes</a><span aria-hidden="true">/</span><span aria-current="page"><?= e($note['title']) ?></span></nav>

<section class="note-hero">
    <div><div class="project-hero-meta"><span class="note-type-badge note-type-<?= e($note['note_type']) ?>"><?= e(note_label($note['note_type'])) ?></span><span><?= e(note_date_label($note['created_at'])) ?></span></div><h2><?= e($note['title']) ?></h2><p>Project: <a href="<?= e(app_url('modules/projects/show.php?id=' . $note['project_id'])) ?>"><?= e($note['project_title']) ?></a></p></div>
    <div class="hero-actions"><a class="secondary-button" href="<?= e(app_url('modules/notes/edit.php?id=' . $noteId)) ?>">Edit</a><form method="post" action="<?= e(app_url('modules/notes/delete.php')) ?>" data-confirm="Delete this research note? This cannot be undone."><?= csrf_input() ?><input type="hidden" name="id" value="<?= e($noteId) ?>"><button class="danger-button" type="submit">Delete</button></form></div>
</section>

<section class="dashboard-panel note-content-panel" aria-labelledby="note-content-title"><div class="panel-heading"><h2 id="note-content-title">Note content</h2><p>Last updated <?= e(note_date_label($note['updated_at'])) ?></p></div><div class="long-copy note-long-copy"><?= nl2br(e($note['content'])) ?></div></section>
<?php render_app_page_end(); ?>
