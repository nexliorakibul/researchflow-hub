<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection, $user] = note_page_context();
$projects = note_projects($connection, $userId);
$values = note_form_values(['project_id' => $_GET['project_id'] ?? '']);
$errors = [];

if (is_post_request()) {
    $values = note_form_values($_POST);
    try {
        require_valid_csrf_token();
    } catch (RuntimeException $exception) {
        $errors[] = 'Your form session expired. Please try again.';
    }
    $errors = array_merge($errors, validate_note_values($values, $connection, $userId));
    if ($errors === []) {
        $statement = $connection->prepare(
            'INSERT INTO notes (user_id, project_id, title, content, note_type)
             VALUES (:user_id, :project_id, :title, :content, :note_type)'
        );
        $statement->execute(note_database_values($values, $userId));
        $noteId = (int) $connection->lastInsertId();
        flash_message('success', 'Research note created successfully.');
        redirect(app_url('modules/notes/show.php?id=' . $noteId));
    }
}

render_app_page_start('Add Research Note', $user, 'notes');
render_app_feedback();
?>
<section class="page-heading page-heading-compact"><div><p class="page-kicker">Research Notes</p><h2>Add a research note</h2><p>Capture a project-linked idea, meeting record, observation, or important detail.</p></div></section>
<section class="form-panel"><?php render_note_errors($errors); ?><form method="post" action="<?= e(app_url('modules/notes/create.php')) ?>"><?= csrf_input() ?><?php render_note_form($values, $projects, 'Add note'); ?></form></section>
<?php render_app_page_end(); ?>
