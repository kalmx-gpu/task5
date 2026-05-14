<?php
header('Content-Type: text/html; charset=UTF-8');
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax'
]);

require_once 'validators.php';
require_once 'save.php';

$fields = ['full_name', 'phone', 'email', 'birth_date', 'gender', 'languages', 'bio', 'contract_agreed'];
$pdo = getDBConnection();

// ----- Обработка действий POST -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Выход
    if ($action === 'logout') {
        session_destroy();
        header('Location: index.php');
        exit();
    }

    // Логин
    if ($action === 'login') {
        $login = trim($_POST['auth_login'] ?? '');
        $password = $_POST['auth_password'] ?? '';
        if ($login === '' || $password === '') {
            $_SESSION['auth_error'] = 'Введите логин и пароль.';
        } else {
            $appId = authenticate($login, $password, $pdo);
            if ($appId) {
                $_SESSION['application_id'] = $appId;
                unset($_SESSION['auth_error']);
            } else {
                $_SESSION['auth_error'] = 'Неверный логин или пароль.';
            }
        }
        header('Location: index.php');
        exit();
    }

    // Сохранение (новая заявка или обновление)
    if ($action === 'save') {
        // Собираем и нормализуем данные
        $data = [
            'full_name' => trim($_POST['full_name'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'birth_date' => trim($_POST['birth_date'] ?? ''),
            'gender' => $_POST['gender'] ?? '',
            'languages' => $_POST['languages'] ?? [],
            'bio' => trim($_POST['bio'] ?? ''),
            'contract_agreed' => isset($_POST['contract_agreed']) ? 1 : 0
        ];

        // Валидация
        $errors = validateAllFields($data);
        $hasErrors = !empty($errors);

        // Устанавливаем временные куки для ошибок и значений (как в задании 4)
        foreach ($fields as $field) {
            $errorKey = $field . '_error';
            $valueKey = $field . '_value';
            setcookie($errorKey, '', 100000);
            setcookie($valueKey, '', 100000);
            if (isset($errors[$field])) {
                setcookie($errorKey, '1', time() + 86400);
                $val = $data[$field] ?? '';
                if (is_array($val)) $val = serialize($val);
                setcookie($valueKey, $val, time() + 86400);
            }
        }

        if ($hasErrors) {
            header('Location: index.php');
            exit();
        }

        // Ошибок нет
        try {
            $isAuth = isset($_SESSION['application_id']);
            if ($isAuth) {
                // Обновление существующей заявки
                $appId = (int)$_SESSION['application_id'];
                updateApplication($appId, $data, $pdo);
                $_SESSION['success'] = 'Данные успешно обновлены.';
            } else {
                // Новая заявка (гость)
                $appId = saveNewApplication($data, $pdo);
                $login = generateUniqueLogin($pdo);
                $password = generatePassword();
                createAccount($appId, $login, $password, $pdo);
                // Сохраняем учётные данные для показа один раз
                $_SESSION['generated_creds'] = ['login' => $login, 'password' => $password];
                $_SESSION['success'] = 'Анкета сохранена.';
                // Сохраняем успешные значения в долговременные куки (для гостя)
                foreach ($fields as $field) {
                    $val = $data[$field] ?? '';
                    if (is_array($val)) $val = serialize($val);
                    setcookie($field, $val, time() + 365 * 86400);
                }
            }
            // Удаляем временные куки ошибок
            foreach ($fields as $field) {
                setcookie($field . '_error', '', 100000);
                setcookie($field . '_value', '', 100000);
            }
            setcookie('save', '1', time() + 86400);
        } catch (Exception $e) {
            $_SESSION['db_error'] = 'Ошибка сохранения: ' . $e->getMessage();
        }
        header('Location: index.php');
        exit();
    }
}

// ----- GET: подготовка данных для формы -----
$messages = [];
$errors = [];
$values = [];

// Сообщение об успешном сохранении
if (!empty($_COOKIE['save'])) {
    setcookie('save', '', 100000);
    $messages[] = '<div class="success-message">' . ($_SESSION['success'] ?? 'Спасибо, результаты сохранены.') . '</div>';
    unset($_SESSION['success']);
}

// Сообщение о сгенерированных учётных данных
if (!empty($_SESSION['generated_creds'])) {
    $creds = $_SESSION['generated_creds'];
    $messages[] = '<div class="success-message">Сохраните данные для входа:<br>Логин: <strong>' . htmlspecialchars($creds['login']) . '</strong><br>Пароль: <strong>' . htmlspecialchars($creds['password']) . '</strong></div>';
    unset($_SESSION['generated_creds']);
}

// Ошибки БД или авторизации
if (!empty($_SESSION['db_error'])) {
    $messages[] = '<div class="error-message">' . htmlspecialchars($_SESSION['db_error']) . '</div>';
    unset($_SESSION['db_error']);
}
if (!empty($_SESSION['auth_error'])) {
    $messages[] = '<div class="error-message">' . htmlspecialchars($_SESSION['auth_error']) . '</div>';
    unset($_SESSION['auth_error']);
}

// Загружаем данные: если авторизован — из БД, иначе — из кук
$isAuthenticated = isset($_SESSION['application_id']);
if ($isAuthenticated) {
    $appId = (int)$_SESSION['application_id'];
    $appData = getApplicationById($appId, $pdo);
    if ($appData) {
        $values = [
            'full_name' => $appData['full_name'],
            'phone' => $appData['phone'],
            'email' => $appData['email'],
            'birth_date' => $appData['birth_date'],
            'gender' => $appData['gender'],
            'languages' => $appData['languages'],
            'bio' => $appData['bio'],
            'contract_agreed' => $appData['contract_agreed']
        ];
    } else {
        // если заявка удалена — выходим
        session_destroy();
        $isAuthenticated = false;
        $messages[] = '<div class="error-message">Ваша анкета не найдена. Войдите заново.</div>';
    }
}

// Для гостя — берём значения из долговременных кук (если нет flash-ошибок)
if (!$isAuthenticated) {
    // Сначала пытаемся взять из flash-кук (последняя неудачная попытка)
    $flashValues = [];
    foreach ($fields as $field) {
        $valueKey = $field . '_value';
        if (!empty($_COOKIE[$valueKey])) {
            $val = $_COOKIE[$valueKey];
            if ($field === 'languages') $val = unserialize($val);
            $flashValues[$field] = $val;
        }
        // Удаляем временные куки, чтобы они не висели (но их прочитаем один раз)
        setcookie($valueKey, '', 100000);
        // Ошибки тоже удаляем (они уже использованы)
        setcookie($field . '_error', '', 100000);
    }
    if (!empty($flashValues)) {
        $values = $flashValues;
    } else {
        // Иначе из постоянных кук
        foreach ($fields as $field) {
            if (!empty($_COOKIE[$field])) {
                $val = $_COOKIE[$field];
                if ($field === 'languages') $val = unserialize($val);
                $values[$field] = $val;
            } else {
                $values[$field] = '';
            }
        }
        if ($values['languages'] === '') $values['languages'] = [];
        $values['contract_agreed'] = !empty($values['contract_agreed']);
    }
    // Убедимся, что languages — массив
    if (!is_array($values['languages'])) $values['languages'] = [];
}

// Собираем ошибки из кук ошибок (для гостя)
if (!$isAuthenticated) {
    foreach ($fields as $field) {
        if (!empty($_COOKIE[$field . '_error'])) {
            $errors[$field] = true;
            // Добавляем сообщение об ошибке
            $messages[] = '<div class="error-message">' . getFieldError($field) . '</div>';
        }
    }
}

// Вспомогательная функция для сообщений об ошибках
function getFieldError($field) {
    $map = [
        'full_name' => 'ФИО должно содержать только буквы, пробелы и дефис',
        'phone' => 'Телефон должен содержать 11 цифр, начиная с 7 или 8.',
        'email' => 'Введите корректный email.',
        'birth_date' => 'Дата рождения должна быть в формате ГГГГ-ММ-ДД и не быть позже текущей.',
        'gender' => 'Выберите пол.',
        'languages' => 'Выберите хотя бы один язык.',
        'bio' => 'Биография не должна быть пустой и превышать 5000 символов.',
        'contract_agreed' => 'Необходимо подтвердить ознакомление с контрактом.'
    ];
    return $map[$field] ?? 'Некорректное значение.';
}

// Подключаем форму
include 'form.php';