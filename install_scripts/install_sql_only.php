<?php
/**
 * SQL-Only Installation Script for EmailThreads Plugin
 * 
 * This script creates the database tables using raw SQL without needing
 * the full Mautic container, making it more reliable.
 */

echo "🚀 EmailThreads Plugin SQL-Only Installation\n";
echo "===========================================\n\n";

// Colors for output
$GREEN = "\033[0;32m";
$RED = "\033[0;31m";
$YELLOW = "\033[1;33m";
$NC = "\033[0m"; // No Color

function print_status($message) {
    global $GREEN, $NC;
    echo $GREEN . "✅ $message" . $NC . "\n";
}

function print_error($message) {
    global $RED, $NC;
    echo $RED . "❌ $message" . $NC . "\n";
}

function print_warning($message) {
    global $YELLOW, $NC;
    echo $YELLOW . "⚠️  $message" . $NC . "\n";
}

// Step 1: Get database connection details
echo "🔍 Step 1: Getting database connection details...\n";

// Try to read from Mautic's config files
$mauticRoot = null;
$searchPaths = [
    __DIR__ . '/..',
    __DIR__ . '/../..',
    __DIR__ . '/../../..',
    '/var/www/html',
    '/var/www/html/docroot',
];

foreach ($searchPaths as $path) {
    if (file_exists($path . '/app/config/parameters.php')) {
        $mauticRoot = $path;
        break;
    }
}

if (!$mauticRoot) {
    print_error("Could not find Mautic configuration");
    exit(1);
}

print_status("Found Mautic config at: $mauticRoot");

// Read database configuration from multiple sources
$dbHost = 'localhost';
$dbPort = 3306;
$dbName = 'mautic';
$dbUser = 'root';
$dbPassword = '';

// Try to read from parameters.php
if (file_exists($mauticRoot . '/app/config/parameters.php')) {
    $config = include $mauticRoot . '/app/config/parameters.php';
    $dbHost = $config['db_host'] ?? $dbHost;
    $dbPort = $config['db_port'] ?? $dbPort;
    $dbName = $config['db_name'] ?? $dbName;
    $dbUser = $config['db_user'] ?? $dbUser;
    $dbPassword = $config['db_password'] ?? $dbPassword;
}

// Try environment variables (common in Docker)
$dbHost = $_ENV['MAUTIC_DB_HOST'] ?? $dbHost;
$dbPort = $_ENV['MAUTIC_DB_PORT'] ?? $dbPort;
$dbName = $_ENV['MAUTIC_DB_NAME'] ?? $dbName;
$dbUser = $_ENV['MAUTIC_DB_USER'] ?? $dbUser;
$dbPassword = $_ENV['MAUTIC_DB_PASSWORD'] ?? $dbPassword;

// Try getenv as fallback
$dbHost = getenv('MAUTIC_DB_HOST') ?: $dbHost;
$dbPort = getenv('MAUTIC_DB_PORT') ?: $dbPort;
$dbName = getenv('MAUTIC_DB_NAME') ?: $dbName;
$dbUser = getenv('MAUTIC_DB_USER') ?: $dbUser;
$dbPassword = getenv('MAUTIC_DB_PASSWORD') ?: $dbPassword;

print_status("Database: $dbName@$dbHost:$dbPort");

// Step 2: Connect to database
echo "\n📦 Step 2: Connecting to database...\n";
try {
    $dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    print_status("Database connection established");
    
} catch (Exception $e) {
    print_error("Failed to connect to database: " . $e->getMessage());
    exit(1);
}

// Step 3: Create database tables
echo "\n🗄️  Step 3: Creating database tables...\n";
try {
    // Create EmailThread table
    $sql1 = "
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
    ";
    $pdo->exec($sql1);
    print_status("Created mt_EmailThread table");
    
    // Create EmailThreadMessage table
    $sql2 = "
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
    ";
    $pdo->exec($sql2);
    print_status("Created mt_EmailThreadMessage table");
    
} catch (Exception $e) {
    print_error("Failed to create tables: " . $e->getMessage());
    exit(1);
}

// Step 4: Insert configuration
echo "\n⚙️  Step 4: Inserting configuration...\n";
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
    foreach ($configs as $config) {
        $stmt->execute($config);
    }
    print_status("Configuration inserted");
    
} catch (Exception $e) {
    print_error("Failed to insert configuration: " . $e->getMessage());
    exit(1);
}

// Step 5: Clear cache
echo "\n🧹 Step 5: Clearing Mautic cache...\n";
try {
    $output = [];
    $returnCode = 0;
    exec("php $mauticRoot/app/console cache:clear --env=prod 2>&1", $output, $returnCode);
    
    if ($returnCode === 0) {
        print_status("Cache cleared successfully");
    } else {
        print_warning("Cache clear failed, but plugin should still work");
        echo "Output: " . implode("\n", $output) . "\n";
    }
    
} catch (Exception $e) {
    print_warning("Cache clear failed: " . $e->getMessage());
}

// Step 6: Cleanup temporary files
echo "\n🧹 Step 6: Cleaning up temporary files...\n";
$tempFiles = [
    __DIR__ . '/install_plugin.php',
    __DIR__ . '/install_docker_simple.php',
    __DIR__ . '/install_simple.php',
    __DIR__ . '/install_anywhere.php',
    __DIR__ . '/install_force.php',
    __DIR__ . '/test_bootstrap.php',
    __DIR__ . '/debug_paths.php',
    __DIR__ . '/install.sh',
    __DIR__ . '/install_docker.sh',
    __DIR__ . '/auto_install.php',
    __DIR__ . '/install_sql_only.php',
];

$cleanedCount = 0;
foreach ($tempFiles as $file) {
    if (file_exists($file)) {
        if (unlink($file)) {
            $cleanedCount++;
        }
    }
}

if ($cleanedCount > 0) {
    print_status("Cleaned up $cleanedCount temporary files");
} else {
    print_warning("No temporary files to clean up");
}

// Step 7: Final verification
echo "\n🔍 Step 7: Verifying installation...\n";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'mt_EmailThread%'");
    $tables = $stmt->fetchAll();
    
    if (count($tables) >= 2) {
        print_status("Database tables verified (" . count($tables) . " tables found)");
    } else {
        print_warning("Database verification incomplete");
    }
    
} catch (Exception $e) {
    print_warning("Database verification failed: " . $e->getMessage());
}

// Final success message
echo "\n🎉 Installation Complete!\n";
echo "========================\n";
print_status("EmailThreads Plugin installed successfully");
echo "\nNext steps:\n";
echo "1. Restart your web server or Docker container\n";
echo "2. Go to your Mautic admin panel\n";
echo "3. Look for 'Email Threads' in the main menu\n";
echo "4. Configure the plugin in Settings → Plugins → Email Threads\n";
echo "\nFor support, contact: arc.mahir@gmail.com\n";
