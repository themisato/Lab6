<?php
// create_admin.php - Создание администратора в БД
require_once 'config.php';

// Данные администратора (поменяйте пароль на свой)
$login = 'admin';
$password = 'admin123'; // Смените на свой пароль!

// Хешируем пароль
$password_hash = password_hash($password, PASSWORD_DEFAULT);

try {
    // 1. Проверяем, существует ли таблица admin
    $check = $pdo->query("SHOW TABLES LIKE 'admin'");
    if ($check->rowCount() == 0) {
        // Создаём таблицу
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `admin` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `login` VARCHAR(50) UNIQUE NOT NULL,
                `password_hash` VARCHAR(255) NOT NULL
            )
        ");
        echo "✅ Таблица 'admin' создана<br>";
    }
    
    // 2. Добавляем администратора
    $stmt = $pdo->prepare("INSERT INTO admin (login, password_hash) VALUES (:login, :hash)");
    $stmt->execute([
        ':login' => $login,
        ':hash' => $password_hash
    ]);
    
    echo "✅ Администратор создан!<br>";
    echo "🔑 Логин: <strong>$login</strong><br>";
    echo "🔒 Пароль: <strong>$password</strong><br>";
    echo "<br><a href='admin.php'>Перейти в панель администратора</a>";
    
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        echo "⚠️ Администратор с логином '$login' уже существует!<br>";
        echo "Пароль: <strong>$password</strong><br>";
        echo "<br><a href='admin.php'>Перейти в панель администратора</a>";
    } else {
        echo "❌ Ошибка: " . $e->getMessage() . "<br>";
    }
}
?>