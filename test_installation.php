<?php
/**
 * Test script to verify EmailThreads plugin installation
 * This script tests the installation without making any changes
 */

// Get database connection details from environment variables
$dbHost = getenv('MAUTIC_DB_HOST') ?: 'db';
$dbPort = 3306;
$dbName = getenv('MAUTIC_DB_NAME') ?: 'mautic';
$dbUser = getenv('MAUTIC_DB_USER') ?: 'mautic';
$dbPassword = getenv('MAUTIC_DB_PASSWORD') ?: 'mauticpass';

echo "EmailThreads Plugin Installation Test\n";
echo "====================================\n";
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
    
    // Detect table prefix
    function detectTablePrefix($pdo) {
        $commonTables = ['users', 'leads', 'emails', 'campaigns', 'assets', 'categories'];
        
        foreach ($commonTables as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE 'mt_$table'");
            if ($stmt->rowCount() > 0) {
                return 'mt_';
            }
            
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                return '';
            }
            
            $prefixes = ['mautic_', 'mautic', 'mt'];
            foreach ($prefixes as $prefix) {
                $stmt = $pdo->query("SHOW TABLES LIKE '{$prefix}_{$table}'");
                if ($stmt->rowCount() > 0) {
                    return $prefix . '_';
                }
            }
        }
        
        return 'mt_';
    }
    
    $prefix = detectTablePrefix($pdo);
    echo "✓ Detected table prefix: '" . ($prefix ?: 'none') . "'\n";
    
    // Check if plugin tables exist
    $emailThreadTable = $prefix . 'EmailThread';
    $emailThreadMessageTable = $prefix . 'EmailThreadMessage';
    $configTable = $prefix . 'config';
    
    $tables = [$emailThreadTable, $emailThreadMessageTable, $configTable];
    $allTablesExist = true;
    
    foreach ($tables as $table) {
        $checkTable = "SHOW TABLES LIKE '$table'";
        $stmt = $pdo->query($checkTable);
        if ($stmt->rowCount() > 0) {
            echo "✓ Table $table exists\n";
        } else {
            echo "✗ Table $table missing\n";
            $allTablesExist = false;
        }
    }
    
    if ($allTablesExist) {
        // Check configuration
        $checkConfig = "SELECT COUNT(*) as count FROM $configTable WHERE param LIKE 'emailthreads_%'";
        $stmt = $pdo->query($checkConfig);
        $configCount = $stmt->fetchColumn();
        echo "✓ Found $configCount EmailThreads configuration entries\n";
        
        echo "\n🎉 Plugin is properly installed and ready to use!\n";
        echo "\nTo install/update the plugin, run:\n";
        echo "php install_mautic6.php\n";
    } else {
        echo "\n⚠️  Plugin tables are missing. Run the installation script:\n";
        echo "php install_mautic6.php\n";
    }
    
} catch (PDOException $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
