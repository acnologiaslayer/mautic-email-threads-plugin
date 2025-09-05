<?php
/**
 * Database Table Creation Script for EmailThreads Plugin
 * 
 * This script creates the required database tables for the EmailThreads plugin
 * using Doctrine's SchemaTool.
 * 
 * Usage:
 * 1. Place this file in your Mautic root directory
 * 2. Run: php create_tables.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\Setup;

// EmailThreads Plugin Entity Classes
use MauticPlugin\MauticEmailThreadsBundle\Entity\EmailThread;
use MauticPlugin\MauticEmailThreadsBundle\Entity\EmailThreadMessage;

try {
    echo "Creating EmailThreads Plugin Database Tables...\n";
    
    // Check if we're in a Mautic environment
    if (!file_exists(__DIR__ . '/app/config/local.php')) {
        echo "Error: This script must be run from the Mautic root directory.\n";
        echo "Please copy this file to your Mautic root directory and run it from there.\n";
        exit(1);
    }
    
    // Load Mautic's bootstrap
    require_once __DIR__ . '/app/bootstrap.php';
    
    // Get the entity manager from Mautic's container
    $container = \Mautic\CoreBundle\Factory\MauticFactory::getContainer();
    $entityManager = $container->get('doctrine.orm.entity_manager');
    
    // Define the entities
    $entities = [
        EmailThread::class,
        EmailThreadMessage::class,
    ];
    
    // Get metadata for the entities
    $metadata = [];
    foreach ($entities as $entityClass) {
        $metadata[] = $entityManager->getMetadataFactory()->getMetadataFor($entityClass);
    }
    
    // Create schema tool
    $schemaTool = new SchemaTool($entityManager);
    
    // Create the tables
    echo "Creating tables...\n";
    $schemaTool->createSchema($metadata);
    
    echo "✅ Database tables created successfully!\n";
    echo "Tables created:\n";
    echo "- mt_EmailThread\n";
    echo "- mt_EmailThreadMessage\n";
    
    // Insert default configuration
    echo "Inserting default configuration...\n";
    $connection = $entityManager->getConnection();
    
    $configValues = [
        'emailthreads_enabled' => '1',
        'emailthreads_domain' => '',
        'emailthreads_auto_thread' => '1',
        'emailthreads_thread_lifetime' => '30',
        'emailthreads_include_unsubscribe' => '1',
        'emailthreads_inject_previous_messages' => '1',
    ];
    
    foreach ($configValues as $param => $value) {
        $sql = "INSERT IGNORE INTO mt_config (param, value) VALUES (?, ?)";
        $connection->executeStatement($sql, [$param, $value]);
    }
    
    echo "✅ Default configuration inserted successfully!\n";
    echo "\nPlugin installation complete! You can now use the EmailThreads plugin.\n";
    
} catch (Exception $e) {
    echo "❌ Error creating tables: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
