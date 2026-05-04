<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Разбираем REQUEST_URI, чтобы получить путь (аналог PATH_INFO)
$request_uri = $_SERVER['REQUEST_URI'];
if (preg_match('#/api/(.*?)(\?|$)#', $request_uri, $match)) {
    $path_info = '/' . $match[1];
} else {
    $path_info = '/';
}
$_SERVER['PATH_INFO'] = $path_info;

require_once '../includes/config.php';
require_once '../includes/validation.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['PATH_INFO'] ?? '/';
$path = trim($path, '/');

if ($path === '' && $method === 'POST') {
    $path = 'entry';
}

$parts = explode('/', $path);

// ----- ЛОГИН -----
if ($method === 'POST' && $parts[0] === 'login') {
    $input = json_decode(file_get_contents('php://input'), true);
    $login = $input['login'] ?? '';
    $password = $input['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE login = ?");
    $stmt->execute([$login]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_login'] = $user['login'];
        // Загружаем app_id
        $stmt = $pdo->prepare("SELECT id FROM applications WHERE user_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$user['id']]);
        $app = $stmt->fetch();
        $_SESSION['app_id'] = $app ? $app['id'] : null;
        echo json_encode(['status' => 'ok']);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
    }
    exit;
}

// ----- ВЫХОД -----
if ($method === 'POST' && $parts[0] === 'logout') {
    session_destroy();
    echo json_encode(['status' => 'ok']);
    exit;
}

// ----- ПОЛУЧЕНИЕ ДАННЫХ ПОЛЬЗОВАТЕЛЯ -----
if ($method === 'GET' && $parts[0] === 'user') {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    $app_id = $_SESSION['app_id'] ?? null;
    
    $app = null;
    if ($app_id) {
        $stmt = $pdo->prepare("SELECT * FROM applications WHERE id = ? AND user_id = ?");
        $stmt->execute([$app_id, $user_id]);
        $app = $stmt->fetch();
    }
    
    $positions = [];
    if ($app) {
        $stmt = $pdo->prepare("SELECT position_id FROM application_positions WHERE application_id = ?");
        $stmt->execute([$app['id']]);
        $positions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    echo json_encode([
        'user_id' => $user_id,
        'app_id' => $app_id,
        'full_name' => $app ? $app['full_name'] : '',
        'phone' => $app ? $app['phone'] : '',
        'email' => $app ? $app['email'] : '',
        'birth_date' => $app ? $app['birth_date'] : '',
        'work_area' => $app ? $app['work_area'] : '',
        'bio' => $app ? $app['bio'] : '',
        'contract_accepted' => $app ? (bool)$app['contract_accepted'] : false,
        'positions' => $positions
    ]);
    exit;
}

// ----- СОЗДАНИЕ ЗАЯВКИ (POST /entry) -----
if ($method === 'POST' && $parts[0] === 'entry') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) $input = $_POST;

    $errors = validateInput($input, $pdo);
    if (!empty($errors)) {
        http_response_code(422);
        echo json_encode(['errors' => $errors]);
        exit;
    }

    // Если пользователь авторизован
    if (isset($_SESSION['user_id'])) {
        if (!empty($_SESSION['app_id'])) {
            http_response_code(405);
            echo json_encode(['error' => 'For update use PUT /api/entry/{id}']);
            exit;
        }
        $user_id = $_SESSION['user_id'];
        $login = $_SESSION['user_login'];
        
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO applications (full_name, phone, email, birth_date, work_area, bio, contract_accepted, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $input['full_name'],
            $input['phone'],
            $input['email'],
            $input['birth_date'],
            $input['work_area'],
            $input['bio'],
            isset($input['contract']) ? 1 : 0,
            $user_id
        ]);
        $app_id = $pdo->lastInsertId();
        $_SESSION['app_id'] = $app_id;

        if (!empty($input['positions'])) {
            $stmt = $pdo->prepare("INSERT INTO application_positions (application_id, position_id) VALUES (?, ?)");
            foreach ($input['positions'] as $pos_id) {
                $stmt->execute([$app_id, $pos_id]);
            }
        }

        $pdo->commit();

        echo json_encode([
            'status' => 'created',
            'login' => $login,
            'password' => null
        ]);
        exit;
    }

    // Неавторизованный пользователь
    $login = 'user_' . bin2hex(random_bytes(4));
    $password = bin2hex(random_bytes(6));
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO users (login, password_hash) VALUES (?, ?)");
    $stmt->execute([$login, $password_hash]);
    $user_id = $pdo->lastInsertId();

    $_SESSION['user_id'] = $user_id;
    $_SESSION['user_login'] = $login;

    $stmt = $pdo->prepare("INSERT INTO applications (full_name, phone, email, birth_date, work_area, bio, contract_accepted, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $input['full_name'],
        $input['phone'],
        $input['email'],
        $input['birth_date'],
        $input['work_area'],
        $input['bio'],
        isset($input['contract']) ? 1 : 0,
        $user_id
    ]);
    $app_id = $pdo->lastInsertId();
    $_SESSION['app_id'] = $app_id;

    if (!empty($input['positions'])) {
        $stmt = $pdo->prepare("INSERT INTO application_positions (application_id, position_id) VALUES (?, ?)");
        foreach ($input['positions'] as $pos_id) {
            $stmt->execute([$app_id, $pos_id]);
        }
    }

    $pdo->commit();

    echo json_encode([
        'status' => 'created',
        'login' => $login,
        'password' => $password
    ]);
    exit;
}

