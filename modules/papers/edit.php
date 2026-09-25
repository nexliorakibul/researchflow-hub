<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection, $user] = paper_page_context();
$paperId = paper_request_id();
$paper = find_owned_paper($connection, $paperId, $userId);

if ($paper === null) {
    render_paper_not_found($user);
}

$projects = paper_projects($connection, $userId);
$values = paper_form_values($paper);
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
            'UPDATE papers SET
                project_id = :project_id, title = :title, authors = :authors,
                publication_year = :publication_year, venue = :venue, volume = :volume,
                issue = :issue, pages = :pages, doi = :doi, url = :url,
                research_area = :research_area, keywords = :keywords, summary = :summary,
                reading_status = :reading_status, personal_notes = :personal_notes
             WHERE id = :id AND user_id = :user_id'
        );
        $statement->execute([
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
            'id' => $paperId,
            'user_id' => $userId,
        ]);

        flash_message('success', 'Research paper updated successfully.');
        redirect(app_url('modules/papers/show.php?id=' . $paperId));
    }
}

render_app_page_start('Edit Research Paper', $user, 'papers');
render_app_feedback();
?>
<section class="page-heading page-heading-compact">
    <div><p class="page-kicker">Research Papers</p><h2>Edit paper</h2><p>Update the paper metadata, reading status, and notes.</p></div>
</section>
<section class="form-panel">
    <?php render_paper_errors($errors); ?>
    <form method="post" action="<?= e(app_url('modules/papers/edit.php?id=' . $paperId)) ?>" novalidate>
        <?= csrf_input() ?>
        <?php render_paper_form($values, $projects, 'Save changes'); ?>
    </form>
</section>
<?php render_app_page_end(); ?>
