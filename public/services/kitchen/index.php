<?php
// public/services/kitchen/index.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../src/db.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$data = json_decode(file_get_contents('php://input'), true);
$orderId = isset($data['order_id']) ? intval($data['order_id']) : null;
$action = $data['action'] ?? '';

if (!$orderId || !$action) {
    http_response_code(400);
    echo json_encode(['error' => 'order_id y action son requeridos']);
    exit;
}

// acciones: start -> en_preparacion, ready -> listo, deliver -> entregado
$map = [
    'start' => 'en_preparacion',
    'ready' => 'listo',
    'deliver' => 'entregado'
];

if (!isset($map[$action])) {
    http_response_code(400);
    echo json_encode(['error' => 'acción desconocida']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT estado FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);
    $curr = $stmt->fetchColumn();
    if ($curr === false) {
        http_response_code(404);
        echo json_encode(['error' => 'Pedido no encontrado']);
        exit;
    }

    $new = $map[$action];

    // Validar transición simple
    $allowed = [
        'pendiente' => ['en_preparacion'],
        'en_preparacion' => ['listo'],
        'listo' => ['entregado'],
        'entregado' => [],
        'facturado' => []
    ];
    if ($new !== $curr && !in_array($new, $allowed[$curr] ?? [])) {
        http_response_code(400);
        echo json_encode(['error' => "Transición inválida de $curr a $new"]);
        exit;
    }

    $stmt = $pdo->prepare('UPDATE orders SET estado = ? WHERE id = ?');
    $stmt->execute([$new, $orderId]);

    $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    echo json_encode(['ok' => true, 'order' => $order]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}