// ----- ОБНОВЛЕНИЕ ЗАЯВКИ (PUT /entry/{id}) -----
if ($method === 'PUT' && preg_match('/^entry\/(\d+)$/', $path, $matches)) {
    $id = (int)$matches[1];
    $user_id = authenticate($pdo);

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) $input = $_POST;

    $errors = validateInput($input, $pdo);
    if (!empty($errors)) {
        http_response_code(422);
        echo json_encode(['errors' => $errors]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM applications WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Entry not found or access denied']);
        exit;
    }

    $pdo->beginTransaction();
    $stmt = $pdo->prepare("UPDATE applications SET full_name = ?, phone = ?, email = ?, birth_date = ?, work_area = ?, bio = ?, contract_accepted = ? WHERE id = ?");
    $stmt->execute([
        $input['full_name'],
        $input['phone'],
        $input['email'],
        $input['birth_date'],
        $input['work_area'],
        $input['bio'],
        isset($input['contract']) ? 1 : 0,
        $id
    ]);

    $stmt = $pdo->prepare("DELETE FROM application_positions WHERE application_id = ?");
    $stmt->execute([$id]);

    if (!empty($input['positions'])) {
        $stmt = $pdo->prepare("INSERT INTO application_positions (application_id, position_id) VALUES (?, ?)");
        foreach ($input['positions'] as $pos_id) {
            $stmt->execute([$id, $pos_id]);
        }
    }

    $pdo->commit();

    echo json_encode(['status' => 'updated']);
    exit;
}

// ----- АДМИНКА: СПИСОК ЗАЯВОК С ДОЛЖНОСТЯМИ -----
if ($method === 'GET' && $parts[0] === 'admin' && isset($parts[1]) && $parts[1] === 'applications') {
    $admin_login = $_SERVER['PHP_AUTH_USER'] ?? '';
    $admin_pass = $_SERVER['PHP_AUTH_PW'] ?? '';
    if ($admin_login !== 'admin' || $admin_pass !== 'admin123') {
        http_response_code(401);
        header('WWW-Authenticate: Basic realm="Admin"');
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $apps = $pdo->query("
        SELECT a.*, u.login as user_login 
        FROM applications a 
        LEFT JOIN users u ON a.user_id = u.id 
        ORDER BY a.id DESC
    ")->fetchAll();

    // Для каждой заявки подгружаем должности
    foreach ($apps as &$app) {
        $stmt = $pdo->prepare("SELECT position_id FROM application_positions WHERE application_id = ?");
        $stmt->execute([$app['id']]);
        $positions = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $app['positions'] = $positions;
    }

    echo json_encode($apps);
    exit;
}

// ----- НЕИЗВЕСТНЫЙ МАРШРУТ -----
http_response_code(404);
echo json_encode(['error' => 'Not found']);
exit;
?>
