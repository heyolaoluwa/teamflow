<?php
ini_set('display_errors', 0);
function send_http_status($code) {
    if (function_exists('http_response_code')) {
        http_response_code($code);
        return;
    }
    if (!headers_sent()) {
        $protocol = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1';
        $text = $code === 500 ? 'Internal Server Error' : ($code === 400 ? 'Bad Request' : 'OK');
        header($protocol . ' ' . $code . ' ' . $text, true, $code);
    }
}
function handle_error($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    send_http_status(500);
    echo json_encode(array('error' => "$errstr in $errfile line $errline"));
    exit();
}
function handle_exception($e) {
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    send_http_status(500);
    echo json_encode(array('error' => $e->getMessage() . ' in ' . $e->getFile() . ' line ' . $e->getLine()));
    exit();
}
function handle_shutdown() {
    $e = error_get_last();
    if ($e && in_array($e['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR))) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
            send_http_status(500);
        }
        echo json_encode(array('error' => $e['message'] . ' in ' . $e['file'] . ' line ' . $e['line']));
    }
}
set_error_handler('handle_error');
set_exception_handler('handle_exception');
register_shutdown_function('handle_shutdown');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('DB_HOST', 'localhost');
define('DB_USER', 'siuxgjee_teamflow');
define('DB_PASS', '+ei9pDN8,Zxv20kd');
define('DB_NAME', 'siuxgjee_teamflow');

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
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    if (is_array($json)) {
        return $json;
    }
    if (!empty($_POST)) {
        return $_POST;
    }
    return [];
}

function safe($conn, $val) {
    return $conn->real_escape_string(isset($val) ? $val : '');
}
