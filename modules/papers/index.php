<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection, $user] = paper_page_context();
$projects = paper_projects($connection, $userId);

$areaStatement = $connection->prepare(
    'SELECT DISTINCT research_area FROM papers
     WHERE user_id = :user_id AND research_area IS NOT NULL AND research_area <> \'\'
     ORDER BY research_area ASC'
);
$areaStatement->execute(['user_id' => $userId]);
$areas = $areaStatement->fetchAll(PDO::FETCH_COLUMN);

$search = trim(query_string('q'));
$projectId = filter_var($_GET['project_id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$year = filter_var($_GET['year'] ?? null, FILTER_VALIDATE_INT);
$readingStatus = strtolower(trim(query_string('reading_status')));
$area = trim(query_string('area'));
$sort = strtolower(trim(query_string('sort', 'newest')));
$perPage = filter_var($_GET['per_page'] ?? 10, FILTER_VALIDATE_INT);
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);

$search = function_exists('mb_substr') ? mb_substr($search, 0, 100, 'UTF-8') : substr($search, 0, 100);
$area = function_exists('mb_substr') ? mb_substr($area, 0, 150, 'UTF-8') : substr($area, 0, 150);
$projectId = $projectId === false ? null : (int) $projectId;

if ($year === false || !valid_year((int) $year)) {
    $year = null;
}

if (!in_array($readingStatus, PAPER_READING_STATUSES, true)) {
    $readingStatus = '';
}

$sortOptions = [
    'newest' => ['label' => 'Newest first', 'sql' => 'p.created_at DESC, p.id DESC'],
    'oldest' => ['label' => 'Oldest first', 'sql' => 'p.created_at ASC, p.id ASC'],
    'title_asc' => ['label' => 'Title A-Z', 'sql' => 'p.title ASC, p.id DESC'],
    'title_desc' => ['label' => 'Title Z-A', 'sql' => 'p.title DESC, p.id DESC'],
    'year_desc' => ['label' => 'Publication year (newest)', 'sql' => 'p.publication_year IS NULL, p.publication_year DESC, p.id DESC'],
    'year_asc' => ['label' => 'Publication year (oldest)', 'sql' => 'p.publication_year IS NULL, p.publication_year ASC, p.id DESC'],
];

if (!array_key_exists($sort, $sortOptions)) {
    $sort = 'newest';
}

if (!in_array($perPage, [10, 25, 50], true)) {
    $perPage = 10;
}

$page = is_int($page) && $page > 0 ? $page : 1;
$where = ['p.user_id = :user_id'];
$parameters = ['user_id' => $userId];
$integerParameters = ['user_id'];

if ($projectId !== null) {
    $where[] = 'p.project_id = :project_id';
    $parameters['project_id'] = $projectId;
    $integerParameters[] = 'project_id';
}

if ($year !== null) {
    $where[] = 'p.publication_year = :publication_year';
    $parameters['publication_year'] = (int) $year;
    $integerParameters[] = 'publication_year';
}

if ($readingStatus !== '') {
    $where[] = 'p.reading_status = :reading_status';
    $parameters['reading_status'] = $readingStatus;
}

if ($area !== '') {
    $where[] = 'p.research_area = :research_area';
    $parameters['research_area'] = $area;
}

if ($search !== '') {
    $where[] = '(p.title LIKE :search_title OR p.authors LIKE :search_authors OR p.keywords LIKE :search_keywords OR p.venue LIKE :search_venue OR p.doi LIKE :search_doi)';
    $pattern = '%' . $search . '%';
    $parameters['search_title'] = $pattern;
    $parameters['search_authors'] = $pattern;
    $parameters['search_keywords'] = $pattern;
    $parameters['search_venue'] = $pattern;
    $parameters['search_doi'] = $pattern;
}

$whereSql = implode(' AND ', $where);
$countStatement = $connection->prepare('SELECT COUNT(*) FROM papers p WHERE ' . $whereSql);
$countStatement->execute($parameters);
$totalPapers = (int) $countStatement->fetchColumn();
$totalPages = max(1, (int) ceil($totalPapers / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$listStatement = $connection->prepare(
    'SELECT p.id, p.title, p.authors, p.publication_year, p.venue,
            p.research_area, p.reading_status, p.created_at,
            pr.title AS project_title
     FROM papers p
     LEFT JOIN projects pr ON pr.id = p.project_id AND pr.user_id = p.user_id
     WHERE ' . $whereSql . '
     ORDER BY ' . $sortOptions[$sort]['sql'] . '
     LIMIT :limit OFFSET :offset'
);

foreach ($parameters as $key => $value) {
    $listStatement->bindValue(
        ':' . $key,
        $value,
        in_array($key, $integerParameters, true) ? PDO::PARAM_INT : PDO::PARAM_STR
    );
}
$listStatement->bindValue(':limit', $perPage, PDO::PARAM_INT);
$listStatement->bindValue(':offset', $offset, PDO::PARAM_INT);
$listStatement->execute();
$papers = $listStatement->fetchAll();

function papers_page_url(int $targetPage): string
{
    $query = $_GET;
    $query['page'] = $targetPage;

    return app_url('modules/papers/index.php') . '?' . http_build_query($query);
}

$hasFilters = $search !== '' || $projectId !== null || $year !== null || $readingStatus !== '' || $area !== '';

render_app_page_start('Research Papers', $user, 'papers');
render_app_feedback();
?>
<section class="page-heading">
    <div><p class="page-kicker">Research library</p><h2>Research papers</h2><p>Organize paper metadata, reading progress, summaries, and notes.</p></div>
    <a class="primary-button" href="<?= e(app_url('modules/papers/create.php')) ?>">Add paper</a>
</section>

<form class="filter-bar paper-filter-bar" method="get" action="<?= e(app_url('modules/papers/index.php')) ?>">
    <div class="filter-field filter-search">
        <label for="paper-search">Search</label>
        <input id="paper-search" name="q" type="search" value="<?= e($search) ?>" maxlength="100" placeholder="Title, author, keyword, venue, or DOI">
    </div>
    <div class="filter-field">
        <label for="paper-project">Project</label>
        <select id="paper-project" name="project_id">
            <option value="">All projects</option>
            <?php foreach ($projects as $project): ?>
                <option value="<?= e($project['id']) ?>" <?= $projectId === (int) $project['id'] ? 'selected' : '' ?>><?= e($project['title']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-field filter-small">
        <label for="paper-year">Year</label>
        <input id="paper-year" name="year" type="number" min="1000" max="9999" value="<?= e($year ?? '') ?>">
    </div>
    <div class="filter-field">
        <label for="paper-status">Reading status</label>
        <select id="paper-status" name="reading_status">
            <option value="">All statuses</option>
            <?php foreach (PAPER_READING_STATUSES as $status): ?>
                <option value="<?= e($status) ?>" <?= $readingStatus === $status ? 'selected' : '' ?>><?= e(paper_label($status)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-field">
        <label for="paper-area">Area</label>
        <select id="paper-area" name="area">
            <option value="">All areas</option>
            <?php foreach ($areas as $paperArea): ?>
                <option value="<?= e($paperArea) ?>" <?= $area === $paperArea ? 'selected' : '' ?>><?= e($paperArea) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-field">
        <label for="paper-sort">Sort</label>
        <select id="paper-sort" name="sort">
            <?php foreach ($sortOptions as $sortValue => $option): ?>
                <option value="<?= e($sortValue) ?>" <?= $sort === $sortValue ? 'selected' : '' ?>><?= e($option['label']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-field filter-small">
        <label for="paper-page-size">Per page</label>
        <select id="paper-page-size" name="per_page">
            <?php foreach ([10, 25, 50] as $size): ?><option value="<?= e($size) ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= e($size) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="filter-actions"><button class="secondary-button" type="submit">Apply</button><a class="text-button" href="<?= e(app_url('modules/papers/index.php')) ?>">Reset</a></div>
</form>

<div class="list-summary"><p><?= e(number_format($totalPapers)) ?> paper<?= $totalPapers === 1 ? '' : 's' ?></p></div>

<?php if ($papers === []): ?>
    <section class="dashboard-panel"><?php render_empty_state($hasFilters ? 'No papers match the selected filters.' : 'No research papers yet. Add your first paper to begin.'); ?></section>
<?php else: ?>
    <section class="dashboard-panel paper-list-panel">
        <div class="table-scroll">
            <table class="dashboard-table paper-table">
                <thead><tr><th>Paper</th><th>Project</th><th>Venue</th><th>Year</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($papers as $paper): ?>
                    <tr>
                        <td><a class="record-title record-link" href="<?= e(app_url('modules/papers/show.php?id=' . $paper['id'])) ?>"><?= e($paper['title']) ?></a><span class="record-subtitle"><?= e($paper['authors'] ?: ($paper['research_area'] ?: 'Authors not set')) ?></span></td>
                        <td><?= e($paper['project_title'] ?: 'Unassigned') ?></td>
                        <td><?= e($paper['venue'] ?: '—') ?></td>
                        <td><?= e($paper['publication_year'] ?: '—') ?></td>
                        <td><span class="status-badge status-<?= e(str_replace('_', '-', $paper['reading_status'])) ?>"><?= e(paper_label($paper['reading_status'])) ?></span></td>
                        <td><div class="table-actions">
                            <a href="<?= e(app_url('modules/papers/show.php?id=' . $paper['id'])) ?>">View</a>
                            <a href="<?= e(app_url('modules/papers/edit.php?id=' . $paper['id'])) ?>">Edit</a>
                            <form method="post" action="<?= e(app_url('modules/papers/delete.php')) ?>" data-confirm="Delete this research paper? Linked literature reviews will also be deleted. This cannot be undone.">
                                <?= csrf_input() ?><input type="hidden" name="id" value="<?= e($paper['id']) ?>"><button type="submit">Delete</button>
                            </form>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php if ($totalPages > 1): ?>
        <nav class="pagination-nav" aria-label="Paper pages">
            <?php if ($page > 1): ?><a href="<?= e(papers_page_url($page - 1)) ?>">Previous</a><?php endif; ?>
            <span>Page <?= e($page) ?> of <?= e($totalPages) ?></span>
            <?php if ($page < $totalPages): ?><a href="<?= e(papers_page_url($page + 1)) ?>">Next</a><?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
<?php render_app_page_end(); ?>
