<?php

// Load environment variables
$env = parse_ini_file(__DIR__ . '/.env');

$host = $env['DB_HOST'];
$port = $env['DB_PORT'];
$dbname = $env['DB_NAME'];
$user = $env['DB_USER'];
$pass = $env['DB_PASS'];

// JSON file path
$json_file = __DIR__ . '/shopify-history-complete.json';

try {
    // Connect to database
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Connected to database.\n";

    // Load JSON data
    if (!file_exists($json_file)) {
        throw new Exception("JSON file not found: $json_file");
    }

    $json_data = file_get_contents($json_file);
    $data = json_decode($json_data, true);

    if (!$data) {
        throw new Exception("Failed to decode JSON data");
    }

    echo "Loaded " . count($data) . " records from JSON.\n";
    echo "Importing data...\n";

    // Prepare statements
    $storeStmt = $pdo->prepare("
        INSERT INTO stores (store_name, store_url, shopify_store_id)
        VALUES (:store_name, :store_url, :shopify_store_id)
        ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)
    ");

    $categoryStmt = $pdo->prepare("SELECT id FROM event_categories WHERE slug = :slug");

    $eventStmt = $pdo->prepare("
        INSERT INTO events (store_id, event_category_id, event_date, raw_event, event_details, reason_category)
        VALUES (:store_id, :event_category_id, :event_date, :raw_event, :event_details, :reason_category)
    ");

    // Get all categories
    $categoryResult = $pdo->query("SELECT id, slug, name FROM event_categories");
    $categories = [];
    while ($row = $categoryResult->fetch(PDO::FETCH_ASSOC)) {
        $categories[$row['slug']] = $row['id'];
    }

    // Get existing stores
    $storeResult = $pdo->query("SELECT id, store_name FROM stores");
    $existingStores = [];
    while ($row = $storeResult->fetch(PDO::FETCH_ASSOC)) {
        $existingStores[$row['store_name']] = $row['id'];
    }

    $imported = 0;
    $skipped = 0;

    // Process each record
    foreach ($data as $row) {
        $storeName = trim($row['store_name'] ?? '');
        $storeUrl = $row['store_url'] ?? '';
        $eventRaw = trim($row['event'] ?? '');
        $eventDetails = trim($row['event_details'] ?? '');
        $dateStr = $row['date'] ?? '';

        if (empty($storeName)) {
            $skipped++;
            continue;
        }

        // Extract Shopify store ID from URL
        $shopifyStoreId = '';
        if (preg_match('/stores\/(\d+)/', $storeUrl, $matches)) {
            $shopifyStoreId = $matches[1];
        }

        // Get or create store
        $storeId = $existingStores[$storeName] ?? null;
        if ($storeId === null) {
            $storeStmt->execute([
                ':store_name' => $storeName,
                ':store_url' => $storeUrl,
                ':shopify_store_id' => $shopifyStoreId
            ]);
            $storeId = $pdo->lastInsertId();
            $existingStores[$storeName] = $storeId;
        }

        // Determine event category
        $eventLower = strtolower($eventRaw);
        $categorySlug = 'other';

        if (strpos($eventLower, 'uninstall') !== false) {
            $categorySlug = 'uninstalled';
        } elseif (strpos($eventLower, 'install') !== false && strpos($eventLower, 'uninstall') === false) {
            $categorySlug = 'installed';
        } elseif (strpos($eventLower, 'subscription charge activated') !== false) {
            $categorySlug = 'subscription-activated';
        } elseif (strpos($eventLower, 'subscription charge canceled') !== false) {
            $categorySlug = 'subscription-canceled';
        } elseif (strpos($eventLower, 'subscription charge expired') !== false) {
            $categorySlug = 'subscription-expired';
        } elseif (strpos($eventLower, 'trial') !== false) {
            if (strpos($eventLower, 'start') !== false) {
                $categorySlug = 'trial-started';
            } else {
                $categorySlug = 'trial-ended';
            }
        }

        $categoryId = $categories[$categorySlug] ?? $categories['other'];

        // Parse date
        $eventDate = null;
        if (!empty($dateStr)) {
            // Parse formats like "February 8, 2026 at 10:41 am"
            if (preg_match('/(\w+) (\d+), (\d{4}) at (\d+):(\d+)\s*(am|pm)?/i', $dateStr, $matches)) {
                $month = $matches[1];
                $day = $matches[2];
                $year = $matches[3];
                $hour = $matches[4];
                $min = $matches[5];
                $meridiem = strtolower($matches[6] ?? 'am');

                $months = [
                    'january' => 1, 'february' => 2, 'march' => 3, 'april' => 4,
                    'may' => 5, 'june' => 6, 'july' => 7, 'august' => 8,
                    'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12
                ];

                $monthNum = $months[strtolower($month)] ?? 1;

                if ($meridiem === 'pm' && $hour < 12) {
                    $hour += 12;
                } elseif ($meridiem === 'am' && $hour == 12) {
                    $hour = 0;
                }

                $eventDate = sprintf('%04d-%02d-%02d %02d:%02d:00', $year, $monthNum, $day, $hour, $min);
            }
        }

        // Categorize uninstall reason
        $reasonCategory = categorizeReason($eventDetails);

        // Insert event
        $eventStmt->execute([
            ':store_id' => $storeId,
            ':event_category_id' => $categoryId,
            ':event_date' => $eventDate,
            ':raw_event' => $eventRaw,
            ':event_details' => $eventDetails,
            ':reason_category' => $reasonCategory
        ]);

        $imported++;

        if ($imported % 100 == 0) {
            echo "  Imported $imported records...\n";
        }
    }

    echo "\n✅ Import complete!\n";
    echo "   - Imported: $imported records\n";
    echo "   - Skipped: $skipped records\n";

    // Show summary
    $stmt = $pdo->query("SELECT COUNT(*) FROM stores");
    echo "   - Total stores: " . $stmt->fetchColumn() . "\n";

    $stmt = $pdo->query("SELECT COUNT(*) FROM events");
    echo "   - Total events: " . $stmt->fetchColumn() . "\n";

} catch(PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
    exit(1);
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

function categorizeReason($details) {
    $details = strtolower(trim($details));

    if (empty($details)) {
        return 'no-reason';
    }

    // Common reason categories
    if (preg_match('/(don\'t|not|never|no longer) (use|using|need)/i', $details)) {
        return 'not-using';
    }

    if (preg_match('/(expensive|cost|price|too much|afford)/i', $details)) {
        return 'pricing';
    }

    if (preg_match('/(difficult|hard|confusing|complicated|set up|setup|configure)/i', $details)) {
        return 'usability';
    }

    if (preg_match('/(switch|changed|found another|alternative|competitor)/i', $details)) {
        return 'switched';
    }

    if (preg_match('/(bug|error|broken|not work|doesn\'t work|issue|problem)/i', $details)) {
        return 'technical-issue';
    }

    if (preg_match('/(feature|missing|doesn\'t have|limited|restriction)/i', $details)) {
        return 'missing-feature';
    }

    if (preg_match('/(test|trial|try|checking|testing)/i', $details)) {
        return 'testing';
    }

    if (preg_match('/(temporary|temp|short|while|now|moment)/i', $details)) {
        return 'temporary';
    }

    return 'other';
}
