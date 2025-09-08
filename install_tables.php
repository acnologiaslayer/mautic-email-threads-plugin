<?php
/**
 * EmailThreads Plugin Database Installation
 * 
 * Simple script to create the required database tables.
 * Run this script and provide your database credentials when prompted.
 */

echo "EmailThreads Plugin - Database Installation\n";
echo "==========================================\n\n";

// Get database credentials from user
echo "Please enter your database connection details:\n\n";

$dbHost = readline("Database Host [localhost]: ");
if (empty($dbHost)) $dbHost = 'localhost';

$dbPort = readline("Database Port [3306]: ");
if (empty($dbPort)) $dbPort = 3306;

$dbName = readline("Database Name [mautic]: ");
if (empty($dbName)) $dbName = 'mautic';

$dbUser = readline("Database User [root]: ");
if (empty($dbUser)) $dbUser = 'root';

$dbPassword = readline("Database Password: ");

echo "\nConnecting to database...\n";

// Connect to database
try {
    $dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "✅ Connected to database successfully\n\n";
} catch (Exception $e) {
    echo "❌ Failed to connect to database: " . $e->getMessage() . "\n";
    echo "Please check your credentials and try again.\n";
    exit(1);
}

// Create tables
echo "Creating database tables...\n";

try {
    // Create EmailThread table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `mt_EmailThread` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `thread_id` varchar(255) NOT NULL,
            `lead_id` int(11) NOT NULL,
            `subject` varchar(500) DEFAULT NULL,
            `from_email` varchar(255) DEFAULT NULL,
            `from_name` varchar(255) DEFAULT NULL,
            `first_message_date` datetime DEFAULT NULL,
            `last_message_date` datetime DEFAULT NULL,
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `date_added` datetime NOT NULL,
            `date_modified` datetime DEFAULT NULL,
            `created_by` int(11) DEFAULT NULL,
            `modified_by` int(11) DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_thread_id` (`thread_id`),
            KEY `thread_id_idx` (`thread_id`),
            KEY `thread_lead_idx` (`lead_id`),
            KEY `thread_active_idx` (`is_active`),
            KEY `thread_last_message_idx` (`last_message_date`),
            KEY `thread_subject_lead_idx` (`subject`, `lead_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Created mt_EmailThread table\n";

    // Create EmailThreadMessage table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `mt_EmailThreadMessage` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `thread_id` int(11) NOT NULL,
            `email_stat_id` int(11) DEFAULT NULL,
            `subject` varchar(500) DEFAULT NULL,
            `content` longtext,
            `from_email` varchar(255) DEFAULT NULL,
            `from_name` varchar(255) DEFAULT NULL,
            `date_sent` datetime DEFAULT NULL,
            `email_type` varchar(50) DEFAULT NULL,
            `date_added` datetime NOT NULL,
            `date_modified` datetime DEFAULT NULL,
            `created_by` int(11) DEFAULT NULL,
            `modified_by` int(11) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `message_thread_idx` (`thread_id`),
            KEY `message_stat_idx` (`email_stat_id`),
            KEY `message_date_sent_idx` (`date_sent`),
            KEY `message_email_type_idx` (`email_type`),
            CONSTRAINT `FK_EmailThreadMessage_thread` FOREIGN KEY (`thread_id`) REFERENCES `mt_EmailThread` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Created mt_EmailThreadMessage table\n";

    // Insert default configuration
    $configs = [
        ['emailthreads_enabled', '1'],
        ['emailthreads_domain', ''],
        ['emailthreads_auto_thread', '1'],
        ['emailthreads_thread_lifetime', '30'],
        ['emailthreads_include_unsubscribe', '1'],
        ['emailthreads_inject_previous_messages', '1'],
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO mt_config (param, value) VALUES (?, ?)");
    foreach ($configs as $config) {
        $stmt->execute($config);
    }
    echo "✅ Inserted default configuration\n";

    // Verify installation
    $stmt = $pdo->query("SHOW TABLES LIKE 'mt_EmailThread%'");
    $tables = $stmt->fetchAll();
    echo "✅ Verified installation (" . count($tables) . " tables created)\n\n";

} catch (Exception $e) {
    echo "❌ Failed to create tables: " . $e->getMessage() . "\n";
    exit(1);
}

echo "🎉 Installation completed successfully!\n\n";
echo "Next steps:\n";
echo "1. Clear Mautic cache: php app/console cache:clear --env=prod\n";
echo "2. Restart your web server or Docker container\n";
echo "3. Go to your Mautic admin panel\n";
echo "4. Look for 'Email Threads' in the main menu\n";
echo "5. Configure the plugin in Settings → Plugins → Email Threads\n\n";
echo "For support, contact: arc.mahir@gmail.com\n";
