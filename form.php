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
        <!-- ========= ПОЛЯ АНКЕТЫ (все, как было) ========= -->
        <div class="form-group">
            <label class="required" for="full_name">ФИО</label>
            <input type="text" name="full_name" id="full_name" 
                   class="<?= !empty($errors['full_name']) ? 'error' : '' ?>"
                   value="<?= htmlspecialchars($values['full_name'] ?? '') ?>"
                   maxlength="150" required>
            <small>Только буквы и пробелы, не более 150 символов</small>
        </div>

        <div class="form-group">
            <label class="required" for="phone">Телефон</label>
            <input type="tel" name="phone" id="phone" 
                   class="<?= !empty($errors['phone']) ? 'error' : '' ?>"
                   value="<?= htmlspecialchars($values['phone'] ?? '') ?>"
                   placeholder="+7XXXXXXXXXX" required>
            <small>В формате +7XXXXXXXXXX или 8XXXXXXXXXX</small>
        </div>

        <div class="form-group">
            <label class="required" for="email">E-mail</label>
            <input type="email" name="email" id="email" 
                   class="<?= !empty($errors['email']) ? 'error' : '' ?>"
                   value="<?= htmlspecialchars($values['email'] ?? '') ?>" required>
        </div>

        <div class="form-group">
            <label class="required" for="birth_date">Дата рождения</label>
            <input type="date" name="birth_date" id="birth_date" 
                   class="<?= !empty($errors['birth_date']) ? 'error' : '' ?>"
                   value="<?= htmlspecialchars($values['birth_date'] ?? '') ?>" required>
        </div>

        <div class="form-group">
            <label class="required">Пол</label>
            <div class="radio-group">
                <label>
                    <input type="radio" name="gender" value="male" 
                           <?= (isset($values['gender']) && $values['gender'] == 'male') ? 'checked' : '' ?> required>
                    Мужской
                </label>
                <label>
                    <input type="radio" name="gender" value="female" 
                           <?= (isset($values['gender']) && $values['gender'] == 'female') ? 'checked' : '' ?> required>
                    Женский
                </label>
            </div>
        </div>

        <div class="form-group">
            <label class="required" for="languages">Любимые языки программирования</label>
            <select name="languages[]" id="languages" multiple 
                    class="<?= !empty($errors['languages']) ? 'error' : '' ?>" required>
                <?php
                $all_languages = ['Pascal','C','C++','JavaScript','PHP','Python','Java','Haskell','Clojure','Prolog','Scala','Go'];
                $selected_langs = $values['languages'] ?? [];
                foreach ($all_languages as $lang) {
                    $selected = in_array($lang, $selected_langs) ? 'selected' : '';
                    echo "<option value=\"" . htmlspecialchars($lang) . "\" $selected>" 
                        . htmlspecialchars($lang) . "</option>";
                }
                ?>
            </select>
            <small>Удерживайте Ctrl (Cmd) для выбора нескольких</small>
        </div>

        <div class="form-group">
            <label class="required" for="bio">Биография</label>
            <textarea name="bio" id="bio" rows="5" 
                      class="<?= !empty($errors['bio']) ? 'error' : '' ?>" required><?= 
                htmlspecialchars($values['bio'] ?? '') 
            ?></textarea>
        </div>

        <div class="form-group checkbox-group">
            <input type="checkbox" name="contract_agreed" id="contract" value="1" 
                   class="<?= !empty($errors['contract_agreed']) ? 'error' : '' ?>"
                   <?= isset($values['contract_agreed']) && $values['contract_agreed'] == '1' ? 'checked' : '' ?> required>
            <label for="contract" class="required">С контрактом ознакомлен(а)</label>
        </div>

        <button type="submit" name="submit_application">Сохранить</button>
    </form>

    <!-- Форма входа показывается ТОЛЬКО если есть куки логина/пароля (анкета уже сохранена) И пользователь не авторизован -->
    <?php if (empty($_SESSION['login']) && !empty($_COOKIE['login']) && !empty($_COOKIE['pass'])): ?>
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