<?php
function validateInput($data, $pdo) {
    $errors = [];

    if (empty($data['full_name'])) {
        $errors['full_name'] = "Имя обязательно";
    } elseif (!preg_match("/^[A-Za-zА-Яа-я\s]{2,150}$/u", $data['full_name'])) {
        $errors['full_name'] = "Имя: только буквы и пробелы (2-150 символов)";
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

    if (empty($data['work_area']) || !in_array($data['work_area'], ['office', 'racing'])) {
        $errors['work_area'] = "Выберите сферу трудоустройства";
    }

    if (empty($data['positions']) || !is_array($data['positions'])) {
        $errors['positions'] = "Выберите хотя бы одну должность";
    } else {
        $placeholders = implode(',', array_fill(0, count($data['positions']), '?'));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM positions WHERE id IN ($placeholders)");
        $stmt->execute($data['positions']);
        if ($stmt->fetchColumn() != count($data['positions'])) {
            $errors['positions'] = "Выбраны некорректные должности";
        }
    }

    if (empty($data['bio'])) {
        $errors['bio'] = "Сообщение обязательно";
    } elseif (strlen($data['bio']) > 5000) {
        $errors['bio'] = "Сообщение слишком длинное";
    }

    if (!isset($data['contract'])) {
        $errors['contract'] = "Необходимо согласие на обработку данных";
    }

    return $errors;
}
?>
