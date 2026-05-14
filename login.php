<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();

if (!empty($_SESSION['login'])) {
    header('Location: ./');
    exit();
}

$error = '';
$loginValue = $_COOKIE['login'] ?? '';
$passValue = $_COOKIE['pass'] ?? '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['pass'] ?? '';

    if ($login === '' || $password === '') {
        $error = 'Заполните логин и пароль.';
    } else {
        require_once 'config.php';
        $config = require 'config.php';
        $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        $stmt = $pdo->prepare("SELECT id, login, password_hash FROM users WHERE login = ?");
        $stmt->execute([$login]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['login'] = $user['login'];
            $_SESSION['uid'] = $user['id'];
            header('Location: ./');
            exit();
        } else {
            $error = 'Неверный логин или пароль.';
            // при ошибке подставляем введённые значения
            $loginValue = $login;
            $passValue = $password;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Вход для редактирования</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .info-box {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .error-message {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
<div class="form-container" style="max-width: 500px;">
    <h2>Вход для редактирования</h2>

    <?php if (!empty($_COOKIE['login']) && !empty($_COOKIE['pass'])): ?>
        <div class="info-box">
            <strong>Ваши данные для входа (сохраните их):</strong><br>
            Логин: <?= htmlspecialchars($_COOKIE['login']) ?><br>
            Пароль: <?= htmlspecialchars($_COOKIE['pass']) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error-message"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="form-group">
            <label>Логин</label>
            <input type="text" name="login" value="<?= htmlspecialchars($loginValue) ?>" required>
        </div>
        <div class="form-group">
            <label>Пароль</label>
            <input type="password" name="pass" value="<?= htmlspecialchars($passValue) ?>" required>
        </div>
        <button type="submit">Войти</button>
    </form>
</div>
</body>
</html>