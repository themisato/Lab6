<?php
// admin_stats.php - Подробная статистика
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

$lang_stats = $pdo->query("
    SELECT pl.name, COUNT(DISTINCT al.application_id) AS count
    FROM programming_languages pl
    LEFT JOIN application_languages al ON pl.id = al.language_id
    GROUP BY pl.id, pl.name
    ORDER BY count DESC, pl.name
")->fetchAll();

$gender_stats = $pdo->query("SELECT gender, COUNT(*) as count FROM applications GROUP BY gender")->fetchAll();
$male_count = 0; $female_count = 0;
foreach ($gender_stats as $g) {
    if ($g['gender'] == 'male') $male_count = $g['count'];
    if ($g['gender'] == 'female') $female_count = $g['count'];
}

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

$contract_stats = $pdo->query("SELECT contract_accepted, COUNT(*) as count FROM applications GROUP BY contract_accepted")->fetchAll();
$contract_yes = 0; $contract_no = 0;
foreach ($contract_stats as $c) {
    if ($c['contract_accepted'] == 1) $contract_yes = $c['count'];
    if ($c['contract_accepted'] == 0) $contract_no = $c['count'];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Подробная статистика</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { background: #f5f0ff; font-family: 'Segoe UI', sans-serif; }
        .container { max-width: 1000px; margin: 0 auto; padding: 20px; }
        .header {
            background: linear-gradient(135deg, #9662f0, #110d52);
            color: white;
            padding: 20px 30px;
            border-radius: 24px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        .header h1 { margin: 0; }
        .header a { color: white; text-decoration: none; padding: 8px 20px; border-radius: 30px; background: rgba(255,255,255,0.2); }
        .header a:hover { background: rgba(255,255,255,0.4); }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(150,98,240,0.1);
        }
        .stat-card .number {
            font-size: 32px;
            font-weight: bold;
            background: linear-gradient(135deg, #9662f0, #110d52);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .stat-card .label { color: #666; font-size: 14px; }
        .chart-box {
            background: white;
            padding: 25px;
            border-radius: 20px;
            box-shadow: 0 4px 16px rgba(150,98,240,0.1);
            margin-bottom: 25px;
        }
        .chart-box h2 { color: #110d52; margin-bottom: 20px; border-bottom: 2px solid #e8d5f5; padding-bottom: 10px; }
        .lang-bar {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
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
            font-weight: bold;
            font-size: 13px;
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
            gap: 40px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .gender-item { text-align: center; }
        .gender-item .circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            font-weight: bold;
            color: white;
            margin: 0 auto 10px;
        }
        .gender-item .circle.male { background: #2b3cf0; }
        .gender-item .circle.female { background: #d55a83; }
        .gender-item .gender-label { font-weight: 600; color: #110d52; }
        .age-chart {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .age-item {
            text-align: center;
            min-width: 70px;
            padding: 15px;
            background: #f5f0ff;
            border-radius: 12px;
        }
        .age-item .age-bar {
            width: 40px;
            height: 100px;
            background: #f0e8f5;
            border-radius: 20px;
            margin: 0 auto 10px;
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
        .bottom-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        .bottom-actions a {
            padding: 12px 30px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: bold;
            transition: transform 0.2s;
        }
        .bottom-actions a:hover { transform: translateY(-2px); }
        .btn-back { background: linear-gradient(135deg, #9662f0, #110d52); color: white; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Подробная статистика</h1>
            <a href="admin.php">← Назад</a>
        </div>

        <div class="stats-grid">
            <div class="stat-card"><div class="number"><?php echo $total_users; ?></div><div class="label">👤 Всего пользователей</div></div>
            <div class="stat-card"><div class="number"><?php echo $male_count; ?></div><div class="label">♂ Мужчины</div></div>
            <div class="stat-card"><div class="number"><?php echo $female_count; ?></div><div class="label">♀ Женщины</div></div>
            <div class="stat-card"><div class="number"><?php echo count($lang_stats); ?></div><div class="label">🌐 Языков</div></div>
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
            <h2>🌐 Популярность языков</h2>
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
                            <div style="font-weight:bold;color:#110d52;"><?php echo $age['count']; ?></div>
                            <div style="color:#666;font-size:13px;"><?php echo $age['age_group']; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="chart-box">
            <h2>📄 Согласие с контрактом</h2>
            <div style="display:flex; gap:40px; justify-content:center; flex-wrap:wrap;">
                <div style="text-align:center;">
                    <div style="font-size:32px; font-weight:bold; color:#2e7d32;"><?php echo $contract_yes; ?></div>
                    <div style="color:#666;">✅ Согласились</div>
                </div>
                <div style="text-align:center;">
                    <div style="font-size:32px; font-weight:bold; color:#c62828;"><?php echo $contract_no; ?></div>
                    <div style="color:#666;">❌ Не согласились</div>
                </div>
            </div>
        </div>

        <div class="bottom-actions">
            <a href="admin.php" class="btn-back">← Назад в админ-панель</a>
        </div>
    </div>
</body>
</html>