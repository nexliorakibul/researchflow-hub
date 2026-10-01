<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';
require_once dirname(__DIR__, 2) . '/includes/paper-exports.php';

[$userId, $connection] = paper_page_context();
$format = strtolower(query_string('format'));

if (!in_array($format, ['bibtex', 'ris'], true)) {
    http_response_code(400);
    exit('Unsupported export format.');
}

$rawPaperId = $_GET['id'] ?? null;
$paperId = filter_var($rawPaperId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($rawPaperId !== null && $paperId === false) {
    http_response_code(400);
    exit('Invalid paper ID.');
}
if ($paperId !== false && $paperId !== null) {
    $paper = find_owned_paper($connection, (int) $paperId, $userId);
    if ($paper === null) {
        http_response_code(404);
        exit('Paper not found.');
    }
    $papers = [$paper];
    $fileBase = 'researchflow-paper-' . (int) $paperId;
} else {
    $statement = $connection->prepare(
        'SELECT id, title, authors, publication_year, venue, volume, issue, pages,
                doi, url, keywords, summary
         FROM papers WHERE user_id = :user_id ORDER BY title ASC, id ASC'
    );
    $statement->execute(['user_id' => $userId]);
    $papers = $statement->fetchAll();
    $fileBase = 'researchflow-papers-' . date('Y-m-d');
}

if ($papers === []) {
    flash_message('warning', 'Add at least one paper before exporting your library.');
    redirect(app_url('modules/papers/index.php'));
}

$extension = $format === 'bibtex' ? 'bib' : 'ris';
$contentType = $format === 'bibtex'
    ? 'application/x-bibtex; charset=UTF-8'
    : 'application/x-research-info-systems; charset=UTF-8';

header('Content-Type: ' . $contentType);
header('Content-Disposition: attachment; filename="' . $fileBase . '.' . $extension . '"');
header('Cache-Control: private, no-store, max-age=0');
echo export_papers($papers, $format);
