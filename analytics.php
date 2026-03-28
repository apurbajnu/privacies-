<?php
// Load JSON data
$json_file = __DIR__ . '/shopify-history-complete.json';
$json_data = file_get_contents($json_file);
$data = json_decode($json_data, true);

$total = count($data);

// Calculate stats
$stats = [
    'installs' => 0,
    'uninstalls' => 0,
    'uninstalls_with_reason' => 0,
    'churn_rate' => 0
];

$uninstall_reasons = [];
$monthly_data = [];
$store_patterns = [];
$event_types = [];

foreach ($data as $row) {
    $event = strtolower($row['event'] ?? '');
    $store = $row['store_name'] ?? 'Unknown';

    // Count event types
    if (!isset($event_types[$row['event']])) {
        $event_types[$row['event']] = 0;
    }
    $event_types[$row['event']]++;

    // Install/Uninstall counts
    if (strpos($event, 'install') !== false && strpos($event, 'uninstall') === false) {
        $stats['installs']++;
    } elseif (strpos($event, 'uninstall') !== false) {
        $stats['uninstalls']++;

        // Collect uninstall reasons
        $reason = trim($row['event_details'] ?? '');
        if (!empty($reason)) {
            $stats['uninstalls_with_reason']++;
            if (!isset($uninstall_reasons[$reason])) {
                $uninstall_reasons[$reason] = 0;
            }
            $uninstall_reasons[$reason]++;
        }
    }

    // Monthly trends
    if (preg_match('/(\w+) \d+, (\d{4})/', $row['date'] ?? '', $matches)) {
        $month = $matches[1] . ' ' . $matches[2];
        if (!isset($monthly_data[$month])) {
            $monthly_data[$month] = ['installs' => 0, 'uninstalls' => 0, 'net' => 0];
        }
        if (strpos($event, 'install') !== false && strpos($event, 'uninstall') === false) {
            $monthly_data[$month]['installs']++;
        } elseif (strpos($event, 'uninstall') !== false) {
            $monthly_data[$month]['uninstalls']++;
        }
    }

    // Store patterns
    if (!isset($store_patterns[$store])) {
        $store_patterns[$store] = ['total' => 0, 'installs' => 0, 'uninstalls' => 0];
    }
    $store_patterns[$store]['total']++;
    if (strpos($event, 'install') !== false && strpos($event, 'uninstall') === false) {
        $store_patterns[$store]['installs']++;
    } elseif (strpos($event, 'uninstall') !== false) {
        $store_patterns[$store]['uninstalls']++;
    }
}

// Calculate net monthly growth
foreach ($monthly_data as &$month) {
    $month['net'] = $month['installs'] - $month['uninstalls'];
}

// Calculate churn rate
if ($stats['installs'] > 0) {
    $stats['churn_rate'] = round(($stats['uninstalls'] / $stats['installs']) * 100, 1);
}

// Sort uninstall reasons by count
arsort($uninstall_reasons);

// Sort monthly data (newest first)
krsort($monthly_data);

// Sort store patterns by total events
uasort($store_patterns, function($a, $b) {
    return $b['total'] - $a['total'];
});

// Sort event types by count
arsort($event_types);

