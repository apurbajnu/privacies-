<?php
require_once 'functions.php';

$uninstalled = getMostUninstalled(25);
$installed = getMostInstalled(25);
$stats = getEventStats();
$monthly = getMonthlyTrends();
$reasons = getUninstallReasons();
$monthlyData = getMonthlyComparisonByYear();

// Calculate totals
$installsCount = 0;
$uninstallsCount = 0;
foreach ($stats as $s) {
    $eventLower = strtolower($s['event']);
    if (strpos($eventLower, 'uninstall') !== false) {
        $uninstallsCount += $s['count'];
    } elseif (strpos($eventLower, 'install') !== false) {
        $installsCount += $s['count'];
    }
}

$db = getDb();
$totalStores = count($db->query("SELECT id FROM stores")->fetchAll());
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopify App Analytics</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">📊 Shopify App Analytics</h1>
            <p class="text-gray-600 mt-1">Track your app performance</p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm font-medium text-gray-500">Total Events</div>
                <div class="text-2xl font-bold text-gray-900 mt-1"><?= number_format(array_sum(array_column($stats, 'count'))) ?></div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm font-medium text-gray-500">Installs</div>
                <div class="text-2xl font-bold text-green-600 mt-1"><?= number_format($installsCount) ?></div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm font-medium text-gray-500">Uninstalls</div>
                <div class="text-2xl font-bold text-red-600 mt-1"><?= number_format($uninstallsCount) ?></div>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-sm font-medium text-gray-500">Unique Stores</div>
                <div class="text-2xl font-bold text-blue-600 mt-1"><?= number_format($totalStores) ?></div>
            </div>
        </div>

        <!-- Main Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Most Uninstalled -->
            <div class="bg-white rounded-lg shadow">
                <div class="bg-red-50 px-6 py-4 border-b border-red-100">
                    <h2 class="text-lg font-semibold text-red-800">🔴 Most Uninstalled Stores</h2>
                </div>
                <div class="p-4">
                    <?php renderTable($uninstalled, [
                        ['label' => 'Store Name', 'value' => function($r) { return htmlspecialchars($r['store_name']); }],
                        ['label' => 'Count', 'value' => function($r) { return "<span class='font-semibold text-red-600'>{$r['count']}</span>"; }]
                    ]) ?>
                </div>
            </div>

            <!-- Most Installed -->
            <div class="bg-white rounded-lg shadow">
                <div class="bg-green-50 px-6 py-4 border-b border-green-100">
                    <h2 class="text-lg font-semibold text-green-800">🟢 Most Installed Stores</h2>
                </div>
                <div class="p-4">
                    <?php renderTable($installed, [
                        ['label' => 'Store Name', 'value' => function($r) { return htmlspecialchars($r['store_name']); }],
                        ['label' => 'Count', 'value' => function($r) { return "<span class='font-semibold text-green-600'>{$r['count']}</span>"; }]
                    ]) ?>
                </div>
            </div>

            <!-- Uninstall Reasons -->
            <div class="bg-white rounded-lg shadow">
                <div class="bg-orange-50 px-6 py-4 border-b border-orange-100">
                    <h2 class="text-lg font-semibold text-orange-800">💬 Top Uninstall Reasons</h2>
                </div>
                <div class="p-4">
                    <?php renderTable($reasons, [
                        ['label' => 'Reason', 'value' => function($r) { return htmlspecialchars($r['reason']); }],
                        ['label' => 'Count', 'value' => function($r) { return "<span class='font-semibold text-orange-600'>{$r['count']}</span>"; }]
                    ]) ?>
                </div>
            </div>

            <!-- Monthly Trends Summary -->
            <div class="bg-white rounded-lg shadow">
                <div class="bg-blue-50 px-6 py-4 border-b border-blue-100">
                    <h2 class="text-lg font-semibold text-blue-800">📅 Recent Monthly Trends</h2>
                </div>
                <div class="p-4">
                    <?php
                    renderTable($monthly, [
                        ['label' => 'Month', 'value' => function($r) { return $r['month']; }],
                        ['label' => 'Installs', 'value' => function($r) { return "<span class='text-green-600 font-semibold'>{$r['installs']}</span>"; }],
                        ['label' => 'Uninstalls', 'value' => function($r) { return "<span class='text-red-600 font-semibold'>{$r['uninstalls']}</span>"; }],
                        ['label' => 'Net', 'value' => function($r) {
                            $net = $r['installs'] - $r['uninstalls'];
                            $color = $net >= 0 ? 'text-green-600' : 'text-red-600';
                            $prefix = $net >= 0 ? '+' : '';
                            return "<span class='$color font-semibold'>$prefix$net</span>";
                        }]
                    ]) ?>
                </div>
            </div>
        </div>

        <!-- Monthly Comparison by Year -->
        <div class="mb-8">
            <h2 class="text-xl font-bold text-gray-800 mb-4">📆 Monthly Install vs Uninstall Comparison</h2>

            <?php foreach ($monthlyData as $year => $months): ?>
                <div class="mb-8">
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 px-6 py-4 border-b border-blue-100 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="bg-blue-600 text-white px-4 py-2 rounded-lg font-bold text-lg"><?= $year ?></span>
                                <span class="text-sm text-gray-600">
                                    🟢 <?= number_format(array_sum(array_column($months, 'installs'))) ?> installs |
                                    🔴 <?= number_format(array_sum(array_column($months, 'uninstalls'))) ?> uninstalls
                                </span>
                            </div>
                        </div>
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Month</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-green-600 uppercase">Installs</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-red-600 uppercase">Uninstalls</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Net</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Churn</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-1/3">Visual</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php
                                for ($m = 12; $m >= 1; $m--):
                                    $monthData = $months[$m] ?? null;
                                    $monthName = date('M', mktime(0, 0, 0, $m, 1));
                                    $installs = $monthData['installs'] ?? 0;
                                    $uninstalls = $monthData['uninstalls'] ?? 0;
                                    $net = $installs - $uninstalls;
                                    $churnRate = $installs > 0 ? round(($uninstalls / $installs) * 100, 1) : 0;
                                    $maxVal = max($installs, $uninstalls, 1);
                                    $installWidth = ($installs / $maxVal) * 100;
                                    $uninstallWidth = ($uninstalls / $maxVal) * 100;
                                ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 text-sm font-medium text-gray-900"><?= $monthName ?></td>
                                    <td class="px-4 py-2 text-sm text-center text-green-600 font-semibold"><?= $installs ?></td>
                                    <td class="px-4 py-2 text-sm text-center text-red-600 font-semibold"><?= $uninstalls ?></td>
                                    <td class="px-4 py-2 text-sm text-center font-semibold <?= $net >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                        <?= $net >= 0 ? '+' : '' ?><?= $net ?>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-center <?= $churnRate < 70 ? 'text-green-600' : ($churnRate < 90 ? 'text-orange-600' : 'text-red-600') ?>">
                                        <?= $churnRate ?>%
                                    </td>
                                    <td class="px-4 py-2">
                                        <div class="flex h-5 rounded overflow-hidden bg-gray-100">
                                            <div class="bg-green-500" style="width: <?= $installWidth ?>%"></div>
                                            <div class="bg-red-500" style="width: <?= $uninstallWidth ?>%"></div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Overall Summary -->
        <div class="bg-gradient-to-r from-purple-50 to-pink-50 rounded-lg shadow p-6 mb-8">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">📊 Overall Summary (All Years)</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <?php
                $allInstalls = 0;
                $allUninstalls = 0;
                foreach ($monthlyData as $year => $months) {
                    $allInstalls += array_sum(array_column($months, 'installs'));
                    $allUninstalls += array_sum(array_column($months, 'uninstalls'));
                }
                ?>
                <div class="text-center">
                    <div class="text-2xl font-bold text-green-600"><?= number_format($allInstalls) ?></div>
                    <div class="text-sm text-gray-500">Total Installs</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-red-600"><?= number_format($allUninstalls) ?></div>
                    <div class="text-sm text-gray-500">Total Uninstalls</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold <?= ($allInstalls - $allUninstalls) >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                        <?= ($allInstalls - $allUninstalls) >= 0 ? '+' : '' ?><?= number_format($allInstalls - $allUninstalls) ?>
                    </div>
                    <div class="text-sm text-gray-500">Net Growth</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-600"><?= round(($allUninstalls / $allInstalls) * 100, 1) ?>%</div>
                    <div class="text-sm text-gray-500">Churn Rate</div>
                </div>
            </div>
        </div>

        <!-- Event Types -->
        <div class="bg-white rounded-lg shadow">
            <div class="bg-purple-50 px-6 py-4 border-b border-purple-100">
                <h2 class="text-lg font-semibold text-purple-800">📋 Event Types Breakdown</h2>
            </div>
            <div class="p-4">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <?php foreach ($stats as $stat): ?>
                        <div class="border border-gray-200 rounded-lg p-4 text-center">
                            <div class="text-2xl font-bold text-purple-600"><?= number_format($stat['count']) ?></div>
                            <div class="text-sm text-gray-600 mt-1"><?= htmlspecialchars($stat['event']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
