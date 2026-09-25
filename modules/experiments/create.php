<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection, $user] = experiment_page_context();
$projects = experiment_projects($connection, $userId);
$datasets = experiment_datasets($connection, $userId);
$values = experiment_form_values([
    'project_id' => $_GET['project_id'] ?? '',
    'dataset_id' => $_GET['dataset_id'] ?? '',
    'experiment_date' => date('Y-m-d'),
]);
$errors = [];

if (is_post_request()) {
    $values = experiment_form_values($_POST);
    try {
        require_valid_csrf_token();
    } catch (RuntimeException $exception) {
        $errors[] = 'Your form session expired. Please try again.';
    }
    $errors = array_merge($errors, validate_experiment_values($values, $connection, $userId));
    if ($errors === []) {
        $statement = $connection->prepare(
            'INSERT INTO experiments
                (user_id, project_id, dataset_id, experiment_name, model_name, model_type,
                 preprocessing, feature_engineering, train_split, validation_split, test_split,
                 random_seed, learning_rate, batch_size, epochs, optimizer, loss_function,
                 accuracy, precision_score, recall_score, f1_score, auroc, auprc, specificity,
                 notes, experiment_date)
             VALUES
                (:user_id, :project_id, :dataset_id, :experiment_name, :model_name, :model_type,
                 :preprocessing, :feature_engineering, :train_split, :validation_split, :test_split,
                 :random_seed, :learning_rate, :batch_size, :epochs, :optimizer, :loss_function,
                 :accuracy, :precision_score, :recall_score, :f1_score, :auroc, :auprc, :specificity,
                 :notes, :experiment_date)'
        );
        $statement->execute(experiment_database_values($values, $userId));
        $experimentId = (int) $connection->lastInsertId();
        flash_message('success', 'Experiment added successfully.');
        redirect(app_url('modules/experiments/show.php?id=' . $experimentId));
    }
}

render_app_page_start('Add Experiment', $user, 'experiments');
render_app_feedback();
?>
<section class="page-heading page-heading-compact"><div><p class="page-kicker">Experiments</p><h2>Add an experiment</h2><p>Record a model configuration, dataset, training setup, and optional evaluation metrics.</p></div></section>
<section class="form-panel experiment-form-panel"><?php render_experiment_errors($errors); ?><form method="post" action="<?= e(app_url('modules/experiments/create.php')) ?>" novalidate><?= csrf_input() ?><?php render_experiment_form($values, $projects, $datasets, 'Add experiment'); ?></form></section>
<?php render_app_page_end(); ?>
