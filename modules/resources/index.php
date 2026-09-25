<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection, $user] = resource_page_context();
$projects = resource_projects($connection, $userId);

$areaStatement = $connection->prepare(
    'SELECT DISTINCT research_area FROM resources
     WHERE user_id = :user_id AND research_area IS NOT NULL AND research_area <> \'\'
     ORDER BY research_area ASC'
);
$areaStatement->execute(['user_id' => $userId]);
$areas = $areaStatement->fetchAll(PDO::FETCH_COLUMN);

$search = trim(query_string('q'));
$projectId = filter_var($_GET['project_id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$type = strtolower(trim(query_string('type')));
$status = strtolower(trim(query_string('status')));
$area = trim(query_string('area'));
$year = filter_var($_GET['year'] ?? null, FILTER_VALIDATE_INT);
$favorite = query_string('favorite');
$sort = strtolower(trim(query_string('sort', 'newest')));
$perPage = filter_var($_GET['per_page'] ?? 10, FILTER_VALIDATE_INT);
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);

$search = function_exists('mb_substr')
    ? mb_substr($search, 0, 100, 'UTF-8')
    : substr($search, 0, 100);
$area = function_exists('mb_substr')
    ? mb_substr($area, 0, 150, 'UTF-8')
    : substr($area, 0, 150);

if (!in_array($type, RESOURCE_TYPES, true)) {
    $type = '';
}

if (!in_array($status, RESOURCE_STATUSES, true)) {
    $status = '';
}

if (!in_array($favorite, ['0', '1'], true)) {
    $favorite = '';
}

if ($year === false || !valid_year((int) $year)) {
    $year = null;
}

$projectId = $projectId === false ? null : (int) $projectId;
$sortOptions = [
    'newest' => ['label' => 'Newest first', 'sql' => 'r.created_at DESC, r.id DESC'],
    'oldest' => ['label' => 'Oldest first', 'sql' => 'r.created_at ASC, r.id ASC'],
    'title_asc' => ['label' => 'Title A-Z', 'sql' => 'r.title ASC, r.id DESC'],
    'title_desc' => ['label' => 'Title Z-A', 'sql' => 'r.title DESC, r.id DESC'],
    'year_desc' => ['label' => 'Publication year (newest)', 'sql' => 'r.publication_year IS NULL, r.publication_year DESC, r.id DESC'],
    'year_asc' => ['label' => 'Publication year (oldest)', 'sql' => 'r.publication_year IS NULL, r.publication_year ASC, r.id DESC'],
];

if (!array_key_exists($sort, $sortOptions)) {
    $sort = 'newest';
}

if (!in_array($perPage, [10, 25, 50], true)) {
    $perPage = 10;
}

$page = is_int($page) && $page > 0 ? $page : 1;
$where = ['r.user_id = :user_id'];
$parameters = ['user_id' => $userId];
$integerParameters = ['user_id'];

if ($projectId !== null) {
    $where[] = 'r.project_id = :project_id';
    $parameters['project_id'] = $projectId;
    $integerParameters[] = 'project_id';
}

if ($type !== '') {
    $where[] = 'r.resource_type = :resource_type';
    $parameters['resource_type'] = $type;
}

if ($status !== '') {
    $where[] = 'r.status = :status';
    $parameters['status'] = $status;
}

if ($area !== '') {
    $where[] = 'r.research_area = :research_area';
    $parameters['research_area'] = $area;
}

if ($year !== null) {
    $where[] = 'r.publication_year = :publication_year';
    $parameters['publication_year'] = (int) $year;
    $integerParameters[] = 'publication_year';
}

if ($favorite !== '') {
    $where[] = 'r.is_favorite = :is_favorite';
    $parameters['is_favorite'] = (int) $favorite;
    $integerParameters[] = 'is_favorite';
}

if ($search !== '') {
    $where[] = '(r.title LIKE :search_title OR r.authors LIKE :search_authors OR r.tags LIKE :search_tags OR r.doi LIKE :search_doi)';
    $pattern = '%' . $search . '%';
    $parameters['search_title'] = $pattern;
    $parameters['search_authors'] = $pattern;
    $parameters['search_tags'] = $pattern;
    $parameters['search_doi'] = $pattern;
}

$whereSql = implode(' AND ', $where);
$countStatement = $connection->prepare('SELECT COUNT(*) FROM resources r WHERE ' . $whereSql);
$countStatement->execute($parameters);
$totalResources = (int) $countStatement->fetchColumn();
$totalPages = max(1, (int) ceil($totalResources / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$listStatement = $connection->prepare(
    'SELECT r.id, r.project_id, r.title, r.resource_type, r.research_area,
            r.authors, r.publication_year, r.url, r.doi, r.tags, r.status,
            r.is_favorite, r.created_at, p.title AS project_title
     FROM resources r
     LEFT JOIN projects p ON p.id = r.project_id AND p.user_id = r.user_id
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
$resources = $listStatement->fetchAll();

function resources_page_url(int $targetPage): string
{
    $query = $_GET;
    $query['page'] = $targetPage;

    return app_url('modules/resources/index.php') . '?' . http_build_query($query);
}

$hasFilters = $search !== '' || $projectId !== null || $type !== '' || $status !== ''
    || $area !== '' || $year !== null || $favorite !== '';

render_app_page_start('Research Resources', $user, 'resources');
render_app_feedback();
?>
<section class="page-heading">
    <div>
        <p class="page-kicker">Research library</p>
        <h2>General research resources</h2>
        <p>Organize useful websites, repositories, books, tools, courses, and other academic material.</p>
    </div>
    <a class="primary-button" href="<?= e(app_url('modules/resources/create.php')) ?>">Add resource</a>
</section>

<form class="filter-bar resource-filter-bar" method="get" action="<?= e(app_url('modules/resources/index.php')) ?>">
    <div class="filter-field filter-search">
        <label for="resource-search">Search</label>
        <input id="resource-search" name="q" type="search" value="<?= e($search) ?>"
               maxlength="100" placeholder="Title, author, tag, or DOI">
    </div>
    <div class="filter-field">
        <label for="resource-project">Project</label>
        <select id="resource-project" name="project_id">
            <option value="">All projects</option>
            <?php foreach ($projects as $project): ?>
                <option value="<?= e($project['id']) ?>" <?= $projectId === (int) $project['id'] ? 'selected' : '' ?>>
                    <?= e($project['title']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-field">
        <label for="resource-type">Type</label>
        <select id="resource-type" name="type">
            <option value="">All types</option>
            <?php foreach (RESOURCE_TYPES as $resourceType): ?>
                <option value="<?= e($resourceType) ?>" <?= $type === $resourceType ? 'selected' : '' ?>><?= e(resource_label($resourceType)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-field">
        <label for="resource-status">Status</label>
        <select id="resource-status" name="status">
            <option value="">All statuses</option>
            <?php foreach (RESOURCE_STATUSES as $resourceStatus): ?>
                <option value="<?= e($resourceStatus) ?>" <?= $status === $resourceStatus ? 'selected' : '' ?>><?= e(resource_label($resourceStatus)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-field">
        <label for="resource-area">Area</label>
        <select id="resource-area" name="area">
            <option value="">All areas</option>
            <?php foreach ($areas as $resourceArea): ?>
                <option value="<?= e($resourceArea) ?>" <?= $area === $resourceArea ? 'selected' : '' ?>><?= e($resourceArea) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-field filter-small">
        <label for="resource-year">Year</label>
        <input id="resource-year" name="year" type="number" min="1000" max="9999" value="<?= e($year ?? '') ?>">
    </div>
    <div class="filter-field">
        <label for="resource-favorite">Favorite</label>
        <select id="resource-favorite" name="favorite">
            <option value="">All resources</option>
            <option value="1" <?= $favorite === '1' ? 'selected' : '' ?>>Favorites only</option>
            <option value="0" <?= $favorite === '0' ? 'selected' : '' ?>>Not favorites</option>
        </select>
    </div>
    <div class="filter-field">
        <label for="resource-sort">Sort</label>
        <select id="resource-sort" name="sort">
            <?php foreach ($sortOptions as $sortValue => $option): ?>
                <option value="<?= e($sortValue) ?>" <?= $sort === $sortValue ? 'selected' : '' ?>><?= e($option['label']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-field filter-small">
        <label for="resource-page-size">Per page</label>
        <select id="resource-page-size" name="per_page">
            <?php foreach ([10, 25, 50] as $size): ?>
                <option value="<?= e($size) ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= e($size) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-actions">
        <button class="secondary-button" type="submit">Apply</button>
        <a class="text-button" href="<?= e(app_url('modules/resources/index.php')) ?>">Reset</a>
    </div>
</form>

<div class="list-summary">
    <p><?= e(number_format($totalResources)) ?> resource<?= $totalResources === 1 ? '' : 's' ?></p>
</div>

<?php if ($resources === []): ?>
    <section class="dashboard-panel">
        <?php render_empty_state($hasFilters
            ? 'No resources match the selected filters.'
            : 'No research resources yet. Add your first resource to begin.'); ?>
    </section>
<?php else: ?>
    <section class="dashboard-panel resource-list-panel">
        <div class="table-scroll">
            <table class="dashboard-table resource-table">
                <thead>
                <tr><th>Resource</th><th>Type</th><th>Project</th><th>Year</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($resources as $resource): ?>
                    <tr>
                        <td>
                            <a class="record-title record-link" href="<?= e(app_url('modules/resources/show.php?id=' . $resource['id'])) ?>">
                                <?= $resource['is_favorite'] ? '<span class="favorite-mark" aria-label="Favorite">&#9733;</span> ' : '' ?><?= e($resource['title']) ?>
                            </a>
                            <span class="record-subtitle"><?= e($resource['authors'] ?: ($resource['research_area'] ?: 'No author or area specified')) ?></span>
                        </td>
                        <td><?= e(resource_label($resource['resource_type'])) ?></td>
                        <td><?= e($resource['project_title'] ?: 'Unassigned') ?></td>
                        <td><?= e($resource['publication_year'] ?: '—') ?></td>
                        <td><span class="status-badge status-<?= e(str_replace('_', '-', $resource['status'])) ?>"><?= e(resource_label($resource['status'])) ?></span></td>
                        <td>
                            <div class="table-actions">
                                <a href="<?= e(app_url('modules/resources/show.php?id=' . $resource['id'])) ?>">View</a>
                                <a href="<?= e(app_url('modules/resources/edit.php?id=' . $resource['id'])) ?>">Edit</a>
                                <form method="post" action="<?= e(app_url('modules/resources/delete.php')) ?>"
                                      data-confirm="Delete this research resource? This cannot be undone.">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="id" value="<?= e($resource['id']) ?>">
                                    <button type="submit">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php if ($totalPages > 1): ?>
        <nav class="pagination-nav" aria-label="Resource pages">
            <?php if ($page > 1): ?><a href="<?= e(resources_page_url($page - 1)) ?>">Previous</a><?php endif; ?>
            <span>Page <?= e($page) ?> of <?= e($totalPages) ?></span>
            <?php if ($page < $totalPages): ?><a href="<?= e(resources_page_url($page + 1)) ?>">Next</a><?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
<?php render_app_page_end(); ?>
