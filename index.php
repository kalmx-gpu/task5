<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();
require_once 'validators.php';
require_once 'save.php';

$config = require 'config.php';
$dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
$pdo = new PDO($dsn, $config['username'], $config['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false
]);

$fields = ['full_name', 'phone', 'email', 'birth_date', 'gender', 'languages', 'bio', 'contract_agreed'];

function generateRandomPassword($length = 10) {
    $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    return substr(str_shuffle(str_repeat($chars, ceil($length / strlen($chars)))), 1, $length);
}

function generateUniqueLogin($pdo) {
    $base = 'user_';
    do {
        $suffix = bin2hex(random_bytes(2));
        $login = $base . $suffix;
        $stmt = $pdo->prepare("SELECT id FROM users WHERE login = ?");
        $stmt->execute([$login]);
    } while ($stmt->fetch());
    return $login;
}

// Обработка POST (вход или сохранение анкеты)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Если отправлена форма входа
    if (isset($_POST['login_submit'])) {
        $login = trim($_POST['login'] ?? '');
        $password = $_POST['pass'] ?? '';
        $error = '';
        if ($login === '' || $password === '') {
            $error = 'Заполните логин и пароль.';
        } else {
            $stmt = $pdo->prepare("SELECT id, login, password_hash FROM users WHERE login = ?");
            $stmt->execute([$login]);
            $user = $stmt->fetch();
            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['login'] = $user['login'];
                $_SESSION['uid'] = $user['id'];
                $_SESSION['pass'] = $password;
                header('Location: ./');
                exit();
            } else {
                $error = 'Неверный логин или пароль.';
            }
        }
        $_SESSION['login_error'] = $error;
        $_SESSION['login_value'] = $login;
        $_SESSION['pass_value'] = $password;
        header('Location: ./');
        exit();
    }
    
    // Иначе – сохранение анкеты
    $data = $_POST;
    $errors = validateAllFields($data);
    $has_errors = !empty($errors);

    foreach ($fields as $field) {
        $error_key = $field . '_error';
        $value_key = $field . '_value';
        setcookie($error_key, '', 100000);
        setcookie($value_key, '', 100000);
        if (isset($errors[$field])) {
            setcookie($error_key, '1', time() + 24 * 60 * 60);
            $value = $data[$field] ?? '';
            if (is_array($value)) $value = serialize($value);
            setcookie($value_key, $value, time() + 24 * 60 * 60);
        }
    }

    if ($has_errors) {
        header('Location: index.php');
        exit();
    }

    try {
        $pdo->beginTransaction();
        $isLoggedIn = !empty($_SESSION['login']);
        if ($isLoggedIn) {
            updateApplication($data, $_SESSION['uid'], $pdo);
        } else {
            $login = generateUniqueLogin($pdo);
            $plainPassword = generateRandomPassword(10);
            $passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (login, password_hash) VALUES (?, ?)");
            $stmt->execute([$login, $passwordHash]);
            $userId = $pdo->lastInsertId();
            saveApplication($data, $userId, $pdo);
            setcookie('login', $login, time() + 30 * 24 * 60 * 60);
            setcookie('pass', $plainPassword, time() + 30 * 24 * 60 * 60);
        }
        foreach ($fields as $field) {
            $value = $data[$field] ?? '';
            if (is_array($value)) $value = serialize($value);
            setcookie($field, $value, time() + 365 * 24 * 60 * 60);
        }
        setcookie('save', '1');
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Ошибка сохранения: ' . $e->getMessage());
    }
    header('Location: index.php');
    exit();
}

// GET-запрос
$messages = [];
$errors = [];
$values = [];

if (!empty($_COOKIE['save'])) {
    setcookie('save', '', 100000);
    $messages[] = '<div class="success-message">Спасибо, результаты сохранены.</div>';
}

$hasTempCookies = false;
foreach ($fields as $field) {
    $error_key = $field . '_error';
    $value_key = $field . '_value';
    $errors[$field] = !empty($_COOKIE[$error_key]);
    if ($errors[$field]) {
        $hasTempCookies = true;
        setcookie($error_key, '', 100000);
        setcookie($value_key, '', 100000);
        $messages[] = '<div class="error-message">' . getFieldError($field) . '</div>';
    }
    if (isset($_COOKIE[$value_key])) {
        $values[$field] = $_COOKIE[$value_key];
    }
}

if (!$hasTempCookies && !empty($_SESSION['login'])) {
    $stmt = $pdo->prepare("SELECT * FROM applications WHERE user_id = ?");
    $stmt->execute([$_SESSION['uid']]);
    $dbData = $stmt->fetch();
    if ($dbData) {
        foreach ($fields as $field) {
            if ($field == 'languages') {
                $stmtLang = $pdo->prepare("
                    SELECT pl.name FROM programming_languages pl
                    JOIN application_languages al ON al.language_id = pl.id
                    JOIN applications a ON a.id = al.application_id
                    WHERE a.user_id = ?
                ");
                $stmtLang->execute([$_SESSION['uid']]);
                $values['languages'] = $stmtLang->fetchAll(PDO::FETCH_COLUMN);
            } else {
                $values[$field] = $dbData[$field] ?? '';
            }
        }
    }
}

foreach ($fields as $field) {
    if (!isset($values[$field]) && isset($_COOKIE[$field])) {
        $values[$field] = $_COOKIE[$field];
    } elseif (!isset($values[$field])) {
        $values[$field] = '';
    }
}

if (!empty($values['languages']) && is_string($values['languages'])) {
    $values['languages'] = unserialize($values['languages']);
}
if (empty($values['languages'])) $values['languages'] = [];

$loginError = $_SESSION['login_error'] ?? '';
$loginValue = $_SESSION['login_value'] ?? $_COOKIE['login'] ?? '';
$passValue = $_SESSION['pass_value'] ?? $_COOKIE['pass'] ?? '';
unset($_SESSION['login_error'], $_SESSION['login_value'], $_SESSION['pass_value']);

include('form.php');

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