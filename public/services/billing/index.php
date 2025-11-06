<?php
// public/services/billing/index.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../src/db.php';
require_once __DIR__ . '/../../../src/adapter/ThirdPartyPaymentAdapter.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$data = json_decode(file_get_contents('php://input'), true);
$orderId = isset($data['order_id']) ? intval($data['order_id']) : null;

if (!$orderId) {
    http_response_code(400);
    echo json_encode(['error' => 'order_id requerido']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) {
        http_response_code(404);
        echo json_encode(['error' => 'Pedido no encontrado']);
        exit;
    }

    if ($order['estado'] !== 'listo') {
        http_response_code(400);
        echo json_encode(['error' => 'Solo se puede facturar un pedido con estado "listo"']);
        exit;
    }

    // Crear adaptador con credenciales del proveedor simulado
    $adapter = new ThirdPartyPaymentAdapter(['api_key' => 'demo-key-123']);
    $res = $adapter->processPayment(intval($orderId), floatval($order['total']));

    if (!$res['ok']) {
        http_response_code(502);
        echo json_encode(['error' => 'Payment failed: ' . ($res['msg'] ?? 'unknown')]);
        exit;
    }

    // actualizar estado a facturado
    $stmt = $pdo->prepare('UPDATE orders SET estado = ? WHERE id = ?');
    $stmt->execute(['facturado', $orderId]);

    $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);
    $updated = $stmt->fetch();

    echo json_encode(['ok' => true, 'transaction_id' => $res['transaction_id'], 'order' => $updated]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}