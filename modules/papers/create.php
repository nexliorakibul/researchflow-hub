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
        $statement = $connection->prepare(
            'INSERT INTO papers
                (user_id, project_id, title, authors, publication_year, venue,
                 volume, issue, pages, doi, url, research_area, keywords,
                 summary, reading_status, personal_notes)
             VALUES
                (:user_id, :project_id, :title, :authors, :publication_year, :venue,
                 :volume, :issue, :pages, :doi, :url, :research_area, :keywords,
                 :summary, :reading_status, :personal_notes)'
        );
        $statement->execute([
            'user_id' => $userId,
            'project_id' => $values['project_id'] !== '' ? (int) $values['project_id'] : null,
            'title' => $values['title'],
            'authors' => $values['authors'] !== '' ? $values['authors'] : null,
            'publication_year' => $values['publication_year'] !== '' ? (int) $values['publication_year'] : null,
            'venue' => $values['venue'] !== '' ? $values['venue'] : null,
            'volume' => $values['volume'] !== '' ? $values['volume'] : null,
            'issue' => $values['issue'] !== '' ? $values['issue'] : null,
            'pages' => $values['pages'] !== '' ? $values['pages'] : null,
            'doi' => $values['doi'] !== '' ? $values['doi'] : null,
            'url' => $values['url'] !== '' ? $values['url'] : null,
            'research_area' => $values['research_area'] !== '' ? $values['research_area'] : null,
            'keywords' => $values['keywords'] !== '' ? $values['keywords'] : null,
            'summary' => $values['summary'] !== '' ? $values['summary'] : null,
            'reading_status' => $values['reading_status'],
            'personal_notes' => $values['personal_notes'] !== '' ? $values['personal_notes'] : null,
        ]);

        $paperId = (int) $connection->lastInsertId();
        flash_message('success', 'Research paper added successfully.');
        redirect(app_url('modules/papers/show.php?id=' . $paperId));
    }
}

render_app_page_start('Add Research Paper', $user, 'papers');
render_app_feedback();
?>
<section class="page-heading page-heading-compact">
    <div><p class="page-kicker">Research Papers</p><h2>Add a paper</h2><p>Save citation-ready metadata and your reading notes.</p></div>
</section>
<section class="form-panel">
    <?php render_paper_errors($errors); ?>
    <form method="post" action="<?= e(app_url('modules/papers/create.php')) ?>" novalidate>
        <?= csrf_input() ?>
        <?php render_paper_form($values, $projects, 'Add paper'); ?>
    </form>
</section>
<?php render_app_page_end(); ?>
