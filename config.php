<?php
// config.php

// Database settings
$DB_HOST = 'localhost';
$DB_NAME = 'ilmsuit_gatepass';
$DB_USER = 'ilmsuit_26user';
$DB_PASS = 'XLN73ftgYrPq0Ok5';

// Local development fallback
if (isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST'] === 'localhost:8000' || $_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1')) {
    $DB_NAME = 'gate_pass_system';
    $DB_USER = 'root';
    $DB_PASS = '';
}

// Timezone (for expiry, logs, etc.)
date_default_timezone_set('Asia/Kolkata');

// Start session for staff login, etc.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// PDO connection
try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    // Force MySQL session to run in Indian Standard Time (IST)
    $pdo->exec("SET time_zone = '+05:30'");
} catch (PDOException $e) {
    die('Database connection failed');
}
