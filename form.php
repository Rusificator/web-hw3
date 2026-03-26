<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Задание 3 - Анкета</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Анкета</h1>

        <?php if ($success_message): ?>
            <div class="success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="errors">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="form-group">
                <label for="full_name">ФИО:</label>
                <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($form_data['full_name']) ?>" required>
                <?php if (isset($errors['full_name'])): ?><span class="field-error"><?= $errors['full_name'] ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="phone">Телефон:</label>
                <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($form_data['phone']) ?>" required>
                <?php if (isset($errors['phone'])): ?><span class="field-error"><?= $errors['phone'] ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="email">E-mail:</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($form_data['email']) ?>" required>
                <?php if (isset($errors['email'])): ?><span class="field-error"><?= $errors['email'] ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="birth_date">Дата рождения:</label>
                <input type="date" id="birth_date" name="birth_date" value="<?= htmlspecialchars($form_data['birth_date']) ?>" required>
                <?php if (isset($errors['birth_date'])): ?><span class="field-error"><?= $errors['birth_date'] ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <label>Пол:</label>
                <div class="radio-group">
                    <label><input type="radio" name="gender" value="male" <?= $form_data['gender'] === 'male' ? 'checked' : '' ?> required> Мужской</label>
                    <label><input type="radio" name="gender" value="female" <?= $form_data['gender'] === 'female' ? 'checked' : '' ?>> Женский</label>
                </div>
                <?php if (isset($errors['gender'])): ?><span class="field-error"><?= $errors['gender'] ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="languages">Любимые языки программирования (выберите один или несколько):</label>
                <select id="languages" name="languages[]" multiple size="6" required>
                    <?php foreach ($languages_from_db as $lang): ?>
                        <option value="<?= htmlspecialchars($lang) ?>" <?= in_array($lang, $form_data['languages']) ? 'selected' : '' ?>><?= htmlspecialchars($lang) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['languages'])): ?><span class="field-error"><?= $errors['languages'] ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="biography">Биография:</label>
                <textarea id="biography" name="biography" rows="6"><?= htmlspecialchars($form_data['biography']) ?></textarea>
                <?php if (isset($errors['biography'])): ?><span class="field-error"><?= $errors['biography'] ?></span><?php endif; ?>
            </div>

            <div class="form-group checkbox">
                <label>
                    <input type="checkbox" name="contract_accepted" value="1" <?= $form_data['contract_accepted'] ? 'checked' : '' ?>>
                    Я ознакомлен(а) с контрактом
                </label>
                <?php if (isset($errors['contract_accepted'])): ?><span class="field-error"><?= $errors['contract_accepted'] ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <button type="submit">Сохранить</button>
            </div>
        </form>

        <div class="nav-buttons">
            <a href="podg.html">📖 Этапы выполнения работы</a>
            <a href="view.php">📊 Просмотр сохранённых анкет</a>
        </div>
    </div>
</body>
</html>