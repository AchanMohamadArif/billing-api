<?php
require_once __DIR__ . '/../cors.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../common.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // fetch all or by id
    if (isset($_GET['id'])) {
        $id = intval($_GET['id']);
        // bill
        $stmt = $mysqli->prepare("SELECT b.*, c.name AS customer_name, c.contact AS customer_contact FROM bills b LEFT JOIN customers c ON b.customer_id = c.id WHERE b.id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $bill = $stmt->get_result()->fetch_assoc();
        if (!$bill) sendJson([], 404);

        // items
        $stmt2 = $mysqli->prepare("SELECT bi.id, bi.item_id, i.name, i.price, bi.quantity, bi.subtotal FROM bill_items bi JOIN items i ON bi.item_id = i.id WHERE bi.bill_id = ?");
        $stmt2->bind_param('i', $id);
        $stmt2->execute();
        $items = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
        $bill['items'] = $items;
        sendJson($bill);
    }

    // list fetch with optional search (name/date) and paid_status filter
    $q = $_GET['q'] ?? '';
    $status = $_GET['status'] ?? '';
    $where = [];
    $params = [];
    $types = '';

    if ($q !== '') {
        // search in customer name or date
        $where[] = "(c.name LIKE CONCAT('%', ?, '%') OR DATE(b.date) = ?)";
        $params[] = $q;
        $params[] = $q;
        $types .= 'ss';
    }
    if ($status !== '') {
        $where[] = "b.paid_status = ?";
        $params[] = $status;
        $types .= 's';
    }
    $sql = "SELECT b.id, b.date, b.total, b.paid_status, b.amount_given, b.amount_returned, c.name AS customer_name
            FROM bills b LEFT JOIN customers c ON b.customer_id = c.id";
    if ($where) $sql .= " WHERE " . implode(' AND ', $where);
    $sql .= " ORDER BY b.date DESC LIMIT 1000"; // pagination handled on frontend later

    if ($params) {
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        sendJson($res);
    } else {
        $res = $mysqli->query($sql)->fetch_all(MYSQLI_ASSOC);
        sendJson($res);
    }
}

if ($method === 'POST') {
    // Create new bill
    $data = getJsonInput();
    // expected: customer_id (or customer object), items: [{item_id, quantity}], total, amount_given, amount_returned, paid_status
    $customer_id = isset($data['customer_id']) && intval($data['customer_id'])>0 ? intval($data['customer_id']) : null;
    $items = $data['items'] ?? [];
    $total = floatval($data['total'] ?? 0);
    $amount_given = floatval($data['amount_given'] ?? 0);
    $amount_returned = floatval($data['amount_returned'] ?? 0);
    $paid_status = ($data['paid_status'] ?? 'paid') === 'unpaid' ? 'unpaid' : 'paid';

    if (empty($items) || $total <= 0) {
        sendJson(['error'=>'No items or invalid total'], 400);
    }

    // start transaction
    $mysqli->begin_transaction();
    try {
        $stmt = $mysqli->prepare("INSERT INTO bills (customer_id, total, amount_given, amount_returned, paid_status) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('iddds', $customer_id, $total, $amount_given, $amount_returned, $paid_status);
        $stmt->execute();
        $bill_id = $mysqli->insert_id;

        // insert bill_items & reduce stock
        $stmtBI = $mysqli->prepare("INSERT INTO bill_items (bill_id, item_id, quantity, subtotal) VALUES (?, ?, ?, ?)");
        $stmtStock = $mysqli->prepare("UPDATE items SET stock = stock - ? WHERE id = ? AND stock >= ?");
        foreach ($items as $it) {
            $item_id = intval($it['item_id']);
            $qty = intval($it['quantity']);
            $subtotal = floatval($it['subtotal']);
            $stmtBI->bind_param('iiid', $bill_id, $item_id, $qty, $subtotal);
            $stmtBI->execute();

            // reduce stock (fail-safe)
            $stmtStock->bind_param('iii', $qty, $item_id, $qty);
            if (!$stmtStock->execute() || $stmtStock->affected_rows === 0) {
                // could be zero stock; but we'll allow negative? Here we rollback
                throw new Exception("Insufficient stock for item id $item_id");
            }
        }

        $mysqli->commit();
        sendJson(['success'=>true, 'bill_id'=>$bill_id], 201);
    } catch (Exception $e) {
        $mysqli->rollback();
        sendJson(['error'=>'Transaction failed', 'detail'=>$e->getMessage()], 500);
    }
}

sendJson(['error'=>'Unsupported method'], 405);
