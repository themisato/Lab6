<?php
// admin_test.php - МАКСИМАЛЬНО ПРОСТОЙ ТЕСТ
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
$pass = $_SERVER['PHP_AUTH_PW'];

// ===== 2. ПРОВЕРКА ЧЕРЕЗ БД =====
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
echo "Пароль: <strong>$pass</strong><br>";

if ($admin) {
    echo "✅ Админ найден в БД!<br>";
    echo "Хеш в БД: " . $admin['password_hash'] . "<br>";
    
    if (password_verify($pass, $admin['password_hash'])) {
        echo "✅ ПАРОЛЬ ВЕРНЫЙ!<br>";
        echo "<h1 style='color:green;'>✅ ДОСТУП РАЗРЕШЕН</h1>";
        echo "<p><a href='admin.php'>Перейти в админку</a></p>";
    } else {
        echo "❌ ПАРОЛЬ НЕВЕРНЫЙ!<br>";
        echo "Попробуйте пароль: <strong>admin123</strong><br>";
        // Показываем, какой хеш должен быть для admin123
        echo "Правильный хеш для admin123: <br>";
        echo "<code>\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi</code><br>";
    }
} else {
    echo "❌ Админ с логином '$login' НЕ НАЙДЕН в БД!<br>";
    echo "Проверьте таблицу admin в БД u82686<br>";
}
?>