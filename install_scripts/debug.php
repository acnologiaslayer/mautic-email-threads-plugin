<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "PHP Error Testing Page<br>";
echo "Current error_log setting: " . ini_get('error_log') . "<br>";
echo "Log errors enabled: " . (ini_get('log_errors') ? 'Yes' : 'No') . "<br>";
echo "Display errors enabled: " . (ini_get('display_errors') ? 'Yes' : 'No') . "<br>";
echo "Error reporting level: " . error_reporting() . "<br>";

// Test the plugin class loading
try {
    echo "Testing plugin class loading...<br>";
    require_once '/var/www/html/plugins/MauticEmailThreadsBundle/Controller/DefaultController.php';
    echo "DefaultController class loaded successfully<br>";
} catch (Exception $e) {
    echo "Error loading class: " . $e->getMessage() . "<br>";
}
?>
