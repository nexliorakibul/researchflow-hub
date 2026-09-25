<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection, $user] = resource_page_context();
$resourceId = resource_request_id();
$resource = find_owned_resource($connection, $resourceId, $userId);

if ($resource === null) {
    render_resource_not_found($user);
}

$projects = resource_projects($connection, $userId);
$values = resource_form_values($resource);
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
            'UPDATE resources
             SET project_id = :project_id,
                 title = :title,
                 resource_type = :resource_type,
                 research_area = :research_area,
                 authors = :authors,
                 publication_year = :publication_year,
                 url = :url,
                 doi = :doi,
                 description = :description,
                 personal_notes = :personal_notes,
                 tags = :tags,
                 status = :status,
                 is_favorite = :is_favorite
             WHERE id = :id AND user_id = :user_id'
        );
        $statement->execute([
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
            'id' => $resourceId,
            'user_id' => $userId,
        ]);

        flash_message('success', 'Research resource updated successfully.');
        redirect(app_url('modules/resources/show.php?id=' . $resourceId));
    }
}

render_app_page_start('Edit Research Resource', $user, 'resources');
render_app_feedback();
?>
<section class="page-heading page-heading-compact">
    <div>
        <p class="page-kicker">Research Resources</p>
        <h2>Edit resource</h2>
        <p>Update the saved metadata, status, project, and personal notes.</p>
    </div>
</section>

<section class="form-panel">
    <?php render_resource_errors($errors); ?>
    <form method="post" action="<?= e(app_url('modules/resources/edit.php?id=' . $resourceId)) ?>" novalidate>
        <?= csrf_input() ?>
        <?php render_resource_form($values, $projects, 'Save changes'); ?>
    </form>
</section>
<?php render_app_page_end(); ?>
