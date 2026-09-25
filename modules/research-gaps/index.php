<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection, $user] = gap_page_context();
$projects = gap_projects($connection, $userId);

$search = trim(query_string('q'));
$projectId = filter_var($_GET['project_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$paperId = filter_var($_GET['paper_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$type = strtolower(trim(query_string('type')));
$priority = strtolower(trim(query_string('priority')));
$status = strtolower(trim(query_string('status')));
$sort = strtolower(trim(query_string('sort', 'newest')));
$perPage = filter_var($_GET['per_page'] ?? 10, FILTER_VALIDATE_INT);
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);

$search = function_exists('mb_substr') ? mb_substr($search, 0, 100, 'UTF-8') : substr($search, 0, 100);
$projectId = $projectId === false ? null : (int) $projectId;
$paperId = $paperId === false ? null : (int) $paperId;
if (!in_array($type, GAP_TYPES, true)) {
    $type = '';
}
if (!in_array($priority, GAP_PRIORITIES, true)) {
    $priority = '';
}
if (!in_array($status, GAP_STATUSES, true)) {
    $status = '';
}

$sortOptions = [
    'newest' => ['label' => 'Newest first', 'sql' => 'g.created_at DESC, g.id DESC'],
    'oldest' => ['label' => 'Oldest first', 'sql' => 'g.created_at ASC, g.id ASC'],
    'title_asc' => ['label' => 'Title A-Z', 'sql' => 'g.gap_title ASC, g.id DESC'],
    'title_desc' => ['label' => 'Title Z-A', 'sql' => 'g.gap_title DESC, g.id DESC'],
    'priority_desc' => ['label' => 'Highest priority', 'sql' => "FIELD(g.priority, 'critical', 'high', 'medium', 'low'), g.created_at DESC"],
    'priority_asc' => ['label' => 'Lowest priority', 'sql' => "FIELD(g.priority, 'low', 'medium', 'high', 'critical'), g.created_at DESC"],
];
if (!array_key_exists($sort, $sortOptions)) {
    $sort = 'newest';
}
if (!in_array($perPage, [10, 25, 50], true)) {
    $perPage = 10;
}
$page = is_int($page) && $page > 0 ? $page : 1;

$where = ['g.user_id = :user_id'];
$parameters = ['user_id' => $userId];
$integerParameters = ['user_id'];
if ($projectId !== null) {
    $where[] = 'g.project_id = :project_id';
    $parameters['project_id'] = $projectId;
    $integerParameters[] = 'project_id';
}
if ($paperId !== null) {
    $where[] = 'g.paper_id = :paper_id';
    $parameters['paper_id'] = $paperId;
    $integerParameters[] = 'paper_id';
}
if ($type !== '') {
    $where[] = 'g.gap_type = :gap_type';
    $parameters['gap_type'] = $type;
}
if ($priority !== '') {
    $where[] = 'g.priority = :priority';
    $parameters['priority'] = $priority;
}
if ($status !== '') {
    $where[] = 'g.status = :status';
    $parameters['status'] = $status;
}
if ($search !== '') {
    $where[] = '(g.gap_title LIKE :search_title OR g.description LIKE :search_description OR g.evidence LIKE :search_evidence OR g.potential_solution LIKE :search_solution OR g.notes LIKE :search_notes OR pa.title LIKE :search_paper)';
    $pattern = '%' . $search . '%';
    foreach (['title', 'description', 'evidence', 'solution', 'notes', 'paper'] as $field) {
        $parameters['search_' . $field] = $pattern;
    }
}

$whereSql = implode(' AND ', $where);
$fromSql = ' FROM research_gaps g
             INNER JOIN projects p ON p.id = g.project_id AND p.user_id = g.user_id
             LEFT JOIN papers pa ON pa.id = g.paper_id AND pa.user_id = g.user_id ';
$countStatement = $connection->prepare('SELECT COUNT(*)' . $fromSql . 'WHERE ' . $whereSql);
$countStatement->execute($parameters);
$totalGaps = (int) $countStatement->fetchColumn();
$totalPages = max(1, (int) ceil($totalGaps / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$listStatement = $connection->prepare(
    'SELECT g.id, g.gap_title, g.gap_type, g.description, g.priority,
            g.status, g.updated_at, p.title AS project_title,
            pa.title AS paper_title'
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
$gaps = $listStatement->fetchAll();

function gaps_page_url(int $targetPage): string
{
    $query = $_GET;
    $query['page'] = $targetPage;

    return app_url('modules/research-gaps/index.php') . '?' . http_build_query($query);
}

$hasFilters = $search !== '' || $projectId !== null || $paperId !== null || $type !== '' || $priority !== '' || $status !== '';
render_app_page_start('Research Gaps', $user, 'gaps');
render_app_feedback();
?>
<section class="page-heading"><div><p class="page-kicker">Research progress</p><h2>Research gaps</h2><p>Track evidence-backed gaps, priorities, investigation status, and potential solutions.</p></div><div class="heading-actions"><a class="secondary-button" href="<?= e(app_url('modules/research-gaps/statistics.php')) ?>">View statistics</a><a class="primary-button" href="<?= e(app_url('modules/research-gaps/create.php')) ?>">Add gap</a></div></section>

<form class="filter-bar gap-filter-bar" method="get" action="<?= e(app_url('modules/research-gaps/index.php')) ?>">
    <div class="filter-field filter-search"><label for="gap-search">Search</label><input id="gap-search" name="q" type="search" value="<?= e($search) ?>" maxlength="100" placeholder="Title, paper, evidence, solution"></div>
    <div class="filter-field"><label for="gap-project">Project</label><select id="gap-project" name="project_id"><option value="">All projects</option><?php foreach ($projects as $project): ?><option value="<?= e($project['id']) ?>" <?= $projectId === (int) $project['id'] ? 'selected' : '' ?>><?= e($project['title']) ?></option><?php endforeach; ?></select></div>
    <div class="filter-field"><label for="gap-type">Type</label><select id="gap-type" name="type"><option value="">All types</option><?php foreach (GAP_TYPES as $gapType): ?><option value="<?= e($gapType) ?>" <?= $type === $gapType ? 'selected' : '' ?>><?= e(gap_label($gapType)) ?></option><?php endforeach; ?></select></div>
    <div class="filter-field"><label for="gap-priority">Priority</label><select id="gap-priority" name="priority"><option value="">All priorities</option><?php foreach (GAP_PRIORITIES as $gapPriority): ?><option value="<?= e($gapPriority) ?>" <?= $priority === $gapPriority ? 'selected' : '' ?>><?= e(gap_label($gapPriority)) ?></option><?php endforeach; ?></select></div>
    <div class="filter-field"><label for="gap-status">Status</label><select id="gap-status" name="status"><option value="">All statuses</option><?php foreach (GAP_STATUSES as $gapStatus): ?><option value="<?= e($gapStatus) ?>" <?= $status === $gapStatus ? 'selected' : '' ?>><?= e(gap_label($gapStatus)) ?></option><?php endforeach; ?></select></div>
    <div class="filter-field"><label for="gap-sort">Sort</label><select id="gap-sort" name="sort"><?php foreach ($sortOptions as $sortValue => $option): ?><option value="<?= e($sortValue) ?>" <?= $sort === $sortValue ? 'selected' : '' ?>><?= e($option['label']) ?></option><?php endforeach; ?></select></div>
    <div class="filter-field filter-small"><label for="gap-page-size">Per page</label><select id="gap-page-size" name="per_page"><?php foreach ([10, 25, 50] as $size): ?><option value="<?= e($size) ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= e($size) ?></option><?php endforeach; ?></select></div>
    <div class="filter-actions"><button class="secondary-button" type="submit">Apply</button><a class="text-button" href="<?= e(app_url('modules/research-gaps/index.php')) ?>">Reset</a></div>
</form>

<div class="list-summary"><p><?= e(number_format($totalGaps)) ?> gap<?= $totalGaps === 1 ? '' : 's' ?></p></div>
<?php if ($gaps === []): ?>
    <section class="dashboard-panel"><?php render_empty_state($hasFilters ? 'No research gaps match the selected filters.' : 'No research gaps yet. Add your first evidence-backed gap.'); ?></section>
<?php else: ?>
    <section class="gap-card-grid"><?php foreach ($gaps as $gap): ?><article class="gap-card">
        <div class="gap-card-meta"><span class="priority-badge priority-<?= e($gap['priority']) ?>"><?= e(gap_label($gap['priority'])) ?></span><span class="status-badge status-<?= e(str_replace('_', '-', $gap['status'])) ?>"><?= e(gap_label($gap['status'])) ?></span></div>
        <h3><a href="<?= e(app_url('modules/research-gaps/show.php?id=' . $gap['id'])) ?>"><?= e($gap['gap_title']) ?></a></h3>
        <p class="gap-description"><?= e($gap['description']) ?></p>
        <dl class="gap-card-details"><div><dt>Project</dt><dd><?= e($gap['project_title']) ?></dd></div><div><dt>Type</dt><dd><?= e(gap_label($gap['gap_type'])) ?></dd></div><div><dt>Paper</dt><dd><?= e($gap['paper_title'] ?: 'Not linked') ?></dd></div><div><dt>Updated</dt><dd><?= e(gap_date_label($gap['updated_at'])) ?></dd></div></dl>
        <div class="card-actions"><a class="secondary-button button-small" href="<?= e(app_url('modules/research-gaps/show.php?id=' . $gap['id'])) ?>">View</a><a class="secondary-button button-small" href="<?= e(app_url('modules/research-gaps/edit.php?id=' . $gap['id'])) ?>">Edit</a><form method="post" action="<?= e(app_url('modules/research-gaps/delete.php')) ?>" data-confirm="Delete this research gap? This cannot be undone."><?= csrf_input() ?><input type="hidden" name="id" value="<?= e($gap['id']) ?>"><button class="danger-button button-small" type="submit">Delete</button></form></div>
    </article><?php endforeach; ?></section>
    <?php if ($totalPages > 1): ?><nav class="pagination-nav" aria-label="Research gap pages"><?php if ($page > 1): ?><a href="<?= e(gaps_page_url($page - 1)) ?>">Previous</a><?php endif; ?><span>Page <?= e($page) ?> of <?= e($totalPages) ?></span><?php if ($page < $totalPages): ?><a href="<?= e(gaps_page_url($page + 1)) ?>">Next</a><?php endif; ?></nav><?php endif; ?>
<?php endif; ?>
<?php render_app_page_end(); ?>
