<?php
require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../common.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $stmt = $mysqli->prepare("SELECT id, name, contact FROM customers WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        sendJson($res ?: []);
    } else {
        $result = $mysqli->query("SELECT id, name, contact FROM customers ORDER BY name");
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        sendJson($rows);
    }
}

if ($method === 'POST') {
    $data = getJsonInput();
    $name = trim($data['name'] ?? '');
    $contact = trim($data['contact'] ?? '');
    if ($name === '') sendJson(['error'=>'Name required'], 400);
    $stmt = $mysqli->prepare("INSERT INTO customers (name, contact) VALUES (?, ?)");
    $stmt->bind_param('ss', $name, $contact);
    if ($stmt->execute()) sendJson(['success'=>true, 'id'=>$mysqli->insert_id], 201);
    else sendJson(['error'=>'DB insert failed'], 500);
}

sendJson(['error'=>'Unsupported method'], 405);
