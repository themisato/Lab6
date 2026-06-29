<?php
// config.php - Конфигурация подключения к базе данных
$host = 'localhost';
$dbname = 'u82686';
$username = 'u82686';
$password = '8078259';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}

// Секретный ключ для JWT (используется в login.php и edit.php)
define('SECRET_KEY', 'your-secret-key-here-change-it-2026');
?>