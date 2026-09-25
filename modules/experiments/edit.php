<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection, $user] = experiment_page_context();
$experimentId = experiment_request_id();
$experiment = find_owned_experiment($connection, $experimentId, $userId);
if ($experiment === null) {
    render_experiment_not_found($user);
}
$projects = experiment_projects($connection, $userId);
$datasets = experiment_datasets($connection, $userId);
$values = experiment_form_values($experiment);
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
            'UPDATE experiments SET
                project_id = :project_id, dataset_id = :dataset_id,
                experiment_name = :experiment_name, model_name = :model_name, model_type = :model_type,
                preprocessing = :preprocessing, feature_engineering = :feature_engineering,
                train_split = :train_split, validation_split = :validation_split, test_split = :test_split,
                random_seed = :random_seed, learning_rate = :learning_rate, batch_size = :batch_size,
                epochs = :epochs, optimizer = :optimizer, loss_function = :loss_function,
                accuracy = :accuracy, precision_score = :precision_score, recall_score = :recall_score,
                f1_score = :f1_score, auroc = :auroc, auprc = :auprc, specificity = :specificity,
                notes = :notes, experiment_date = :experiment_date
             WHERE id = :id AND user_id = :user_id'
        );
        $parameters = experiment_database_values($values, $userId);
        $parameters['id'] = $experimentId;
        $statement->execute($parameters);
        flash_message('success', 'Experiment updated successfully.');
        redirect(app_url('modules/experiments/show.php?id=' . $experimentId));
    }
}

render_app_page_start('Edit Experiment', $user, 'experiments');
render_app_feedback();
?>
<section class="page-heading page-heading-compact"><div><p class="page-kicker">Experiments</p><h2>Edit experiment</h2><p>Update the recorded configuration and results.</p></div></section>
<section class="form-panel experiment-form-panel"><?php render_experiment_errors($errors); ?><form method="post" action="<?= e(app_url('modules/experiments/edit.php?id=' . $experimentId)) ?>" novalidate><?= csrf_input() ?><?php render_experiment_form($values, $projects, $datasets, 'Save changes'); ?></form></section>
<?php render_app_page_end(); ?>
