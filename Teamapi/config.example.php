<?php
// Copy this file to config.php and fill in your cPanel database details
define('DB_HOST', 'localhost');
define('DB_USER', 'your_cpanel_username_teamflow');
define('DB_PASS', 'your_db_password');
define('DB_NAME', 'your_cpanel_username_teamflow');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

function db() {
    static $conn;
    if (!$conn) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            http_response_code(500);
            echo json_encode(['error' => 'DB connection failed: ' . $conn->connect_error]);
            exit();
        }
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit();
}

function body() {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}

function safe($conn, $val) {
    return $conn->real_escape_string($val ?? '');
}
