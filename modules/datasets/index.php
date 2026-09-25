<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection, $user] = dataset_page_context();
$projects = dataset_projects($connection, $userId);

$domainStatement = $connection->prepare(
    'SELECT DISTINCT domain FROM datasets
     WHERE user_id = :user_id AND domain IS NOT NULL AND domain <> \'\'
     ORDER BY domain ASC'
);
$domainStatement->execute(['user_id' => $userId]);
$domains = $domainStatement->fetchAll(PDO::FETCH_COLUMN);

$search = trim(query_string('q'));
$projectId = filter_var($_GET['project_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$accessType = strtolower(trim(query_string('access_type')));
$status = strtolower(trim(query_string('status')));
$domain = trim(query_string('domain'));
$sort = strtolower(trim(query_string('sort', 'newest')));
$perPage = filter_var($_GET['per_page'] ?? 10, FILTER_VALIDATE_INT);
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);

$search = function_exists('mb_substr') ? mb_substr($search, 0, 100, 'UTF-8') : substr($search, 0, 100);
$domain = function_exists('mb_substr') ? mb_substr($domain, 0, 150, 'UTF-8') : substr($domain, 0, 150);
$projectId = $projectId === false ? null : (int) $projectId;

if (!in_array($accessType, DATASET_ACCESS_TYPES, true)) {
    $accessType = '';
}
if (!in_array($status, DATASET_STATUSES, true)) {
    $status = '';
}

$sortOptions = [
    'newest' => ['label' => 'Newest first', 'sql' => 'd.created_at DESC, d.id DESC'],
    'oldest' => ['label' => 'Oldest first', 'sql' => 'd.created_at ASC, d.id ASC'],
    'name_asc' => ['label' => 'Name A-Z', 'sql' => 'd.name ASC, d.id DESC'],
    'name_desc' => ['label' => 'Name Z-A', 'sql' => 'd.name DESC, d.id DESC'],
    'rows_desc' => ['label' => 'Largest row count', 'sql' => 'd.row_count IS NULL, d.row_count DESC, d.id DESC'],
    'rows_asc' => ['label' => 'Smallest row count', 'sql' => 'd.row_count IS NULL, d.row_count ASC, d.id DESC'],
];

if (!array_key_exists($sort, $sortOptions)) {
    $sort = 'newest';
}
if (!in_array($perPage, [10, 25, 50], true)) {
    $perPage = 10;
}
$page = is_int($page) && $page > 0 ? $page : 1;

$where = ['d.user_id = :user_id'];
$parameters = ['user_id' => $userId];
$integerParameters = ['user_id'];

if ($projectId !== null) {
    $where[] = 'd.project_id = :project_id';
    $parameters['project_id'] = $projectId;
    $integerParameters[] = 'project_id';
}
if ($accessType !== '') {
    $where[] = 'd.access_type = :access_type';
    $parameters['access_type'] = $accessType;
}
if ($status !== '') {
    $where[] = 'd.status = :status';
    $parameters['status'] = $status;
}
if ($domain !== '') {
    $where[] = 'd.domain = :domain';
    $parameters['domain'] = $domain;
}
if ($search !== '') {
    $where[] = '(d.name LIKE :search_name OR d.domain LIKE :search_domain OR d.source LIKE :search_source OR d.license LIKE :search_license)';
    $pattern = '%' . $search . '%';
    $parameters['search_name'] = $pattern;
    $parameters['search_domain'] = $pattern;
    $parameters['search_source'] = $pattern;
    $parameters['search_license'] = $pattern;
}

$whereSql = implode(' AND ', $where);
$countStatement = $connection->prepare('SELECT COUNT(*) FROM datasets d WHERE ' . $whereSql);
$countStatement->execute($parameters);
$totalDatasets = (int) $countStatement->fetchColumn();
$totalPages = max(1, (int) ceil($totalDatasets / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$listStatement = $connection->prepare(
    'SELECT d.id, d.name, d.domain, d.source, d.row_count, d.column_count,
            d.access_type, d.status, d.file_size, d.created_at,
            p.title AS project_title
     FROM datasets d
     LEFT JOIN projects p ON p.id = d.project_id AND p.user_id = d.user_id
     WHERE ' . $whereSql . '
     ORDER BY ' . $sortOptions[$sort]['sql'] . '
     LIMIT :limit OFFSET :offset'
);
foreach ($parameters as $key => $value) {
    $listStatement->bindValue(':' . $key, $value, in_array($key, $integerParameters, true) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$listStatement->bindValue(':limit', $perPage, PDO::PARAM_INT);
$listStatement->bindValue(':offset', $offset, PDO::PARAM_INT);
$listStatement->execute();
$datasets = $listStatement->fetchAll();

function datasets_page_url(int $targetPage): string
{
    $query = $_GET;
    $query['page'] = $targetPage;

    return app_url('modules/datasets/index.php') . '?' . http_build_query($query);
}

$hasFilters = $search !== '' || $projectId !== null || $accessType !== '' || $status !== '' || $domain !== '';

render_app_page_start('Dataset Manager', $user, 'datasets');
render_app_feedback();
?>
<section class="page-heading">
    <div><p class="page-kicker">Research library</p><h2>Dataset manager</h2><p>Track dataset metadata, access, licensing, size, and preparation status.</p></div>
    <a class="primary-button" href="<?= e(app_url('modules/datasets/create.php')) ?>">Add dataset</a>
</section>

<form class="filter-bar dataset-filter-bar" method="get" action="<?= e(app_url('modules/datasets/index.php')) ?>">
    <div class="filter-field filter-search"><label for="dataset-search">Search</label><input id="dataset-search" name="q" type="search" value="<?= e($search) ?>" maxlength="100" placeholder="Name, domain, source, or license"></div>
    <div class="filter-field"><label for="dataset-project">Project</label><select id="dataset-project" name="project_id"><option value="">All projects</option><?php foreach ($projects as $project): ?><option value="<?= e($project['id']) ?>" <?= $projectId === (int) $project['id'] ? 'selected' : '' ?>><?= e($project['title']) ?></option><?php endforeach; ?></select></div>
    <div class="filter-field"><label for="dataset-access">Access type</label><select id="dataset-access" name="access_type"><option value="">All access types</option><?php foreach (DATASET_ACCESS_TYPES as $type): ?><option value="<?= e($type) ?>" <?= $accessType === $type ? 'selected' : '' ?>><?= e(dataset_label($type)) ?></option><?php endforeach; ?></select></div>
    <div class="filter-field"><label for="dataset-status">Status</label><select id="dataset-status" name="status"><option value="">All statuses</option><?php foreach (DATASET_STATUSES as $datasetStatus): ?><option value="<?= e($datasetStatus) ?>" <?= $status === $datasetStatus ? 'selected' : '' ?>><?= e(dataset_label($datasetStatus)) ?></option><?php endforeach; ?></select></div>
    <div class="filter-field"><label for="dataset-domain">Domain</label><select id="dataset-domain" name="domain"><option value="">All domains</option><?php foreach ($domains as $datasetDomain): ?><option value="<?= e($datasetDomain) ?>" <?= $domain === $datasetDomain ? 'selected' : '' ?>><?= e($datasetDomain) ?></option><?php endforeach; ?></select></div>
    <div class="filter-field"><label for="dataset-sort">Sort</label><select id="dataset-sort" name="sort"><?php foreach ($sortOptions as $sortValue => $option): ?><option value="<?= e($sortValue) ?>" <?= $sort === $sortValue ? 'selected' : '' ?>><?= e($option['label']) ?></option><?php endforeach; ?></select></div>
    <div class="filter-field filter-small"><label for="dataset-page-size">Per page</label><select id="dataset-page-size" name="per_page"><?php foreach ([10, 25, 50] as $size): ?><option value="<?= e($size) ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= e($size) ?></option><?php endforeach; ?></select></div>
    <div class="filter-actions"><button class="secondary-button" type="submit">Apply</button><a class="text-button" href="<?= e(app_url('modules/datasets/index.php')) ?>">Reset</a></div>
</form>

<div class="list-summary"><p><?= e(number_format($totalDatasets)) ?> dataset<?= $totalDatasets === 1 ? '' : 's' ?></p></div>
<?php if ($datasets === []): ?>
    <section class="dashboard-panel"><?php render_empty_state($hasFilters ? 'No datasets match the selected filters.' : 'No datasets yet. Add your first dataset to begin.'); ?></section>
<?php else: ?>
    <section class="dashboard-panel dataset-list-panel"><div class="table-scroll"><table class="dashboard-table dataset-table">
        <thead><tr><th>Dataset</th><th>Project</th><th>Access</th><th>Shape</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody><?php foreach ($datasets as $dataset): ?><tr>
            <td><a class="record-title record-link" href="<?= e(app_url('modules/datasets/show.php?id=' . $dataset['id'])) ?>"><?= e($dataset['name']) ?></a><span class="record-subtitle"><?= e($dataset['domain'] ?: ($dataset['source'] ?: 'Domain not set')) ?></span></td>
            <td><?= e($dataset['project_title'] ?: 'Unassigned') ?></td>
            <td><?= e(dataset_label($dataset['access_type'])) ?></td>
            <td><?= e($dataset['row_count'] !== null ? number_format((int) $dataset['row_count']) : '—') ?> × <?= e($dataset['column_count'] !== null ? number_format((int) $dataset['column_count']) : '—') ?></td>
            <td><span class="status-badge status-<?= e(str_replace('_', '-', (string) $dataset['status'])) ?>"><?= e(dataset_label($dataset['status'])) ?></span></td>
            <td><div class="table-actions"><a href="<?= e(app_url('modules/datasets/show.php?id=' . $dataset['id'])) ?>">View</a><a href="<?= e(app_url('modules/datasets/edit.php?id=' . $dataset['id'])) ?>">Edit</a><form method="post" action="<?= e(app_url('modules/datasets/delete.php')) ?>" data-confirm="Delete this dataset metadata record? This cannot be undone."><?= csrf_input() ?><input type="hidden" name="id" value="<?= e($dataset['id']) ?>"><button type="submit">Delete</button></form></div></td>
        </tr><?php endforeach; ?></tbody>
    </table></div></section>
    <?php if ($totalPages > 1): ?><nav class="pagination-nav" aria-label="Dataset pages"><?php if ($page > 1): ?><a href="<?= e(datasets_page_url($page - 1)) ?>">Previous</a><?php endif; ?><span>Page <?= e($page) ?> of <?= e($totalPages) ?></span><?php if ($page < $totalPages): ?><a href="<?= e(datasets_page_url($page + 1)) ?>">Next</a><?php endif; ?></nav><?php endif; ?>
<?php endif; ?>
<?php render_app_page_end(); ?>
