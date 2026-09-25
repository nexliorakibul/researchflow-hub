<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection, $user] = gap_page_context();
$projects = gap_projects($connection, $userId);
$papers = gap_papers($connection, $userId);
$values = gap_form_values(['project_id' => $_GET['project_id'] ?? '', 'paper_id' => $_GET['paper_id'] ?? '']);
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
            'INSERT INTO research_gaps
                (user_id, project_id, paper_id, gap_title, gap_type, description,
                 evidence, potential_solution, priority, status, notes)
             VALUES
                (:user_id, :project_id, :paper_id, :gap_title, :gap_type, :description,
                 :evidence, :potential_solution, :priority, :status, :notes)'
        );
        $statement->execute(gap_database_values($values, $userId));
        $gapId = (int) $connection->lastInsertId();
        flash_message('success', 'Research gap added successfully.');
        redirect(app_url('modules/research-gaps/show.php?id=' . $gapId));
    }
}

render_app_page_start('Add Research Gap', $user, 'gaps');
render_app_feedback();
?>
<section class="page-heading page-heading-compact"><div><p class="page-kicker">Research Gaps</p><h2>Add a research gap</h2><p>Capture evidence, priority, status, and a possible direction for future work.</p></div></section>
<section class="form-panel"><?php render_gap_errors($errors); ?><form method="post" action="<?= e(app_url('modules/research-gaps/create.php')) ?>" novalidate><?= csrf_input() ?><?php render_gap_form($values, $projects, $papers, 'Add gap'); ?></form></section>
<?php render_app_page_end(); ?>
