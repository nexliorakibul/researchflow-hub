<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection, $user] = project_page_context();

$search = trim(query_string('q'));
$status = strtolower(trim(query_string('status')));
$sort = strtolower(trim(query_string('sort', 'newest')));
$perPage = filter_var($_GET['per_page'] ?? 10, FILTER_VALIDATE_INT);
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);

if (function_exists('mb_substr')) {
    $search = mb_substr($search, 0, 100, 'UTF-8');
} else {
    $search = substr($search, 0, 100);
}

if (!in_array($status, PROJECT_STATUSES, true)) {
    $status = '';
}

$sortOptions = [
    'newest' => ['label' => 'Newest first', 'sql' => 'created_at DESC, id DESC'],
    'oldest' => ['label' => 'Oldest first', 'sql' => 'created_at ASC, id ASC'],
    'title_asc' => ['label' => 'Title A-Z', 'sql' => 'title ASC, id DESC'],
    'title_desc' => ['label' => 'Title Z-A', 'sql' => 'title DESC, id DESC'],
    'target_asc' => ['label' => 'Target date', 'sql' => 'target_date IS NULL, target_date ASC, id DESC'],
];

if (!array_key_exists($sort, $sortOptions)) {
    $sort = 'newest';
}

if (!in_array($perPage, [10, 25, 50], true)) {
    $perPage = 10;
}

$page = is_int($page) && $page > 0 ? $page : 1;
$where = ['user_id = :user_id'];
$parameters = ['user_id' => $userId];

if ($status !== '') {
    $where[] = 'status = :status';
    $parameters['status'] = $status;
}

if ($search !== '') {
    $where[] = '(title LIKE :search_title OR research_area LIKE :search_area OR description LIKE :search_description)';
    $searchPattern = '%' . $search . '%';
    $parameters['search_title'] = $searchPattern;
    $parameters['search_area'] = $searchPattern;
    $parameters['search_description'] = $searchPattern;
}

$whereSql = implode(' AND ', $where);
$countStatement = $connection->prepare('SELECT COUNT(*) FROM projects WHERE ' . $whereSql);
$countStatement->execute($parameters);
$totalProjects = (int) $countStatement->fetchColumn();
$totalPages = max(1, (int) ceil($totalProjects / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$listSql = 'SELECT id, title, research_area, description, status, start_date, target_date, created_at
            FROM projects
            WHERE ' . $whereSql . '
            ORDER BY ' . $sortOptions[$sort]['sql'] . '
            LIMIT :limit OFFSET :offset';
$listStatement = $connection->prepare($listSql);

foreach ($parameters as $key => $value) {
    $listStatement->bindValue(':' . $key, $value, $key === 'user_id' ? PDO::PARAM_INT : PDO::PARAM_STR);
}

$listStatement->bindValue(':limit', $perPage, PDO::PARAM_INT);
$listStatement->bindValue(':offset', $offset, PDO::PARAM_INT);
$listStatement->execute();
$projects = $listStatement->fetchAll();

function projects_page_url(int $targetPage): string
{
    $query = $_GET;
    $query['page'] = $targetPage;

    return app_url('modules/projects/index.php') . '?' . http_build_query($query);
}

render_app_page_start('Research Projects', $user, 'projects');
render_app_feedback();
?>
<section class="page-heading">
    <div>
        <p class="page-kicker">Project management</p>
        <h2>Your research projects</h2>
        <p>Plan and track each research project from one workspace.</p>
    </div>
    <a class="primary-button" href="<?= e(app_url('modules/projects/create.php')) ?>">Create project</a>
</section>

<form class="filter-bar" method="get" action="<?= e(app_url('modules/projects/index.php')) ?>">
    <div class="filter-field filter-search">
        <label for="project-search">Search</label>
        <input id="project-search" name="q" type="search" value="<?= e($search) ?>"
               maxlength="100" placeholder="Title, area, or description">
    </div>
    <div class="filter-field">
        <label for="project-status">Status</label>
        <select id="project-status" name="status">
            <option value="">All statuses</option>
            <?php foreach (PROJECT_STATUSES as $projectStatus): ?>
                <option value="<?= e($projectStatus) ?>" <?= $status === $projectStatus ? 'selected' : '' ?>>
                    <?= e(project_status_label($projectStatus)) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-field">
        <label for="project-sort">Sort</label>
        <select id="project-sort" name="sort">
            <?php foreach ($sortOptions as $sortValue => $option): ?>
                <option value="<?= e($sortValue) ?>" <?= $sort === $sortValue ? 'selected' : '' ?>>
                    <?= e($option['label']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-field filter-small">
        <label for="project-page-size">Per page</label>
        <select id="project-page-size" name="per_page">
            <?php foreach ([10, 25, 50] as $size): ?>
                <option value="<?= e($size) ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= e($size) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-actions">
        <button class="secondary-button" type="submit">Apply</button>
        <a class="text-button" href="<?= e(app_url('modules/projects/index.php')) ?>">Reset</a>
    </div>
</form>

<div class="list-summary">
    <p><?= e(number_format($totalProjects)) ?> project<?= $totalProjects === 1 ? '' : 's' ?></p>
</div>

<?php if ($projects === []): ?>
    <section class="dashboard-panel">
        <?php render_empty_state($search !== '' || $status !== ''
            ? 'No projects match the selected filters.'
            : 'No research projects yet. Create your first project to begin.'); ?>
    </section>
<?php else: ?>
    <div class="project-card-grid">
        <?php foreach ($projects as $project): ?>
            <article class="project-card">
                <div class="project-card-heading">
                    <span class="status-badge status-<?= e(str_replace('_', '-', $project['status'])) ?>">
                        <?= e(project_status_label($project['status'])) ?>
                    </span>
                    <span class="project-area"><?= e($project['research_area'] ?: 'General research') ?></span>
                </div>
                <h3>
                    <a href="<?= e(app_url('modules/projects/show.php?id=' . $project['id'])) ?>">
                        <?= e($project['title']) ?>
                    </a>
                </h3>
                <p class="project-description"><?= e($project['description'] ?: 'No description provided.') ?></p>
                <dl class="project-dates">
                    <div><dt>Start</dt><dd><?= e(project_date_label($project['start_date'])) ?></dd></div>
                    <div><dt>Target</dt><dd><?= e(project_date_label($project['target_date'])) ?></dd></div>
                </dl>
                <div class="card-actions">
                    <a class="primary-button button-small" href="<?= e(app_url('modules/projects/show.php?id=' . $project['id'])) ?>">Open workspace</a>
                    <a class="secondary-button button-small" href="<?= e(app_url('modules/projects/edit.php?id=' . $project['id'])) ?>">Edit</a>
                    <form method="post" action="<?= e(app_url('modules/projects/delete.php')) ?>"
                          data-confirm="Delete this project? Reviews, gaps, experiments, tasks, and notes for it will also be deleted. Linked resources, papers, and datasets will remain unassigned. This cannot be undone.">
                        <?= csrf_input() ?>
                        <input type="hidden" name="id" value="<?= e($project['id']) ?>">
                        <button class="danger-button button-small" type="submit">Delete</button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="pagination-nav" aria-label="Project pages">
            <?php if ($page > 1): ?>
                <a href="<?= e(projects_page_url($page - 1)) ?>">Previous</a>
            <?php endif; ?>
            <span>Page <?= e($page) ?> of <?= e($totalPages) ?></span>
            <?php if ($page < $totalPages): ?>
                <a href="<?= e(projects_page_url($page + 1)) ?>">Next</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
<?php render_app_page_end(); ?>
