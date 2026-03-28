<?php

// Database connection
function getDb() {
    static $pdo = null;
    if ($pdo === null) {
        $env = parse_ini_file(__DIR__ . '/.env');
        $pdo = new PDO(
            "mysql:host={$env['DB_HOST']};dbname={$env['DB_NAME']};charset=utf8mb4",
            $env['DB_USER'],
            $env['DB_PASS']
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }
    return $pdo;
}

// Get most frequently uninstalled stores
function getMostUninstalled($limit = 20) {
    $db = getDb();
    $stmt = $db->prepare("
        SELECT s.store_name, COUNT(*) as count
        FROM event_logs el
        JOIN stores s ON el.store_id = s.id
        JOIN events e ON el.event_id = e.id
        WHERE e.event LIKE '%Uninstall%'
        GROUP BY s.store_name
        ORDER BY count DESC
        LIMIT :limit
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

// Get most frequently installed stores
function getMostInstalled($limit = 20) {
    $db = getDb();
    $stmt = $db->prepare("
        SELECT s.store_name, COUNT(*) as count
        FROM event_logs el
        JOIN stores s ON el.store_id = s.id
        JOIN events e ON el.event_id = e.id
        WHERE e.event LIKE '%Install%' AND e.event NOT LIKE '%Uninstall%'
        GROUP BY s.store_name
        ORDER BY count DESC
        LIMIT :limit
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

// Get event stats
function getEventStats() {
    $db = getDb();
    $stmt = $db->query("
        SELECT
            e.event,
            COUNT(*) as count
        FROM event_logs el
        JOIN events e ON el.event_id = e.id
        GROUP BY e.event
        ORDER BY count DESC
    ");
    return $stmt->fetchAll();
}

// Get monthly trends
function getMonthlyTrends() {
    $db = getDb();
    $stmt = $db->query("
        SELECT
            DATE_FORMAT(event_date, '%Y-%m') as month,
            SUM(CASE WHEN e.event LIKE '%Install%' AND e.event NOT LIKE '%Uninstall%' THEN 1 ELSE 0 END) as installs,
            SUM(CASE WHEN e.event LIKE '%Uninstall%' THEN 1 ELSE 0 END) as uninstalls
        FROM event_logs el
        JOIN events e ON el.event_id = e.id
        WHERE event_date IS NOT NULL
        GROUP BY DATE_FORMAT(event_date, '%Y-%m')
        ORDER BY month DESC
        LIMIT 12
    ");
    return $stmt->fetchAll();
}

// Render table helper
function renderTable($data, $columns, $emptyMessage = 'No data') {
    if (empty($data)) {
        return "<div class='text-gray-500 text-center py-4'>$emptyMessage</div>";
    }
    ?>
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <?php foreach ($columns as $col): ?>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <?= $col['label'] ?>
                    </th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            <?php foreach ($data as $row): ?>
                <tr class="hover:bg-gray-50">
                    <?php foreach ($columns as $col): ?>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?= $col['value']($row) ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

// Get all uninstall reasons
function getUninstallReasons() {
    $db = getDb();
    $stmt = $db->query("
        SELECT
            SUBSTRING_INDEX(SUBSTRING_INDEX(el.event_details, ' ', 20), ' ', 20) as reason,
            COUNT(*) as count
        FROM event_logs el
        JOIN events e ON el.event_id = e.id
        WHERE e.event LIKE '%Uninstall%' AND el.event_details != ''
        GROUP BY reason
        ORDER BY count DESC
        LIMIT 20
    ");
    return $stmt->fetchAll();
}

// Get monthly comparison data (install vs uninstall) by year
function getMonthlyComparisonByYear($limitYears = 3) {
    $db = getDb();
    $stmt = $db->query("
        SELECT
            YEAR(event_date) as year,
            MONTH(event_date) as month,
            MONTHNAME(event_date) as month_name,
            SUM(CASE WHEN e.event LIKE '%Install%' AND e.event NOT LIKE '%Uninstall%' THEN 1 ELSE 0 END) as installs,
            SUM(CASE WHEN e.event LIKE '%Uninstall%' THEN 1 ELSE 0 END) as uninstalls
        FROM event_logs el
        JOIN events e ON el.event_id = e.id
        WHERE event_date IS NOT NULL
        GROUP BY YEAR(event_date), MONTH(event_date), MONTHNAME(event_date)
        ORDER BY year DESC, month DESC
    ");
    $data = $stmt->fetchAll();

    // Organize by year
    $result = [];
    foreach ($data as $row) {
        $year = $row['year'];
        if (!isset($result[$year])) {
            $result[$year] = [];
        }
        $result[$year][$row['month']] = $row;
    }

    return $result;
}
