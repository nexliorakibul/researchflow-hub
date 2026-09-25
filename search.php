<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/app-layout.php';

$userId = require_authenticated_user();
$connection = get_database_connection();
$userStatement = $connection->prepare('SELECT name, email FROM users WHERE id = :id LIMIT 1');
$userStatement->execute(['id' => $userId]);
$user = $userStatement->fetch();
if (!is_array($user)) {
    logout_user();
    flash_message('error', 'Your account session is no longer valid.');
    redirect(app_url('login.php'));
}

$rawQuery = $_GET['q'] ?? '';
$query = is_string($rawQuery) ? trim($rawQuery) : '';
$query = function_exists('mb_substr') ? mb_substr($query, 0, 100, 'UTF-8') : substr($query, 0, 100);
$perPage = filter_var($_GET['per_page'] ?? 10, FILTER_VALIDATE_INT);
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
if (!in_array($perPage, [10, 25, 50], true)) {
    $perPage = 10;
}
$page = is_int($page) && $page > 0 ? $page : 1;

$results = [];
$totalResults = 0;
$totalPages = 1;

$searchSelects = [
    "SELECT 'project' AS result_type, p.id, p.title,
            COALESCE(p.research_area, '') AS subtitle,
            COALESCE(p.description, '') AS excerpt,
            p.updated_at
     FROM projects p
     WHERE p.user_id = :project_user
       AND CONCAT_WS(' ', p.title, p.research_area, p.description) LIKE :project_search",
    "SELECT 'resource' AS result_type, r.id, r.title,
            CONCAT_WS(' · ', NULLIF(r.authors, ''), NULLIF(r.resource_type, '')) AS subtitle,
            CONCAT_WS(' · ', NULLIF(r.tags, ''), NULLIF(r.description, ''), NULLIF(r.personal_notes, '')) AS excerpt,
            r.updated_at
     FROM resources r
     WHERE r.user_id = :resource_user
       AND CONCAT_WS(' ', r.title, r.authors, r.tags, r.research_area, r.description, r.personal_notes, r.doi) LIKE :resource_search",
    "SELECT 'paper' AS result_type, pa.id, pa.title,
            COALESCE(pa.authors, '') AS subtitle,
            CONCAT_WS(' · ', NULLIF(pa.venue, ''), NULLIF(pa.keywords, ''), NULLIF(pa.summary, '')) AS excerpt,
            pa.updated_at
     FROM papers pa
     WHERE pa.user_id = :paper_user
       AND CONCAT_WS(' ', pa.title, pa.authors, pa.keywords, pa.venue, pa.research_area, pa.summary, pa.personal_notes, pa.doi) LIKE :paper_search",
    "SELECT 'dataset' AS result_type, d.id, d.name AS title,
            COALESCE(d.domain, '') AS subtitle,
            CONCAT_WS(' · ', NULLIF(d.source, ''), NULLIF(d.description, ''), NULLIF(d.notes, ''), NULLIF(d.license, '')) AS excerpt,
            d.updated_at
     FROM datasets d
     WHERE d.user_id = :dataset_user
       AND CONCAT_WS(' ', d.name, d.domain, d.source, d.description, d.notes, d.license) LIKE :dataset_search",
    "SELECT 'gap' AS result_type, g.id, g.gap_title AS title,
            COALESCE(g.gap_type, '') AS subtitle,
            CONCAT_WS(' · ', NULLIF(g.description, ''), NULLIF(g.evidence, ''), NULLIF(g.potential_solution, ''), NULLIF(g.notes, '')) AS excerpt,
            g.updated_at
     FROM research_gaps g
     WHERE g.user_id = :gap_user
       AND CONCAT_WS(' ', g.gap_title, g.gap_type, g.description, g.evidence, g.potential_solution, g.notes) LIKE :gap_search",
    "SELECT 'experiment' AS result_type, e.id, e.experiment_name AS title,
            CONCAT_WS(' · ', NULLIF(e.model_name, ''), NULLIF(e.model_type, '')) AS subtitle,
            CONCAT_WS(' · ', NULLIF(e.preprocessing, ''), NULLIF(e.feature_engineering, ''), NULLIF(e.optimizer, ''), NULLIF(e.notes, '')) AS excerpt,
            e.updated_at
     FROM experiments e
     WHERE e.user_id = :experiment_user
       AND CONCAT_WS(' ', e.experiment_name, e.model_name, e.model_type, e.preprocessing, e.feature_engineering, e.optimizer, e.notes) LIKE :experiment_search",
];

