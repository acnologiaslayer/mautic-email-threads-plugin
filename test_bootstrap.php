<?php
/**
 * Test script to verify Mautic bootstrap works
 */

echo "Testing Mautic bootstrap...\n";

// Check if bootstrap exists
if (!file_exists('app/config/bootstrap.php')) {
    die("❌ Bootstrap file not found at app/config/bootstrap.php\n");
}

echo "✅ Bootstrap file found\n";

// Try to load Mautic
try {
    require_once 'app/config/bootstrap.php';
    echo "✅ Mautic bootstrap loaded successfully\n";
    
    // Try to get container
    $container = \Mautic\CoreBundle\Factory\MauticFactory::getContainer();
    echo "✅ Mautic container loaded successfully\n";
    
    // Try to get entity manager
    $entityManager = $container->get('doctrine.orm.entity_manager');
    echo "✅ Entity manager loaded successfully\n";
    
    // Try to get connection
    $connection = $entityManager->getConnection();
    echo "✅ Database connection established\n";
    
    echo "\n🎉 All tests passed! Ready to install plugin.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
