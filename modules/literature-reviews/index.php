<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection, $user] = review_page_context();
$projects = review_projects($connection, $userId);
$papers = review_papers($connection, $userId);

$search = trim(query_string('q'));
$projectId = filter_var($_GET['project_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$paperId = filter_var($_GET['paper_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$year = filter_var($_GET['year'] ?? null, FILTER_VALIDATE_INT);
$sort = strtolower(trim(query_string('sort', 'newest')));
$perPage = filter_var($_GET['per_page'] ?? 10, FILTER_VALIDATE_INT);
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);

$search = function_exists('mb_substr') ? mb_substr($search, 0, 100, 'UTF-8') : substr($search, 0, 100);
$projectId = $projectId === false ? null : (int) $projectId;
$paperId = $paperId === false ? null : (int) $paperId;
if ($year === false || !valid_year((int) $year)) {
    $year = null;
}

$sortOptions = [
    'newest' => ['label' => 'Newest first', 'sql' => 'lr.created_at DESC, lr.id DESC'],
    'oldest' => ['label' => 'Oldest first', 'sql' => 'lr.created_at ASC, lr.id ASC'],
    'paper_asc' => ['label' => 'Paper title A-Z', 'sql' => 'pa.title ASC, lr.id DESC'],
    'paper_desc' => ['label' => 'Paper title Z-A', 'sql' => 'pa.title DESC, lr.id DESC'],
    'year_desc' => ['label' => 'Publication year (newest)', 'sql' => 'pa.publication_year IS NULL, pa.publication_year DESC, lr.id DESC'],
    'year_asc' => ['label' => 'Publication year (oldest)', 'sql' => 'pa.publication_year IS NULL, pa.publication_year ASC, lr.id DESC'],
];
if (!array_key_exists($sort, $sortOptions)) {
    $sort = 'newest';
}
if (!in_array($perPage, [10, 25, 50], true)) {
    $perPage = 10;
}
$page = is_int($page) && $page > 0 ? $page : 1;

$where = ['lr.user_id = :user_id'];
$parameters = ['user_id' => $userId];
$integerParameters = ['user_id'];
if ($projectId !== null) {
    $where[] = 'lr.project_id = :project_id';
    $parameters['project_id'] = $projectId;
    $integerParameters[] = 'project_id';
}
if ($paperId !== null) {
    $where[] = 'lr.paper_id = :paper_id';
    $parameters['paper_id'] = $paperId;
    $integerParameters[] = 'paper_id';
}
if ($year !== null) {
    $where[] = 'pa.publication_year = :publication_year';
    $parameters['publication_year'] = (int) $year;
    $integerParameters[] = 'publication_year';
}
if ($search !== '') {
    $where[] = '(pa.title LIKE :search_title OR pa.authors LIKE :search_authors OR lr.research_objective LIKE :search_objective OR lr.dataset_used LIKE :search_dataset OR lr.models_used LIKE :search_models OR lr.key_findings LIKE :search_findings OR lr.limitations LIKE :search_limitations)';
    $pattern = '%' . $search . '%';
    foreach (['title', 'authors', 'objective', 'dataset', 'models', 'findings', 'limitations'] as $field) {
        $parameters['search_' . $field] = $pattern;
    }
}

$whereSql = implode(' AND ', $where);
$fromSql = ' FROM literature_reviews lr
             INNER JOIN projects p ON p.id = lr.project_id AND p.user_id = lr.user_id
             INNER JOIN papers pa ON pa.id = lr.paper_id AND pa.user_id = lr.user_id ';
$countStatement = $connection->prepare('SELECT COUNT(*)' . $fromSql . 'WHERE ' . $whereSql);
$countStatement->execute($parameters);
$totalReviews = (int) $countStatement->fetchColumn();
$totalPages = max(1, (int) ceil($totalReviews / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$listStatement = $connection->prepare(
    'SELECT lr.id, lr.dataset_used, lr.dataset_size, lr.models_used,
            lr.metrics, lr.main_results, lr.updated_at,
            p.title AS project_title, pa.title AS paper_title,
            pa.authors AS paper_authors, pa.publication_year AS paper_year'
    . $fromSql . 'WHERE ' . $whereSql . '
      ORDER BY ' . $sortOptions[$sort]['sql'] . '
      LIMIT :limit OFFSET :offset'
);
foreach ($parameters as $key => $value) {
    $listStatement->bindValue(':' . $key, $value, in_array($key, $integerParameters, true) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$listStatement->bindValue(':limit', $perPage, PDO::PARAM_INT);
$listStatement->bindValue(':offset', $offset, PDO::PARAM_INT);
$listStatement->execute();
$reviews = $listStatement->fetchAll();

function reviews_page_url(int $targetPage): string
{
    $query = $_GET;
    $query['page'] = $targetPage;

    return app_url('modules/literature-reviews/index.php') . '?' . http_build_query($query);
}

$hasFilters = $search !== '' || $projectId !== null || $paperId !== null || $year !== null;
render_app_page_start('Literature Reviews', $user, 'reviews');
render_app_feedback();
?>
<section class="page-heading">
    <div><p class="page-kicker">Evidence library</p><h2>Literature reviews</h2><p>Record structured methods, evidence, results, limitations, and research notes.</p></div>
    <div class="heading-actions"><a class="secondary-button" href="<?= e(app_url('modules/literature-reviews/matrix.php')) ?>">View matrix</a><a class="primary-button" href="<?= e(app_url('modules/literature-reviews/create.php')) ?>">Add review</a></div>
</section>

<form class="filter-bar review-filter-bar" method="get" action="<?= e(app_url('modules/literature-reviews/index.php')) ?>">
    <div class="filter-field filter-search"><label for="review-search">Search</label><input id="review-search" name="q" type="search" value="<?= e($search) ?>" maxlength="100" placeholder="Paper, author, dataset, model, finding"></div>
    <div class="filter-field"><label for="review-project">Project</label><select id="review-project" name="project_id"><option value="">All projects</option><?php foreach ($projects as $project): ?><option value="<?= e($project['id']) ?>" <?= $projectId === (int) $project['id'] ? 'selected' : '' ?>><?= e($project['title']) ?></option><?php endforeach; ?></select></div>
    <div class="filter-field"><label for="review-paper">Paper</label><select id="review-paper" name="paper_id"><option value="">All papers</option><?php foreach ($papers as $paper): ?><option value="<?= e($paper['id']) ?>" <?= $paperId === (int) $paper['id'] ? 'selected' : '' ?>><?= e($paper['title']) ?></option><?php endforeach; ?></select></div>
    <div class="filter-field filter-small"><label for="review-year">Paper year</label><input id="review-year" name="year" type="number" min="1000" max="9999" value="<?= e($year ?? '') ?>"></div>
    <div class="filter-field"><label for="review-sort">Sort</label><select id="review-sort" name="sort"><?php foreach ($sortOptions as $sortValue => $option): ?><option value="<?= e($sortValue) ?>" <?= $sort === $sortValue ? 'selected' : '' ?>><?= e($option['label']) ?></option><?php endforeach; ?></select></div>
    <div class="filter-field filter-small"><label for="review-page-size">Per page</label><select id="review-page-size" name="per_page"><?php foreach ([10, 25, 50] as $size): ?><option value="<?= e($size) ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= e($size) ?></option><?php endforeach; ?></select></div>
    <div class="filter-actions"><button class="secondary-button" type="submit">Apply</button><a class="text-button" href="<?= e(app_url('modules/literature-reviews/index.php')) ?>">Reset</a></div>
</form>

<div class="list-summary"><p><?= e(number_format($totalReviews)) ?> review<?= $totalReviews === 1 ? '' : 's' ?></p></div>
<?php if ($reviews === []): ?>
    <section class="dashboard-panel"><?php render_empty_state($hasFilters ? 'No literature reviews match the selected filters.' : 'No literature reviews yet. Add your first review to begin.'); ?></section>
<?php else: ?>
    <section class="review-card-grid">
        <?php foreach ($reviews as $review): ?>
            <article class="review-card">
                <div class="review-card-heading"><span><?= e($review['paper_year'] ?: 'Year not set') ?></span><span><?= e(review_date_label($review['updated_at'])) ?></span></div>
                <h3><a href="<?= e(app_url('modules/literature-reviews/show.php?id=' . $review['id'])) ?>"><?= e($review['paper_title']) ?></a></h3>
                <p class="record-subtitle"><?= e($review['paper_authors'] ?: 'Authors not set') ?></p>
                <dl class="review-card-details"><div><dt>Project</dt><dd><?= e($review['project_title']) ?></dd></div><div><dt>Dataset</dt><dd><?= e($review['dataset_used'] ?: 'Not set') ?></dd></div><div><dt>Models</dt><dd><?= e($review['models_used'] ?: 'Not set') ?></dd></div><div><dt>Metrics</dt><dd><?= e($review['metrics'] ?: 'Not set') ?></dd></div></dl>
                <div class="card-actions"><a class="secondary-button button-small" href="<?= e(app_url('modules/literature-reviews/show.php?id=' . $review['id'])) ?>">View</a><a class="secondary-button button-small" href="<?= e(app_url('modules/literature-reviews/edit.php?id=' . $review['id'])) ?>">Edit</a><form method="post" action="<?= e(app_url('modules/literature-reviews/delete.php')) ?>" data-confirm="Delete this literature review? This cannot be undone."><?= csrf_input() ?><input type="hidden" name="id" value="<?= e($review['id']) ?>"><button class="danger-button button-small" type="submit">Delete</button></form></div>
            </article>
        <?php endforeach; ?>
    </section>
    <?php if ($totalPages > 1): ?><nav class="pagination-nav" aria-label="Literature review pages"><?php if ($page > 1): ?><a href="<?= e(reviews_page_url($page - 1)) ?>">Previous</a><?php endif; ?><span>Page <?= e($page) ?> of <?= e($totalPages) ?></span><?php if ($page < $totalPages): ?><a href="<?= e(reviews_page_url($page + 1)) ?>">Next</a><?php endif; ?></nav><?php endif; ?>
<?php endif; ?>
<?php render_app_page_end(); ?>
