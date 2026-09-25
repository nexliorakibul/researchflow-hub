<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection, $user] = review_page_context();
$projects = review_projects($connection, $userId);

$datasetStatement = $connection->prepare(
    'SELECT DISTINCT dataset_used FROM literature_reviews
     WHERE user_id = :user_id AND dataset_used IS NOT NULL AND dataset_used <> \'\'
     ORDER BY dataset_used ASC'
);
$datasetStatement->execute(['user_id' => $userId]);
$datasets = $datasetStatement->fetchAll(PDO::FETCH_COLUMN);

$modelStatement = $connection->prepare(
    'SELECT DISTINCT models_used FROM literature_reviews
     WHERE user_id = :user_id AND models_used IS NOT NULL AND models_used <> \'\'
     ORDER BY models_used ASC'
);
$modelStatement->execute(['user_id' => $userId]);
$models = $modelStatement->fetchAll(PDO::FETCH_COLUMN);

$search = trim(query_string('q'));
$projectId = filter_var($_GET['project_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$year = filter_var($_GET['year'] ?? null, FILTER_VALIDATE_INT);
$dataset = trim(query_string('dataset'));
$model = trim(query_string('model'));
$sort = strtolower(trim(query_string('sort', 'paper_asc')));
$perPage = filter_var($_GET['per_page'] ?? 10, FILTER_VALIDATE_INT);
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);

$search = function_exists('mb_substr') ? mb_substr($search, 0, 100, 'UTF-8') : substr($search, 0, 100);
$dataset = function_exists('mb_substr') ? mb_substr($dataset, 0, 500, 'UTF-8') : substr($dataset, 0, 500);
$model = function_exists('mb_substr') ? mb_substr($model, 0, 500, 'UTF-8') : substr($model, 0, 500);
$projectId = $projectId === false ? null : (int) $projectId;
if ($year === false || !valid_year((int) $year)) {
    $year = null;
}

$sortOptions = [
    'paper_asc' => ['label' => 'Paper title A-Z', 'sql' => 'pa.title ASC, lr.id DESC'],
    'paper_desc' => ['label' => 'Paper title Z-A', 'sql' => 'pa.title DESC, lr.id DESC'],
    'year_desc' => ['label' => 'Publication year (newest)', 'sql' => 'pa.publication_year IS NULL, pa.publication_year DESC, pa.title ASC'],
    'year_asc' => ['label' => 'Publication year (oldest)', 'sql' => 'pa.publication_year IS NULL, pa.publication_year ASC, pa.title ASC'],
    'newest' => ['label' => 'Review newest first', 'sql' => 'lr.created_at DESC, lr.id DESC'],
    'oldest' => ['label' => 'Review oldest first', 'sql' => 'lr.created_at ASC, lr.id ASC'],
];
if (!array_key_exists($sort, $sortOptions)) {
    $sort = 'paper_asc';
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
if ($year !== null) {
    $where[] = 'pa.publication_year = :publication_year';
    $parameters['publication_year'] = (int) $year;
    $integerParameters[] = 'publication_year';
}
if ($dataset !== '') {
    $where[] = 'lr.dataset_used = :dataset_used';
    $parameters['dataset_used'] = $dataset;
}
if ($model !== '') {
    $where[] = 'lr.models_used = :models_used';
    $parameters['models_used'] = $model;
}
if ($search !== '') {
    $where[] = '(pa.title LIKE :search_title OR pa.authors LIKE :search_authors OR lr.dataset_used LIKE :search_dataset OR lr.methodology LIKE :search_method OR lr.models_used LIKE :search_model OR lr.main_results LIKE :search_result OR lr.limitations LIKE :search_limitation)';
    $pattern = '%' . $search . '%';
    foreach (['title', 'authors', 'dataset', 'method', 'model', 'result', 'limitation'] as $field) {
        $parameters['search_' . $field] = $pattern;
    }
}

$whereSql = implode(' AND ', $where);
$fromSql = ' FROM literature_reviews lr
             INNER JOIN projects p ON p.id = lr.project_id AND p.user_id = lr.user_id
             INNER JOIN papers pa ON pa.id = lr.paper_id AND pa.user_id = lr.user_id ';
$countStatement = $connection->prepare('SELECT COUNT(*)' . $fromSql . 'WHERE ' . $whereSql);
$countStatement->execute($parameters);
$totalRows = (int) $countStatement->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$matrixStatement = $connection->prepare(
    'SELECT lr.id, lr.dataset_used, lr.methodology, lr.models_used,
            lr.main_results, lr.limitations, p.title AS project_title,
            pa.title AS paper_title, pa.authors AS paper_authors,
            pa.publication_year AS paper_year'
    . $fromSql . 'WHERE ' . $whereSql . '
      ORDER BY ' . $sortOptions[$sort]['sql'] . '
      LIMIT :limit OFFSET :offset'
);
foreach ($parameters as $key => $value) {
    $matrixStatement->bindValue(':' . $key, $value, in_array($key, $integerParameters, true) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$matrixStatement->bindValue(':limit', $perPage, PDO::PARAM_INT);
$matrixStatement->bindValue(':offset', $offset, PDO::PARAM_INT);
$matrixStatement->execute();
$matrixRows = $matrixStatement->fetchAll();

function matrix_page_url(int $targetPage): string
{
    $query = $_GET;
    $query['page'] = $targetPage;

    return app_url('modules/literature-reviews/matrix.php') . '?' . http_build_query($query);
}

$hasFilters = $search !== '' || $projectId !== null || $year !== null || $dataset !== '' || $model !== '';
render_app_page_start('Literature Review Matrix', $user, 'review-matrix');
render_app_feedback();
?>
<nav class="breadcrumb-nav" aria-label="Breadcrumb"><a href="<?= e(app_url('modules/literature-reviews/index.php')) ?>">Literature Reviews</a><span aria-hidden="true">/</span><span aria-current="page">Matrix</span></nav>
<section class="page-heading">
    <div><p class="page-kicker">Evidence comparison</p><h2>Literature review matrix</h2><p>Compare papers, datasets, methods, models, results, and limitations from stored reviews.</p></div>
    <a class="secondary-button" href="<?= e(app_url('modules/literature-reviews/index.php')) ?>">Review list</a>
</section>

<form class="filter-bar matrix-filter-bar" method="get" action="<?= e(app_url('modules/literature-reviews/matrix.php')) ?>">
    <div class="filter-field filter-search"><label for="matrix-search">Search</label><input id="matrix-search" name="q" type="search" value="<?= e($search) ?>" maxlength="100" placeholder="Paper, author, method, result"></div>
    <div class="filter-field"><label for="matrix-project">Project</label><select id="matrix-project" name="project_id"><option value="">All projects</option><?php foreach ($projects as $project): ?><option value="<?= e($project['id']) ?>" <?= $projectId === (int) $project['id'] ? 'selected' : '' ?>><?= e($project['title']) ?></option><?php endforeach; ?></select></div>
    <div class="filter-field filter-small"><label for="matrix-year">Paper year</label><input id="matrix-year" name="year" type="number" min="1000" max="9999" value="<?= e($year ?? '') ?>"></div>
    <div class="filter-field"><label for="matrix-dataset">Dataset</label><select id="matrix-dataset" name="dataset"><option value="">All datasets</option><?php foreach ($datasets as $datasetOption): ?><option value="<?= e($datasetOption) ?>" <?= $dataset === $datasetOption ? 'selected' : '' ?>><?= e($datasetOption) ?></option><?php endforeach; ?></select></div>
    <div class="filter-field"><label for="matrix-model">Model</label><select id="matrix-model" name="model"><option value="">All models</option><?php foreach ($models as $modelOption): ?><option value="<?= e($modelOption) ?>" <?= $model === $modelOption ? 'selected' : '' ?>><?= e($modelOption) ?></option><?php endforeach; ?></select></div>
    <div class="filter-field"><label for="matrix-sort">Sort</label><select id="matrix-sort" name="sort"><?php foreach ($sortOptions as $sortValue => $option): ?><option value="<?= e($sortValue) ?>" <?= $sort === $sortValue ? 'selected' : '' ?>><?= e($option['label']) ?></option><?php endforeach; ?></select></div>
    <div class="filter-field filter-small"><label for="matrix-page-size">Per page</label><select id="matrix-page-size" name="per_page"><?php foreach ([10, 25, 50] as $size): ?><option value="<?= e($size) ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= e($size) ?></option><?php endforeach; ?></select></div>
    <div class="filter-actions"><button class="secondary-button" type="submit">Apply</button><a class="text-button" href="<?= e(app_url('modules/literature-reviews/matrix.php')) ?>">Reset</a></div>
</form>

<div class="list-summary"><p><?= e(number_format($totalRows)) ?> matrix row<?= $totalRows === 1 ? '' : 's' ?></p></div>
<?php if ($matrixRows === []): ?>
    <section class="dashboard-panel"><?php render_empty_state($hasFilters ? 'No review evidence matches the selected matrix filters.' : 'No review evidence is available yet. Add a literature review first.'); ?></section>
<?php else: ?>
    <section class="dashboard-panel matrix-panel"><div class="table-scroll"><table class="dashboard-table matrix-table">
        <thead><tr><th>Paper</th><th>Dataset</th><th>Method</th><th>Model</th><th>Result</th><th>Limitation</th></tr></thead>
        <tbody><?php foreach ($matrixRows as $row): ?><tr>
            <td class="matrix-paper-cell"><a class="record-title record-link" href="<?= e(app_url('modules/literature-reviews/show.php?id=' . $row['id'])) ?>"><?= e($row['paper_title']) ?></a><span class="record-subtitle"><?= e($row['paper_authors'] ?: 'Authors not set') ?></span><span class="matrix-meta"><?= e($row['project_title']) ?> · <?= e($row['paper_year'] ?: 'Year not set') ?></span></td>
            <td><div class="matrix-cell"><?= nl2br(e($row['dataset_used'] ?: 'Not set')) ?></div></td>
            <td><div class="matrix-cell"><?= nl2br(e($row['methodology'] ?: 'Not set')) ?></div></td>
            <td><div class="matrix-cell"><?= nl2br(e($row['models_used'] ?: 'Not set')) ?></div></td>
            <td><div class="matrix-cell"><?= nl2br(e($row['main_results'] ?: 'Not set')) ?></div></td>
            <td><div class="matrix-cell"><?= nl2br(e($row['limitations'] ?: 'Not set')) ?></div></td>
        </tr><?php endforeach; ?></tbody>
    </table></div></section>
    <?php if ($totalPages > 1): ?><nav class="pagination-nav" aria-label="Matrix pages"><?php if ($page > 1): ?><a href="<?= e(matrix_page_url($page - 1)) ?>">Previous</a><?php endif; ?><span>Page <?= e($page) ?> of <?= e($totalPages) ?></span><?php if ($page < $totalPages): ?><a href="<?= e(matrix_page_url($page + 1)) ?>">Next</a><?php endif; ?></nav><?php endif; ?>
<?php endif; ?>
<?php render_app_page_end(); ?>
