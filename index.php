<?php

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $db_host = 'localhost';
        $db_user = 'u82457';
        $db_pass = '7777166';
        $db_name = 'u82457';
        try {
            $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Ошибка подключения к БД: " . $e->getMessage());
        }
    }
    return $pdo;
}

// ========== ФУНКЦИЯ ПОЛУЧЕНИЯ СПИСКА ЯЗЫКОВ ИЗ БД ==========
function getLanguages() {
    $pdo = getDB();          // теперь подключится только при первом вызове
    $stmt = $pdo->query("SELECT name FROM language ORDER BY name");
    $languages = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $languages[] = $row['name'];
    }
    return $languages;
}

// ========== ДОПУСТИМЫЕ ЗНАЧЕНИЯ (белые списки) ==========
$allowed_languages = [
    'Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python',
    'Java', 'Haskell', 'Clojure', 'Prolog', 'Scala', 'Go'
];
$allowed_genders = ['male', 'female'];

// ========== ИНИЦИАЛИЗАЦИЯ ПЕРЕМЕННЫХ ==========
$form_data = [
    'full_name' => '', 'phone' => '', 'email' => '', 'birth_date' => '',
    'gender' => '', 'biography' => '', 'contract_accepted' => false, 'languages' => []
];
$errors = [];
$success_message = '';

// ========== ОБРАБОТКА POST-ЗАПРОСА (ОТПРАВКА ФОРМЫ) ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Заполняем $form_data из $_POST
    $form_data['full_name'] = trim($_POST['full_name'] ?? '');
    $form_data['phone'] = trim($_POST['phone'] ?? '');
    $form_data['email'] = trim($_POST['email'] ?? '');
    $form_data['birth_date'] = trim($_POST['birth_date'] ?? '');
    $form_data['gender'] = $_POST['gender'] ?? '';
    $form_data['biography'] = trim($_POST['biography'] ?? '');
    $form_data['contract_accepted'] = isset($_POST['contract_accepted']);
    $form_data['languages'] = $_POST['languages'] ?? [];

    // ---- ВАЛИДАЦИЯ ----
    // ФИО
    if (empty($form_data['full_name'])) {
        $errors['full_name'] = 'ФИО обязательно для заполнения.';
    } elseif (!preg_match('/^[а-яА-Яa-zA-Z\s]+$/u', $form_data['full_name'])) {
        $errors['full_name'] = 'ФИО должно содержать только буквы и пробелы.';
    } elseif (strlen($form_data['full_name']) > 150) {
        $errors['full_name'] = 'ФИО не должно превышать 150 символов.';
    }

    // Телефон
    if (empty($form_data['phone'])) {
        $errors['phone'] = 'Телефон обязателен.';
    } elseif (!preg_match('/^[\d\s\-\+\(\)]+$/', $form_data['phone'])) {
        $errors['phone'] = 'Телефон содержит недопустимые символы.';
    } elseif (strlen($form_data['phone']) < 6 || strlen($form_data['phone']) > 12) {
        $errors['phone'] = 'Телефон должен содержать от 6 до 12 символов.';
    }

    // Email
    if (empty($form_data['email'])) {
        $errors['email'] = 'Email обязателен.';
    } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Некорректный формат email.';
    }

    // Дата рождения
    if (empty($form_data['birth_date'])) {
        $errors['birth_date'] = 'Дата рождения обязательна.';
    } else {
        $date = DateTime::createFromFormat('Y-m-d', $form_data['birth_date']);
        if (!$date || $date->format('Y-m-d') !== $form_data['birth_date']) {
            $errors['birth_date'] = 'Некорректная дата. Используйте формат ГГГГ-ММ-ДД.';
        } elseif ($date > new DateTime('today')) {
            $errors['birth_date'] = 'Дата рождения не может быть позже сегодняшнего дня.';
        }
    }

    // Пол
    if (empty($form_data['gender'])) {
        $errors['gender'] = 'Выберите пол.';
    } elseif (!in_array($form_data['gender'], $allowed_genders)) {
        $errors['gender'] = 'Недопустимое значение пола.';
    }

    // Языки
    if (empty($form_data['languages'])) {
        $errors['languages'] = 'Выберите хотя бы один язык.';
    } else {
        foreach ($form_data['languages'] as $lang) {
            if (!in_array($lang, $allowed_languages)) {
                $errors['languages'] = 'Выбран недопустимый язык.';
                break;
            }
        }
    }

    // Биография
    if (strlen($form_data['biography']) > 10000) {
        $errors['biography'] = 'Биография слишком длинная (макс. 10000 символов).';
    }

    // Чекбокс
    if (!$form_data['contract_accepted']) {
        $errors['contract_accepted'] = 'Необходимо подтвердить согласие.';
    }

    // ---- СОХРАНЕНИЕ В БД (только если ошибок нет) ----
    if (empty($errors)) {
        try {
            $pdo = getDB();                       // подключаемся к БД только сейчас
            $pdo->beginTransaction();

            // Вставка в application
            $stmt = $pdo->prepare("
                INSERT INTO application 
                (full_name, phone, email, birth_date, gender, biography, contract_accepted)
                VALUES (:full_name, :phone, :email, :birth_date, :gender, :biography, :contract_accepted)
            ");
            $stmt->execute([
                ':full_name' => $form_data['full_name'],
                ':phone' => $form_data['phone'],
                ':email' => $form_data['email'],
                ':birth_date' => $form_data['birth_date'],
                ':gender' => $form_data['gender'],
                ':biography' => $form_data['biography'],
                ':contract_accepted' => $form_data['contract_accepted'] ? 1 : 0
            ]);
            $application_id = $pdo->lastInsertId();

            // Получаем map язык → id
            $lang_map = [];
            $stmt = $pdo->query("SELECT id, name FROM language");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $lang_map[$row['name']] = $row['id'];
            }

            // Вставка связей
            $stmt = $pdo->prepare("INSERT INTO application_language (application_id, language_id) VALUES (?, ?)");
            foreach ($form_data['languages'] as $lang_name) {
                if (isset($lang_map[$lang_name])) {
                    $stmt->execute([$application_id, $lang_map[$lang_name]]);
                }
            }

            $pdo->commit();
            $success_message = 'Данные успешно сохранены!';
            // Очищаем форму
            $form_data = array_map(function() { return ''; }, $form_data);
            $form_data['languages'] = [];
            $form_data['contract_accepted'] = false;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors['db'] = 'Ошибка при сохранении: ' . $e->getMessage();
        }
    }
}

// ========== ПОЛУЧАЕМ ЯЗЫКИ ДЛЯ ФОРМЫ (только при GET или при ошибках POST) ==========
$languages_from_db = getLanguages();
if (empty($languages_from_db)) {
    $languages_from_db = $allowed_languages;   // запасной список, если таблица пуста
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Задание 3 - Анкета</title>
    <link rel="stylesheet" href="style.css">
    <style>
    .nav-buttons {
        margin-top: 30px;
        text-align: center;
        border-top: 1px solid #e0e0e0;
        padding-top: 20px;
        display: flex;
        justify-content: center;
        gap: 20px;
        flex-wrap: wrap;
}
.nav-buttons a {
    display: inline-block;
    background-color: #39e704;
    color: white;
    text-decoration: none;
    padding: 10px 25px;
    border-radius: 5px;
    font-weight: bold;
    transition: background-color 0.2s;
}
.nav-buttons a:hover {
    background-color: #2ecc71;
}
        </style>
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