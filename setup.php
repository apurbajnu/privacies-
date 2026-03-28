<?php

// Load environment variables
$env = parse_ini_file(__DIR__ . '/.env');

$host = $env['DB_HOST'];
$port = $env['DB_PORT'];
$dbname = $env['DB_NAME'];
$user = $env['DB_USER'];
$pass = $env['DB_PASS'];

try {
    // Connect to MySQL
    $pdo = new PDO("mysql:host=$host;port=$port", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");

    echo "Database '$dbname' created/selected.\n";

    // Drop existing tables if needed (for clean setup)
    $pdo->exec("DROP TABLE IF EXISTS events");
    $pdo->exec("DROP TABLE IF EXISTS stores");
    $pdo->exec("DROP TABLE IF EXISTS event_categories");

    // 1. Create stores table
    $sql = "CREATE TABLE stores (
        id INT AUTO_INCREMENT PRIMARY KEY,
        store_name VARCHAR(255) NOT NULL,
        store_url VARCHAR(500),
        shopify_store_id VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_store_name (store_name),
        UNIQUE KEY unique_store (store_name, store_url)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo "✓ Table 'stores' created.\n";

    // 2. Create event_categories table
    $sql = "CREATE TABLE event_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(100) NOT NULL UNIQUE,
        description TEXT,
        is_negative BOOLEAN DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo "✓ Table 'event_categories' created.\n";

    // 3. Create events table
    $sql = "CREATE TABLE events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        store_id INT NOT NULL,
        event_category_id INT NOT NULL,
        event_date DATETIME,
        raw_event TEXT,
        event_details TEXT,
        reason_category VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
        FOREIGN KEY (event_category_id) REFERENCES event_categories(id),
        INDEX idx_event_date (event_date),
        INDEX idx_store_id (store_id),
        INDEX idx_category (event_category_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo "✓ Table 'events' created.\n";

    // Insert default event categories
    $categories = [
        ['Installed', 'installed', 'App installed by store', 0],
        ['Uninstalled', 'uninstalled', 'App uninstalled by store', 1],
        ['Subscription Activated', 'subscription-activated', 'Subscription charge activated', 0],
        ['Subscription Canceled', 'subscription-canceled', 'Subscription charge canceled', 1],
        ['Subscription Expired', 'subscription-expired', 'Subscription charge expired', 1],
        ['Trial Started', 'trial-started', 'Free trial started', 0],
        ['Trial Ended', 'trial-ended', 'Free trial ended', 1],
        ['Plan Upgraded', 'plan-upgraded', 'Store upgraded to higher plan', 0],
        ['Plan Downgraded', 'plan-downgraded', 'Store downgraded plan', 1],
        ['Payment Failed', 'payment-failed', 'Payment processing failed', 1],
        ['Payment Recovered', 'payment-recovered', 'Payment recovered after failure', 0],
        ['Other', 'other', 'Other events', 0]
    ];

    $stmt = $pdo->prepare("INSERT INTO event_categories (name, slug, description, is_negative) VALUES (?, ?, ?, ?)");
    foreach ($categories as $cat) {
        $stmt->execute($cat);
    }
    echo "✓ Default event categories inserted.\n";

    echo "\n✅ Database setup complete!\n";
    echo "\nNext step: Run 'php import.php' to import your JSON data.\n";

} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
