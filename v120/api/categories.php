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
require_once 'auth_helpers.php'; // conterrà solo la funzione verifyJWT e la costante JWT_SECRET

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

// GET /categories.php
if ($method === 'GET') {
    $stmt = $pdo->prepare('SELECT * FROM moneymate_categories WHERE user_id = ?');
    $stmt->execute([$userId]);
    echo json_encode($stmt->fetchAll());
    exit;
}

// POST /categories.php
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $type = $input['type'] ?? '';
    $name = trim($input['name'] ?? '');
    $emoji = $input['emoji'] ?? '';
    $color = $input['color'] ?? '';

    if (!in_array($type, ['expense', 'income']) || empty($name) || empty($emoji) || !preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
        http_response_code(400);
        echo json_encode(['message' => 'Dati categoria non validi']);
        exit;
    }

    $stmt = $pdo->prepare('INSERT INTO moneymate_categories (user_id, type, name, emoji, color) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $type, $name, $emoji, $color]);
    http_response_code(201);
    echo json_encode(['message' => 'Categoria creata']);
    exit;
}

// DELETE /categories.php?id=...
if ($method === 'DELETE' && isset($_GET['id'])) {
    $catId = intval($_GET['id']);
    // Controlla che la categoria appartenga all'utente e non abbia transazioni
    $stmt = $pdo->prepare('SELECT id FROM moneymate_categories WHERE id = ? AND user_id = ?');
    $stmt->execute([$catId, $userId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['message' => 'Categoria non trovata']);
        exit;
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) AS cnt FROM moneymate_transactions WHERE category_id = ? AND user_id = ?');
    $stmt->execute([$catId, $userId]);
    $count = $stmt->fetch()['cnt'];
    if ($count > 0) {
        http_response_code(400);
        echo json_encode(['message' => 'La categoria ha movimenti associati, non può essere eliminata']);
        exit;
    }
    $stmt = $pdo->prepare('DELETE FROM moneymate_categories WHERE id = ? AND user_id = ?');
    $stmt->execute([$catId, $userId]);
    echo json_encode(['message' => 'Categoria eliminata']);
    exit;
}

http_response_code(404);
echo json_encode(['message' => 'Metodo non supportato']);