<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection, $user] = dataset_page_context();
$projects = dataset_projects($connection, $userId);
$values = dataset_form_values(['project_id' => $_GET['project_id'] ?? '']);
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
            'INSERT INTO datasets
                (user_id, project_id, name, domain, source, url, row_count,
                 column_count, image_count, class_count, file_size, license,
                 access_type, description, status, notes)
             VALUES
                (:user_id, :project_id, :name, :domain, :source, :url, :row_count,
                 :column_count, :image_count, :class_count, :file_size, :license,
                 :access_type, :description, :status, :notes)'
        );
        $statement->execute(dataset_database_values($values, $userId));
        $datasetId = (int) $connection->lastInsertId();
        flash_message('success', 'Dataset added successfully.');
        redirect(app_url('modules/datasets/show.php?id=' . $datasetId));
    }
}

render_app_page_start('Add Dataset', $user, 'datasets');
render_app_feedback();
?>
<section class="page-heading page-heading-compact"><div><p class="page-kicker">Dataset Manager</p><h2>Add a dataset</h2><p>Store dataset metadata and links without uploading large files.</p></div></section>
<section class="form-panel"><?php render_dataset_errors($errors); ?><form method="post" action="<?= e(app_url('modules/datasets/create.php')) ?>" novalidate><?= csrf_input() ?><?php render_dataset_form($values, $projects, 'Add dataset'); ?></form></section>
<?php render_app_page_end(); ?>
