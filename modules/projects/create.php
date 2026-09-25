<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection, $user] = project_page_context();
$values = project_form_values([]);
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
            'INSERT INTO projects
                (user_id, title, research_area, description, status, start_date, target_date)
             VALUES
                (:user_id, :title, :research_area, :description, :status, :start_date, :target_date)'
        );
        $statement->execute([
            'user_id' => $userId,
            'title' => $values['title'],
            'research_area' => $values['research_area'] !== '' ? $values['research_area'] : null,
            'description' => $values['description'] !== '' ? $values['description'] : null,
            'status' => $values['status'],
            'start_date' => $values['start_date'] !== '' ? $values['start_date'] : null,
            'target_date' => $values['target_date'] !== '' ? $values['target_date'] : null,
        ]);

        $projectId = (int) $connection->lastInsertId();
        flash_message('success', 'Research project created successfully.');
        redirect(app_url('modules/projects/show.php?id=' . $projectId));
    }
}

render_app_page_start('Create Research Project', $user, 'projects');
render_app_feedback();
?>
<section class="page-heading page-heading-compact">
    <div>
        <p class="page-kicker">Research Projects</p>
        <h2>Create a new project</h2>
        <p>Add the essential details now; related research records can be organized later.</p>
    </div>
</section>

<section class="form-panel">
    <?php render_project_errors($errors); ?>
    <form method="post" action="<?= e(app_url('modules/projects/create.php')) ?>" novalidate>
        <?= csrf_input() ?>
        <?php render_project_form($values, 'Create project'); ?>
    </form>
</section>
<?php render_app_page_end(); ?>
