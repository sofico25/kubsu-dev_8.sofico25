<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'includes/config.php';

// Валидация (та же, что и в задании 4)
function validateInput($data) {
    global $pdo;
    $errors = [];

    if (empty($data['full_name'])) {
        $errors['full_name'] = "ФИО обязательно";
    } elseif (!preg_match("/^[A-Za-zА-Яа-я\s]{2,150}$/u", $data['full_name'])) {
        $errors['full_name'] = "ФИО: только буквы и пробелы (2-150 символов)";
    }

    if (empty($data['phone'])) {
        $errors['phone'] = "Телефон обязателен";
    } elseif (!preg_match("/^\+?[0-9\s\-\(\)]{10,20}$/", $data['phone'])) {
        $errors['phone'] = "Неверный формат телефона";
    }

    if (empty($data['email'])) {
        $errors['email'] = "Email обязателен";
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Неверный формат email";
    } elseif (strlen($data['email']) > 100) {
        $errors['email'] = "Email слишком длинный";
    }

    if (empty($data['birth_date'])) {
        $errors['birth_date'] = "Дата рождения обязательна";
    } elseif (!DateTime::createFromFormat('Y-m-d', $data['birth_date'])) {
        $errors['birth_date'] = "Неверный формат даты";
    }

    $valid_genders = ['male', 'female', 'other'];
    if (empty($data['gender']) || !in_array($data['gender'], $valid_genders)) {
        $errors['gender'] = "Выберите корректный пол";
    }

    if (empty($data['languages']) || !is_array($data['languages'])) {
        $errors['languages'] = "Выберите хотя бы один язык";
    } else {
        $placeholders = implode(',', array_fill(0, count($data['languages']), '?'));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM languages WHERE id IN ($placeholders)");
        $stmt->execute($data['languages']);
        if ($stmt->fetchColumn() != count($data['languages'])) {
            $errors['languages'] = "Выбраны некорректные языки";
        }
    }

    if (empty($data['bio'])) {
        $errors['bio'] = "Биография обязательна";
    } elseif (strlen($data['bio']) > 5000) {
        $errors['bio'] = "Биография слишком длинная";
    }

    if (!isset($data['contract'])) {
        $errors['contract'] = "Необходимо подтверждение контракта";
    }

    return $errors;
}

// Обработка входа
if (isset($_POST['action']) && $_POST['action'] === 'login') {
    $login = $_POST['login'];
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE login = ?");
    $stmt->execute([$login]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_login'] = $user['login'];
        header("Location: index.php?login_success=1&login=" . urlencode($login));
        exit;
    } else {
        header("Location: index.php?error=" . urlencode("Неверный логин или пароль"));
        exit;
    }
}

// Обработка выхода
if (isset($_POST['action']) && $_POST['action'] === 'logout') {
    session_destroy();
    header("Location: index.php");
    exit;
}

// Если форма отправлена
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $errors = validateInput($_POST);
    $edit_mode = isset($_POST['edit_mode']) && $_POST['edit_mode'] == 1;
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Если пользователь НЕ авторизован и это НЕ редактирование
            if (!isset($_SESSION['user_id']) && !$edit_mode) {
                // Генерируем логин и пароль
                $login = 'user_' . bin2hex(random_bytes(4));
                $password = bin2hex(random_bytes(6));
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                // Создаём пользователя
                $stmt = $pdo->prepare("INSERT INTO users (login, password_hash) VALUES (?, ?)");
                $stmt->execute([$login, $password_hash]);
                $user_id = $pdo->lastInsertId();
                
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_login'] = $login;
                
                // Сохраняем заявку
                $stmt = $pdo->prepare("
                    INSERT INTO applications 
                    (full_name, phone, email, birth_date, gender, bio, contract_accepted, user_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_POST['full_name'],
                    $_POST['phone'],
                    $_POST['email'],
                    $_POST['birth_date'],
                    $_POST['gender'],
                    $_POST['bio'],
                    1,
                    $user_id
                ]);
                
                $application_id = $pdo->lastInsertId();
                
                // Языки
                $stmt = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, ?)");
                foreach ($_POST['languages'] as $lang_id) {
                    $stmt->execute([$application_id, $lang_id]);
                }
                
                $pdo->commit();
                
                // Сохраняем данные в Cookies на год
                $form_data = [
                    'full_name' => $_POST['full_name'],
                    'phone' => $_POST['phone'],
                    'email' => $_POST['email'],
                    'birth_date' => $_POST['birth_date'],
                    'gender' => $_POST['gender'],
                    'languages' => $_POST['languages'],
                    'bio' => $_POST['bio'],
                    'contract' => $_POST['contract'] ?? ''
                ];
                setcookie('form_data', json_encode($form_data), time() + 31536000, '/');
                
                header("Location: index.php?registered=1&login=" . urlencode($login) . "&password=" . urlencode($password));
                exit;
                
            } elseif (isset($_SESSION['user_id'])) {
                // Авторизованный пользователь — обновляем данные
                $user_id = $_SESSION['user_id'];
                
                // Находим последнюю заявку пользователя
                $stmt = $pdo->prepare("SELECT id FROM applications WHERE user_id = ? ORDER BY id DESC LIMIT 1");
                $stmt->execute([$user_id]);
                $app = $stmt->fetch();
                
                if ($app) {
                    // Обновляем заявку
                    $stmt = $pdo->prepare("
                        UPDATE applications SET 
                            full_name = ?, phone = ?, email = ?, birth_date = ?, gender = ?, bio = ?, contract_accepted = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $_POST['full_name'],
                        $_POST['phone'],
                        $_POST['email'],
                        $_POST['birth_date'],
                        $_POST['gender'],
                        $_POST['bio'],
                        1,
                        $app['id']
                    ]);
                    
                    // Обновляем языки (удаляем старые, добавляем новые)
                    $stmt = $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?");
                    $stmt->execute([$app['id']]);
                    
                    $stmt = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, ?)");
                    foreach ($_POST['languages'] as $lang_id) {
                        $stmt->execute([$app['id'], $lang_id]);
                    }
                } else {
                    // Создаём новую заявку (на случай, если её нет)
                    $stmt = $pdo->prepare("
                        INSERT INTO applications 
                        (full_name, phone, email, birth_date, gender, bio, contract_accepted, user_id) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $_POST['full_name'],
                        $_POST['phone'],
                        $_POST['email'],
                        $_POST['birth_date'],
                        $_POST['gender'],
                        $_POST['bio'],
                        1,
                        $user_id
                    ]);
                    
                    $application_id = $pdo->lastInsertId();
                    
                    $stmt = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, ?)");
                    foreach ($_POST['languages'] as $lang_id) {
                        $stmt->execute([$application_id, $lang_id]);
                    }
                }
                
                $pdo->commit();
                header("Location: index.php?success=1");
                exit;
            } else {
                // Не авторизован и пытается редактировать — не должно случиться
                header("Location: index.php?error=" . urlencode("Вы не авторизованы"));
                exit;
            }
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Ошибка базы данных: " . $e->getMessage();
            header("Location: index.php?error=" . urlencode($error));
            exit;
        }
    } else {
        setcookie('form_errors', json_encode($errors), time() + 3600, '/');
        header("Location: index.php");
        exit;
    }
} else {
    header("Location: index.php");
    exit;
}
?>