if ($query !== '') {
    $pattern = '%' . $query . '%';
    $parameters = [];
    foreach (['project', 'resource', 'paper', 'dataset', 'gap', 'experiment'] as $type) {
        $parameters[$type . '_user'] = $userId;
        $parameters[$type . '_search'] = $pattern;
    }
    $unionSql = implode(' UNION ALL ', $searchSelects);
    $countStatement = $connection->prepare('SELECT COUNT(*) FROM (' . $unionSql . ') AS global_results');
    $countStatement->execute($parameters);
    $totalResults = (int) $countStatement->fetchColumn();
    $totalPages = max(1, (int) ceil($totalResults / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $listStatement = $connection->prepare(
        'SELECT result_type, id, title, subtitle, excerpt, updated_at
         FROM (' . $unionSql . ') AS global_results
         ORDER BY updated_at DESC, title ASC
         LIMIT :limit OFFSET :offset'
    );
    foreach ($parameters as $key => $value) {
        $listStatement->bindValue(':' . $key, $value, str_ends_with($key, '_user') ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $listStatement->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $listStatement->bindValue(':offset', $offset, PDO::PARAM_INT);
    $listStatement->execute();
    $results = $listStatement->fetchAll();
}

function global_search_type_label(string $type): string
{
    return [
        'project' => 'Research Project',
        'resource' => 'Resource',
        'paper' => 'Research Paper',
        'dataset' => 'Dataset',
        'gap' => 'Research Gap',
        'experiment' => 'Experiment',
    ][$type] ?? 'Record';
}

function global_search_result_url(string $type, int $id): string
{
    $paths = [
        'project' => 'modules/projects/show.php',
        'resource' => 'modules/resources/show.php',
        'paper' => 'modules/papers/show.php',
        'dataset' => 'modules/datasets/show.php',
        'gap' => 'modules/research-gaps/show.php',
        'experiment' => 'modules/experiments/show.php',
    ];

    return app_url(($paths[$type] ?? 'search.php') . '?id=' . $id);
}

function global_search_excerpt(mixed $value, int $maximum = 220): string
{
    $text = trim((string) preg_replace('/\s+/u', ' ', (string) $value));
    if ($text === '') {
        return 'No additional details available.';
    }
    $length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    if ($length <= $maximum) {
        return $text;
    }
    $short = function_exists('mb_substr') ? mb_substr($text, 0, $maximum, 'UTF-8') : substr($text, 0, $maximum);

    return rtrim($short) . '…';
}

function global_search_page_url(int $targetPage): string
{
    $query = $_GET;
    $query['page'] = $targetPage;

    return app_url('search.php') . '?' . http_build_query($query);
}

render_app_page_start('Global Search', $user, 'search');
render_app_feedback();
?>
<section class="page-heading page-heading-compact"><div><p class="page-kicker">Research workspace</p><h2>Global search</h2><p>Search your projects, resources, papers and authors, datasets, research gaps, experiments, tags, and keywords.</p></div></section>

<form class="global-search-form" method="get" action="<?= e(app_url('search.php')) ?>" role="search">
    <div class="form-field"><label for="global-search-query">Search term</label><input id="global-search-query" name="q" type="search" value="<?= e($query) ?>" maxlength="100" placeholder="Search your research workspace" required autofocus></div>
    <div class="form-field"><label for="global-search-page-size">Results per page</label><select id="global-search-page-size" name="per_page"><?php foreach ([10, 25, 50] as $size): ?><option value="<?= e($size) ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= e($size) ?></option><?php endforeach; ?></select></div>
    <button class="primary-button" type="submit">Search</button>
</form>

<?php if ($query === ''): ?>
    <section class="dashboard-panel search-state-panel"><?php render_empty_state('Enter a search term to find records across your research workspace.'); ?></section>
<?php else: ?>
    <div class="list-summary"><p><?= e(number_format($totalResults)) ?> result<?= $totalResults === 1 ? '' : 's' ?> for “<?= e($query) ?>”</p></div>
    <?php if ($results === []): ?>
        <section class="dashboard-panel search-state-panel"><?php render_empty_state('No records match this search term.'); ?></section>
    <?php else: ?>
        <section class="search-result-list" aria-label="Global search results">
            <?php foreach ($results as $result): ?><article class="search-result-card">
                <div class="search-result-meta"><span class="search-type-badge search-type-<?= e($result['result_type']) ?>"><?= e(global_search_type_label($result['result_type'])) ?></span><time datetime="<?= e(substr((string) $result['updated_at'], 0, 10)) ?>">Updated <?= e(date('M j, Y', strtotime((string) $result['updated_at']))) ?></time></div>
                <h3><a href="<?= e(global_search_result_url($result['result_type'], (int) $result['id'])) ?>"><?= e($result['title']) ?></a></h3>
                <?php if ($result['subtitle'] !== ''): ?><p class="search-result-subtitle"><?= e(str_replace('_', ' ', $result['subtitle'])) ?></p><?php endif; ?>
                <p class="search-result-excerpt"><?= e(global_search_excerpt($result['excerpt'])) ?></p>
            </article><?php endforeach; ?>
        </section>
        <?php if ($totalPages > 1): ?><nav class="pagination-nav" aria-label="Global search result pages"><?php if ($page > 1): ?><a href="<?= e(global_search_page_url($page - 1)) ?>">Previous</a><?php endif; ?><span>Page <?= e($page) ?> of <?= e($totalPages) ?></span><?php if ($page < $totalPages): ?><a href="<?= e(global_search_page_url($page + 1)) ?>">Next</a><?php endif; ?></nav><?php endif; ?>
    <?php endif; ?>
<?php endif; ?>
<?php render_app_page_end(); ?>
