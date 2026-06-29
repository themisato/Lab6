<?php
// admin_stats.php - Подробная статистика
session_start();
require_once 'config.php';

// ========== HTTP-АВТОРИЗАЦИЯ ==========
if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
    header('WWW-Authenticate: Basic realm="Админ-панель"');
    header('HTTP/1.0 401 Unauthorized');
    echo '<h1 style="text-align:center;color:#110d52;margin-top:100px;">🔒 Доступ запрещен</h1>';
    exit;
}

$auth_login = $_SERVER['PHP_AUTH_USER'];
$auth_pass  = $_SERVER['PHP_AUTH_PW'];

$stmt = $pdo->prepare("SELECT password_hash FROM admin WHERE login = ?");
$stmt->execute([$auth_login]);
$admin_row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin_row || !password_verify($auth_pass, $admin_row['password_hash'])) {
    header('WWW-Authenticate: Basic realm="Админ-панель"');
    header('HTTP/1.0 401 Unauthorized');
    echo '<h1 style="text-align:center;color:#110d52;margin-top:100px;">❌ Неверный логин или пароль!</h1>';
    exit;
}

// ========== СТАТИСТИКА ==========
$total_users = $pdo->query("SELECT COUNT(*) as total FROM applications")->fetch()['total'];

$lang_stats = [];
$stmt = $pdo->query("
    SELECT pl.name, COUNT(DISTINCT al.application_id) AS count
    FROM programming_languages pl
    LEFT JOIN application_languages al ON pl.id = al.language_id
    GROUP BY pl.id, pl.name
    ORDER BY count DESC, pl.name
");
$lang_stats = $stmt->fetchAll();

$gender_stats = $pdo->query("
    SELECT gender, COUNT(*) as count FROM applications GROUP BY gender
")->fetchAll();
$male_count = 0;
$female_count = 0;
foreach ($gender_stats as $g) {
    if ($g['gender'] == 'male') $male_count = $g['count'];
    if ($g['gender'] == 'female') $female_count = $g['count'];
}

// Возрастная статистика
$age_stats = $pdo->query("
    SELECT 
        CASE 
            WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) < 18 THEN 'до 18'
            WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) BETWEEN 18 AND 25 THEN '18-25'
            WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) BETWEEN 26 AND 35 THEN '26-35'
            WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) BETWEEN 36 AND 50 THEN '36-50'
            ELSE '50+'
        END AS age_group,
        COUNT(*) as count
    FROM applications
    GROUP BY age_group
    ORDER BY age_group
")->fetchAll();

