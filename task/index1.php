<?php

declare(strict_types=1);

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
]);

set_time_limit(10);
ini_set('default_socket_timeout', '5');

const DB_HOST = 'localhost';
const DB_PORT = '3306';
const DB_NAME = 'u82295';
const DB_USER = 'u82295';
const DB_PASSWORD = '7819341';

const FLASH_ERRORS_COOKIE = 'task5_flash_errors';
const FLASH_VALUES_COOKIE = 'task5_flash_values';
const PERSISTENT_VALUES_COOKIE = 'task5_persistent_values';

const AUTH_SESSION_KEY = 'task5_auth_submission_id';
const AUTH_FORM_SESSION_KEY = 'task5_auth_form';
const AUTH_FLASH_ERROR_SESSION_KEY = 'task5_auth_flash_error';
const FLASH_SUCCESS_SESSION_KEY = 'task5_flash_success';
const FLASH_DB_ERROR_SESSION_KEY = 'task5_flash_db_error';
const GENERATED_CREDENTIALS_SESSION_KEY = 'task5_generated_credentials';

$availableLanguages = [
    'Pascal',
    'C',
    'C++',
    'JavaScript',
    'PHP',
    'Python',
    'Java',
    'Haskell',
    'Clojure',
    'Prolog',
    'Scala',
    'Go',
];

$genderOptions = [
    'male' => 'Мужской',
    'female' => 'Женский',
];

$emptyValues = [
    'full_name' => '',
    'phone' => '',
    'email' => '',
    'birth_date' => '',
    'gender' => '',
    'languages' => [],
    'biography' => '',
    'contract_accepted' => false,
];

function stringLength(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value);
    }

    return strlen($value);
}

