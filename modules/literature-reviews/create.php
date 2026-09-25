<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection, $user] = review_page_context();
$projects = review_projects($connection, $userId);
$papers = review_papers($connection, $userId);
$values = review_form_values(['project_id' => $_GET['project_id'] ?? '', 'paper_id' => $_GET['paper_id'] ?? '']);
$errors = [];

if (is_post_request()) {
    $values = review_form_values($_POST);
    try {
        require_valid_csrf_token();
    } catch (RuntimeException $exception) {
        $errors[] = 'Your form session expired. Please try again.';
    }
    $errors = array_merge($errors, validate_review_values($values, $connection, $userId));

    if ($errors === []) {
        $statement = $connection->prepare(
            'INSERT INTO literature_reviews
                (user_id, project_id, paper_id, research_objective, dataset_used,
                 dataset_size, methodology, models_used, preprocessing, metrics,
                 main_results, key_findings, strengths, limitations, future_work,
                 researcher_notes)
             VALUES
                (:user_id, :project_id, :paper_id, :research_objective, :dataset_used,
                 :dataset_size, :methodology, :models_used, :preprocessing, :metrics,
                 :main_results, :key_findings, :strengths, :limitations, :future_work,
                 :researcher_notes)'
        );
        $statement->execute(review_database_values($values, $userId));
        $reviewId = (int) $connection->lastInsertId();
        flash_message('success', 'Literature review added successfully.');
        redirect(app_url('modules/literature-reviews/show.php?id=' . $reviewId));
    }
}

render_app_page_start('Add Literature Review', $user, 'reviews');
render_app_feedback();
?>
<section class="page-heading page-heading-compact"><div><p class="page-kicker">Literature Reviews</p><h2>Add a review</h2><p>Capture structured evidence and observations from a saved paper.</p></div></section>
<section class="form-panel review-form-panel"><?php render_review_errors($errors); ?><form method="post" action="<?= e(app_url('modules/literature-reviews/create.php')) ?>" novalidate><?= csrf_input() ?><?php render_review_form($values, $projects, $papers, 'Add review'); ?></form></section>
<?php render_app_page_end(); ?>
