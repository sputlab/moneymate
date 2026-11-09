<?php
// Copia questo file in db.php e inserisci le credenziali reali (db.php e' in .gitignore)
$host = 'localhost';        // di solito è localhost
$db   = 'database_name';       // nome del tuo database
$user = 'database_user';
$pass = 'CHANGE_ME';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Connessione al database fallita']);
    exit;
}