function setCookieValue(string $name, string $value, int $expires): void
{
    setcookie($name, $value, [
        'expires' => $expires,
        'path' => '/',
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
}

function setCookieArray(string $name, array $value, int $expires): void
{
    setCookieValue($name, json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $expires);
}

function getCookieArray(string $name): ?array
{
    if (!isset($_COOKIE[$name])) {
        return null;
    }

    $decoded = json_decode((string) $_COOKIE[$name], true);
    return is_array($decoded) ? $decoded : null;
}

function clearCookie(string $name): void
{
    setCookieValue($name, '', time() - 3600);
    unset($_COOKIE[$name]);
}

function setSessionFlash(string $key, $value): void
{
    $_SESSION[$key] = $value;
}

function pullSessionFlash(string $key)
{
    if (!array_key_exists($key, $_SESSION)) {
        return null;
    }

    $value = $_SESSION[$key];
    unset($_SESSION[$key]);

    return $value;
}

function redirectToForm(): never
{
    header('Location: ' . strtok((string) $_SERVER['REQUEST_URI'], '?'));
    exit();
}

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function getPdo(): PDO
{
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME),
        DB_USER,
        DB_PASSWORD,
        [
            PDO::ATTR_TIMEOUT => 5,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    ensureApplicationSchema($pdo);

    return $pdo;
}

function ensureApplicationSchema(PDO $pdo): void
{
    static $schemaChecked = false;

    if ($schemaChecked) {
        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS submission_accounts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            submission_id INT UNSIGNED NOT NULL UNIQUE,
            login VARCHAR(64) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_submission_accounts_submission
                FOREIGN KEY (submission_id) REFERENCES submissions(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $schemaChecked = true;
}

function normalizeFormValues(array $input): array
{
    return [
        'full_name' => trim((string) ($input['full_name'] ?? '')),
        'phone' => trim((string) ($input['phone'] ?? '')),
        'email' => trim((string) ($input['email'] ?? '')),
        'birth_date' => trim((string) ($input['birth_date'] ?? '')),
        'gender' => trim((string) ($input['gender'] ?? '')),
        'languages' => array_values(array_unique(array_map('strval', $input['languages'] ?? []))),
        'biography' => trim((string) ($input['biography'] ?? '')),
        'contract_accepted' => isset($input['contract_accepted']) && preg_match('/^1$/', (string) $input['contract_accepted']) === 1,
    ];
}

function validateFormValues(array $values, array $availableLanguages, array $genderOptions): array
{
    $errors = [];
    $genderPattern = '/^(male|female)$/';
    $languagePattern = '/^(Pascal|C|C\+\+|JavaScript|PHP|Python|Java|Haskell|Clojure|Prolog|Scala|Go)$/';

    if ($values['full_name'] === '') {
        $errors['full_name'] = 'Укажите ФИО.';
    } elseif (stringLength($values['full_name']) > 150) {
        $errors['full_name'] = 'ФИО не должно превышать 150 символов.';
    } elseif (!preg_match('/^[\p{L}\s-]+$/u', $values['full_name'])) {
        $errors['full_name'] = 'ФИО должно содержать только буквы, пробелы и дефис.';
    }

    if ($values['phone'] === '') {
        $errors['phone'] = 'Укажите телефон.';
    } elseif (!preg_match('/^\+?[0-9\s\-()]{7,20}$/', $values['phone'])) {
        $errors['phone'] = 'Телефон должен содержать только цифры, пробелы, круглые скобки, дефис и необязательный плюс в начале.';
    }

    if ($values['email'] === '') {
        $errors['email'] = 'Укажите e-mail.';
    } elseif (!preg_match('/^[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}$/i', $values['email'])) {
        $errors['email'] = 'E-mail может содержать латинские буквы, цифры и символы . _ % + - до знака @.';
    } elseif (stringLength($values['email']) > 255) {
        $errors['email'] = 'E-mail не должен превышать 255 символов.';
    }

    if ($values['birth_date'] === '') {
        $errors['birth_date'] = 'Укажите дату рождения.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $values['birth_date'])) {
        $errors['birth_date'] = 'Дата рождения должна быть в формате ГГГГ-ММ-ДД.';
    } else {
        $birthDate = DateTimeImmutable::createFromFormat('Y-m-d', $values['birth_date']);
        $birthDateErrors = DateTimeImmutable::getLastErrors();
        if ($birthDateErrors === false) {
            $birthDateErrors = [
                'warning_count' => 0,
                'error_count' => 0,
            ];
        }

        $isBirthDateValid = $birthDate instanceof DateTimeImmutable
            && $birthDate->format('Y-m-d') === $values['birth_date']
            && $birthDateErrors['warning_count'] === 0
            && $birthDateErrors['error_count'] === 0;

        if (!$isBirthDateValid) {
            $errors['birth_date'] = 'Введите корректную дату рождения.';
        } elseif ($birthDate > new DateTimeImmutable('today')) {
            $errors['birth_date'] = 'Дата рождения не может быть в будущем.';
        }
    }

    if (preg_match($genderPattern, $values['gender']) !== 1 || !array_key_exists($values['gender'], $genderOptions)) {
        $errors['gender'] = 'Выберите допустимый пол.';
    }

    if ($values['languages'] === []) {
        $errors['languages'] = 'Выберите хотя бы один любимый язык программирования.';
    } else {
        foreach ($values['languages'] as $language) {
            if (preg_match($languagePattern, $language) !== 1 || !in_array($language, $availableLanguages, true)) {
                $errors['languages'] = 'Можно выбирать только языки из предложенного списка.';
                break;
            }
        }
    }

    if ($values['biography'] === '') {
        $errors['biography'] = 'Напишите биографию.';
    } elseif (stringLength($values['biography']) > 2000) {
        $errors['biography'] = 'Биография не должна превышать 2000 символов.';
    } elseif (!preg_match('/^[\p{L}\p{N}\s.,!?;:()"«»\'\-\/]+$/u', $values['biography'])) {
        $errors['biography'] = 'Биография может содержать буквы, цифры, пробелы и знаки препинания . , ! ? ; : ( ) " « » - /.';
    }

    if (!$values['contract_accepted']) {
        $errors['contract_accepted'] = 'Необходимо ознакомиться с контрактом.';
    }

    return $errors;
}

function generateRandomString(int $length, string $alphabet): string
{
    $maxIndex = strlen($alphabet) - 1;
    $value = '';

    for ($i = 0; $i < $length; $i++) {
        $value .= $alphabet[random_int(0, $maxIndex)];
    }

    return $value;
}

function generateUniqueLogin(PDO $pdo): string
{
    $statement = $pdo->prepare('SELECT COUNT(*) FROM submission_accounts WHERE login = :login');
    $alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';

    do {
        $login = 'user_' . generateRandomString(8, $alphabet);
        $statement->execute([':login' => $login]);
        $exists = (int) $statement->fetchColumn() > 0;
    } while ($exists);

    return $login;
}

function generatePassword(): string
{
    return generateRandomString(12, 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789');
}

function syncSubmissionLanguages(PDO $pdo, int $submissionId, array $languages): void
{
    $deleteStatement = $pdo->prepare('DELETE FROM submission_languages WHERE submission_id = :submission_id');
    $deleteStatement->execute([':submission_id' => $submissionId]);

    $languageSelectStatement = $pdo->prepare('SELECT id FROM programming_languages WHERE name = :name');
    $insertStatement = $pdo->prepare(
        'INSERT INTO submission_languages (submission_id, language_id) VALUES (:submission_id, :language_id)'
    );

    foreach ($languages as $language) {
        $languageSelectStatement->execute([':name' => $language]);
        $languageId = $languageSelectStatement->fetchColumn();

        if ($languageId === false) {
            throw new RuntimeException('Не найден язык программирования в справочнике: ' . $language);
        }

        $insertStatement->execute([
            ':submission_id' => $submissionId,
            ':language_id' => (int) $languageId,
        ]);
    }
}

function loadSubmissionValues(PDO $pdo, int $submissionId, array $emptyValues): ?array
{
    $submissionStatement = $pdo->prepare(
        'SELECT id, full_name, phone, email, birth_date, gender, biography, contract_accepted
         FROM submissions
         WHERE id = :id'
    );
    $submissionStatement->execute([':id' => $submissionId]);
    $submission = $submissionStatement->fetch();

    if ($submission === false) {
        return null;
    }

    $languagesStatement = $pdo->prepare(
        'SELECT pl.name
         FROM submission_languages sl
         INNER JOIN programming_languages pl ON pl.id = sl.language_id
         WHERE sl.submission_id = :submission_id
         ORDER BY pl.name'
    );
    $languagesStatement->execute([':submission_id' => $submissionId]);

    return array_merge($emptyValues, [
        'full_name' => (string) $submission['full_name'],
        'phone' => (string) $submission['phone'],
        'email' => (string) $submission['email'],
        'birth_date' => (string) $submission['birth_date'],
        'gender' => (string) $submission['gender'],
        'languages' => array_map('strval', $languagesStatement->fetchAll(PDO::FETCH_COLUMN)),
        'biography' => (string) $submission['biography'],
        'contract_accepted' => (bool) $submission['contract_accepted'],
    ]);
}

function loadSubmissionLogin(PDO $pdo, int $submissionId): ?string
{
    $statement = $pdo->prepare(
        'SELECT login
         FROM submission_accounts
         WHERE submission_id = :submission_id'
    );
    $statement->execute([':submission_id' => $submissionId]);
    $login = $statement->fetchColumn();

    return $login === false ? null : (string) $login;
}

function getAuthSubmissionId(): ?int
{
    if (!isset($_SESSION[AUTH_SESSION_KEY])) {
        return null;
    }

    $submissionId = (int) $_SESSION[AUTH_SESSION_KEY];
    return $submissionId > 0 ? $submissionId : null;
}

$action = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? trim((string) ($_POST['action'] ?? 'save'))
    : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'logout') {
    unset($_SESSION[AUTH_SESSION_KEY]);
    unset($_SESSION[AUTH_FORM_SESSION_KEY]);
    setSessionFlash(FLASH_SUCCESS_SESSION_KEY, 'Сессия завершена.');
    redirectToForm();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'login') {
    $login = trim((string) ($_POST['auth_login'] ?? ''));
    $password = (string) ($_POST['auth_password'] ?? '');

    $_SESSION[AUTH_FORM_SESSION_KEY] = ['login' => $login];

    if ($login === '' || $password === '') {
        setSessionFlash(AUTH_FLASH_ERROR_SESSION_KEY, 'Введите логин и пароль.');
        redirectToForm();
    }

    try {
        $pdo = getPdo();
        $statement = $pdo->prepare(
            'SELECT submission_id, password_hash
             FROM submission_accounts
             WHERE login = :login'
        );
        $statement->execute([':login' => $login]);
        $account = $statement->fetch();

        if ($account === false || !password_verify($password, (string) $account['password_hash'])) {
            setSessionFlash(AUTH_FLASH_ERROR_SESSION_KEY, 'Неверный логин или пароль.');
            redirectToForm();
        }

        $_SESSION[AUTH_SESSION_KEY] = (int) $account['submission_id'];
        unset($_SESSION[AUTH_FORM_SESSION_KEY]);
        setSessionFlash(FLASH_SUCCESS_SESSION_KEY, 'Вход выполнен. Можно редактировать сохранённую анкету.');
        redirectToForm();
    } catch (Throwable $exception) {
        setSessionFlash(FLASH_DB_ERROR_SESSION_KEY, 'Не удалось выполнить вход: ' . $exception->getMessage());
        redirectToForm();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save') {
    $values = normalizeFormValues($_POST);
    $errors = validateFormValues($values, $availableLanguages, $genderOptions);

    if ($errors !== []) {
        setCookieArray(FLASH_ERRORS_COOKIE, $errors, 0);
        setCookieArray(FLASH_VALUES_COOKIE, $values, 0);
        redirectToForm();
    }

    try {
        $pdo = getPdo();
        $submissionId = getAuthSubmissionId();

        $pdo->beginTransaction();

        if ($submissionId !== null) {
            $updateStatement = $pdo->prepare(
                'UPDATE submissions
                 SET full_name = :full_name,
                     phone = :phone,
                     email = :email,
                     birth_date = :birth_date,
                     gender = :gender,
                     biography = :biography,
                     contract_accepted = :contract_accepted
                 WHERE id = :id'
            );

            $updateStatement->execute([
                ':id' => $submissionId,
                ':full_name' => $values['full_name'],
                ':phone' => $values['phone'],
                ':email' => $values['email'],
                ':birth_date' => $values['birth_date'],
                ':gender' => $values['gender'],
                ':biography' => $values['biography'],
                ':contract_accepted' => 1,
            ]);

            if ($updateStatement->rowCount() === 0) {
                $existsStatement = $pdo->prepare('SELECT COUNT(*) FROM submissions WHERE id = :id');
                $existsStatement->execute([':id' => $submissionId]);
                if ((int) $existsStatement->fetchColumn() === 0) {
                    throw new RuntimeException('Анкета авторизованного пользователя не найдена.');
                }
            }

            syncSubmissionLanguages($pdo, $submissionId, $values['languages']);
            $pdo->commit();

            clearCookie(FLASH_ERRORS_COOKIE);
            clearCookie(FLASH_VALUES_COOKIE);
            setSessionFlash(FLASH_SUCCESS_SESSION_KEY, 'Данные анкеты успешно обновлены.');
            redirectToForm();
        }

        $insertStatement = $pdo->prepare(
            'INSERT INTO submissions (full_name, phone, email, birth_date, gender, biography, contract_accepted)
             VALUES (:full_name, :phone, :email, :birth_date, :gender, :biography, :contract_accepted)'
        );

        $insertStatement->execute([
            ':full_name' => $values['full_name'],
            ':phone' => $values['phone'],
            ':email' => $values['email'],
            ':birth_date' => $values['birth_date'],
            ':gender' => $values['gender'],
            ':biography' => $values['biography'],
            ':contract_accepted' => 1,
        ]);

        $submissionId = (int) $pdo->lastInsertId();
        syncSubmissionLanguages($pdo, $submissionId, $values['languages']);

        $generatedLogin = generateUniqueLogin($pdo);
        $generatedPassword = generatePassword();

        $accountStatement = $pdo->prepare(
            'INSERT INTO submission_accounts (submission_id, login, password_hash)
             VALUES (:submission_id, :login, :password_hash)'
        );
        $accountStatement->execute([
            ':submission_id' => $submissionId,
            ':login' => $generatedLogin,
            ':password_hash' => password_hash($generatedPassword, PASSWORD_DEFAULT),
        ]);

        $pdo->commit();

        setCookieArray(PERSISTENT_VALUES_COOKIE, $values, time() + 31536000);
        clearCookie(FLASH_ERRORS_COOKIE);
        clearCookie(FLASH_VALUES_COOKIE);
        setSessionFlash(FLASH_SUCCESS_SESSION_KEY, 'Данные успешно сохранены.');
        setSessionFlash(GENERATED_CREDENTIALS_SESSION_KEY, [
            'login' => $generatedLogin,
            'password' => $generatedPassword,
        ]);
        redirectToForm();
    } catch (Throwable $exception) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        setCookieArray(FLASH_VALUES_COOKIE, $values, 0);
        setSessionFlash(FLASH_DB_ERROR_SESSION_KEY, 'Не удалось сохранить данные: ' . $exception->getMessage());
        redirectToForm();
    }
}

$errors = [];
$successMessage = pullSessionFlash(FLASH_SUCCESS_SESSION_KEY);
$dbError = pullSessionFlash(FLASH_DB_ERROR_SESSION_KEY);
$generatedCredentials = pullSessionFlash(GENERATED_CREDENTIALS_SESSION_KEY);
$authError = pullSessionFlash(AUTH_FLASH_ERROR_SESSION_KEY);
$authForm = $_SESSION[AUTH_FORM_SESSION_KEY] ?? ['login' => ''];
$authLogin = null;

$values = $emptyValues;
$authSubmissionId = getAuthSubmissionId();
$isAuthenticated = $authSubmissionId !== null;

if ($isAuthenticated) {
    try {
        $pdo = getPdo();
        $storedValues = loadSubmissionValues($pdo, $authSubmissionId, $emptyValues);

        if ($storedValues === null) {
            unset($_SESSION[AUTH_SESSION_KEY]);
            $isAuthenticated = false;
            $authSubmissionId = null;
            $dbError = 'Сохранённая анкета не найдена. Выполните вход повторно.';
        } else {
            $values = $storedValues;
            $authLogin = loadSubmissionLogin($pdo, $authSubmissionId);
        }
    } catch (Throwable $exception) {
        $dbError = 'Не удалось загрузить данные анкеты: ' . $exception->getMessage();
    }
} else {
    $persistentValues = getCookieArray(PERSISTENT_VALUES_COOKIE);
    if (is_array($persistentValues)) {
        $values = array_merge($values, $persistentValues);
        $values['languages'] = is_array($persistentValues['languages'] ?? null)
            ? array_values(array_map('strval', $persistentValues['languages']))
            : [];
        $values['contract_accepted'] = !empty($persistentValues['contract_accepted']);
    }
}

$flashValues = getCookieArray(FLASH_VALUES_COOKIE);
if (is_array($flashValues)) {
    $values = array_merge($values, $flashValues);
    $values['languages'] = is_array($flashValues['languages'] ?? null)
        ? array_values(array_map('strval', $flashValues['languages']))
        : $values['languages'];
    $values['contract_accepted'] = !empty($flashValues['contract_accepted']);
    clearCookie(FLASH_VALUES_COOKIE);
}

$flashErrors = getCookieArray(FLASH_ERRORS_COOKIE);
if (is_array($flashErrors)) {
    $errors = $flashErrors;
    clearCookie(FLASH_ERRORS_COOKIE);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Задание 5 - Анкета с авторизацией</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="page">
    <div class="card">
        <div class="card__header">
            <p class="eyebrow">Web / Backend</p>
            <div class="hero">
                <div>
                    <h1>Анкета пользователя</h1>
                    <p class="subtitle">Первичная отправка создаёт логин и пароль, а вход по ним открывает режим редактирования уже сохранённых данных.</p>
                </div>
                <div class="status-box">
                    <span class="status-box__label">Статус</span>
                    <strong><?php echo $isAuthenticated ? 'Авторизован' : 'Гость'; ?></strong>
                    <?php if ($isAuthenticated && $authLogin !== null && $authLogin !== ''): ?>
                        <div class="status-box__name"><?php echo escape($authLogin); ?></div>
                    <?php endif; ?>
                    <?php if ($isAuthenticated): ?>
                        <form action="" method="post">
                            <input type="hidden" name="action" value="logout">
                            <button class="button button--secondary button--small" type="submit">Выйти</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($successMessage !== null): ?>
            <div class="alert alert--success"><?php echo escape((string) $successMessage); ?></div>
        <?php endif; ?>

        <?php if (is_array($generatedCredentials)): ?>
            <div class="alert alert--success">
                <strong>Сохраните данные для входа.</strong><br>
                Логин: <code><?php echo escape((string) ($generatedCredentials['login'] ?? '')); ?></code><br>
                Пароль: <code><?php echo escape((string) ($generatedCredentials['password'] ?? '')); ?></code>
            </div>
        <?php endif; ?>

        <?php if ($dbError !== null): ?>
            <div class="alert alert--error"><?php echo escape((string) $dbError); ?></div>
        <?php endif; ?>

        <?php if ($errors !== []): ?>
            <div class="alert alert--error">
                <?php foreach ($errors as $error): ?>
                    <div><?php echo escape((string) $error); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="layout">
            <?php if (!$isAuthenticated): ?>
                <aside class="auth-panel">
                    <h2>Вход для редактирования</h2>
                    <p class="auth-panel__text">Используйте логин и пароль, которые были выданы после первой успешной отправки формы.</p>

                    <?php if ($authError !== null): ?>
                        <div class="alert alert--error auth-panel__alert"><?php echo escape((string) $authError); ?></div>
                    <?php endif; ?>

                    <form action="" method="post" class="auth-form">
                        <input type="hidden" name="action" value="login">

                        <div class="field">
                            <label for="auth_login">Логин</label>
                            <input
                                id="auth_login"
                                name="auth_login"
                                type="text"
                                value="<?php echo escape((string) ($authForm['login'] ?? '')); ?>"
                                autocomplete="username"
                            >
                        </div>

                        <div class="field">
                            <label for="auth_password">Пароль</label>
                            <input
                                id="auth_password"
                                name="auth_password"
                                type="password"
                                autocomplete="current-password"
                            >
                        </div>

                        <button class="button button--secondary" type="submit">Войти</button>
                    </form>
                </aside>
            <?php endif; ?>

            <div class="form-panel">
                <form action="" method="post" novalidate>
                    <input type="hidden" name="action" value="save">

                    <div class="form-grid">
                        <div class="field field--full">
                            <label for="full_name">ФИО</label>
                            <input
                                id="full_name"
                                name="full_name"
                                type="text"
                                maxlength="150"
                                value="<?php echo escape($values['full_name']); ?>"
                                class="<?php echo isset($errors['full_name']) ? 'is-invalid' : ''; ?>"
                                placeholder="Например, Иванов Иван Иванович"
                            >
                            <?php if (isset($errors['full_name'])): ?>
                                <span class="error-text"><?php echo escape($errors['full_name']); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="field">
                            <label for="phone">Телефон</label>
                            <input
                                id="phone"
                                name="phone"
                                type="tel"
                                value="<?php echo escape($values['phone']); ?>"
                                class="<?php echo isset($errors['phone']) ? 'is-invalid' : ''; ?>"
                                placeholder="+7 (900) 123-45-67"
                            >
                            <?php if (isset($errors['phone'])): ?>
                                <span class="error-text"><?php echo escape($errors['phone']); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="field">
                            <label for="email">E-mail</label>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                value="<?php echo escape($values['email']); ?>"
                                class="<?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>"
                                placeholder="example@mail.com"
                            >
                            <?php if (isset($errors['email'])): ?>
                                <span class="error-text"><?php echo escape($errors['email']); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="field">
                            <label for="birth_date">Дата рождения</label>
                            <input
                                id="birth_date"
                                name="birth_date"
                                type="date"
                                value="<?php echo escape($values['birth_date']); ?>"
                                class="<?php echo isset($errors['birth_date']) ? 'is-invalid' : ''; ?>"
                            >
                            <?php if (isset($errors['birth_date'])): ?>
                                <span class="error-text"><?php echo escape($errors['birth_date']); ?></span>
                            <?php endif; ?>
                        </div>

                        <fieldset class="field field--full fieldset <?php echo isset($errors['gender']) ? 'fieldset--invalid' : ''; ?>">
                            <legend>Пол</legend>
                            <div class="radio-group">
                                <?php foreach ($genderOptions as $genderValue => $genderLabel): ?>
                                    <label class="radio-option">
                                        <input
                                            type="radio"
                                            name="gender"
                                            value="<?php echo escape($genderValue); ?>"
                                            <?php echo $values['gender'] === $genderValue ? 'checked' : ''; ?>
                                        >
                                        <span><?php echo escape($genderLabel); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <?php if (isset($errors['gender'])): ?>
                                <span class="error-text"><?php echo escape($errors['gender']); ?></span>
                            <?php endif; ?>
                        </fieldset>

                        <div class="field field--full">
                            <label for="languages">Любимые языки программирования</label>
                            <select
                                id="languages"
                                name="languages[]"
                                multiple
                                size="8"
                                class="<?php echo isset($errors['languages']) ? 'is-invalid' : ''; ?>"
                            >
                                <?php foreach ($availableLanguages as $language): ?>
                                    <option value="<?php echo escape($language); ?>" <?php echo in_array($language, $values['languages'], true) ? 'selected' : ''; ?>>
                                        <?php echo escape($language); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="hint">Зажмите Ctrl или Cmd для выбора нескольких вариантов.</small>
                            <?php if (isset($errors['languages'])): ?>
                                <span class="error-text"><?php echo escape($errors['languages']); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="field field--full">
                            <label for="biography">Биография</label>
                            <textarea
                                id="biography"
                                name="biography"
                                rows="6"
                                class="<?php echo isset($errors['biography']) ? 'is-invalid' : ''; ?>"
                                placeholder="Кратко расскажите о себе"
                            ><?php echo escape($values['biography']); ?></textarea>
                            <?php if (isset($errors['biography'])): ?>
                                <span class="error-text"><?php echo escape($errors['biography']); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="field field--full">
                            <label class="checkbox-option <?php echo isset($errors['contract_accepted']) ? 'checkbox-option--invalid' : ''; ?>">
                                <input
                                    type="checkbox"
                                    name="contract_accepted"
                                    value="1"
                                    <?php echo $values['contract_accepted'] ? 'checked' : ''; ?>
                                >
                                <span>С контрактом ознакомлен(а)</span>
                            </label>
                            <?php if (isset($errors['contract_accepted'])): ?>
                                <span class="error-text"><?php echo escape($errors['contract_accepted']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <button class="button" type="submit">
                        <?php echo $isAuthenticated ? 'Обновить данные' : 'Сохранить'; ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>
