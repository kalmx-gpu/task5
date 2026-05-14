<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();

// Если уже авторизован – на главную
if (!empty($_SESSION['login'])) {
    header('Location: ./');
    exit();
}

$error = '';

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
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Вход</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="form-container" style="max-width: 400px;">
    <h2>Авторизация</h2>
    <?php if ($error): ?>
        <div class="error-message"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post">
        <div class="form-group">
            <label>Логин</label>
            <input type="text" name="login" required>
        </div>
        <div class="form-group">
            <label>Пароль</label>
            <input type="password" name="pass" required>
        </div>
        <button type="submit">Войти</button>
    </form>
</div>
</body>
</html>