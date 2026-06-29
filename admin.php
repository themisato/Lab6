<?php
// admin.php - Панель администратора
session_start();
require_once 'config.php';

// ========== ПРОВЕРКА АДМИНИСТРАТОРА ЧЕРЕЗ БД ==========
// Проверяем, есть ли таблица admin
$check = $pdo->query("SHOW TABLES LIKE 'admin'");
if ($check->rowCount() == 0) {
    die("❌ Таблица 'admin' не найдена! Создайте через <a href='create_admin.php'>create_admin.php</a>");
}

// Получаем данные администратора
$stmt = $pdo->query("SELECT login, password_hash FROM admin LIMIT 1");
$admin_data = $stmt->fetch();

if (!$admin_data) {
    die("❌ Нет администраторов в БД! Создайте через <a href='create_admin.php'>create_admin.php</a>");
}

$admin_login = $admin_data['login'];
$admin_hash = $admin_data['password_hash'];

// HTTP-авторизация
if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    header('HTTP/1.0 401 Unauthorized');
    echo '<h1 style="text-align:center;margin-top:50px;color:#d32f2f;">🔒 Доступ запрещен</h1>';
    echo '<p style="text-align:center;">Требуется авторизация</p>';
    exit;
}

// Проверяем логин
if ($_SERVER['PHP_AUTH_USER'] != $admin_login) {
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    header('HTTP/1.0 401 Unauthorized');
    echo '<h1 style="text-align:center;margin-top:50px;">❌ Неверный логин</h1>';
    exit;
}

// Проверяем пароль
if (!password_verify($_SERVER['PHP_AUTH_PW'], $admin_hash)) {
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    header('HTTP/1.0 401 Unauthorized');
    echo '<h1 style="text-align:center;margin-top:50px;">❌ Неверный пароль</h1>';
    exit;
}

// ========== ОБРАБОТКА GET-ПАРАМЕТРОВ ==========
// Удаление
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    try {
        $pdo->prepare("DELETE FROM application_languages WHERE application_id = :id")->execute([':id' => $delete_id]);
        $pdo->prepare("DELETE FROM applications WHERE id = :id")->execute([':id' => $delete_id]);
        $success_msg = "✅ Анкета #$delete_id успешно удалена!";
    } catch (PDOException $e) {
        $error_msg = "Ошибка удаления: " . $e->getMessage();
    }
}

// ========== ПОЛУЧАЕМ ДАННЫЕ ==========
// Все анкеты с языками
$sql = "SELECT a.*, 
        GROUP_CONCAT(pl.name ORDER BY pl.name SEPARATOR ', ') as languages
        FROM applications a
        LEFT JOIN application_languages al ON a.id = al.application_id
        LEFT JOIN programming_languages pl ON al.language_id = pl.id
        GROUP BY a.id
        ORDER BY a.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$applications = $stmt->fetchAll();

// Статистика по языкам
$stats_sql = "SELECT pl.name, COUNT(al.application_id) as count 
              FROM programming_languages pl
              LEFT JOIN application_languages al ON pl.id = al.language_id
              GROUP BY pl.id
              ORDER BY count DESC";
$stats_stmt = $pdo->prepare($stats_sql);
$stats_stmt->execute();
$language_stats = $stats_stmt->fetchAll();

// Статистика по полу
$gender_stats = $pdo->query("SELECT gender, COUNT(*) as count FROM applications GROUP BY gender")->fetchAll();