// Контракты
$contract_stats = $pdo->query("
    SELECT contract_accepted, COUNT(*) as count FROM applications GROUP BY contract_accepted
")->fetchAll();
$contract_yes = 0;
$contract_no = 0;
foreach ($contract_stats as $c) {
    if ($c['contract_accepted'] == 1) $contract_yes = $c['count'];
    if ($c['contract_accepted'] == 0) $contract_no = $c['count'];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Подробная статистика</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { background: #f5f0ff; }
        .container { max-width: 1000px; margin: 0 auto; padding: 20px; }
        .admin-header {
            background: linear-gradient(135deg, #9662f0, #110d52);
            color: white;
            padding: 1.5rem 2rem;
            border-radius: 24px;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        .admin-header h1 { margin: 0; }
        .admin-header .user-info { 
            background: rgba(255,255,255,0.2);
            padding: 0.5rem 1.2rem;
            border-radius: 30px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(150, 98, 240, 0.15);
            border: 1px solid #e8d5f5;
        }
        .stat-card .number { 
            font-size: 2.5rem; 
            font-weight: bold; 
            background: linear-gradient(135deg, #9662f0, #110d52);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .stat-card .label { color: #666; margin-top: 0.3rem; }
        
        .chart-box {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 4px 12px rgba(150, 98, 240, 0.1);
            margin-bottom: 2rem;
        }
        .chart-box h2 {
            color: #110d52;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #e8d5f5;
            padding-bottom: 0.5rem;
        }
        
        .lang-bar {
            display: flex;
            align-items: center;
            margin-bottom: 0.7rem;
        }
        .lang-bar .lang-name {
            width: 120px;
            font-weight: 600;
            color: #110d52;
            flex-shrink: 0;
        }
        .lang-bar .bar-track {
            flex: 1;
            height: 30px;
            background: #f0e8f5;
            border-radius: 15px;
            overflow: hidden;
            position: relative;
        }
        .lang-bar .bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #f8b0c0, #9662f0);
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
        
        .gender-chart {
            display: flex;
            gap: 2rem;
            justify-content: center;
            margin-top: 1rem;
            flex-wrap: wrap;
        }
        .gender-item {
            text-align: center;
            min-width: 120px;
        }
        .gender-item .circle {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: bold;
            color: white;
            margin: 0 auto 0.5rem;
        }
        .gender-item .circle.male { background: #2b3cf0; }
        .gender-item .circle.female { background: #d55a83; }
        .gender-item .gender-label { font-weight: 600; color: #110d52; }
        
        .age-chart {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 1rem;
        }
        .age-item {
            text-align: center;
            min-width: 80px;
            padding: 1rem;
            background: #f5f0ff;
            border-radius: 12px;
        }
        .age-item .age-bar {
            width: 40px;
            height: 100px;
            background: #f0e8f5;
            border-radius: 20px;
            margin: 0 auto 0.5rem;
            overflow: hidden;
            position: relative;
        }
        .age-item .age-fill {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            background: linear-gradient(180deg, #9662f0, #110d52);
            border-radius: 20px;
            transition: height 1s ease;
        }
        .age-item .age-label { color: #666; font-size: 0.8rem; }
        .age-item .age-count { font-weight: bold; color: #110d52; }
        
        .bottom-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 2rem;
        }
        .bottom-actions a {
            padding: 0.7rem 1.5rem;
            border-radius: 40px;
            text-decoration: none;
            font-weight: bold;
            transition: transform 0.2s;
        }
        .bottom-actions a:hover { transform: translateY(-2px); }
        .btn-back {
            background: linear-gradient(135deg, #9662f0, #110d52);
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="admin-header">
            <h1>📊 Подробная статистика</h1>
            <div class="user-info">👤 <?php echo htmlspecialchars($auth_login); ?></div>
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
                <div class="number"><?php echo count($lang_stats); ?></div>
                <div class="label">🌐 Языков</div>
            </div>
        </div>

        <div class="chart-box">
            <h2>👫 Статистика по полу</h2>
            <div class="gender-chart">
                <div class="gender-item">
                    <div class="circle male"><?php echo $male_count; ?></div>
                    <div class="gender-label">♂ Мужчины</div>
                    <div style="color:#999;"><?php echo $total_users > 0 ? round($male_count * 100 / $total_users, 1) : 0; ?>%</div>
                </div>
                <div class="gender-item">
                    <div class="circle female"><?php echo $female_count; ?></div>
                    <div class="gender-label">♀ Женщины</div>
                    <div style="color:#999;"><?php echo $total_users > 0 ? round($female_count * 100 / $total_users, 1) : 0; ?>%</div>
                </div>
            </div>
        </div>

        <div class="chart-box">
            <h2>🌐 Популярность языков программирования</h2>
            <?php 
            $max_count = !empty($lang_stats) ? max(array_column($lang_stats, 'count')) : 1;
            if ($max_count == 0) $max_count = 1;
            foreach ($lang_stats as $stat): 
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
            <div style="color:#999; font-size:0.85rem; margin-top:0.5rem; text-align:center;">
                * Всего пользователей: <?php echo $total_users; ?>
            </div>
        </div>

        <div class="chart-box">
            <h2>📅 Возрастные группы</h2>
            <?php if (empty($age_stats)): ?>
                <p style="color:#999;">Нет данных</p>
            <?php else: ?>
                <div class="age-chart">
                    <?php 
                    $max_age = !empty($age_stats) ? max(array_column($age_stats, 'count')) : 1;
                    if ($max_age == 0) $max_age = 1;
                    foreach ($age_stats as $age): 
                    ?>
                        <div class="age-item">
                            <div class="age-bar">
                                <div class="age-fill" style="height: <?php echo ($age['count'] / $max_age) * 100; ?>%;"></div>
                            </div>
                            <div class="age-count"><?php echo $age['count']; ?></div>
                            <div class="age-label"><?php echo $age['age_group']; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="chart-box">
            <h2>📄 Согласие с контрактом</h2>
            <div style="display:flex; gap:2rem; justify-content:center; flex-wrap:wrap;">
                <div style="text-align:center;">
                    <div style="font-size:2.5rem; font-weight:bold; color:#2e7d32;"><?php echo $contract_yes; ?></div>
                    <div style="color:#666;">✅ Согласились</div>
                    <div style="color:#999;font-size:0.85rem;"><?php echo $total_users > 0 ? round($contract_yes * 100 / $total_users, 1) : 0; ?>%</div>
                </div>
                <div style="text-align:center;">
                    <div style="font-size:2.5rem; font-weight:bold; color:#c62828;"><?php echo $contract_no; ?></div>
                    <div style="color:#666;">❌ Не согласились</div>
                    <div style="color:#999;font-size:0.85rem;"><?php echo $total_users > 0 ? round($contract_no * 100 / $total_users, 1) : 0; ?>%</div>
                </div>
            </div>
        </div>

        <div class="bottom-actions">
            <a href="admin.php" class="btn-back">← Назад в админ-панель</a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Анимация баров
            const bars = document.querySelectorAll('.bar-fill');
            bars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(() => { bar.style.width = width; }, 300);
            });
            
            const ageFills = document.querySelectorAll('.age-fill');
            ageFills.forEach(fill => {
                const height = fill.style.height;
                fill.style.height = '0%';
                setTimeout(() => { fill.style.height = height; }, 400);
            });
        });
    </script>
</body>
</html>