// Helper function
function getEventBadge($event) {
    $event = strtolower($event);
    if (strpos($event, 'install') !== false && strpos($event, 'uninstall') === false) {
        return '<span class="badge badge-install">Installed</span>';
    } elseif (strpos($event, 'uninstall') !== false) {
        return '<span class="badge badge-uninstall">Uninstalled</span>';
    } elseif (strpos($event, 'subscription') !== false) {
        return '<span class="badge badge-subscription">Subscription</span>';
    }
    return '<span class="badge">' . htmlspecialchars($event) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopify App History Analytics</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; }
        h1 { color: #333; margin-bottom: 20px; }
        h2 { color: #555; margin: 20px 0 10px; font-size: 18px; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stat-card .label { font-size: 12px; color: #888; text-transform: uppercase; }
        .stat-card .value { font-size: 32px; font-weight: bold; color: #333; margin-top: 5px; }
        .stat-card.installs .value { color: #5b8ff9; }
        .stat-card.uninstalls .value { color: #f56868; }
        .stat-card.churn .value { color: #ff9800; }

        .tabs { display: flex; gap: 5px; margin-bottom: 20px; }
        .tab { padding: 10px 20px; background: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; }
        .tab.active { background: #5b8ff9; color: white; }

        .view-section { display: none; }
        .view-section.active { display: block; }

        table { width: 100%; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        thead { background: #f8f9fa; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; font-size: 13px; }
        th { font-weight: 600; color: #555; }
        tr:hover { background: #f8f9fa; }

        .badge { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 500; }
        .badge-install { background: #e3f2e6; color: #5b8ff9; }
        .badge-uninstall { background: #ffebee; color: #f56868; }
        .badge-subscription { background: #fff3e0; color: #ff9800; }

        .search-box { padding: 10px 15px; border: 1px solid #ddd; border-radius: 5px; width: 300px; font-size: 14px; }

        .chart-bar { height: 30px; background: #5b8ff9; border-radius: 4px; display: flex; align-items: center; padding: 0 10px; color: white; font-size: 12px; }
        .chart-bar.uninstall { background: #f56868; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 Shopify App History Analytics</h1>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="label">Total Records</div>
                <div class="value"><?php echo number_format($total); ?></div>
            </div>
            <div class="stat-card installs">
                <div class="label">Installs</div>
                <div class="value"><?php echo number_format($stats['installs']); ?></div>
            </div>
            <div class="stat-card uninstalls">
                <div class="label">Uninstalls</div>
                <div class="value"><?php echo number_format($stats['uninstalls']); ?></div>
            </div>
            <div class="stat-card churn">
                <div class="label">Churn Rate</div>
                <div class="value"><?php echo $stats['churn_rate']; ?>%</div>
            </div>
        </div>

        <div class="tabs">
            <button class="tab active" onclick="showView('all-events')">All Events</button>
            <button class="tab" onclick="showView('uninstall-reasons')">Uninstall Reasons</button>
            <button class="tab" onclick="showView('monthly-trends')">Monthly Trends</button>
            <button class="tab" onclick="showView('store-patterns')">Store Patterns</button>
            <button class="tab" onclick="showView('event-types')">Event Types</button>
        </div>

        <!-- All Events View -->
        <div id="all-events" class="view-section active">
            <h2>All Events</h2>
            <input type="text" class="search-box" placeholder="Search store name..." id="searchBox" onkeyup="filterTable()">
            <table id="mainTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Store</th>
                        <th>Event</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['date']); ?></td>
                        <td><?php echo htmlspecialchars($row['store_name']); ?></td>
                        <td><?php echo getEventBadge($row['event']); ?></td>
                        <td><?php echo htmlspecialchars($row['event_details']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Uninstall Reasons View -->
        <div id="uninstall-reasons" class="view-section">
            <h2>Uninstall Reasons</h2>
            <table>
                <thead>
                    <tr>
                        <th>Reason</th>
                        <th>Count</th>
                        <th>%</th>
                        <th>Chart</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($uninstall_reasons as $reason => $count): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($reason ?: 'No reason given'); ?></td>
                        <td><?php echo number_format($count); ?></td>
                        <td><?php echo round(($count / $stats['uninstalls_with_reason']) * 100, 1); ?>%</td>
                        <td style="width: 50%">
                            <div class="chart-bar uninstall" style="width: <?php echo ($count / max($uninstall_reasons)) * 100; ?>%">
                                <?php echo number_format($count); ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Monthly Trends View -->
        <div id="monthly-trends" class="view-section">
            <h2>Monthly Trends</h2>
            <table>
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Installs</th>
                        <th>Uninstalls</th>
                        <th>Net</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($monthly_data as $month => $data): ?>
                    <tr>
                        <td><?php echo $month; ?></td>
                        <td style="color: #5b8ff9"><?php echo number_format($data['installs']); ?></td>
                        <td style="color: #f56868"><?php echo number_format($data['uninstalls']); ?></td>
                        <td><?php echo $data['net']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Store Patterns View -->
        <div id="store-patterns" class="view-section">
            <h2>Store Patterns (Repeat Stores)</h2>
            <table>
                <thead>
                    <tr>
                        <th>Store</th>
                        <th>Total Events</th>
                        <th>Installs</th>
                        <th>Uninstalls</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($store_patterns as $store => $data): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($store); ?></td>
                        <td><?php echo $data['total']; ?></td>
                        <td><?php echo $data['installs']; ?></td>
                        <td><?php echo $data['uninstalls']; ?></td>
                        <td>
                            <?php if ($data['uninstalls'] > $data['installs']): ?>
                                <span class="badge badge-uninstall">Churned</span>
                            <?php else: ?>
                                <span class="badge badge-install">Active</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Event Types View -->
        <div id="event-types" class="view-section">
            <h2>Event Types Breakdown</h2>
            <table>
                <thead>
                    <tr>
                        <th>Event Type</th>
                        <th>Count</th>
                        <th>%</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($event_types as $event => $count): ?>
                    <tr>
                        <td><?php echo getEventBadge($event); ?></td>
                        <td><?php echo number_format($count); ?></td>
                        <td><?php echo round(($count / $total) * 100, 1); ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function showView(viewId) {
            document.querySelectorAll('.view-section').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
            document.getElementById(viewId).classList.add('active');
            event.target.classList.add('active');
        }

        function filterTable() {
            const input = document.getElementById('searchBox');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('mainTable');
            const rows = table.getElementsByTagName('tr');

            for (let i = 1; i < rows.length; i++) {
                const storeCell = rows[i].getElementsByTagName('td')[1];
                if (storeCell) {
                    const txt = storeCell.textContent.toLowerCase();
                    rows[i].style.display = txt.indexOf(filter) > -1 ? '' : 'none';
                }
            }
        }
    </script>
</body>
</html>
