<?php
// admin_test.php - ИСПРАВЛЕННАЯ ВЕРСИЯ
header('Content-Type: text/html; charset=UTF-8');

// ===== 1. ПРОВЕРКА HTTP-АВТОРИЗАЦИИ =====
if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    header('HTTP/1.0 401 Unauthorized');
    echo '<h1>🔒 Введите логин и пароль</h1>';
    echo '<p>Логин: <strong>admin</strong></p>';
    echo '<p>Пароль: <strong>admin123</strong></p>';
    exit;
}

$login = $_SERVER['PHP_AUTH_USER'];
$password = $_SERVER['PHP_AUTH_PW'];  // ← ЭТО ТО, ЧТО ВЫ ВВЕЛИ!

// ===== 2. ПОДКЛЮЧЕНИЕ К БД =====
$db_host = 'localhost';
$db_user = 'u82686';
$db_pass = '8078259';
$db_name = 'u82686';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("❌ Ошибка БД: " . $e->getMessage());
}

// ===== 3. ИЩЕМ АДМИНА =====
$stmt = $pdo->prepare("SELECT * FROM admin WHERE login = ?");
$stmt->execute([$login]);
$admin = $stmt->fetch();

echo "<h2>Результат проверки:</h2>";
echo "Логин: <strong>$login</strong><br>";
echo "Введенный пароль: <strong>$password</strong><br>";  // ← ТЕПЕРЬ ПРАВИЛЬНО!

if ($admin) {
    echo "✅ Админ найден в БД!<br>";
    echo "Хеш в БД: " . $admin['password_hash'] . "<br>";
    
    if (password_verify($password, $admin['password_hash'])) {
        echo "✅ ПАРОЛЬ ВЕРНЫЙ!<br>";
        echo "<h1 style='color:green;'>✅ ДОСТУП РАЗРЕШЕН</h1>";
    } else {
        echo "❌ ПАРОЛЬ НЕВЕРНЫЙ!<br>";
        echo "Попробуйте пароль: <strong>admin123</strong><br>";
        // Проверяем хеш для admin123
        $test_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
        if (password_verify('admin123', $test_hash)) {
            echo "✅ Хеш в БД соответствует паролю 'admin123'<br>";
        } else {
            echo "❌ Хеш в БД НЕ соответствует паролю 'admin123'<br>";
        }
    }
} else {
    echo "❌ Админ с логином '$login' НЕ НАЙДЕН в БД!<br>";
}
?>