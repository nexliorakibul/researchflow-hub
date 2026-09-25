<?php

declare(strict_types=1);

require_once __DIR__ . '/_common.php';

[$userId, $connection, $user] = gap_page_context();

$summaryStatement = $connection->prepare(
    'SELECT COUNT(*) AS total_gaps,
            COALESCE(SUM(priority = \'high\'), 0) AS high_priority_gaps,
            COALESCE(SUM(status = \'addressed\'), 0) AS addressed_gaps,
            COALESCE(SUM(status IN (\'identified\', \'investigating\')), 0) AS open_gaps
     FROM research_gaps
     WHERE user_id = :user_id'
);
$summaryStatement->execute(['user_id' => $userId]);
$summary = $summaryStatement->fetch();

if (!is_array($summary)) {
    throw new RuntimeException('Unable to load research gap statistics.');
}

$distributionStatement = $connection->prepare(
    'SELECT COALESCE(gap_type, \'other\') AS gap_type, COUNT(*) AS total
     FROM research_gaps
     WHERE user_id = :user_id
     GROUP BY COALESCE(gap_type, \'other\')'
);
$distributionStatement->execute(['user_id' => $userId]);
$storedDistribution = [];
foreach ($distributionStatement->fetchAll() as $row) {
    $storedDistribution[(string) $row['gap_type']] = (int) $row['total'];
}

$typeLabels = [];
$typeCounts = [];
foreach (GAP_TYPES as $type) {
    $typeLabels[] = gap_label($type);
    $typeCounts[] = $storedDistribution[$type] ?? 0;
}

$totalGaps = (int) $summary['total_gaps'];
$statistics = [
    ['key' => 'total', 'label' => 'Total gaps', 'value' => $totalGaps, 'note' => 'All saved research gaps'],
    ['key' => 'high', 'label' => 'High priority', 'value' => (int) $summary['high_priority_gaps'], 'note' => 'Priority is High'],
    ['key' => 'addressed', 'label' => 'Addressed', 'value' => (int) $summary['addressed_gaps'], 'note' => 'Status is Addressed'],
    ['key' => 'open', 'label' => 'Open gaps', 'value' => (int) $summary['open_gaps'], 'note' => 'Identified or Investigating'],
];

render_app_page_start('Research Gap Statistics', $user, 'gap-statistics');
render_app_feedback();
?>
<nav class="breadcrumb-nav" aria-label="Breadcrumb"><a href="<?= e(app_url('modules/research-gaps/index.php')) ?>">Research Gaps</a><span aria-hidden="true">/</span><span aria-current="page">Statistics</span></nav>

<section class="page-heading"><div><p class="page-kicker">Research progress</p><h2>Research gap dashboard</h2><p>Database-driven totals and gap-type distribution for your research records.</p></div><a class="secondary-button" href="<?= e(app_url('modules/research-gaps/index.php')) ?>">View gaps</a></section>

<section class="gap-statistics-grid" aria-label="Research gap summary">
    <?php foreach ($statistics as $statistic): ?>
        <article class="gap-stat-card" data-stat="<?= e($statistic['key']) ?>"><p><?= e($statistic['label']) ?></p><strong><?= e(number_format($statistic['value'])) ?></strong><span><?= e($statistic['note']) ?></span></article>
    <?php endforeach; ?>
</section>

<?php if ($totalGaps === 0): ?>
    <section class="dashboard-panel gap-statistics-empty"><?php render_empty_state('No research gaps are available for statistics yet.'); ?><a class="primary-button" href="<?= e(app_url('modules/research-gaps/create.php')) ?>">Add first gap</a></section>
<?php else: ?>
    <div class="gap-statistics-layout">
        <section class="dashboard-panel gap-chart-panel" aria-labelledby="gap-chart-title">
            <div class="panel-heading"><h2 id="gap-chart-title">Distribution by gap type</h2><p>Counts calculated from your stored research gaps</p></div>
            <div class="gap-chart-wrap"><canvas id="gap-type-chart" role="img" aria-label="Doughnut chart showing research gaps grouped by type" data-labels="<?= e(json_encode($typeLabels, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) ?>" data-values="<?= e(json_encode($typeCounts, JSON_THROW_ON_ERROR)) ?>"></canvas></div>
            <p class="chart-fallback" id="gap-chart-fallback" hidden>The chart could not be loaded. The same values are available in the distribution table.</p>
        </section>

        <section class="dashboard-panel" aria-labelledby="gap-distribution-title">
            <div class="panel-heading"><h2 id="gap-distribution-title">Type totals</h2><p>Accessible count summary</p></div>
            <div class="table-scroll"><table class="dashboard-table gap-distribution-table"><thead><tr><th>Gap type</th><th>Count</th></tr></thead><tbody>
                <?php foreach (GAP_TYPES as $index => $type): ?><tr><td><?= e($typeLabels[$index]) ?></td><td><?= e(number_format($typeCounts[$index])) ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
        </section>
    </div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script src="<?= e(app_url('assets/js/gap-statistics.js')) ?>" defer></script>
<?php render_app_page_end(); ?>
