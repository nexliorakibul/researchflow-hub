<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection, $user] = gap_page_context();
$gapId = gap_request_id();
$gap = find_owned_gap($connection, $gapId, $userId);
if ($gap === null) {
    render_gap_not_found($user);
}
$projects = gap_projects($connection, $userId);
$papers = gap_papers($connection, $userId);
$values = gap_form_values($gap);
$errors = [];

if (is_post_request()) {
    $values = gap_form_values($_POST);
    try {
        require_valid_csrf_token();
    } catch (RuntimeException $exception) {
        $errors[] = 'Your form session expired. Please try again.';
    }
    $errors = array_merge($errors, validate_gap_values($values, $connection, $userId));
    if ($errors === []) {
        $statement = $connection->prepare(
            'UPDATE research_gaps SET project_id = :project_id, paper_id = :paper_id,
                gap_title = :gap_title, gap_type = :gap_type, description = :description,
                evidence = :evidence, potential_solution = :potential_solution,
                priority = :priority, status = :status, notes = :notes
             WHERE id = :id AND user_id = :user_id'
        );
        $parameters = gap_database_values($values, $userId);
        $parameters['id'] = $gapId;
        $statement->execute($parameters);
        flash_message('success', 'Research gap updated successfully.');
        redirect(app_url('modules/research-gaps/show.php?id=' . $gapId));
    }
}

render_app_page_start('Edit Research Gap', $user, 'gaps');
render_app_feedback();
?>
<section class="page-heading page-heading-compact"><div><p class="page-kicker">Research Gaps</p><h2>Edit research gap</h2><p>Update the gap evidence, priority, status, and proposed solution.</p></div></section>
<section class="form-panel"><?php render_gap_errors($errors); ?><form method="post" action="<?= e(app_url('modules/research-gaps/edit.php?id=' . $gapId)) ?>" novalidate><?= csrf_input() ?><?php render_gap_form($values, $projects, $papers, 'Save changes'); ?></form></section>
<?php render_app_page_end(); ?>
