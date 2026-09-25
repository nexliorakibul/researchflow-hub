<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection, $user] = dataset_page_context();
$datasetId = dataset_request_id();
$dataset = find_owned_dataset($connection, $datasetId, $userId);

if ($dataset === null) {
    render_dataset_not_found($user);
}

$projects = dataset_projects($connection, $userId);
$values = dataset_form_values($dataset);
$errors = [];

if (is_post_request()) {
    $values = dataset_form_values($_POST);
    try {
        require_valid_csrf_token();
    } catch (RuntimeException $exception) {
        $errors[] = 'Your form session expired. Please try again.';
    }
    $errors = array_merge($errors, validate_dataset_values($values, $connection, $userId));

    if ($errors === []) {
        $statement = $connection->prepare(
            'UPDATE datasets SET project_id = :project_id, name = :name,
                domain = :domain, source = :source, url = :url,
                row_count = :row_count, column_count = :column_count,
                image_count = :image_count, class_count = :class_count,
                file_size = :file_size, license = :license,
                access_type = :access_type, description = :description,
                status = :status, notes = :notes
             WHERE id = :id AND user_id = :user_id'
        );
        $parameters = dataset_database_values($values, $userId);
        $parameters['id'] = $datasetId;
        $statement->execute($parameters);
        flash_message('success', 'Dataset updated successfully.');
        redirect(app_url('modules/datasets/show.php?id=' . $datasetId));
    }
}

render_app_page_start('Edit Dataset', $user, 'datasets');
render_app_feedback();
?>
<section class="page-heading page-heading-compact"><div><p class="page-kicker">Dataset Manager</p><h2>Edit dataset</h2><p>Update metadata, access details, status, and notes.</p></div></section>
<section class="form-panel"><?php render_dataset_errors($errors); ?><form method="post" action="<?= e(app_url('modules/datasets/edit.php?id=' . $datasetId)) ?>" novalidate><?= csrf_input() ?><?php render_dataset_form($values, $projects, 'Save changes'); ?></form></section>
<?php render_app_page_end(); ?>
