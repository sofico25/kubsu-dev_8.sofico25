<?php
header('Content-Type: application/json');

$host = 'localhost';
$dbname = 'angelinaliverova_db';
$username = 'phpuser';
$password = 'phpuser';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$errors = [];

if (empty($input['name'])) {
    $errors['name'] = 'Введите имя';
}
if (empty($input['phone'])) {
    $errors['phone'] = 'Введите телефон';
}
if (empty($input['email'])) {
    $errors['email'] = 'Введите email';
} elseif (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Неверный формат email';
}
if (empty($input['message'])) {
    $errors['message'] = 'Введите сообщение';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['errors' => $errors]);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO feedback (name, phone, email, message) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        $input['name'],
        $input['phone'],
        $input['email'],
        $input['message']
    ]);
    
    echo json_encode(['status' => 'success', 'message' => 'Спасибо! Ваше сообщение отправлено.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка сохранения']);
}
?>
