<?php
// public/services/orders/index.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../src/db.php';

// Allow CORS for frontend access (same host)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

try {
    if ($method === 'GET') {
        // GET single or list, filter by mesa or cliente
        if (isset($_GET['id'])) {
            $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
            $stmt->execute([$_GET['id']]);
            $order = $stmt->fetch();
            if (!$order) respond(['error' => 'Order not found'], 404);
            respond($order);
        } else {
            $sql = 'SELECT * FROM orders';
            $params = [];
            $where = [];
            if (!empty($_GET['mesa'])) {
                $where[] = 'mesa = ?';
                $params[] = $_GET['mesa'];
            }
            if (!empty($_GET['cliente'])) {
                $where[] = 'cliente = ?';
                $params[] = $_GET['cliente'];
            }
            if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
            $sql .= ' ORDER BY created_at DESC';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            respond($rows);
        }
    }

    if ($method === 'POST') {
        // Crear pedido
        $data = json_decode(file_get_contents('php://input'), true);
        $mesa = trim($data['mesa'] ?? '');
        $cliente = trim($data['cliente'] ?? '');
        $platillo = trim($data['platillo'] ?? '');
        $total = isset($data['total']) ? floatval($data['total']) : null;

        if ($mesa === '' || $platillo === '' || $total === null) {
            respond(['error' => 'Campos requeridos: mesa, platillo, total'], 400);
        }

        $stmt = $pdo->prepare('INSERT INTO orders (mesa, cliente, platillo, total) VALUES (?, ?, ?, ?)');
        $stmt->execute([$mesa, $cliente, $platillo, $total]);
        $id = $pdo->lastInsertId();
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$id]);
        $order = $stmt->fetch();
        respond($order, 201);
    }

    if ($method === 'PUT') {
        if (!isset($_GET['id'])) respond(['error' => 'id requerido'], 400);
        $id = intval($_GET['id']);
        $data = json_decode(file_get_contents('php://input'), true);

        // Si se intenta cambiar estado, validar transiciones
        if (isset($data['estado'])) {
            // obtener estado actual
            $stmt = $pdo->prepare('SELECT estado FROM orders WHERE id = ?');
            $stmt->execute([$id]);
            $curr = $stmt->fetchColumn();
            if ($curr === false) respond(['error' => 'Order not found'], 404);

            $allowed = [
                'pendiente' => ['en_preparacion'],
                'en_preparacion' => ['listo'],
                'listo' => ['entregado', 'facturado'],
                'entregado' => [],
                'facturado' => []
            ];
            $new = $data['estado'];
            if ($new === $curr) {
                // ok
            } else if (!in_array($new, $allowed[$curr] ?? [])) {
                respond(['error' => "Transición inválida de $curr a $new"], 400);
            }
        }

        // Campos editables: mesa, cliente, platillo, total, estado
        $fields = [];
        $params = [];
        foreach (['mesa','cliente','platillo','total','estado'] as $f) {
            if (isset($data[$f])) {
                $fields[] = "$f = ?";
                $params[] = $data[$f];
            }
        }
        if (!$fields) respond(['error' => 'Nada que actualizar'], 400);

        $params[] = $id;
        $sql = 'UPDATE orders SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->execute([$id]);
        $order = $stmt->fetch();
        respond($order);
    }

    if ($method === 'DELETE') {
        if (!isset($_GET['id'])) respond(['error' => 'id requerido'], 400);
        $id = intval($_GET['id']);
        $stmt = $pdo->prepare('DELETE FROM orders WHERE id = ?');
        $stmt->execute([$id]);
        respond(['ok' => true]);
    }

    respond(['error' => 'Método no soportado'], 405);
} catch (Exception $e) {
    respond(['error' => $e->getMessage()], 500);
}