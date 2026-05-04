<?php
require_once 'includes/config.php';
session_start();

// Защита от включения сторонних файлов
if (isset($_GET['file'])) {
    die('Local file inclusion is disabled');
}

// Защита от загрузки файлов
if (!empty($_FILES)) {
    die('File upload is disabled');
}

// CSRF-токен для админки
if (empty($_SESSION['csrf_token_admin'])) {
    $_SESSION['csrf_token_admin'] = bin2hex(random_bytes(32));
}

// HTTP-авторизация
if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
    header('HTTP/1.1 401 Unauthorized');
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    echo 'Требуется авторизация';
    exit;
}

$login = $_SERVER['PHP_AUTH_USER'];
$password = $_SERVER['PHP_AUTH_PW'];

$stmt = $pdo->prepare("SELECT password_hash FROM admins WHERE login = ?");
$stmt->execute([$login]);
$admin = $stmt->fetch();

if (!$admin || md5($password) !== $admin['password_hash']) {
    header('HTTP/1.1 401 Unauthorized');
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    echo 'Неверный логин или пароль';
    exit;
}

// Обработка удаления
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?");
    $stmt->execute([$id]);
    $stmt = $pdo->prepare("DELETE FROM applications WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: admin.php?msg=deleted");
    exit;
}

// Обработка редактирования с CSRF-проверкой
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    if (!isset($_POST['csrf_token_admin']) || $_POST['csrf_token_admin'] !== $_SESSION['csrf_token_admin']) {
        die('CSRF token validation failed');
    }
    
    $id = (int)$_POST['edit_id'];
    $stmt = $pdo->prepare("UPDATE applications SET 
        full_name = ?, phone = ?, email = ?, birth_date = ?, gender = ?, bio = ?, contract_accepted = ? 
        WHERE id = ?");
    $stmt->execute([
        $_POST['full_name'],
        $_POST['phone'],
        $_POST['email'],
        $_POST['birth_date'],
        $_POST['gender'],
        $_POST['bio'],
        isset($_POST['contract_accepted']) ? 1 : 0,
        $id
    ]);
    
    $stmt = $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?");
    $stmt->execute([$id]);
    if (isset($_POST['languages'])) {
        $stmt = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, ?)");
        foreach ($_POST['languages'] as $lang_id) {
            $stmt->execute([$id, $lang_id]);
        }
    }
    header("Location: admin.php?msg=updated");
    exit;
}