$total_users = count($applications);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель администратора</title>
    <link rel="stylesheet" href="style.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f5f0ff; }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #9662f0, #110d52);
            color: white;
            padding: 1rem 2rem;
            border-radius: 20px;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .admin-header h1 { font-size: 1.8rem; }
        .admin-header .user-info { display: flex; align-items: center; gap: 1rem; }
        .admin-header .logout-link {
            color: white;
            text-decoration: none;
            background: rgba(255,255,255,0.2);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            transition: background 0.3s;
        }
        .admin-header .logout-link:hover { background: rgba(255,255,255,0.3); }
        
        .success-msg, .error-msg {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        .success-msg { background: #e8f5e9; color: #2e7d32; }
        .error-msg { background: #ffebee; color: #c62828; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stats-card {
            background: white;
            padding: 1.2rem;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .stats-card .number {
            font-size: 2.2rem;
            font-weight: bold;
            color: #110d52;
        }
        .stats-card .label {
            color: #666;
            font-size: 0.85rem;
            margin-top: 0.3rem;
        }
        .stats-card .lang-name {
            font-weight: bold;
            color: #110d52;
        }
        
        .table-wrapper {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            overflow-x: auto;
        }
        .table-wrapper h2 { color: #110d52; margin-bottom: 1rem; }
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
            font-size: 0.9rem;
        }
        .admin-table th {
            background: #f5f0ff;
            color: #110d52;
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 2px solid #9662f0;
            white-space: nowrap;
        }
        .admin-table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }
        .admin-table tr:hover { background: #faf5ff; }
        
        .badge {
            display: inline-block;
            background: #9662f0;
            color: white;
            padding: 0.15rem 0.6rem;
            border-radius: 20px;
            font-size: 0.7rem;
            margin: 0.1rem;
        }
        .gender-male { color: #2b3cf0; font-weight: bold; }
        .gender-female { color: #d55a83; font-weight: bold; }
        
        .actions { display: flex; gap: 0.3rem; flex-wrap: nowrap; }
        .btn-view, .btn-edit, .btn-delete {
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            text-decoration: none;
            font-size: 0.75rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .btn-view { background: #2196f3; color: white; }
        .btn-view:hover { background: #1976d2; }
        .btn-edit { background: #4caf50; color: white; }
        .btn-edit:hover { background: #388e3c; }
        .btn-delete { background: #f44336; color: white; }
        .btn-delete:hover { background: #d32f2f; }
        
        .empty-state { text-align: center; padding: 3rem; color: #9662f0; }
        
        .action-buttons {
            margin-top: 2rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            justify-content: center;
        }
        .action-btn {
            background: linear-gradient(135deg, #9662f0, #110d52);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 40px;
            text-decoration: none;
            font-weight: bold;
            transition: transform 0.2s;
            display: inline-block;
        }
        .action-btn:hover { transform: translateY(-2px); }
        .action-btn.secondary { background: linear-gradient(135deg, #b0bec5, #90a4ae); }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: white;
            border-radius: 24px;
            max-width: 500px;
            width: 90%;
            padding: 2rem;
            position: relative;
            max-height: 80vh;
            overflow-y: auto;
        }
        .modal-close {
            position: absolute;
            right: 1.5rem;
            top: 1rem;
            font-size: 1.5rem;
            cursor: pointer;
            color: #9662f0;
        }
        .modal-close:hover { color: #110d52; }
        .modal h3 { color: #110d52; margin-bottom: 1rem; }
        .modal-field { margin-bottom: 0.75rem; }
        .modal-field strong {
            color: #110d52;
            display: inline-block;
            width: 120px;
        }
        
        @media (max-width: 768px) {
            .admin-header { flex-direction: column; text-align: center; }
            .admin-table { font-size: 0.75rem; min-width: 700px; }
            .admin-table th, .admin-table td { padding: 0.4rem; }
            .actions { flex-wrap: wrap; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="admin-header">
            <h1>🔐 Панель администратора</h1>
            <div class="user-info">
                <span>👤 <?php echo htmlspecialchars($_SERVER['PHP_AUTH_USER']); ?></span>
                <a href="?logout=1" class="logout-link">🚪 Выйти</a>
            </div>
        </div>

        <?php if (isset($success_msg)): ?>
            <div class="success-msg"><?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if (isset($error_msg)): ?>
            <div class="error-msg"><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stats-card">
                <div class="number"><?php echo $total_users; ?></div>
                <div class="label">👤 Всего пользователей</div>
            </div>
            <?php 
            $gender_male = 0;
            $gender_female = 0;
            foreach ($gender_stats as $g) {
                if ($g['gender'] == 'male') $gender_male = $g['count'];
                if ($g['gender'] == 'female') $gender_female = $g['count'];
            }
            ?>
            <div class="stats-card">
                <div class="number"><?php echo $gender_male; ?></div>
                <div class="label">♂ Мужчины</div>
            </div>
            <div class="stats-card">
                <div class="number"><?php echo $gender_female; ?></div>
                <div class="label">♀ Женщины</div>
            </div>
            <?php foreach (array_slice($language_stats, 0, 3) as $stat): ?>
                <div class="stats-card">
                    <div class="lang-name"><?php echo htmlspecialchars($stat['name']); ?></div>
                    <div class="number" style="font-size:1.5rem;"><?php echo $stat['count']; ?></div>
                    <div class="label">пользователей</div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="table-wrapper">
            <h2>📋 Все анкеты</h2>
            
            <?php if (empty($applications)): ?>
                <div class="empty-state">
                    <p>😕 Пока нет ни одной сохранённой анкеты.</p>
                </div>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>ФИО</th>
                            <th>Телефон</th>
                            <th>Email</th>
                            <th>Дата рождения</th>
                            <th>Пол</th>
                            <th>Языки</th>
                            <th>Дата</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications as $app): ?>
                            <tr>
                                <td><?php echo $app['id']; ?></td>
                                <td><?php echo htmlspecialchars($app['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($app['phone']); ?></td>
                                <td><?php echo htmlspecialchars($app['email']); ?></td>
                                <td><?php echo date('d.m.Y', strtotime($app['birth_date'])); ?></td>
                                <td class="<?php echo $app['gender'] == 'male' ? 'gender-male' : 'gender-female'; ?>">
                                    <?php echo $app['gender'] == 'male' ? '♂ Мужской' : '♀ Женский'; ?>
                                </td>
                                <td>
                                    <?php 
                                    $langs = explode(', ', $app['languages'] ?? '');
                                    foreach ($langs as $lang):
                                        if (trim($lang)):
                                    ?>
                                        <span class="badge"><?php echo htmlspecialchars(trim($lang)); ?></span>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </td>
                                <td><?php echo date('d.m.Y H:i', strtotime($app['created_at'])); ?></td>
                                <td>
                                    <div class="actions">
                                        <a href="admin_view.php?id=<?php echo $app['id']; ?>" class="btn-view" target="_blank">👁️</a>
                                        <a href="admin_edit.php?id=<?php echo $app['id']; ?>" class="btn-edit">✏️</a>
                                        <a href="admin.php?delete=<?php echo $app['id']; ?>" class="btn-delete" onclick="return confirm('Удалить анкету #<?php echo $app['id']; ?>?')">🗑️</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="action-buttons">
            <a href="admin_stats.php" class="action-btn">📊 Подробная статистика</a>
            <a href="index.php" class="action-btn secondary">📝 На главную</a>
            <a href="list.php" class="action-btn secondary">📋 Все анкеты</a>
        </div>
    </div>

    <script>
        // Выход
        <?php if (isset($_GET['logout'])): ?>
            <?php
            header('WWW-Authenticate: Basic realm="Admin Panel"');
            header('HTTP/1.0 401 Unauthorized');
            echo '<h1 style="text-align:center;margin-top:50px;">Выход выполнен</h1>';
            echo '<p style="text-align:center;"><a href="admin.php">Войти снова</a></p>';
            exit;
            ?>
        <?php endif; ?>
    </script>
</body>
</html>