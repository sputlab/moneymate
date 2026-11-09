<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'db.php';
require_once 'auth_helpers.php';

function generateJWT($userId) {
    $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload = base64_encode(json_encode(['userId' => $userId, 'exp' => time() + 7*24*3600]));
    $signature = base64_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
    return "$header.$payload.$signature";
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ==================== REGISTRAZIONE ====================
if ($method === 'POST' && $action === 'register') {
    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? '';
    $password = $input['password'] ?? '';
    $username = $input['username'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6 || empty($username)) {
        http_response_code(400);
        echo json_encode(['message' => 'Dati non validi']);
        exit;
    }

    try {
        $stmt = $pdo->prepare('SELECT id FROM moneymate_users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['message' => 'Email già registrata']);
            exit;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $token = generateRandomToken(32);
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('INSERT INTO moneymate_users (email, username, password_hash, verification_token) VALUES (?, ?, ?, ?)');
        $stmt->execute([$email, $username, $hash, $token]);
        $userId = $pdo->lastInsertId();

        // Categorie predefinite
        $defaultCats = [
            ['expense', 'Alimentari', '🛒', '#8b4513'],
            ['expense', 'Trasporti', '🚗', '#4a7c59'],
            ['expense', 'Casa', '🏠', '#c17f3a'],
            ['expense', 'Salute', '💊', '#c0392b'],
            ['expense', 'Svago', '🎭', '#5b4a8a'],
            ['expense', 'Abbigliamento', '👗', '#d35400'],
            ['income', 'Stipendio', '💰', '#4a7c59'],
            ['income', 'Freelance', '💻', '#2980b9'],
            ['income', 'Bonus', '🎁', '#c17f3a']
        ];
        $stmtCat = $pdo->prepare('INSERT INTO moneymate_categories (user_id, type, name, emoji, color) VALUES (?, ?, ?, ?, ?)');
        foreach ($defaultCats as $cat) {
            $stmtCat->execute([$userId, $cat[0], $cat[1], $cat[2], $cat[3]]);
        }

        $pdo->commit();

        // Invia email di verifica
        $verificationLink = "https://www.sputlab.com/webapp/moneymate/api/auth.php?action=verify&token=$token";
        $subject = "Conferma la tua registrazione a MoneyMate";
        $body = "Ciao $username,\n\nGrazie per esserti registrato! Clicca sul link seguente per verificare il tuo indirizzo email:\n$verificationLink\n\nIl team di MoneyMate";
        sendMail($email, $subject, $body);

        http_response_code(201);
        echo json_encode(['message' => 'Utente creato. Controlla la tua email per verificare l\'account.']);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(['message' => 'Errore del server: ' . $e->getMessage()]);
    }
    exit;
}

// ==================== VERIFICA EMAIL ====================
if ($method === 'GET' && $action === 'verify') {
    $token = $_GET['token'] ?? '';
    $redirect = 'https://www.sputlab.com/webapp/moneymate/index.html';
    if (empty($token)) {
        header('Location: ' . $redirect . '?verified=0');
        exit;
    }
    $stmt = $pdo->prepare('SELECT id FROM moneymate_users WHERE verification_token = ? AND email_verified = 0');
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if (!$user) {
        header('Location: ' . $redirect . '?verified=0');
        exit;
    }
    $stmt = $pdo->prepare('UPDATE moneymate_users SET email_verified = 1, verification_token = NULL WHERE id = ?');
    $stmt->execute([$user['id']]);
    header('Location: ' . $redirect . '?verified=1');
    exit;
}

// ==================== LOGIN ====================
if ($method === 'POST' && $action === 'login') {
    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? '';
    $password = $input['password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM moneymate_users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['message' => 'Credenziali non valide']);
        exit;
    }

    if ($user['email_verified'] != 1) {
        http_response_code(403);
        echo json_encode(['message' => 'Devi verificare il tuo indirizzo email prima di accedere.']);
        exit;
    }

    $token = generateJWT($user['id']);
    echo json_encode([
        'token' => $token,
        'user'   => [
            'id'           => $user['id'],
            'email'        => $user['email'],
            'username'     => $user['username'],
            'avatar_emoji' => $user['avatar_emoji'],
            'avatar_color' => $user['avatar_color'],
            'savings_goal' => $user['savings_goal']
        ]
    ]);
    exit;
}

// ==================== RICHIESTA RESET PASSWORD ====================
if ($method === 'POST' && $action === 'forgot') {
    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['message' => 'Email non valida']);
        exit;
    }
    $stmt = $pdo->prepare('SELECT id, username FROM moneymate_users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user) {
        $resetToken = generateRandomToken(32);
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $stmt = $pdo->prepare('UPDATE moneymate_users SET reset_token = ?, reset_token_expires = ? WHERE id = ?');
        $stmt->execute([$resetToken, $expires, $user['id']]);

        $resetLink = "https://www.sputlab.com/webapp/moneymate/index.html?reset_token=$resetToken";
        $subject = "Reset della password di MoneyMate";
        $body = "Ciao {$user['username']},\n\nAbbiamo ricevuto una richiesta per reimpostare la tua password. Clicca sul link seguente per procedere:\n$resetLink\n\nSe non hai richiesto tu questa operazione, ignora questa email.\n\nIl team di MoneyMate";
        sendMail($email, $subject, $body);
    }
    echo json_encode(['message' => 'Se l\'email è registrata, riceverai un link per reimpostare la password.']);
    exit;
}

