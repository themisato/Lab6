<?php
// process.php - Обработчик формы (рабочая версия с отладкой)
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config.php';

// ========== ФУНКЦИИ ==========
function setError($field, $message) {
    setcookie("error_$field", $message, time() + 60, '/');
}

function saveFormData($data) {
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            setcookie("form_$key", implode(',', $value), time() + 3600, '/');
        } else {
            setcookie("form_$key", $value, time() + 3600, '/');
        }
    }
}

// ========== ПОЛУЧАЕМ ДАННЫЕ ==========
$full_name = trim($_GET['full_name'] ?? '');
$phone = trim($_GET['phone'] ?? '');
$email = trim($_GET['email'] ?? '');
$birth_date = trim($_GET['birth_date'] ?? '');
$gender = $_GET['gender'] ?? '';
$languages = $_GET['languages'] ?? [];
$biography = trim($_GET['biography'] ?? '');
$contract_accepted = isset($_GET['contract_accepted']) ? 1 : 0;
$edit_id = isset($_GET['edit_id']) && is_numeric($_GET['edit_id']) ? (int)$_GET['edit_id'] : 0;

// ========== ВАЛИДАЦИЯ ==========
$errors = [];

// 1. ФИО
if (empty($full_name)) {
    $errors['full_name'] = "ФИО обязательно для заполнения";
} elseif (strlen($full_name) > 150) {
    $errors['full_name'] = "ФИО не должно превышать 150 символов";
} elseif (!preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]+$/u', $full_name)) {
    $errors['full_name'] = "ФИО может содержать только буквы, пробелы и дефис";
}

// 2. Телефон
$phone_clean = preg_replace('/[^0-9+]/', '', $phone);
if (empty($phone_clean)) {
    $errors['phone'] = "Телефон обязателен для заполнения";
} elseif (!preg_match('/^(\+7|8)[0-9]{10}$/', $phone_clean)) {
    $errors['phone'] = "Телефон должен быть в формате +7XXXXXXXXXX или 8XXXXXXXXXX";
}

// 3. Email
if (empty($email)) {
    $errors['email'] = "E-mail обязателен для заполнения";
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = "Введите корректный E-mail";
}

// 4. Дата рождения
if (empty($birth_date)) {
    $errors['birth_date'] = "Дата рождения обязательна для заполнения";
} else {
    $date_obj = DateTime::createFromFormat('Y-m-d', $birth_date);
    if (!$date_obj || $date_obj->format('Y-m-d') !== $birth_date) {
        $errors['birth_date'] = "Неверный формат даты";
    }
}

// 5. Пол
if (empty($gender)) {
    $errors['gender'] = "Выберите пол";
} elseif (!in_array($gender, ['male', 'female'])) {
    $errors['gender'] = "Некорректное значение пола";
}

// 6. Языки
if (empty($languages)) {
    $errors['languages'] = "Выберите хотя бы один язык";
}

// 7. Контракт
if (!$contract_accepted) {
    $errors['contract_accepted'] = "Вы должны согласиться с контрактом";
}

