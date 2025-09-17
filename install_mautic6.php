<?php
/**
 * EmailThreads Plugin Installation Script for Mautic 6.0.3
 * 
 * This script creates the required database tables and configuration
 * for the EmailThreads plugin in Mautic 6.0.3
 */

// Get database connection details from environment variables
$dbHost = getenv('MAUTIC_DB_HOST') ?: 'db';
$dbPort = 3306;
$dbName = getenv('MAUTIC_DB_NAME') ?: 'mautic';
$dbUser = getenv('MAUTIC_DB_USER') ?: 'mautic';
$dbPassword = getenv('MAUTIC_DB_PASSWORD') ?: 'mauticpass';

echo "EmailThreads Plugin Installation for Mautic 6.0.3\n";
echo "================================================\n";
echo "Database Host: $dbHost\n";
echo "Database Name: $dbName\n";
echo "Database User: $dbUser\n\n";

try {
    // Create PDO connection
    $dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo "✓ Connected to database successfully\n";
    
    // Check if mt_config table exists
    $checkConfigTable = "SHOW TABLES LIKE 'mt_config'";
    $stmt = $pdo->query($checkConfigTable);
    $configTableExists = $stmt->rowCount() > 0;
    
    if (!$configTableExists) {
        echo "Creating mt_config table...\n";
        $createConfigTable = "
            CREATE TABLE `mt_config` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `param` varchar(255) NOT NULL,
                `value` longtext,
                `date_added` datetime NOT NULL,
                `date_modified` datetime DEFAULT NULL,
                `created_by` int(11) DEFAULT NULL,
                `modified_by` int(11) DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_param` (`param`),
                KEY `date_added` (`date_added`),
                KEY `date_modified` (`date_modified`),
                KEY `created_by` (`created_by`),
                KEY `modified_by` (`modified_by`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        $pdo->exec($createConfigTable);
        echo "✓ Created mt_config table\n";
    } else {
        echo "✓ mt_config table already exists\n";
    }
    
    // Create EmailThread table
    echo "Creating mt_EmailThread table...\n";
    $createEmailThreadTable = "
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
            KEY `thread_subject_lead_idx` (`subject`, `lead_id`),
            KEY `date_added` (`date_added`),
            KEY `date_modified` (`date_modified`),
            KEY `created_by` (`created_by`),
            KEY `modified_by` (`modified_by`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($createEmailThreadTable);
    echo "✓ Created mt_EmailThread table\n";
    
    // Create EmailThreadMessage table
    echo "Creating mt_EmailThreadMessage table...\n";
    $createEmailThreadMessageTable = "
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
            KEY `date_added` (`date_added`),
            KEY `date_modified` (`date_modified`),
            KEY `created_by` (`created_by`),
            KEY `modified_by` (`modified_by`),
            CONSTRAINT `FK_EmailThreadMessage_thread` FOREIGN KEY (`thread_id`) REFERENCES `mt_EmailThread` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $pdo->exec($createEmailThreadMessageTable);
    echo "✓ Created mt_EmailThreadMessage table\n";
    
    // Insert default configuration
    echo "Inserting default configuration...\n";
    $configValues = [
        'emailthreads_enabled' => '1',
        'emailthreads_domain' => '',
        'emailthreads_auto_thread' => '1',
        'emailthreads_thread_lifetime' => '30',
        'emailthreads_include_unsubscribe' => '1',
        'emailthreads_inject_previous_messages' => '1'
    ];
    
    foreach ($configValues as $param => $value) {
        $insertConfig = "
            INSERT INTO mt_config (param, value, date_added, date_modified) 
            VALUES (?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE value = VALUES(value), date_modified = NOW()
        ";
        $stmt = $pdo->prepare($insertConfig);
        $stmt->execute([$param, $value]);
    }
    echo "✓ Inserted default configuration\n";
    
    // Verify installation
    echo "\nVerifying installation...\n";
    
    // Check tables exist
    $tables = ['mt_EmailThread', 'mt_EmailThreadMessage', 'mt_config'];
    foreach ($tables as $table) {
        $checkTable = "SHOW TABLES LIKE '$table'";
        $stmt = $pdo->query($checkTable);
        if ($stmt->rowCount() > 0) {
            echo "✓ Table $table exists\n";
        } else {
            echo "✗ Table $table missing\n";
        }
    }
    
    // Check configuration
    $checkConfig = "SELECT COUNT(*) as count FROM mt_config WHERE param LIKE 'emailthreads_%'";
    $stmt = $pdo->query($checkConfig);
    $configCount = $stmt->fetchColumn();
    echo "✓ Found $configCount EmailThreads configuration entries\n";
    
    echo "\n🎉 EmailThreads Plugin installation completed successfully!\n";
    echo "\nNext steps:\n";
    echo "1. Clear Mautic cache: php /var/www/html/bin/console cache:clear\n";
    echo "2. Test the plugin by sending an email\n";
    echo "3. Check the error logs if you encounter any issues\n";
    
} catch (PDOException $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
