<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Царенов Олег</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .error { border: 2px solid red; }
        .auth-block {
            background: #e8f4fd;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .login-form {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #ddd;
        }
        .error-message { color: red; margin-bottom: 10px; }
        .success-message { color: green; margin-bottom: 10px; }
    </style>
</head>
<body>
<div class="form-container">
    <?php if (!empty($_SESSION['login'])): ?>
        <div class="auth-block">
            <div><strong>Вы авторизованы как:</strong> <?= htmlspecialchars($_SESSION['login']) ?></div>
            <div style="margin-top: 10px; text-align: right;">
                <a href="logout.php" style="color: red;">Выйти</a>
            </div>
        </div>
    <?php endif; ?>

    <h2>Заполните анкету</h2>
    
    <?php if (!empty($messages)): ?>
        <div id="messages">
            <?php foreach ($messages as $msg) { print $msg; } ?>
        </div>
    <?php endif; ?>

    <form action="" method="POST">
        <!-- ВСЕ ВАШИ ПОЛЯ АНКЕТЫ (скопируйте из вашего текущего form.php) -->
        <!-- Например: -->
        <div class="form-group">
            <label class="required" for="full_name">ФИО</label>
            <input type="text" name="full_name" id="full_name" 
                   class="<?= !empty($errors['full_name']) ? 'error' : '' ?>"
                   value="<?= htmlspecialchars($values['full_name'] ?? '') ?>"
                   maxlength="150" required>
            <small>Только буквы и пробелы, не более 150 символов</small>
        </div>
        <!-- ... и так все остальные поля (телефон, email, дата, пол, языки, биография, чекбокс) ... -->
        
        <button type="submit" name="submit_application">Сохранить</button>
    </form>

    <?php if (empty($_SESSION['login'])): ?>
        <div class="login-form">
            <h3>Вход для редактирования</h3>
            <?php if ($loginError): ?>
                <div class="error-message"><?= htmlspecialchars($loginError) ?></div>
            <?php endif; ?>
            <form action="" method="POST">
                <div class="form-group">
                    <label>Логин</label>
                    <input type="text" name="login" value="<?= htmlspecialchars($loginValue) ?>" required>
                </div>
                <div class="form-group">
                    <label>Пароль</label>
                    <input type="password" name="pass" value="<?= htmlspecialchars($passValue) ?>" required>
                </div>
                <button type="submit" name="login_submit">Войти</button>
            </form>
        </div>
    <?php endif; ?>
</div>
</body>
</html>