// ========== ЕСЛИ ЕСТЬ ОШИБКИ - ПОКАЗЫВАЕМ ИХ ==========
if (!empty($errors)) {
    // Сохраняем данные в Cookies
    saveFormData($_GET);
    
    // Показываем ошибки на этой же странице
    echo "<!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Ошибки валидации</title>
        <link rel='stylesheet' href='style.css'>
        <style>
            .error-container {
                max-width: 600px;
                margin: 50px auto;
                background: #fff;
                padding: 2rem;
                border-radius: 20px;
                box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            }
            .error-container h2 {
                color: #d32f2f;
                text-align: center;
            }
            .error-item {
                background: #ffebee;
                color: #c62828;
                padding: 0.75rem;
                margin: 0.5rem 0;
                border-radius: 8px;
                border-left: 4px solid #d32f2f;
            }
            .back-btn {
                display: inline-block;
                margin-top: 1rem;
                padding: 0.75rem 1.5rem;
                background: linear-gradient(135deg, #9662f0, #110d52);
                color: white;
                text-decoration: none;
                border-radius: 40px;
            }
        </style>
    </head>
    <body>
        <div class='error-container'>
            <h2>❌ Ошибки при заполнении формы</h2>";
            
    foreach ($errors as $field => $message) {
        echo "<div class='error-item'>⚠️ $message</div>";
    }
    
    // Сохраняем ошибки в Cookies для отображения на форме
    foreach ($errors as $field => $message) {
        setcookie("error_$field", $message, 0, '/');
    }
    
    echo "<br><a href='index.php' class='back-btn'>← Вернуться к форме</a>
        </div>
    </body>
    </html>";
    exit;
}

// ========== СОХРАНЕНИЕ В БД ==========
try {
    // Проверяем, существует ли таблица
    $check = $pdo->query("SHOW TABLES LIKE 'applications'");
    if ($check->rowCount() == 0) {
        throw new Exception("Таблица 'applications' не существует! Создайте таблицы в БД.");
    }
    
    $pdo->beginTransaction();
    
    $isEdit = ($edit_id > 0 && isset($_SESSION['user_id']) && $_SESSION['user_id'] == $edit_id);
    
    if ($isEdit) {
        // Обновление
        $sql = "UPDATE applications SET 
                full_name = :full_name,
                phone = :phone,
                email = :email,
                birth_date = :birth_date,
                gender = :gender,
                biography = :biography,
                contract_accepted = :contract_accepted
                WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':full_name' => $full_name,
            ':phone' => $phone_clean,
            ':email' => $email,
            ':birth_date' => $birth_date,
            ':gender' => $gender,
            ':biography' => $biography,
            ':contract_accepted' => $contract_accepted,
            ':id' => $edit_id
        ]);
        $application_id = $edit_id;
        
        // Удаляем старые языки
        $pdo->prepare("DELETE FROM application_languages WHERE application_id = :id")->execute([':id' => $edit_id]);
        
    } else {
        // Новая запись
        $sql = "INSERT INTO applications (full_name, phone, email, birth_date, gender, biography, contract_accepted) 
                VALUES (:full_name, :phone, :email, :birth_date, :gender, :biography, :contract_accepted)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':full_name' => $full_name,
            ':phone' => $phone_clean,
            ':email' => $email,
            ':birth_date' => $birth_date,
            ':gender' => $gender,
            ':biography' => $biography,
            ':contract_accepted' => $contract_accepted
        ]);
        $application_id = $pdo->lastInsertId();
        
        // Генерируем логин и пароль
        $login = strtolower(preg_replace('/[^a-zA-Z]/', '', $full_name));
        $login = substr($login, 0, 8) . '_' . rand(100, 999);
        $password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%'), 0, 12);
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // Сохраняем логин и пароль
        $updateStmt = $pdo->prepare("UPDATE applications SET login = :login, password_hash = :hash WHERE id = :id");
        $updateStmt->execute([
            ':login' => $login,
            ':hash' => $password_hash,
            ':id' => $application_id
        ]);
        
        // Авторизуем пользователя
        $_SESSION['user_id'] = $application_id;
        $_SESSION['user_name'] = $full_name;
    }
    
    // Сохраняем языки
    if (!empty($languages)) {
        $langStmt = $pdo->prepare("SELECT id FROM programming_languages WHERE name = :name");
        $linkStmt = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (:app_id, :lang_id)");
        
        foreach ($languages as $lang_name) {
            $langStmt->execute([':name' => $lang_name]);
            $langRow = $langStmt->fetch();
            if ($langRow) {
                $linkStmt->execute([
                    ':app_id' => $application_id,
                    ':lang_id' => $langRow['id']
                ]);
            }
        }
    }
    
    $pdo->commit();
    
    // Очищаем Cookies ошибок
    foreach (['full_name', 'phone', 'email', 'birth_date', 'gender', 'languages', 'biography', 'contract_accepted'] as $field) {
        setcookie("error_$field", "", time() - 3600, '/');
    }
    
    // ========== ПОКАЗЫВАЕМ СТРАНИЦУ УСПЕХА ==========
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Успешное сохранение</title>
        <link rel="stylesheet" href="style.css">
        <style>
            .success-container {
                max-width: 600px;
                margin: 50px auto;
                background: #fff;
                padding: 2rem;
                border-radius: 20px;
                box-shadow: 0 8px 20px rgba(0,0,0,0.1);
                text-align: center;
            }
            .success-container h2 {
                color: #2e7d32;
            }
            .success-box {
                background: #e8f5e9;
                padding: 1.5rem;
                border-radius: 16px;
                margin: 1.5rem 0;
            }
            .credentials-box {
                background: #fff3e0;
                border: 2px dashed #ff9800;
                padding: 1.5rem;
                border-radius: 16px;
                margin: 1.5rem 0;
            }
            .credentials-box .login-cred {
                font-size: 1.2rem;
                font-weight: bold;
                color: #110d52;
            }
            .action-buttons {
                margin-top: 2rem;
                display: flex;
                gap: 1rem;
                justify-content: center;
                flex-wrap: wrap;
            }
            .action-btn {
                background: linear-gradient(135deg, #9662f0, #110d52);
                color: white;
                padding: 0.75rem 1.5rem;
                border-radius: 40px;
                text-decoration: none;
                font-weight: bold;
                display: inline-block;
            }
            .action-btn:hover {
                transform: translateY(-2px);
            }
            .id-number {
                font-size: 1.5rem;
                color: #110d52;
                font-weight: bold;
            }
        </style>
    </head>
    <body>
        <header>
            <div class="container">
                <h2>✅ Анкета сохранена!</h2>
            </div>
        </header>

        <main class="container">
            <div class="success-container">
                <div class="success-box">
                    <h2>🎉 Данные успешно сохранены!</h2>
                    <p>ID записи: <span class="id-number">#<?php echo $application_id; ?></span></p>
                </div>

                <?php if (!$isEdit): ?>
                <div class="credentials-box">
                    <h3>🔑 Ваши данные для входа</h3>
                    <p class="login-cred">Логин: <strong><?php echo htmlspecialchars($login); ?></strong></p>
                    <p class="login-cred">Пароль: <strong><?php echo htmlspecialchars($password); ?></strong></p>
                    <p style="color: #d32f2f; font-size: 0.9rem;">⚠️ Сохраните эти данные! Пароль отображается только один раз.</p>
                </div>
                <?php endif; ?>

                <div class="action-buttons">
                    <a href="index.php" class="action-btn">📝 Новая анкета</a>
                    <a href="list.php" class="action-btn">📋 Все анкеты</a>
                    <a href="login.php" class="action-btn">🔐 Войти</a>
                </div>
            </div>
        </main>

        <footer>
            <div class="container">
                <p>Лабораторная работа №5 | Май 2026</p>
            </div>
        </footer>
    </body>
    </html>
    <?php
    exit;
    
} catch (PDOException $e) {
    $pdo->rollBack();
    // Показываем ошибку БД
    echo "<!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Ошибка БД</title>
        <link rel='stylesheet' href='style.css'>
        <style>
            .error-container {
                max-width: 600px;
                margin: 50px auto;
                background: #fff;
                padding: 2rem;
                border-radius: 20px;
                box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            }
            .error-container h2 {
                color: #d32f2f;
                text-align: center;
            }
            .error-detail {
                background: #ffebee;
                padding: 1rem;
                border-radius: 8px;
                margin: 1rem 0;
                color: #c62828;
            }
            .back-btn {
                display: inline-block;
                margin-top: 1rem;
                padding: 0.75rem 1.5rem;
                background: linear-gradient(135deg, #9662f0, #110d52);
                color: white;
                text-decoration: none;
                border-radius: 40px;
            }
        </style>
    </head>
    <body>
        <div class='error-container'>
            <h2>❌ Ошибка базы данных</h2>
            <div class='error-detail'>
                <strong>Сообщение:</strong> " . $e->getMessage() . "
            </div>
            <div class='error-detail'>
                <strong>Код:</strong> " . $e->getCode() . "
            </div>
            <br>
            <a href='index.php' class='back-btn'>← Вернуться к форме</a>
        </div>
    </body>
    </html>";
    exit;
    
} catch (Exception $e) {
    // Показываем общую ошибку
    echo "<!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Ошибка</title>
        <link rel='stylesheet' href='style.css'>
        <style>
            .error-container {
                max-width: 600px;
                margin: 50px auto;
                background: #fff;
                padding: 2rem;
                border-radius: 20px;
                box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            }
            .error-container h2 {
                color: #d32f2f;
                text-align: center;
            }
            .error-detail {
                background: #ffebee;
                padding: 1rem;
                border-radius: 8px;
                margin: 1rem 0;
                color: #c62828;
            }
            .back-btn {
                display: inline-block;
                margin-top: 1rem;
                padding: 0.75rem 1.5rem;
                background: linear-gradient(135deg, #9662f0, #110d52);
                color: white;
                text-decoration: none;
                border-radius: 40px;
            }
        </style>
    </head>
    <body>
        <div class='error-container'>
            <h2>❌ Ошибка</h2>
            <div class='error-detail'>
                <strong>Сообщение:</strong> " . $e->getMessage() . "
            </div>
            <br>
            <a href='index.php' class='back-btn'>← Вернуться к форме</a>
        </div>
    </body>
    </html>";
    exit;
}
?>