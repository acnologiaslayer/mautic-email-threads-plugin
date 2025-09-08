<?php
/**
 * Test database connection and table structure
 */

echo "Testing EmailThreads Database Structure\n";
echo "=====================================\n\n";

// Get database credentials from environment
$dbHost = getenv('MAUTIC_DB_HOST') ?: 'db';
$dbPort = 3306;
$dbName = getenv('MAUTIC_DB_NAME') ?: 'mautic';
$dbUser = getenv('MAUTIC_DB_USER') ?: 'mautic';
$dbPassword = getenv('MAUTIC_DB_PASSWORD') ?: 'mauticpass';

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

// Check table structure
echo "Checking mt_EmailThread table structure:\n";
try {
    $stmt = $pdo->query("DESCRIBE mt_EmailThread");
    $columns = $stmt->fetchAll();
    
    echo "Columns in mt_EmailThread:\n";
    foreach ($columns as $column) {
        echo "  - {$column['Field']} ({$column['Type']}) - {$column['Null']} - {$column['Default']}\n";
    }
    echo "\n";
    
} catch (Exception $e) {
    echo "❌ Error checking mt_EmailThread: " . $e->getMessage() . "\n";
}

echo "Checking mt_EmailThreadMessage table structure:\n";
try {
    $stmt = $pdo->query("DESCRIBE mt_EmailThreadMessage");
    $columns = $stmt->fetchAll();
    
    echo "Columns in mt_EmailThreadMessage:\n";
    foreach ($columns as $column) {
        echo "  - {$column['Field']} ({$column['Type']}) - {$column['Null']} - {$column['Default']}\n";
    }
    echo "\n";
    
} catch (Exception $e) {
    echo "❌ Error checking mt_EmailThreadMessage: " . $e->getMessage() . "\n";
}

// Test inserting a simple record
echo "Testing simple insert into mt_EmailThread:\n";
try {
    $stmt = $pdo->prepare("INSERT INTO mt_EmailThread (thread_id, lead_id, subject, from_email, from_name, first_message_date, last_message_date, is_active, date_added) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $result = $stmt->execute([
        'test-thread-' . time(),
        1,
        'Test Subject',
        'test@example.com',
        'Test Sender',
        date('Y-m-d H:i:s'),
        date('Y-m-d H:i:s'),
        1,
        date('Y-m-d H:i:s')
    ]);
    
    if ($result) {
        $threadId = $pdo->lastInsertId();
        echo "✅ Successfully inserted test thread with ID: $threadId\n";
        
        // Test inserting into mt_EmailThreadMessage
        echo "Testing simple insert into mt_EmailThreadMessage:\n";
        $stmt = $pdo->prepare("INSERT INTO mt_EmailThreadMessage (thread_id, subject, content, from_email, from_name, date_sent, email_type, date_added) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $result = $stmt->execute([
            $threadId,
            'Test Message Subject',
            'Test message content',
            'test@example.com',
            'Test Sender',
            date('Y-m-d H:i:s'),
            'test',
            date('Y-m-d H:i:s')
        ]);
        
        if ($result) {
            $messageId = $pdo->lastInsertId();
            echo "✅ Successfully inserted test message with ID: $messageId\n";
        } else {
            echo "❌ Failed to insert test message\n";
        }
        
        // Clean up test data
        $pdo->exec("DELETE FROM mt_EmailThreadMessage WHERE thread_id = $threadId");
        $pdo->exec("DELETE FROM mt_EmailThread WHERE id = $threadId");
        echo "✅ Cleaned up test data\n";
        
    } else {
        echo "❌ Failed to insert test thread\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error testing insert: " . $e->getMessage() . "\n";
}

echo "\nTest completed.\n";
