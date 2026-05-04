<?php
session_start();

// Защита от включения сторонних файлов
if (isset($_GET['file'])) {
    die('Local file inclusion is disabled');
}

// Защита от загрузки файлов
if (!empty($_FILES)) {
    die('File upload is disabled');
}

// CSRF-токен
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once 'includes/config.php';

// Получаем список языков
$stmt = $pdo->query("SELECT id, name FROM languages ORDER BY name");
$languages = $stmt->fetchAll();

// Данные из Cookies (для неавторизованных)
$saved_data = [];
if (isset($_COOKIE['form_data'])) {
    $saved_data = json_decode($_COOKIE['form_data'], true);
}

// Ошибки из Cookies
$errors = [];
if (isset($_COOKIE['form_errors'])) {
    $errors = json_decode($_COOKIE['form_errors'], true);
    setcookie('form_errors', '', time() - 3600, '/');
}

// Если пользователь авторизован — загружаем его данные
$edit_mode = false;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM applications WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user_data = $stmt->fetch();
    if ($user_data) {
        $edit_mode = true;
        $saved_data = [
            'full_name' => $user_data['full_name'],
            'phone' => $user_data['phone'],
            'email' => $user_data['email'],
            'birth_date' => $user_data['birth_date'],
            'gender' => $user_data['gender'],
            'bio' => $user_data['bio'],
            'contract' => $user_data['contract_accepted']
        ];
        $stmt = $pdo->prepare("SELECT language_id FROM application_languages WHERE application_id = ?");
        $stmt->execute([$user_data['id']]);
        $saved_data['languages'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Задание 5 — Анкета с авторизацией</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            padding: 30px;
        }
        h1 { text-align: center; color: #333; margin-bottom: 20px; }
        .auth-box {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 30px;
            text-align: center;
        }
        .auth-box input {
            padding: 8px;
            margin: 0 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .auth-box button {
            background: #667eea;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        .logout-btn {
            background: #dc3545;
            margin-left: 10px;
        }
        .message {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border-left-color: #dc3545;
        }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; color: #555; font-weight: 500; }
        input[type="text"], input[type="tel"], input[type="email"], input[type="date"], textarea, select {
            width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 16px;
        }
        .error-field { border: 2px solid red !important; background-color: #ffe6e6; }
        .error-message { color: red; font-size: 12px; margin-top: 5px; }
        button[type="submit"] {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; border: none; padding: 15px 30px; font-size: 18px; border-radius: 5px;
            cursor: pointer; width: 100%;
        }
        .radio-group { display: flex; gap: 20px; }
        select[multiple] { height: 150px; }
        .checkbox-group { display: flex; align-items: center; gap: 10px; }
        .checkbox-group input { width: auto; }
    </style>
</head>
<body>
<div class="container">
    <h1>Анкета с авторизацией</h1>

    <div class="auth-box">
        <?php if (isset($_SESSION['user_id'])): ?>
            <span>Вы вошли как <strong><?php echo htmlspecialchars($_SESSION['user_login']); ?></strong></span>
            <form action="process.php" method="POST" style="display: inline-block;">
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="logout-btn">Выйти</button>
            </form>
        <?php else: ?>
            <form action="process.php" method="POST" style="display: inline-block;">
                <input type="hidden" name="action" value="login">
                <input type="text" name="login" placeholder="Логин" required>
                <input type="password" name="password" placeholder="Пароль" required>
                <button type="submit">Войти</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="message">✅ Данные успешно сохранены!</div>
    <?php endif; ?>
    <?php if (isset($_GET['login_success']) && isset($_GET['login'])): ?>
        <div class="message">✅ Вы вошли как <?php echo htmlspecialchars($_GET['login']); ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['registered'])): ?>
        <div class="message">
            ✅ Регистрация прошла успешно! Ваш логин: <strong><?php echo htmlspecialchars($_GET['login']); ?></strong><br>
            Пароль: <strong><?php echo htmlspecialchars($_GET['password']); ?></strong><br>
            Сохраните их для входа.
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="message error">❌ Ошибка: <?php echo htmlspecialchars($_GET['error']); ?></div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="message error">
            <strong>❌ Ошибки заполнения:</strong>
            <ul>
                <?php foreach ($errors as $field => $msg): ?>
                    <li><?php echo htmlspecialchars($msg); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="process.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <input type="hidden" name="edit_mode" value="<?php echo $edit_mode ? 1 : 0; ?>">

        <div class="form-group">
            <label for="full_name">ФИО *</label>
            <input type="text" id="full_name" name="full_name" required
                   value="<?php echo htmlspecialchars($saved_data['full_name'] ?? ''); ?>"
                   class="<?php echo isset($errors['full_name']) ? 'error-field' : ''; ?>">
            <?php if (isset($errors['full_name'])): ?>
                <div class="error-message"><?php echo $errors['full_name']; ?></div>
            <?php endif; ?>
        </div>

        <!-- Остальные поля формы без изменений (phone, email, birth_date, gender, languages, bio, contract) -->
        <!-- (они уже есть в старом файле, здесь оставлены как есть) -->

        <div class="form-group">
            <label for="phone">Телефон *</label>
            <input type="tel" id="phone" name="phone" required
                   value="<?php echo htmlspecialchars($saved_data['phone'] ?? ''); ?>"
                   class="<?php echo isset($errors['phone']) ? 'error-field' : ''; ?>">
            <?php if (isset($errors['phone'])): ?>
                <div class="error-message"><?php echo $errors['phone']; ?></div>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="email">Email *</label>
            <input type="email" id="email" name="email" required
                   value="<?php echo htmlspecialchars($saved_data['email'] ?? ''); ?>"
                   class="<?php echo isset($errors['email']) ? 'error-field' : ''; ?>">
            <?php if (isset($errors['email'])): ?>
                <div class="error-message"><?php echo $errors['email']; ?></div>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="birth_date">Дата рождения *</label>
            <input type="date" id="birth_date" name="birth_date" required
                   max="<?php echo date('Y-m-d'); ?>"
                   value="<?php echo htmlspecialchars($saved_data['birth_date'] ?? ''); ?>"
                   class="<?php echo isset($errors['birth_date']) ? 'error-field' : ''; ?>">
            <?php if (isset($errors['birth_date'])): ?>
                <div class="error-message"><?php echo $errors['birth_date']; ?></div>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label>Пол *</label>
            <div class="radio-group">
                <input type="radio" id="gender_male" name="gender" value="male" required
                    <?php echo (($saved_data['gender'] ?? '') == 'male') ? 'checked' : ''; ?>
                    class="<?php echo isset($errors['gender']) ? 'error-field' : ''; ?>">
                <label for="gender_male">Мужской</label>
                <input type="radio" id="gender_female" name="gender" value="female"
                    <?php echo (($saved_data['gender'] ?? '') == 'female') ? 'checked' : ''; ?>
                    class="<?php echo isset($errors['gender']) ? 'error-field' : ''; ?>">
                <label for="gender_female">Женский</label>
                <input type="radio" id="gender_other" name="gender" value="other"
                    <?php echo (($saved_data['gender'] ?? '') == 'other') ? 'checked' : ''; ?>
                    class="<?php echo isset($errors['gender']) ? 'error-field' : ''; ?>">
                <label for="gender_other">Другой</label>
            </div>
            <?php if (isset($errors['gender'])): ?>
                <div class="error-message"><?php echo $errors['gender']; ?></div>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="languages">Любимые языки программирования *</label>
            <select name="languages[]" id="languages" multiple required
                    class="<?php echo isset($errors['languages']) ? 'error-field' : ''; ?>">
                <?php foreach ($languages as $lang): ?>
                    <option value="<?php echo $lang['id']; ?>"
                        <?php echo (isset($saved_data['languages']) && in_array($lang['id'], $saved_data['languages'])) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($lang['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="help-text">Выберите один или несколько (Ctrl/Cmd + клик)</div>
            <?php if (isset($errors['languages'])): ?>
                <div class="error-message"><?php echo $errors['languages']; ?></div>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="bio">Биография *</label>
            <textarea id="bio" name="bio" required
                      class="<?php echo isset($errors['bio']) ? 'error-field' : ''; ?>"><?php echo htmlspecialchars($saved_data['bio'] ?? ''); ?></textarea>
            <?php if (isset($errors['bio'])): ?>
                <div class="error-message"><?php echo $errors['bio']; ?></div>
            <?php endif; ?>
        </div>

        <div class="form-group checkbox-group">
            <input type="checkbox" id="contract" name="contract" required
                   <?php echo isset($saved_data['contract']) ? 'checked' : ''; ?>
                   class="<?php echo isset($errors['contract']) ? 'error-field' : ''; ?>">
            <label for="contract">Я ознакомлен(а) с контрактом *</label>
            <?php if (isset($errors['contract'])): ?>
                <div class="error-message"><?php echo $errors['contract']; ?></div>
            <?php endif; ?>
        </div>

        <button type="submit">Сохранить</button>
    </form>
</div>
<p style="text-align: center; margin-top: 30px;">
    <a href="admin.php" style="background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">🔐 Админ-панель</a>
</p>
</body>
</html>
