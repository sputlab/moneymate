<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'db.php';
require_once 'auth_helpers.php';

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!preg_match('/Bearer\s+(.+)/', $authHeader, $matches)) {
    http_response_code(401);
    echo json_encode(['message' => 'Token mancante']);
    exit;
}
$userId = verifyJWT($matches[1]);
if (!$userId) {
    http_response_code(401);
    echo json_encode(['message' => 'Token non valido']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// GET /transactions.php?month=5&year=2026
if ($method === 'GET') {
    $sql = 'SELECT * FROM moneymate_transactions WHERE user_id = ?';
    $params = [$userId];
    if (isset($_GET['month']) && isset($_GET['year'])) {
        $sql .= ' AND MONTH(date) = ? AND YEAR(date) = ?';
        $params[] = intval($_GET['month']);
        $params[] = intval($_GET['year']);
    } elseif (isset($_GET['year'])) {
        $sql .= ' AND YEAR(date) = ?';
        $params[] = intval($_GET['year']);
    }
    $sql .= ' ORDER BY date DESC, id DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode($stmt->fetchAll());
    exit;
}

// POST /transactions.php
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $type = $input['type'] ?? '';
    $amount = floatval($input['amount'] ?? 0);
    $description = trim($input['description'] ?? '');
    $categoryId = intval($input['category_id'] ?? 0);
    $date = $input['date'] ?? '';

    if (!in_array($type, ['expense', 'income']) || $amount <= 0 || empty($description) || $categoryId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        http_response_code(400);
        echo json_encode(['message' => 'Dati movimento non validi']);
        exit;
    }

    // Verifica che la categoria appartenga all'utente
    $stmt = $pdo->prepare('SELECT id FROM moneymate_categories WHERE id = ? AND user_id = ?');
    $stmt->execute([$categoryId, $userId]);
    if (!$stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['message' => 'Categoria non valida']);
        exit;
    }

    $stmt = $pdo->prepare('INSERT INTO moneymate_transactions (user_id, type, amount, description, category_id, date) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $type, $amount, $description, $categoryId, $date]);
    http_response_code(201);
    echo json_encode(['message' => 'Movimento creato']);
    exit;
}

// DELETE /transactions.php?id=...
if ($method === 'DELETE' && isset($_GET['id'])) {
    $txId = intval($_GET['id']);
    $stmt = $pdo->prepare('DELETE FROM moneymate_transactions WHERE id = ? AND user_id = ?');
    $stmt->execute([$txId, $userId]);
    if ($stmt->rowCount() > 0) {
        echo json_encode(['message' => 'Movimento eliminato']);
    } else {
        http_response_code(404);
        echo json_encode(['message' => 'Movimento non trovato']);
    }
    exit;
}

http_response_code(404);
echo json_encode(['message' => 'Metodo non supportato']);