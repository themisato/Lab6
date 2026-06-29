<?php
// admin_stats.php - Статистика
session_start();
require_once 'config.php';

// Проверка администратора
$check = $pdo->query("SHOW TABLES LIKE 'admin'");
if ($check->rowCount() == 0) {
    die("❌ Таблица 'admin' не найдена!");
}

$stmt = $pdo->query("SELECT login, password_hash FROM admin LIMIT 1");
$admin_data = $stmt->fetch();

if (!$admin_data) {
    die("❌ Нет администраторов!");
}

$admin_login = $admin_data['login'];
$admin_hash = $admin_data['password_hash'];

if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    header('HTTP/1.0 401 Unauthorized');
    echo '<h1>🔒 Доступ запрещен</h1>';
    exit;
}

if ($_SERVER['PHP_AUTH_USER'] != $admin_login || !password_verify($_SERVER['PHP_AUTH_PW'], $admin_hash)) {
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    header('HTTP/1.0 401 Unauthorized');
    echo '<h1>❌ Неверные данные</h1>';
    exit;
}

// Статистика
$stats_sql = "SELECT pl.name, 
              COUNT(al.application_id) as count,
              ROUND(COUNT(al.application_id) * 100.0 / (SELECT COUNT(*) FROM applications), 2) as percentage
              FROM programming_languages pl
              LEFT JOIN application_languages al ON pl.id = al.language_id
              GROUP BY pl.id
              ORDER BY count DESC";

$stats_stmt = $pdo->prepare($stats_sql);
$stats_stmt->execute();
$language_stats = $stats_stmt->fetchAll();

$total_users = $pdo->query("SELECT COUNT(*) as total FROM applications")->fetch()['total'];
$gender_stats = $pdo->query("SELECT gender, COUNT(*) as count FROM applications GROUP BY gender")->fetchAll();

$male_count = 0;
$female_count = 0;
foreach ($gender_stats as $g) {
    if ($g['gender'] == 'male') $male_count = $g['count'];
    if ($g['gender'] == 'female') $female_count = $g['count'];
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Статистика</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .container { max-width: 900px; margin: 0 auto; padding: 20px; }
        .header {
            background: linear-gradient(135deg, #9662f0, #110d52);
            color: white;
            padding: 1rem 2rem;
            border-radius: 20px;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 { margin: 0; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .stat-card .number { font-size: 2.5rem; font-weight: bold; color: #110d52; }
        .stat-card .label { color: #666; margin-top: 0.3rem; }
        .chart-box {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }
        .chart-box h2 { color: #110d52; margin-bottom: 1.5rem; }
        .lang-bar {
            display: flex;
            align-items: center;
            margin-bottom: 0.8rem;
        }
        .lang-bar .lang-name { width: 120px; font-weight: 600; color: #110d52; flex-shrink: 0; }
        .lang-bar .bar-track {
            flex: 1;
            height: 30px;
            background: #f0f0f0;
            border-radius: 15px;
            overflow: hidden;
            position: relative;
        }
        .lang-bar .bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #9662f0, #110d52);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 10px;
            color: white;
            font-size: 0.8rem;
            font-weight: bold;
            transition: width 1s ease;
        }
        .lang-bar .bar-count {
            width: 50px;
            text-align: right;
            font-weight: bold;
            color: #110d52;
            flex-shrink: 0;
            margin-left: 10px;
        }
        .gender-chart { display: flex; gap: 2rem; justify-content: center; margin-top: 1rem; }
        .gender-item { text-align: center; }
        .gender-item .circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: bold;
            color: white;
            margin: 0 auto 0.5rem;
        }
        .gender-item .circle.male { background: #2b3cf0; }
        .gender-item .circle.female { background: #d55a83; }
        .gender-item .gender-label { font-weight: 600; color: #110d52; }
        .back-btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #9662f0, #110d52);
            color: white;
            text-decoration: none;
            border-radius: 40px;
            transition: transform 0.2s;
            margin-top: 1rem;
        }
        .back-btn:hover { transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Статистика</h1>
            <span>👤 <?php echo htmlspecialchars($_SERVER['PHP_AUTH_USER']); ?></span>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="number"><?php echo $total_users; ?></div>
                <div class="label">👤 Всего пользователей</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $male_count; ?></div>
                <div class="label">♂ Мужчины</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo $female_count; ?></div>
                <div class="label">♀ Женщины</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo count($language_stats); ?></div>
                <div class="label">🌐 Языков</div>
            </div>
        </div>

        <div class="chart-box">
            <h2>👫 Статистика по полу</h2>
            <div class="gender-chart">
                <div class="gender-item">
                    <div class="circle male"><?php echo $male_count; ?></div>
                    <div class="gender-label">♂ Мужчины</div>
                    <div style="color:#666;"><?php echo $total_users > 0 ? round($male_count * 100 / $total_users, 1) : 0; ?>%</div>
                </div>
                <div class="gender-item">
                    <div class="circle female"><?php echo $female_count; ?></div>
                    <div class="gender-label">♀ Женщины</div>
                    <div style="color:#666;"><?php echo $total_users > 0 ? round($female_count * 100 / $total_users, 1) : 0; ?>%</div>
                </div>
            </div>
        </div>

        <div class="chart-box">
            <h2>🌐 Популярность языков</h2>
            <?php 
            $max_count = !empty($language_stats) ? max(array_column($language_stats, 'count')) : 1;
            if ($max_count == 0) $max_count = 1;
            foreach ($language_stats as $stat): 
            ?>
                <div class="lang-bar">
                    <div class="lang-name"><?php echo htmlspecialchars($stat['name']); ?></div>
                    <div class="bar-track">
                        <div class="bar-fill" style="width: <?php echo ($stat['count'] / $max_count) * 100; ?>%;">
                            <?php echo $stat['count']; ?>
                        </div>
                    </div>
                    <div class="bar-count"><?php echo $stat['count']; ?></div>
                </div>
            <?php endforeach; ?>
            <div style="margin-top:1rem;color:#666;text-align:center;">* Всего пользователей: <?php echo $total_users; ?></div>
        </div>

        <a href="admin.php" class="back-btn">← Назад</a>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const bars = document.querySelectorAll('.bar-fill');
            bars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(() => { bar.style.width = width; }, 300);
            });
        });
    </script>
</body>
</html>