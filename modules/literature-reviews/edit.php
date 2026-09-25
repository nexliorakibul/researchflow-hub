<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection, $user] = review_page_context();
$reviewId = review_request_id();
$review = find_owned_review($connection, $reviewId, $userId);
if ($review === null) {
    render_review_not_found($user);
}
$projects = review_projects($connection, $userId);
$papers = review_papers($connection, $userId);
$values = review_form_values($review);
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
            'UPDATE literature_reviews SET project_id = :project_id,
                paper_id = :paper_id, research_objective = :research_objective,
                dataset_used = :dataset_used, dataset_size = :dataset_size,
                methodology = :methodology, models_used = :models_used,
                preprocessing = :preprocessing, metrics = :metrics,
                main_results = :main_results, key_findings = :key_findings,
                strengths = :strengths, limitations = :limitations,
                future_work = :future_work, researcher_notes = :researcher_notes
             WHERE id = :id AND user_id = :user_id'
        );
        $parameters = review_database_values($values, $userId);
        $parameters['id'] = $reviewId;
        $statement->execute($parameters);
        flash_message('success', 'Literature review updated successfully.');
        redirect(app_url('modules/literature-reviews/show.php?id=' . $reviewId));
    }
}

render_app_page_start('Edit Literature Review', $user, 'reviews');
render_app_feedback();
?>
<section class="page-heading page-heading-compact"><div><p class="page-kicker">Literature Reviews</p><h2>Edit review</h2><p>Update the structured evidence and your research notes.</p></div></section>
<section class="form-panel review-form-panel"><?php render_review_errors($errors); ?><form method="post" action="<?= e(app_url('modules/literature-reviews/edit.php?id=' . $reviewId)) ?>" novalidate><?= csrf_input() ?><?php render_review_form($values, $projects, $papers, 'Save changes'); ?></form></section>
<?php render_app_page_end(); ?>
