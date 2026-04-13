<?php
// =============================================================
// UMDC – Database Configuration
// =============================================================
// Change credentials via environment variables or this file.
// Never commit real credentials to version control.
// =============================================================

defined('UMDC_APP') or define('UMDC_APP', true);

$host = getenv('DB_HOST')  ?: 'localhost';
$db   = getenv('DB_NAME')  ?: 'umdc';
$user = getenv('DB_USER')  ?: 'root';
$pass = getenv('DB_PASS')  ?: 'Malijan321';  // Set via env in production

$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    http_response_code(503);
    die(json_encode(['success' => false, 'message' => 'Service temporarily unavailable.']));
}
