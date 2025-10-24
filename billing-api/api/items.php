<?php
require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../common.php';

$method = $_SERVER['REQUEST_METHOD'];

// GET all items or single by id ?id=#
if ($method === 'GET') {
    if (isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $stmt = $mysqli->prepare("SELECT id, name, price, stock FROM items WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        sendJson($res ?: []);
    } else {
        $result = $mysqli->query("SELECT id, name, price, stock FROM items ORDER BY name");
        $items = $result->fetch_all(MYSQLI_ASSOC);
        sendJson($items);
    }
}

// POST - create
if ($method === 'POST') {
    $data = getJsonInput();
    $name = trim($data['name'] ?? '');
    $price = floatval($data['price'] ?? 0);
    $stock = intval($data['stock'] ?? 0);
    if ($name === '') { sendJson(['error'=>'Name required'], 400); }
    $stmt = $mysqli->prepare("INSERT INTO items (name, price, stock) VALUES (?, ?, ?)");
    $stmt->bind_param('sdi', $name, $price, $stock);
    if ($stmt->execute()) {
        sendJson(['success'=>true, 'id'=>$mysqli->insert_id], 201);
    } else {
        sendJson(['error'=>'DB insert failed'], 500);
    }
}

// PUT - update
if ($method === 'PUT') {
    $data = getJsonInput();
    $id = intval($data['id'] ?? 0);
    $name = trim($data['name'] ?? '');
    $price = floatval($data['price'] ?? 0);
    $stock = intval($data['stock'] ?? 0);
    if ($id <= 0) sendJson(['error'=>'ID required'], 400);
    $stmt = $mysqli->prepare("UPDATE items SET name=?, price=?, stock=? WHERE id=?");
    $stmt->bind_param('sdii', $name, $price, $stock, $id);
    if ($stmt->execute()) sendJson(['success'=>true]);
    else sendJson(['error'=>'DB update failed'], 500);
}

// DELETE - delete by id (use ?id=)
if ($method === 'DELETE') {
    parse_str(file_get_contents('php://input'), $delVars); // fallback
    $id = intval($_GET['id'] ?? $delVars['id'] ?? 0);
    if ($id <= 0) sendJson(['error'=>'ID required'], 400);
    $stmt = $mysqli->prepare("DELETE FROM items WHERE id = ?");
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) sendJson(['success'=>true]);
    else sendJson(['error'=>'DB delete failed'], 500);
}

sendJson(['error'=>'Unsupported method'], 405);