// ==================== RESET PASSWORD ====================
if ($method === 'POST' && $action === 'reset') {
    $input = json_decode(file_get_contents('php://input'), true);
    $token = $input['token'] ?? '';
    $newPassword = $input['password'] ?? '';

    if (empty($token) || strlen($newPassword) < 6) {
        http_response_code(400);
        echo json_encode(['message' => 'Dati non validi']);
        exit;
    }

    $stmt = $pdo->prepare('SELECT id FROM moneymate_users WHERE reset_token = ? AND reset_token_expires > NOW()');
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if (!$user) {
        http_response_code(400);
        echo json_encode(['message' => 'Token non valido o scaduto.']);
        exit;
    }

    $hash = password_hash($newPassword, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare('UPDATE moneymate_users SET password_hash = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?');
    $stmt->execute([$hash, $user['id']]);
    echo json_encode(['message' => 'Password aggiornata con successo! Ora puoi accedere.']);
    exit;
}

// ==================== ROTTE AUTENTICATE ====================
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!preg_match('/Bearer\s+(.+)/', $authHeader, $matches)) {
    http_response_code(401);
    echo json_encode(['message' => 'Token mancante']);
    exit;
}
$token = $matches[1];
$userId = verifyJWT($token);
if (!$userId) {
    http_response_code(401);
    echo json_encode(['message' => 'Token non valido']);
    exit;
}

// GET /auth/me
if ($method === 'GET' && $action === 'me') {
    $stmt = $pdo->prepare('SELECT id, email, username, avatar_emoji, avatar_color, savings_goal FROM moneymate_users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        http_response_code(404);
        echo json_encode(['message' => 'Utente non trovato']);
        exit;
    }
    echo json_encode($user);
    exit;
}

// PUT /auth/profile
if ($method === 'PUT' && $action === 'profile') {
    $input = json_decode(file_get_contents('php://input'), true);
    $fields = [];
    $params = [];

    if (isset($input['username'])) {
        $fields[] = 'username = ?';
        $params[] = $input['username'];
    }
    if (isset($input['avatar_emoji'])) {
        $fields[] = 'avatar_emoji = ?';
        $params[] = $input['avatar_emoji'];
    }
    if (isset($input['avatar_color'])) {
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $input['avatar_color'])) {
            http_response_code(400);
            echo json_encode(['message' => 'Colore avatar non valido']);
            exit;
        }
        $fields[] = 'avatar_color = ?';
        $params[] = $input['avatar_color'];
    }
    if (array_key_exists('savings_goal', $input)) {
        $fields[] = 'savings_goal = ?';
        $params[] = $input['savings_goal'] !== null ? floatval($input['savings_goal']) : null;
    }

    if (count($fields) > 0) {
        $params[] = $userId;
        $sql = 'UPDATE moneymate_users SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }
    echo json_encode(['message' => 'Profilo aggiornato']);
    exit;
}

// DELETE /auth/account
if ($method === 'DELETE' && $action === 'account') {
    $input = json_decode(file_get_contents('php://input'), true);
    $password = $input['password'] ?? '';

    if (empty($password)) {
        http_response_code(400);
        echo json_encode(['message' => 'Password richiesta per eliminare l\'account.']);
        exit;
    }

    $stmt = $pdo->prepare('SELECT password_hash FROM moneymate_users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        http_response_code(400);
        echo json_encode(['message' => 'Password non corretta.']);
        exit;
    }

    $stmt = $pdo->prepare('DELETE FROM moneymate_users WHERE id = ?');
    $stmt->execute([$userId]);
    echo json_encode(['message' => 'Account eliminato']);
    exit;
}

http_response_code(404);
echo json_encode(['message' => 'Azione non trovata']);