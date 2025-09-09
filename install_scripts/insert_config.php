<?php
/**
 * Insert EmailThreads Plugin Configuration
 * 
 * This script inserts the default configuration values for the EmailThreads plugin.
 * Run this after the main tables have been created.
 */

echo "EmailThreads Plugin - Configuration Setup\n";
echo "========================================\n\n";

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
    exit(1);
}

// Check if mt_config table exists
echo "Checking for configuration table...\n";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'mt_config'");
    $tables = $stmt->fetchAll();
    
    if (empty($tables)) {
        echo "❌ mt_config table not found. This is unusual for a Mautic installation.\n";
        echo "The plugin tables were created successfully, but configuration cannot be inserted.\n";
        echo "You can manually configure the plugin through the Mautic admin interface.\n\n";
        echo "Plugin tables created:\n";
        echo "- mt_EmailThread\n";
        echo "- mt_EmailThreadMessage\n\n";
        echo "Next steps:\n";
        echo "1. Clear Mautic cache: php app/console cache:clear --env=prod\n";
        echo "2. Restart your web server or Docker container\n";
        echo "3. Go to your Mautic admin panel\n";
        echo "4. Look for 'Email Threads' in the main menu\n";
        echo "5. Configure the plugin in Settings → Plugins → Email Threads\n";
        exit(0);
    }
    
    echo "✅ Found mt_config table\n\n";
    
} catch (Exception $e) {
    echo "❌ Error checking for configuration table: " . $e->getMessage() . "\n";
    exit(1);
}

// Insert configuration
echo "Inserting plugin configuration...\n";
try {
    $configs = [
        ['emailthreads_enabled', '1'],
        ['emailthreads_domain', ''],
        ['emailthreads_auto_thread', '1'],
        ['emailthreads_thread_lifetime', '30'],
        ['emailthreads_include_unsubscribe', '1'],
        ['emailthreads_inject_previous_messages', '1'],
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO mt_config (param, value) VALUES (?, ?)");
    $insertedCount = 0;
    
    foreach ($configs as $config) {
        $result = $stmt->execute($config);
        if ($result) {
            $insertedCount++;
        }
    }
    
    echo "✅ Inserted $insertedCount configuration values\n\n";

} catch (Exception $e) {
    echo "❌ Failed to insert configuration: " . $e->getMessage() . "\n";
    echo "The plugin tables were created successfully, but configuration could not be inserted.\n";
    echo "You can manually configure the plugin through the Mautic admin interface.\n";
    exit(1);
}

// Verify installation
echo "Verifying installation...\n";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'mt_EmailThread%'");
    $tables = $stmt->fetchAll();
    echo "✅ Plugin tables verified (" . count($tables) . " tables found)\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM mt_config WHERE param LIKE 'emailthreads_%'");
    $configCount = $stmt->fetch()['count'];
    echo "✅ Configuration verified ($configCount settings found)\n\n";
    
} catch (Exception $e) {
    echo "⚠️  Verification failed: " . $e->getMessage() . "\n";
}

echo "🎉 Configuration setup completed successfully!\n\n";
echo "Next steps:\n";
echo "1. Clear Mautic cache: php app/console cache:clear --env=prod\n";
echo "2. Restart your web server or Docker container\n";
echo "3. Go to your Mautic admin panel\n";
echo "4. Look for 'Email Threads' in the main menu\n";
echo "5. Configure the plugin in Settings → Plugins → Email Threads\n\n";
echo "For support, contact: arc.mahir@gmail.com\n";
