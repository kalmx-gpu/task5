<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Задание 5 - Анкета с авторизацией</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="form-container">
    <h2>Анкета пользователя</h2>
    <p class="subtitle">Первая отправка создаст логин и пароль. Войдите, чтобы редактировать данные.</p>

    <?php if (!empty($messages)): ?>
        <div id="messages">
            <?php foreach ($messages as $msg) echo $msg; ?>
        </div>
    <?php endif; ?>

    <?php if (!$isAuthenticated): ?>
        <div class="auth-panel">
            <h3>Вход для редактирования</h3>
            <form action="" method="post">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label>Логин</label>
                    <input type="text" name="auth_login" required>
                </div>
                <div class="form-group">
                    <label>Пароль</label>
                    <input type="password" name="auth_password" required>
                </div>
                <button type="submit" class="button-secondary">Войти</button>
            </form>
        </div>
        <hr>
    <?php else: ?>
        <div class="auth-panel">
            <p>Вы авторизованы. <a href="?logout=1" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">Выйти</a></p>
            <form id="logout-form" action="" method="post" style="display:none;">
                <input type="hidden" name="action" value="logout">
            </form>
        </div>
    <?php endif; ?>

    <form action="" method="POST">
        <input type="hidden" name="action" value="save">

        <div class="form-group">
            <label class="required" for="full_name">ФИО</label>
            <input type="text" name="full_name" id="full_name"
                   class="<?= !empty($errors['full_name']) ? 'error' : '' ?>"
                   value="<?= htmlspecialchars($values['full_name'] ?? '') ?>"
                   maxlength="150" required>
        </div>

        <div class="form-group">
            <label class="required" for="phone">Телефон</label>
            <input type="tel" name="phone" id="phone"
                   class="<?= !empty($errors['phone']) ? 'error' : '' ?>"
                   value="<?= htmlspecialchars($values['phone'] ?? '') ?>"
                   placeholder="+7XXXXXXXXXX" required>
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
                <label><input type="radio" name="gender" value="male" <?= (isset($values['gender']) && $values['gender'] == 'male') ? 'checked' : '' ?> required> Мужской</label>
                <label><input type="radio" name="gender" value="female" <?= (isset($values['gender']) && $values['gender'] == 'female') ? 'checked' : '' ?> required> Женский</label>
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
                    echo "<option value=\"" . htmlspecialchars($lang) . "\" $selected>" . htmlspecialchars($lang) . "</option>";
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

        <button type="submit"><?= $isAuthenticated ? 'Обновить данные' : 'Сохранить' ?></button>
    </form>
</div>
</body>
</html>