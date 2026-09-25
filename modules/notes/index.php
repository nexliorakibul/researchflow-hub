<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection, $user] = note_page_context();
$projects = note_projects($connection, $userId);

$search = trim(query_string('q'));
$projectId = filter_var($_GET['project_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$type = strtolower(trim(query_string('type')));
$sort = strtolower(trim(query_string('sort', 'newest')));
$perPage = filter_var($_GET['per_page'] ?? 10, FILTER_VALIDATE_INT);
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);

$search = function_exists('mb_substr') ? mb_substr($search, 0, 100, 'UTF-8') : substr($search, 0, 100);
$projectId = $projectId === false ? null : (int) $projectId;
if (!in_array($type, NOTE_TYPES, true)) {
    $type = '';
}
$sortOptions = [
    'newest' => ['label' => 'Newest first', 'sql' => 'n.created_at DESC, n.id DESC'],
    'oldest' => ['label' => 'Oldest first', 'sql' => 'n.created_at ASC, n.id ASC'],
    'title_asc' => ['label' => 'Title A-Z', 'sql' => 'n.title ASC, n.id DESC'],
    'title_desc' => ['label' => 'Title Z-A', 'sql' => 'n.title DESC, n.id DESC'],
];
if (!array_key_exists($sort, $sortOptions)) {
    $sort = 'newest';
}
if (!in_array($perPage, [10, 25, 50], true)) {
    $perPage = 10;
}
$page = is_int($page) && $page > 0 ? $page : 1;

$where = ['n.user_id = :user_id'];
$parameters = ['user_id' => $userId];
$integerParameters = ['user_id'];
if ($projectId !== null) {
    $where[] = 'n.project_id = :project_id';
    $parameters['project_id'] = $projectId;
    $integerParameters[] = 'project_id';
}
if ($type !== '') {
    $where[] = 'n.note_type = :note_type';
    $parameters['note_type'] = $type;
}
if ($search !== '') {
    $where[] = '(n.title LIKE :search_title OR n.content LIKE :search_content OR p.title LIKE :search_project)';
    $pattern = '%' . $search . '%';
    $parameters['search_title'] = $pattern;
    $parameters['search_content'] = $pattern;
    $parameters['search_project'] = $pattern;
}

$whereSql = implode(' AND ', $where);
$fromSql = ' FROM notes n INNER JOIN projects p ON p.id = n.project_id AND p.user_id = n.user_id ';
$countStatement = $connection->prepare('SELECT COUNT(*)' . $fromSql . 'WHERE ' . $whereSql);
$countStatement->execute($parameters);
$totalNotes = (int) $countStatement->fetchColumn();
$totalPages = max(1, (int) ceil($totalNotes / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$listStatement = $connection->prepare(
    'SELECT n.id, n.title, n.content, n.note_type, n.created_at, n.updated_at, p.title AS project_title'
    . $fromSql . 'WHERE ' . $whereSql . ' ORDER BY ' . $sortOptions[$sort]['sql'] . ' LIMIT :limit OFFSET :offset'
);
foreach ($parameters as $key => $value) {
    $listStatement->bindValue(':' . $key, $value, in_array($key, $integerParameters, true) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$listStatement->bindValue(':limit', $perPage, PDO::PARAM_INT);
$listStatement->bindValue(':offset', $offset, PDO::PARAM_INT);
$listStatement->execute();
$notes = $listStatement->fetchAll();

function notes_page_url(int $targetPage): string
{
    $query = $_GET;
    $query['page'] = $targetPage;

    return app_url('modules/notes/index.php') . '?' . http_build_query($query);
}

$hasFilters = $search !== '' || $projectId !== null || $type !== '';
render_app_page_start('Research Notes', $user, 'notes');
render_app_feedback();
?>
<section class="page-heading"><div><p class="page-kicker">Research workspace</p><h2>Research notes</h2><p>Capture and organize project ideas, meetings, observations, and important details.</p></div><a class="primary-button" href="<?= e(app_url('modules/notes/create.php')) ?>">Add note</a></section>

<form class="filter-bar note-filter-bar" method="get" action="<?= e(app_url('modules/notes/index.php')) ?>">
    <div class="filter-field filter-search"><label for="note-search">Search</label><input id="note-search" name="q" type="search" value="<?= e($search) ?>" maxlength="100" placeholder="Title, content, project"></div>
    <div class="filter-field"><label for="note-project">Project</label><select id="note-project" name="project_id"><option value="">All projects</option><?php foreach ($projects as $project): ?><option value="<?= e($project['id']) ?>" <?= $projectId === (int) $project['id'] ? 'selected' : '' ?>><?= e($project['title']) ?></option><?php endforeach; ?></select></div>
    <div class="filter-field"><label for="note-type">Type</label><select id="note-type" name="type"><option value="">All types</option><?php foreach (NOTE_TYPES as $noteType): ?><option value="<?= e($noteType) ?>" <?= $type === $noteType ? 'selected' : '' ?>><?= e(note_label($noteType)) ?></option><?php endforeach; ?></select></div>
    <div class="filter-field"><label for="note-sort">Sort</label><select id="note-sort" name="sort"><?php foreach ($sortOptions as $sortValue => $option): ?><option value="<?= e($sortValue) ?>" <?= $sort === $sortValue ? 'selected' : '' ?>><?= e($option['label']) ?></option><?php endforeach; ?></select></div>
    <div class="filter-field filter-small"><label for="note-page-size">Per page</label><select id="note-page-size" name="per_page"><?php foreach ([10, 25, 50] as $size): ?><option value="<?= e($size) ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= e($size) ?></option><?php endforeach; ?></select></div>
    <div class="filter-actions"><button class="secondary-button" type="submit">Apply</button><a class="text-button" href="<?= e(app_url('modules/notes/index.php')) ?>">Reset</a></div>
</form>

<div class="list-summary"><p><?= e(number_format($totalNotes)) ?> note<?= $totalNotes === 1 ? '' : 's' ?></p></div>
<?php if ($notes === []): ?>
    <section class="dashboard-panel"><?php render_empty_state($hasFilters ? 'No notes match the selected filters.' : 'No research notes yet. Add your first project note.'); ?></section>
<?php else: ?>
    <section class="note-card-grid"><?php foreach ($notes as $note): ?><article class="note-card">
        <div class="note-card-meta"><span class="note-type-badge note-type-<?= e($note['note_type']) ?>"><?= e(note_label($note['note_type'])) ?></span><span><?= e(note_date_label($note['created_at'])) ?></span></div>
        <h3><a href="<?= e(app_url('modules/notes/show.php?id=' . $note['id'])) ?>"><?= e($note['title']) ?></a></h3>
        <p class="note-preview"><?= e($note['content']) ?></p>
        <p class="note-project-name"><?= e($note['project_title']) ?></p>
        <div class="card-actions"><a class="secondary-button button-small" href="<?= e(app_url('modules/notes/show.php?id=' . $note['id'])) ?>">View</a><a class="secondary-button button-small" href="<?= e(app_url('modules/notes/edit.php?id=' . $note['id'])) ?>">Edit</a><form method="post" action="<?= e(app_url('modules/notes/delete.php')) ?>" data-confirm="Delete this research note? This cannot be undone."><?= csrf_input() ?><input type="hidden" name="id" value="<?= e($note['id']) ?>"><button class="danger-button button-small" type="submit">Delete</button></form></div>
    </article><?php endforeach; ?></section>
    <?php if ($totalPages > 1): ?><nav class="pagination-nav" aria-label="Research note pages"><?php if ($page > 1): ?><a href="<?= e(notes_page_url($page - 1)) ?>">Previous</a><?php endif; ?><span>Page <?= e($page) ?> of <?= e($totalPages) ?></span><?php if ($page < $totalPages): ?><a href="<?= e(notes_page_url($page + 1)) ?>">Next</a><?php endif; ?></nav><?php endif; ?>
<?php endif; ?>
<?php render_app_page_end(); ?>
