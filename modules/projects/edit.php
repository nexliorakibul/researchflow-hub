<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection, $user] = project_page_context();
$projectId = project_request_id();
$project = find_owned_project($connection, $projectId, $userId);

if ($project === null) {
    render_project_not_found($user);
}

$values = project_form_values($project);
$errors = [];

if (is_post_request()) {
    $values = project_form_values($_POST);

    try {
        require_valid_csrf_token();
    } catch (RuntimeException $exception) {
        $errors[] = 'Your form session expired. Please try again.';
    }

    $errors = array_merge($errors, validate_project_values($values));

    if ($errors === []) {
        $statement = $connection->prepare(
            'UPDATE projects
             SET title = :title,
                 research_area = :research_area,
                 description = :description,
                 status = :status,
                 start_date = :start_date,
                 target_date = :target_date
             WHERE id = :id AND user_id = :user_id'
        );
        $statement->execute([
            'title' => $values['title'],
            'research_area' => $values['research_area'] !== '' ? $values['research_area'] : null,
            'description' => $values['description'] !== '' ? $values['description'] : null,
            'status' => $values['status'],
            'start_date' => $values['start_date'] !== '' ? $values['start_date'] : null,
            'target_date' => $values['target_date'] !== '' ? $values['target_date'] : null,
            'id' => $projectId,
            'user_id' => $userId,
        ]);

        flash_message('success', 'Research project updated successfully.');
        redirect(app_url('modules/projects/show.php?id=' . $projectId));
    }
}

render_app_page_start('Edit Research Project', $user, 'projects');
render_app_feedback();
?>
<section class="page-heading page-heading-compact">
    <div>
        <p class="page-kicker">Research Projects</p>
        <h2>Edit project</h2>
        <p>Update the project details and research timeline.</p>
    </div>
</section>

<section class="form-panel">
    <?php render_project_errors($errors); ?>
    <form method="post" action="<?= e(app_url('modules/projects/edit.php?id=' . $projectId)) ?>" novalidate>
        <?= csrf_input() ?>
        <?php render_project_form($values, 'Save changes'); ?>
    </form>
</section>
<?php render_app_page_end(); ?>
