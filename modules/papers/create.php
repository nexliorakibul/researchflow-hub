<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection, $user] = paper_page_context();
$projects = paper_projects($connection, $userId);
$values = paper_form_values(['project_id' => $_GET['project_id'] ?? '']);
$errors = [];

if (is_post_request()) {
    $values = paper_form_values($_POST);

    try {
        require_valid_csrf_token();
    } catch (RuntimeException $exception) {
        $errors[] = 'Your form session expired. Please try again.';
    }

    $errors = array_merge($errors, validate_paper_values($values, $connection, $userId));

    if ($errors === []) {
        $paperId = create_owned_paper($connection, $userId, $values);
        flash_message('success', 'Research paper added successfully.');
        redirect(app_url('modules/papers/show.php?id=' . $paperId));
    }
}

render_app_page_start('Add Research Paper', $user, 'papers');
render_app_feedback();
?>
<section class="page-heading page-heading-compact">
    <div><p class="page-kicker">Research Papers</p><h2>Add a paper</h2><p>Save citation-ready metadata and your reading notes.</p></div>
    <div class="hero-actions"><a class="secondary-button" href="<?= e(app_url('modules/papers/import-doi.php')) ?>">Import DOI</a><a class="secondary-button" href="<?= e(app_url('modules/papers/discover.php')) ?>">Search OpenAlex</a></div>
</section>
<section class="form-panel">
    <?php render_paper_errors($errors); ?>
    <form method="post" action="<?= e(app_url('modules/papers/create.php')) ?>" novalidate>
        <?= csrf_input() ?>
        <?php render_paper_form($values, $projects, 'Add paper'); ?>
    </form>
</section>
<?php render_app_page_end(); ?>