$applications = $pdo->query("
    SELECT a.*, u.login as user_login 
    FROM applications a 
    LEFT JOIN users u ON a.user_id = u.id 
    ORDER BY a.id DESC
")->fetchAll();

$langStats = $pdo->query("
    SELECT l.name, COUNT(al.language_id) as cnt 
    FROM languages l
    LEFT JOIN application_languages al ON l.id = al.language_id
    GROUP BY l.id
    ORDER BY cnt DESC
")->fetchAll();

$languages = $pdo->query("SELECT id, name FROM languages ORDER BY name")->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Админ-панель</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f0f0f0; }
        .container { max-width: 1400px; margin: 0 auto; }
        table { width: 100%; border-collapse: collapse; background: white; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #667eea; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        .btn { padding: 4px 8px; background: #dc3545; color: white; text-decoration: none; border-radius: 4px; }
        .btn-edit { background: #28a745; }
        .stats { background: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .form-group { margin-bottom: 10px; }
        label { display: inline-block; width: 120px; }
        input, textarea, select { width: 300px; padding: 5px; }
        .message { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 20px; }
    </style>
</head>
<body>
<div class="container">
    <h1>Админ-панель</h1>
    <p>Вы вошли как <strong><?php echo htmlspecialchars($login); ?></strong></p>
    
    <?php if (isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
        <div class="message">✅ Запись удалена</div>
    <?php elseif (isset($_GET['msg']) && $_GET['msg'] == 'updated'): ?>
        <div class="message">✅ Запись обновлена</div>
    <?php endif; ?>
    
    <h2>Статистика по языкам программирования</h2>
    <div class="stats">
        <table>
            <tr><th>Язык</th><th>Сколько пользователей выбрали</th></tr>
            <?php foreach ($langStats as $stat): ?>
                <tr>
                    <td><?php echo htmlspecialchars($stat['name']); ?></td>
                    <td><?php echo $stat['cnt']; ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    
    <h2>Все заявки пользователей</h2>
    <table>
        <thead>
            <tr><th>ID</th><th>Пользователь</th><th>ФИО</th><th>Телефон</th><th>Email</th><th>Дата рождения</th><th>Пол</th><th>Биография</th><th>Языки</th><th>Действия</th></tr>
        </thead>
        <tbody>
            <?php foreach ($applications as $app): 
                $stmt = $pdo->prepare("SELECT l.name FROM languages l 
                    JOIN application_languages al ON l.id = al.language_id 
                    WHERE al.application_id = ?");
                $stmt->execute([$app['id']]);
                $langs = $stmt->fetchAll(PDO::FETCH_COLUMN);
                ?>
                <tr>
                    <td><?php echo $app['id']; ?></td>
                    <td><?php echo htmlspecialchars($app['user_login'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($app['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($app['phone']); ?></td>
                    <td><?php echo htmlspecialchars($app['email']); ?></td>
                    <td><?php echo $app['birth_date']; ?></td>
                    <td><?php echo $app['gender']; ?></td>
                    <td><?php echo htmlspecialchars(substr($app['bio'], 0, 50)) . '...'; ?></td>
                    <td><?php echo implode(', ', $langs); ?></td>
                    <td>
                        <a href="#" onclick="showEditForm(<?php echo $app['id']; ?>)" class="btn btn-edit">✏️</a>
                        <a href="?delete=<?php echo $app['id']; ?>" class="btn" onclick="return confirm('Удалить?')">🗑️</a>
                    </td>
                </tr>
                
                <tr id="edit-row-<?php echo $app['id']; ?>" style="display:none; background:#eef;">
                    <td colspan="10">
                        <form method="POST">
                            <input type="hidden" name="csrf_token_admin" value="<?php echo $_SESSION['csrf_token_admin']; ?>">
                            <input type="hidden" name="edit_id" value="<?php echo $app['id']; ?>">
                            <div class="form-group"><label>ФИО</label><input name="full_name" value="<?php echo htmlspecialchars($app['full_name']); ?>"></div>
                            <div class="form-group"><label>Телефон</label><input name="phone" value="<?php echo htmlspecialchars($app['phone']); ?>"></div>
                            <div class="form-group"><label>Email</label><input name="email" value="<?php echo htmlspecialchars($app['email']); ?>"></div>
                            <div class="form-group"><label>Дата рождения</label><input type="date" name="birth_date" value="<?php echo $app['birth_date']; ?>"></div>
                            <div class="form-group">
                                <label>Пол</label>
                                <select name="gender">
                                    <option value="male" <?php echo $app['gender'] == 'male' ? 'selected' : ''; ?>>Мужской</option>
                                    <option value="female" <?php echo $app['gender'] == 'female' ? 'selected' : ''; ?>>Женский</option>
                                    <option value="other" <?php echo $app['gender'] == 'other' ? 'selected' : ''; ?>>Другой</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Языки</label>
                                <select name="languages[]" multiple size="5">
                                    <?php 
                                    $stmt2 = $pdo->prepare("SELECT language_id FROM application_languages WHERE application_id = ?");
                                    $stmt2->execute([$app['id']]);
                                    $selected_langs = $stmt2->fetchAll(PDO::FETCH_COLUMN);
                                    foreach ($languages as $lang): 
                                    ?>
                                        <option value="<?php echo $lang['id']; ?>" <?php echo in_array($lang['id'], $selected_langs) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($lang['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group"><label>Биография</label><textarea name="bio"><?php echo htmlspecialchars($app['bio']); ?></textarea></div>
                            <div class="form-group"><label><input type="checkbox" name="contract_accepted" <?php echo $app['contract_accepted'] ? 'checked' : ''; ?>> Контракт принят</label></div>
                            <button type="submit">Сохранить</button>
                            <button type="button" onclick="hideEditForm(<?php echo $app['id']; ?>)">Отмена</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <a href="index.php">← Вернуться на главную</a>
</div>

<script>
function showEditForm(id) {
    document.getElementById('edit-row-' + id).style.display = 'table-row';
}
function hideEditForm(id) {
    document.getElementById('edit-row-' + id).style.display = 'none';
}
</script>
</body>
</html>
