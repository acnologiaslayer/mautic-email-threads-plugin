<?php
/**
 * Force Installation Script - Works from anywhere
 * 
 * This script will find Mautic and install the plugin no matter what
 */

echo "🔍 Searching for Mautic installation...\n";

// Try to find Mautic by looking for common files
$mauticRoot = null;
$possiblePaths = [
    '/var/www/html',
    '/var/www/html/docroot',
    getcwd(),
    __DIR__,
    __DIR__ . '/..',
    __DIR__ . '/../..',
    __DIR__ . '/../../..',
];

foreach ($possiblePaths as $path) {
    echo "Checking: $path\n";
    
    // Check for bootstrap.php
    if (file_exists($path . '/app/bootstrap.php')) {
        $mauticRoot = $path;
        echo "✅ Found Mautic at: $mauticRoot\n";
        break;
    }
    
    // Check for composer.json (Mautic indicator)
    if (file_exists($path . '/composer.json')) {
        $content = file_get_contents($path . '/composer.json');
        if (strpos($content, 'mautic/core-bundle') !== false) {
            $mauticRoot = $path;
            echo "✅ Found Mautic via composer.json at: $mauticRoot\n";
            break;
        }
    }
}

if (!$mauticRoot) {
    echo "❌ Could not find Mautic installation automatically.\n";
    echo "Please specify the Mautic root directory:\n";
    echo "Usage: php install_force.php /path/to/mautic\n";
    
    if (isset($argv[1])) {
        $mauticRoot = $argv[1];
        if (!file_exists($mauticRoot . '/app/bootstrap.php')) {
            die("❌ Invalid Mautic directory: $mauticRoot\n");
        }
    } else {
        exit(1);
    }
}

echo "🚀 Installing EmailThreads Plugin...\n";

// Load Mautic
require_once $mauticRoot . '/app/bootstrap.php';

try {
    $container = \Mautic\CoreBundle\Factory\MauticFactory::getContainer();
    $connection = $container->get('doctrine.orm.entity_manager')->getConnection();
    
    echo "📊 Creating database tables...\n";
    
    // Create EmailThread table
    $connection->executeStatement("
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
    $connection->executeStatement("
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
    
    // Insert configuration
    echo "⚙️  Inserting configuration...\n";
    $configs = [
        ['emailthreads_enabled', '1'],
        ['emailthreads_domain', ''],
        ['emailthreads_auto_thread', '1'],
        ['emailthreads_thread_lifetime', '30'],
        ['emailthreads_include_unsubscribe', '1'],
        ['emailthreads_inject_previous_messages', '1'],
    ];
    
    foreach ($configs as $config) {
        $connection->executeStatement("INSERT IGNORE INTO mt_config (param, value) VALUES (?, ?)", $config);
    }
    echo "✅ Configuration inserted\n";
    
    echo "\n🎉 Installation complete!\n";
    echo "Next steps:\n";
    echo "1. Clear cache: php $mauticRoot/app/console cache:clear --env=prod\n";
    echo "2. Restart your web server/container\n";
    echo "3. Go to Mautic admin → Email Threads\n";
    
} catch (Exception $e) {
    echo "❌ Installation failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
