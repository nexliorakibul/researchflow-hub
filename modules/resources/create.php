<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection, $user] = resource_page_context();
$projects = resource_projects($connection, $userId);
$values = resource_form_values([
    'project_id' => $_GET['project_id'] ?? '',
]);
$errors = [];

if (is_post_request()) {
    $values = resource_form_values($_POST);

    try {
        require_valid_csrf_token();
    } catch (RuntimeException $exception) {
        $errors[] = 'Your form session expired. Please try again.';
    }

    $errors = array_merge($errors, validate_resource_values($values, $connection, $userId));

    if ($errors === []) {
        $statement = $connection->prepare(
            'INSERT INTO resources
                (user_id, project_id, title, resource_type, research_area, authors,
                 publication_year, url, doi, description, personal_notes, tags,
                 status, is_favorite)
             VALUES
                (:user_id, :project_id, :title, :resource_type, :research_area, :authors,
                 :publication_year, :url, :doi, :description, :personal_notes, :tags,
                 :status, :is_favorite)'
        );
        $statement->execute([
            'user_id' => $userId,
            'project_id' => $values['project_id'] !== '' ? (int) $values['project_id'] : null,
            'title' => $values['title'],
            'resource_type' => $values['resource_type'],
            'research_area' => $values['research_area'] !== '' ? $values['research_area'] : null,
            'authors' => $values['authors'] !== '' ? $values['authors'] : null,
            'publication_year' => $values['publication_year'] !== '' ? (int) $values['publication_year'] : null,
            'url' => $values['url'] !== '' ? $values['url'] : null,
            'doi' => $values['doi'] !== '' ? $values['doi'] : null,
            'description' => $values['description'] !== '' ? $values['description'] : null,
            'personal_notes' => $values['personal_notes'] !== '' ? $values['personal_notes'] : null,
            'tags' => $values['tags'] !== '' ? $values['tags'] : null,
            'status' => $values['status'],
            'is_favorite' => $values['is_favorite'] ? 1 : 0,
        ]);

        $resourceId = (int) $connection->lastInsertId();
        flash_message('success', 'Research resource added successfully.');
        redirect(app_url('modules/resources/show.php?id=' . $resourceId));
    }
}

render_app_page_start('Add Research Resource', $user, 'resources');
render_app_feedback();
?>
<section class="page-heading page-heading-compact">
    <div>
        <p class="page-kicker">Research Resources</p>
        <h2>Add a resource</h2>
        <p>Save academic material and optionally connect it to one of your projects.</p>
    </div>
</section>

<section class="form-panel">
    <?php render_resource_errors($errors); ?>
    <form method="post" action="<?= e(app_url('modules/resources/create.php')) ?>" novalidate>
        <?= csrf_input() ?>
        <?php render_resource_form($values, $projects, 'Add resource'); ?>
    </form>
</section>
<?php render_app_page_end(); ?>
