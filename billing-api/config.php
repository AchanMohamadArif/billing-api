<?php
// config.php

$db_host = getenv('DB_HOST') ?: '127.0.0.1';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') ?: '';
$db_name = getenv('DB_NAME') ?: 'shop_billing';

$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_errno) {
    http_response_code(500);
    echo json_encode(['error' => 'DB Connection failed', 'detail' => $mysqli->connect_error]);
    exit;
}
$mysqli->set_charset('utf8mb4');
?>
