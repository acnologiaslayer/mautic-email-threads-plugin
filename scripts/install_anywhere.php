<?php
/**
 * Install EmailThreads Plugin - Works from anywhere
 * 
 * This script automatically finds Mautic and installs the plugin
 */

// Find Mautic installation by looking for bootstrap.php
$mauticRoot = null;
$searchPaths = [
    __DIR__,
    __DIR__ . '/..',
    __DIR__ . '/../..',
    '/var/www/html',
    '/var/www/html/docroot',
    getcwd(),
    getcwd() . '/..',
    getcwd() . '/../..'
];

foreach ($searchPaths as $path) {
    if (file_exists($path . '/app/config/bootstrap.php')) {
        $mauticRoot = $path;
        break;
    }
}

if (!$mauticRoot) {
    die("Error: Could not find Mautic installation. Please ensure you're in a Mautic directory.\n");
}

echo "Found Mautic at: $mauticRoot\n";

// Load Mautic
require_once $mauticRoot . '/app/config/bootstrap.php';

try {
    echo "Installing EmailThreads Plugin...\n";
    
    $container = \Mautic\CoreBundle\Factory\MauticFactory::getContainer();
    $connection = $container->get('doctrine.orm.entity_manager')->getConnection();
    
    // Create tables
    echo "Creating database tables...\n";
    
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
    
    // Insert config
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
    
    echo "✅ Installation complete! Clear cache and restart.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
