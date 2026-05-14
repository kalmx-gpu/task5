<?php
header('Content-Type: text/html; charset=UTF-8');
require_once 'validators.php';
require_once 'save.php';

$fields = ['full_name', 'phone', 'email', 'birth_date', 'gender', 'languages', 'bio', 'contract_agreed'];

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $messages = [];
    $errors = [];
    $values = [];

    // Сообщение об успешном сохранении
    if (!empty($_COOKIE['save'])) {
        setcookie('save', '', 100000);
        $messages[] = '<div class="success-message">Спасибо, результаты сохранены.</div>';
    }

    // Собираем ошибки и значения из временных кук (последняя неудачная попытка)
    foreach ($fields as $field) {
        $error_key = $field . '_error';
        $value_key = $field . '_value';
        $errors[$field] = !empty($_COOKIE[$error_key]);

        if ($errors[$field]) {
            // Удаляем временные куки ошибки и значения
            setcookie($error_key, '', 100000);
            setcookie($value_key, '', 100000);

            // Сообщение об ошибке с указанием допустимых символов
            $messages[] = '<div class="error-message">' . getFieldError($field) . '</div>';
        }

        // Приоритет значений: временное (ошибочное) > долговременное (успешное)
        if (isset($_COOKIE[$value_key])) {
            $values[$field] = $_COOKIE[$value_key];
        } elseif (isset($_COOKIE[$field])) {
            // Долговременные куки успешной отправки (хранятся 1 год)
            $values[$field] = $_COOKIE[$field];
        } else {
            $values[$field] = '';
        }
    }

    // Восстановление массива языков (был сериализован)
    if (!empty($values['languages'])) {
        $values['languages'] = unserialize($values['languages']);
    } else {
        $values['languages'] = [];
    }

    include('form.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = $_POST;
    $errors = validateAllFields($data); // валидация (регулярные выражения)
    $has_errors = !empty($errors);

    // Для каждого поля устанавливаем временные куки
    foreach ($fields as $field) {
        $error_key = $field . '_error';
        $value_key = $field . '_value';

        // Удаляем старые временные куки (на всякий случай)
        setcookie($error_key, '', 100000);
        setcookie($value_key, '', 100000);

        if (isset($errors[$field])) {
            // Есть ошибка – ставим куку ошибки и сохраняем введённое значение (на 24 часа)
            setcookie($error_key, '1', time() + 24 * 60 * 60);
            $value = $data[$field] ?? '';
            if (is_array($value)) {
                $value = serialize($value);
            }
            setcookie($value_key, $value, time() + 24 * 60 * 60);
        }
    }

    if ($has_errors) {
        // Перенаправляем на GET-версию страницы, где отобразятся ошибки
        header('Location: index.php');
        exit();
    }

    // Ошибок нет – сохраняем в БД
    try {
        saveApplication($data);

        // Сохраняем успешные данные в куки на 1 год
        foreach ($fields as $field) {
            $value = $data[$field] ?? '';
            if (is_array($value)) {
                $value = serialize($value);
            }
            setcookie($field, $value, time() + 365 * 24 * 60 * 60);
        }

        // Флаг успешного сохранения
        setcookie('save', '1');
    } catch (Exception $e) {
        error_log('Ошибка сохранения: ' . $e->getMessage());
    }

    header('Location: index.php');
    exit();
}


function getFieldError($field) {
    $messages = [
        'full_name' => 'ФИО должно содержать только буквы, пробелы и дефис',
        'phone' => 'Телефон должен содержать 11 цифр, начиная с 7 или 8.',
        'email' => 'Email должен соответствовать формату username@domain.ru.',
        'birth_date' => 'Дата рождения должна быть в формате ГГГГ-ММ-ДД и не быть позже текущей.',
        'gender' => 'Выберите пол: мужской или женский.',
        'languages' => 'Выберите хотя бы один язык из списка.',
        'bio' => 'Биография не должна быть пустой и не превышать 5000 символов.',
        'contract_agreed' => 'Необходимо подтвердить ознакомление с контрактом.'
    ];
    return $messages[$field] ?? 'Некорректное значение.';
}