<?php
// Database configuration

// Check if $_ENV is available (e.g., from Dotenv)
if (isset($_ENV['DB_HOST'])) {
    define('DB_HOST', $_ENV['DB_HOST']);
    define('DB_USER', $_ENV['DB_USER']);
    define('DB_PASS', $_ENV['DB_PASS']);
    define('DB_NAME', $_ENV['DB_NAME']);
} else {
    // Fallback to getenv() for environment variables set by the web server or system
    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
    define('DB_USER', getenv('DB_USER') ?: 'root');
    define('DB_PASS', getenv('DB_PASS') ?: '');
    define('DB_NAME', getenv('DB_NAME') ?: 'leesage_db');
}

// Create connection
function getDBConnection() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=leesage_db", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Set MySQL timezone to Asia/Kolkata
        $pdo->exec("SET time_zone = '+05:30'");
        
        return $pdo;
    } catch(PDOException $e) {
        throw new PDOException("Connection failed: " . $e->getMessage(), (int)$e->getCode());
    }
}
?>
