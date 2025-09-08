<?php
/**
 * Ultra-Simple Installation Script for EmailThreads Plugin
 * 
 * This script works from any location and finds the Mautic installation automatically
 */

// Find Mautic installation
$mauticRoot = null;
$currentDir = __DIR__;

// Check current directory
if (file_exists($currentDir . '/app/bootstrap.php')) {
    $mauticRoot = $currentDir;
}
// Check parent directory
elseif (file_exists($currentDir . '/../app/bootstrap.php')) {
    $mauticRoot = $currentDir . '/..';
}
// Check if we're in docroot
elseif (file_exists($currentDir . '/../docroot/app/bootstrap.php')) {
    $mauticRoot = $currentDir . '/../docroot';
}

if (!$mauticRoot) {
    die("Error: Could not find Mautic installation. Please run this script from the plugin directory or Mautic root.\n");
}

echo "Found Mautic installation at: $mauticRoot\n";

// Load Mautic
require_once $mauticRoot . '/app/bootstrap.php';

try {
    echo "Installing EmailThreads Plugin...\n";
    
    // Get the container
    $container = \Mautic\CoreBundle\Factory\MauticFactory::getContainer();
    $entityManager = $container->get('doctrine.orm.entity_manager');
    
    // Create tables using raw SQL (more reliable)
    $connection = $entityManager->getConnection();
    
    echo "Creating mt_EmailThread table...\n";
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
    $connection->executeStatement($sql1);
    
    echo "Creating mt_EmailThreadMessage table...\n";
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
    $connection->executeStatement($sql2);
    
    echo "Inserting default configuration...\n";
    $configs = [
        ['emailthreads_enabled', '1'],
        ['emailthreads_domain', ''],
        ['emailthreads_auto_thread', '1'],
        ['emailthreads_thread_lifetime', '30'],
        ['emailthreads_include_unsubscribe', '1'],
        ['emailthreads_inject_previous_messages', '1'],
    ];
    
    foreach ($configs as $config) {
        $sql = "INSERT IGNORE INTO mt_config (param, value) VALUES (?, ?)";
        $connection->executeStatement($sql, $config);
    }
    
    echo "✅ EmailThreads Plugin installed successfully!\n";
    echo "You can now access the plugin from the Mautic admin interface.\n";
    
} catch (Exception $e) {
    echo "❌ Installation failed: " . $e->getMessage() . "\n";
    exit(1);
}
