<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'includes/config.php';

function validateInput($data) {
    global $pdo;
    $errors = [];

    // ФИО
    if (empty($data['full_name'])) {
        $errors[] = "ФИО обязательно для заполнения";
    } elseif (!preg_match("/^[A-Za-zА-Яа-я\s]{2,150}$/u", $data['full_name'])) {
        $errors[] = "ФИО должно содержать только буквы и пробелы (2-150 символов)";
    }

    // Телефон
    if (empty($data['phone'])) {
        $errors[] = "Телефон обязателен";
    } elseif (!preg_match("/^\+?[0-9\s\-\(\)]{10,20}$/", $data['phone'])) {
        $errors[] = "Неверный формат телефона";
    }

    // Email
    if (empty($data['email'])) {
        $errors[] = "Email обязателен";
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Неверный формат email";
    } elseif (strlen($data['email']) > 100) {
        $errors[] = "Email слишком длинный";
    }

    // Дата рождения
    if (empty($data['birth_date'])) {
        $errors[] = "Дата рождения обязательна";
    } elseif (!DateTime::createFromFormat('Y-m-d', $data['birth_date'])) {
        $errors[] = "Неверный формат даты";
    }

    // Пол
    $valid_genders = ['male', 'female', 'other'];
    if (empty($data['gender']) || !in_array($data['gender'], $valid_genders)) {
        $errors[] = "Выберите корректный пол";
    }

    // Языки программирования
    if (empty($data['languages']) || !is_array($data['languages'])) {
        $errors[] = "Выберите хотя бы один язык программирования";
    } else {
        $placeholders = implode(',', array_fill(0, count($data['languages']), '?'));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM languages WHERE id IN ($placeholders)");
        $stmt->execute($data['languages']);
        if ($stmt->fetchColumn() != count($data['languages'])) {
            $errors[] = "Выбраны некорректные языки программирования";
        }
    }

    // Биография
    if (empty($data['bio'])) {
        $errors[] = "Биография обязательна";
    } elseif (strlen($data['bio']) > 5000) {
        $errors[] = "Биография слишком длинная (макс. 5000 символов)";
    }

    // Контракт
    if (!isset($data['contract'])) {
        $errors[] = "Необходимо подтвердить ознакомление с контрактом";
    }

    return $errors;
}

// Если форма отправлена
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $errors = validateInput($_POST);
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                INSERT INTO applications 
                (full_name, phone, email, birth_date, gender, bio, contract_accepted) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $_POST['full_name'],
                $_POST['phone'],
                $_POST['email'],
                $_POST['birth_date'],
                $_POST['gender'],
                $_POST['bio'],
                1
            ]);
            
            $application_id = $pdo->lastInsertId();
            
            $stmt = $pdo->prepare("
                INSERT INTO application_languages (application_id, language_id) 
                VALUES (?, ?)
            ");
            
            foreach ($_POST['languages'] as $lang_id) {
                $stmt->execute([$application_id, $lang_id]);
            }
            
            $pdo->commit();
            header("Location: index.php?success=1");
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Ошибка базы данных: " . $e->getMessage();
            header("Location: index.php?error=" . urlencode($error));
            exit;
        }
    } else {
        $error = implode("; ", $errors);
        header("Location: index.php?error=" . urlencode($error));
        exit;
    }
} else {
    header("Location: index.php");
    exit;
}
?>
