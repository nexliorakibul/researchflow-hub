<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection, $user] = note_page_context();
$noteId = note_request_id();
$note = find_owned_note($connection, $noteId, $userId);
if ($note === null) {
    render_note_not_found($user);
}
$projects = note_projects($connection, $userId);
$values = note_form_values($note);
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
            'UPDATE notes SET project_id = :project_id, title = :title, content = :content, note_type = :note_type
             WHERE id = :id AND user_id = :user_id'
        );
        $parameters = note_database_values($values, $userId);
        $parameters['id'] = $noteId;
        $statement->execute($parameters);
        flash_message('success', 'Research note updated successfully.');
        redirect(app_url('modules/notes/show.php?id=' . $noteId));
    }
}

render_app_page_start('Edit Research Note', $user, 'notes');
render_app_feedback();
?>
<section class="page-heading page-heading-compact"><div><p class="page-kicker">Research Notes</p><h2>Edit research note</h2><p>Update the note content, project, or type.</p></div></section>
<section class="form-panel"><?php render_note_errors($errors); ?><form method="post" action="<?= e(app_url('modules/notes/edit.php?id=' . $noteId)) ?>"><?= csrf_input() ?><?php render_note_form($values, $projects, 'Save changes'); ?></form></section>
<?php render_app_page_end(); ?>
