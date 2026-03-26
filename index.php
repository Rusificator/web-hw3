<?php
//ФУНКЦИЯ ПОДКЛЮЧЕНИЯ К БД 
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

// ФУНКЦИЯ ПОЛУЧЕНИЯ СПИСКА ЯЗЫКОВ ИЗ БД 
function getLanguages() {
    $pdo = getDB();
    $stmt = $pdo->query("SELECT name FROM language ORDER BY name");
    $languages = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $languages[] = $row['name'];
    }
    return $languages;
}

// ДОПУСТИМЫЕ ЗНАЧЕНИЯ (белые списки) 
$allowed_languages = [
    'Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python',
    'Java', 'Haskell', 'Clojure', 'Prolog', 'Scala', 'Go'
];
$allowed_genders = ['male', 'female'];

//  ИНИЦИАЛИЗАЦИЯ ПЕРЕМЕННЫХ 
$form_data = [
    'full_name' => '', 'phone' => '', 'email' => '', 'birth_date' => '',
    'gender' => '', 'biography' => '', 'contract_accepted' => false, 'languages' => []
];
$errors = [];
$success_message = '';

// ОБРАБОТКА POST-ЗАПРОСА (ОТПРАВКА ФОРМЫ)
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

    // СОХРАНЕНИЕ В БД (только если ошибок нет)
    if (empty($errors)) {
        try {
            $pdo = getDB();
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

// ПОЛУЧАЕМ ЯЗЫКИ ДЛЯ ФОРМЫ (только при GET или при ошибках POST)
$languages_from_db = getLanguages();
if (empty($languages_from_db)) {
    $languages_from_db = $allowed_languages;   // запасной список, если таблица пуста
}

// ПОДКЛЮЧАЕМ ФОРМУ
include 'form.php';
?>