<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection, $user] = experiment_page_context();
$projects = experiment_projects($connection, $userId);
$datasets = experiment_datasets($connection, $userId);
$modelStatement = $connection->prepare('SELECT DISTINCT model_name FROM experiments WHERE user_id = :user_id ORDER BY model_name');
$modelStatement->execute(['user_id' => $userId]);
$models = $modelStatement->fetchAll(PDO::FETCH_COLUMN);

$search = trim(query_string('q'));
$projectId = filter_var($_GET['project_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$datasetId = filter_var($_GET['dataset_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$model = trim(query_string('model'));
$date = trim(query_string('date'));
$sort = strtolower(trim(query_string('sort', 'newest')));
$perPage = filter_var($_GET['per_page'] ?? 10, FILTER_VALIDATE_INT);
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);

$search = function_exists('mb_substr') ? mb_substr($search, 0, 100, 'UTF-8') : substr($search, 0, 100);
$model = function_exists('mb_substr') ? mb_substr($model, 0, 255, 'UTF-8') : substr($model, 0, 255);
$projectId = $projectId === false ? null : (int) $projectId;
$datasetId = $datasetId === false ? null : (int) $datasetId;
if ($date !== '' && !valid_iso_date($date)) {
    $date = '';
}
$sortOptions = [
    'newest' => ['label' => 'Newest first', 'sql' => 'e.experiment_date DESC, e.id DESC'],
    'oldest' => ['label' => 'Oldest first', 'sql' => 'e.experiment_date ASC, e.id ASC'],
    'title_asc' => ['label' => 'Title A-Z', 'sql' => 'e.experiment_name ASC, e.id DESC'],
    'title_desc' => ['label' => 'Title Z-A', 'sql' => 'e.experiment_name DESC, e.id DESC'],
    'accuracy_desc' => ['label' => 'Highest accuracy', 'sql' => 'e.accuracy IS NULL ASC, e.accuracy DESC, e.id DESC'],
    'accuracy_asc' => ['label' => 'Lowest accuracy', 'sql' => 'e.accuracy IS NULL ASC, e.accuracy ASC, e.id DESC'],
    'f1_desc' => ['label' => 'Highest F1', 'sql' => 'e.f1_score IS NULL ASC, e.f1_score DESC, e.id DESC'],
    'f1_asc' => ['label' => 'Lowest F1', 'sql' => 'e.f1_score IS NULL ASC, e.f1_score ASC, e.id DESC'],
];
if (!array_key_exists($sort, $sortOptions)) {
    $sort = 'newest';
}
if (!in_array($perPage, [10, 25, 50], true)) {
    $perPage = 10;
}
$page = is_int($page) && $page > 0 ? $page : 1;

$where = ['e.user_id = :user_id'];
$parameters = ['user_id' => $userId];
$integerParameters = ['user_id'];
if ($projectId !== null) {
    $where[] = 'e.project_id = :project_id';
    $parameters['project_id'] = $projectId;
    $integerParameters[] = 'project_id';
}
if ($datasetId !== null) {
    $where[] = 'e.dataset_id = :dataset_id';
    $parameters['dataset_id'] = $datasetId;
    $integerParameters[] = 'dataset_id';
}
if ($model !== '') {
    $where[] = 'e.model_name = :model_name';
    $parameters['model_name'] = $model;
}
if ($date !== '') {
    $where[] = 'e.experiment_date = :experiment_date';
    $parameters['experiment_date'] = $date;
}
if ($search !== '') {
    $where[] = '(e.experiment_name LIKE :search_name OR e.model_name LIKE :search_model OR e.model_type LIKE :search_type OR d.name LIKE :search_dataset OR e.notes LIKE :search_notes)';
    $pattern = '%' . $search . '%';
    foreach (['name', 'model', 'type', 'dataset', 'notes'] as $field) {
        $parameters['search_' . $field] = $pattern;
    }
}

$whereSql = implode(' AND ', $where);
$fromSql = ' FROM experiments e INNER JOIN projects p ON p.id = e.project_id AND p.user_id = e.user_id LEFT JOIN datasets d ON d.id = e.dataset_id AND d.user_id = e.user_id ';
$countStatement = $connection->prepare('SELECT COUNT(*)' . $fromSql . 'WHERE ' . $whereSql);
$countStatement->execute($parameters);
$totalExperiments = (int) $countStatement->fetchColumn();
$totalPages = max(1, (int) ceil($totalExperiments / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$listStatement = $connection->prepare(
    'SELECT e.id, e.experiment_name, e.model_name, e.model_type, e.experiment_date,
            e.accuracy, e.f1_score, p.title AS project_title, d.name AS dataset_name'
    . $fromSql . 'WHERE ' . $whereSql . ' ORDER BY ' . $sortOptions[$sort]['sql'] . ' LIMIT :limit OFFSET :offset'
);
foreach ($parameters as $key => $value) {
    $listStatement->bindValue(':' . $key, $value, in_array($key, $integerParameters, true) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$listStatement->bindValue(':limit', $perPage, PDO::PARAM_INT);
$listStatement->bindValue(':offset', $offset, PDO::PARAM_INT);
$listStatement->execute();
$experiments = $listStatement->fetchAll();

function experiments_page_url(int $targetPage): string
{
    $query = $_GET;
    $query['page'] = $targetPage;

    return app_url('modules/experiments/index.php') . '?' . http_build_query($query);
}

$hasFilters = $search !== '' || $projectId !== null || $datasetId !== null || $model !== '' || $date !== '';
render_app_page_start('Experiments', $user, 'experiments');
render_app_feedback();
?>
<section class="page-heading"><div><p class="page-kicker">Research progress</p><h2>Experiments</h2><p>Record ML/DL configurations, datasets, training settings, and evaluation results.</p></div><div class="heading-actions"><a class="secondary-button" href="<?= e(app_url('modules/experiments/compare.php')) ?>">Compare experiments</a><a class="primary-button" href="<?= e(app_url('modules/experiments/create.php')) ?>">Add experiment</a></div></section>

<form class="filter-bar experiment-filter-bar" method="get" action="<?= e(app_url('modules/experiments/index.php')) ?>">
    <div class="filter-field filter-search"><label for="experiment-search">Search</label><input id="experiment-search" name="q" type="search" value="<?= e($search) ?>" maxlength="100" placeholder="Experiment, model, dataset"></div>
    <div class="filter-field"><label for="experiment-project">Project</label><select id="experiment-project" name="project_id"><option value="">All projects</option><?php foreach ($projects as $project): ?><option value="<?= e($project['id']) ?>" <?= $projectId === (int) $project['id'] ? 'selected' : '' ?>><?= e($project['title']) ?></option><?php endforeach; ?></select></div>
    <div class="filter-field"><label for="experiment-dataset">Dataset</label><select id="experiment-dataset" name="dataset_id"><option value="">All datasets</option><?php foreach ($datasets as $dataset): ?><option value="<?= e($dataset['id']) ?>" <?= $datasetId === (int) $dataset['id'] ? 'selected' : '' ?>><?= e($dataset['name']) ?></option><?php endforeach; ?></select></div>
    <div class="filter-field"><label for="experiment-model">Model</label><select id="experiment-model" name="model"><option value="">All models</option><?php foreach ($models as $modelOption): ?><option value="<?= e($modelOption) ?>" <?= $model === $modelOption ? 'selected' : '' ?>><?= e($modelOption) ?></option><?php endforeach; ?></select></div>
    <div class="filter-field"><label for="experiment-date">Date</label><input id="experiment-date" name="date" type="date" value="<?= e($date) ?>"></div>
    <div class="filter-field"><label for="experiment-sort">Sort</label><select id="experiment-sort" name="sort"><?php foreach ($sortOptions as $sortValue => $option): ?><option value="<?= e($sortValue) ?>" <?= $sort === $sortValue ? 'selected' : '' ?>><?= e($option['label']) ?></option><?php endforeach; ?></select></div>
    <div class="filter-field filter-small"><label for="experiment-page-size">Per page</label><select id="experiment-page-size" name="per_page"><?php foreach ([10, 25, 50] as $size): ?><option value="<?= e($size) ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= e($size) ?></option><?php endforeach; ?></select></div>
    <div class="filter-actions"><button class="secondary-button" type="submit">Apply</button><a class="text-button" href="<?= e(app_url('modules/experiments/index.php')) ?>">Reset</a></div>
</form>

<div class="list-summary"><p><?= e(number_format($totalExperiments)) ?> experiment<?= $totalExperiments === 1 ? '' : 's' ?></p></div>
<?php if ($experiments === []): ?>
    <section class="dashboard-panel"><?php render_empty_state($hasFilters ? 'No experiments match the selected filters.' : 'No experiments yet. Add your first experiment record.'); ?></section>
<?php else: ?>
    <section class="dashboard-panel experiment-list-panel"><div class="table-scroll"><table class="dashboard-table experiment-table"><thead><tr><th>Experiment</th><th>Project / Dataset</th><th>Date</th><th>Accuracy</th><th>F1</th><th>Actions</th></tr></thead><tbody>
    <?php foreach ($experiments as $experiment): ?><tr>
        <td><a class="record-title record-link" href="<?= e(app_url('modules/experiments/show.php?id=' . $experiment['id'])) ?>"><?= e($experiment['experiment_name']) ?></a><span class="record-subtitle"><?= e($experiment['model_name']) ?><?= $experiment['model_type'] ? ' · ' . e($experiment['model_type']) : '' ?></span></td>
        <td><span class="record-title"><?= e($experiment['project_title']) ?></span><span class="record-subtitle"><?= e($experiment['dataset_name'] ?: 'Dataset unavailable') ?></span></td>
        <td><?= e(experiment_date_label($experiment['experiment_date'])) ?></td>
        <td><?= e(experiment_metric_label($experiment['accuracy'])) ?></td>
        <td><?= e(experiment_metric_label($experiment['f1_score'])) ?></td>
        <td><div class="table-actions"><a href="<?= e(app_url('modules/experiments/show.php?id=' . $experiment['id'])) ?>">View</a><a href="<?= e(app_url('modules/experiments/edit.php?id=' . $experiment['id'])) ?>">Edit</a><form method="post" action="<?= e(app_url('modules/experiments/delete.php')) ?>" data-confirm="Delete this experiment? This cannot be undone."><?= csrf_input() ?><input type="hidden" name="id" value="<?= e($experiment['id']) ?>"><button type="submit">Delete</button></form></div></td>
    </tr><?php endforeach; ?>
    </tbody></table></div></section>
    <?php if ($totalPages > 1): ?><nav class="pagination-nav" aria-label="Experiment pages"><?php if ($page > 1): ?><a href="<?= e(experiments_page_url($page - 1)) ?>">Previous</a><?php endif; ?><span>Page <?= e($page) ?> of <?= e($totalPages) ?></span><?php if ($page < $totalPages): ?><a href="<?= e(experiments_page_url($page + 1)) ?>">Next</a><?php endif; ?></nav><?php endif; ?>
<?php endif; ?>
<?php render_app_page_end(); ?>
