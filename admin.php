<?php
// admin.php - Админ-панель с HTTP-авторизацией
header('Content-Type: text/html; charset=UTF-8');
require_once 'config.php';

// ========== HTTP-АВТОРИЗАЦИЯ ==========
if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
    header('WWW-Authenticate: Basic realm="Админ-панель"');
    header('HTTP/1.0 401 Unauthorized');
    echo '<h1 style="text-align:center;color:#110d52;margin-top:100px;">🔒 Доступ запрещен<br>Введите логин и пароль администратора.</h1>';
    exit;
}

$auth_login = $_SERVER['PHP_AUTH_USER'];
$auth_pass  = $_SERVER['PHP_AUTH_PW'];

// Проверка в таблице admin
$stmt = $pdo->prepare("SELECT password_hash FROM admin WHERE login = ?");
$stmt->execute([$auth_login]);
$admin_row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin_row || !password_verify($auth_pass, $admin_row['password_hash'])) {
    header('WWW-Authenticate: Basic realm="Админ-панель"');
    header('HTTP/1.0 401 Unauthorized');
    echo '<h1 style="text-align:center;color:#110d52;margin-top:100px;">❌ Неверный логин или пароль!</h1>';
    exit;
}

// ========== ОБРАБОТКА ДЕЙСТВИЙ ==========
$messages = [];

// Удаление
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM applications WHERE id = ?")->execute([$id]);
    $messages[] = '<div class="message success">✅ Анкета №' . $id . ' успешно удалена</div>';
}

// Редактирование - получение данных
$edit_id = 0;
$edit_values = [];
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM applications WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_values = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($edit_values) {
        $lang_stmt = $pdo->prepare("
            SELECT pl.name 
            FROM application_languages al 
            JOIN programming_languages pl ON al.language_id = pl.id 
            WHERE al.application_id = ?
        ");
        $lang_stmt->execute([$edit_id]);
        $edit_values['languages'] = $lang_stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

// Сохранение редактирования
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $id = (int)$_POST['edit_id'];
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $birth_date = trim($_POST['birth_date'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $biography = trim($_POST['biography'] ?? '');
    $contract_accepted = isset($_POST['contract_accepted']) ? 1 : 0;
    $languages = $_POST['languages'] ?? [];

    if (empty($full_name) || empty($email) || empty($phone) || empty($birth_date) || empty($gender)) {
        $messages[] = '<div class="message error">⚠️ Заполните все обязательные поля</div>';
    } else {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                UPDATE applications 
                SET full_name = ?, phone = ?, email = ?, birth_date = ?, 
                    gender = ?, biography = ?, contract_accepted = ?
                WHERE id = ?
            ");
            $stmt->execute([$full_name, $phone, $email, $birth_date, $gender, $biography, $contract_accepted, $id]);

            $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?")->execute([$id]);

            $lang_map = [];
            $stmt = $pdo->query("SELECT id, name FROM programming_languages");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $lang_map[$row['name']] = $row['id'];
            }
            $stmt = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, ?)");
            foreach ($languages as $lang_name) {
                if (isset($lang_map[$lang_name])) {
                    $stmt->execute([$id, $lang_map[$lang_name]]);
                }
            }

            $pdo->commit();
            $messages[] = '<div class="message success">✅ Анкета №' . $id . ' успешно обновлена</div>';
            $edit_id = 0;
        } catch (Exception $e) {
            $pdo->rollBack();
            $messages[] = '<div class="message error">❌ Ошибка: ' . $e->getMessage() . '</div>';
        }
    }
}

// ========== ЗАГРУЗКА ДАННЫХ ==========
$applications = [];
$stmt = $pdo->query("
    SELECT a.*, GROUP_CONCAT(pl.name SEPARATOR ', ') AS languages_list
    FROM applications a
    LEFT JOIN application_languages al ON a.id = al.application_id
    LEFT JOIN programming_languages pl ON al.language_id = pl.id
    GROUP BY a.id
    ORDER BY a.id DESC
");
$applications = $stmt->fetchAll();

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

$gender_stats = [];
$stmt = $pdo->query("SELECT gender, COUNT(*) as count FROM applications GROUP BY gender");
$gender_stats_raw = $stmt->fetchAll();
foreach ($gender_stats_raw as $g) {
    $gender_stats[$g['gender']] = $g['count'];
}
$male_count = $gender_stats['male'] ?? 0;
$female_count = $gender_stats['female'] ?? 0;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Стили в цветовой гамме pink/purple */
        body { background: #f5f0ff; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        
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
        .admin-header h1 { margin: 0; font-size: 1.8rem; }
        .admin-header .user-info { 
            background: rgba(255,255,255,0.2);
            padding: 0.5rem 1.2rem;
            border-radius: 30px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            padding: 1.2rem;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(150, 98, 240, 0.15);
            border: 1px solid #e8d5f5;
        }
        .stat-card .number { 
            font-size: 2.2rem; 
            font-weight: bold; 
            background: linear-gradient(135deg, #9662f0, #110d52);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .stat-card .label { color: #666; margin-top: 0.3rem; font-size: 0.9rem; }
        
        .message {
            padding: 0.8rem 1.2rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            font-weight: 500;
        }
        .message.success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #4caf50; }
        .message.error { background: #ffebee; color: #c62828; border-left: 4px solid #f44336; }
        
        .table-wrapper {
            overflow-x: auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 4px 12px rgba(150, 98, 240, 0.1);
            padding: 0 0 1px 0;
            margin-bottom: 2rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }
        th {
            background: linear-gradient(135deg, #f8b0c0, #9662f0);
            color: white;
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            position: sticky;
            top: 0;
        }
        td {
            padding: 10px 16px;
            border-bottom: 1px solid #f0e8f5;
            vertical-align: middle;
        }
        tr:hover { background: #faf5ff; }
        
        .badge {
            display: inline-block;
            background: #9662f0;
            color: white;
            padding: 0.15rem 0.6rem;
            border-radius: 20px;
            font-size: 0.7rem;
            margin: 0.1rem;
        }
        .actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .actions a {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            text-decoration: none;
            font-size: 0.8rem;
            transition: all 0.2s;
        }
        .actions .btn-edit {
            background: #e8d5f5;
            color: #110d52;
        }
        .actions .btn-edit:hover { background: #9662f0; color: white; }
        .actions .btn-delete {
            background: #ffebee;
            color: #c62828;
        }
        .actions .btn-delete:hover { background: #c62828; color: white; }
        .actions .btn-view {
            background: #e3f2fd;
            color: #0d47a1;
        }
        .actions .btn-view:hover { background: #0d47a1; color: white; }
        
        .gender-male { color: #2b3cf0; font-weight: bold; }
        .gender-female { color: #d55a83; font-weight: bold; }
        
        /* Форма редактирования */
        .edit-form {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 4px 12px rgba(150, 98, 240, 0.15);
            margin-bottom: 2rem;
        }
        .edit-form h2 {
            color: #110d52;
            margin-bottom: 1.5rem;
        }
        .edit-form .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .edit-form .form-group {
            margin-bottom: 1rem;
        }
        .edit-form .form-group label {
            display: block;
            font-weight: 600;
            color: #110d52;
            margin-bottom: 0.3rem;
            font-size: 0.9rem;
        }
        .edit-form .form-group input,
        .edit-form .form-group select,
        .edit-form .form-group textarea {
            width: 100%;
            padding: 0.6rem 0.8rem;
            border: 2px solid #e8d5f5;
            border-radius: 12px;
            font-size: 0.95rem;
            transition: border-color 0.3s;
            font-family: inherit;
        }
        .edit-form .form-group input:focus,
        .edit-form .form-group select:focus,
        .edit-form .form-group textarea:focus {
            outline: none;
            border-color: #9662f0;
        }
        .edit-form .form-group textarea { resize: vertical; min-height: 80px; }
        .edit-form .form-group select[multiple] { min-height: 120px; }
        .edit-form .form-group.checkbox label { 
            display: flex; 
            align-items: center; 
            gap: 0.5rem;
            font-weight: normal;
        }
        .edit-form .form-group.checkbox input { width: auto; }
        
        .edit-form .btn-save {
            background: linear-gradient(135deg, #9662f0, #110d52);
            color: white;
            border: none;
            padding: 0.7rem 2rem;
            border-radius: 40px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .edit-form .btn-save:hover { transform: translateY(-2px); }
        .edit-form .btn-cancel {
            background: #f5f0ff;
            color: #110d52;
            border: 2px solid #e8d5f5;
            padding: 0.7rem 2rem;
            border-radius: 40px;
            font-size: 1rem;
            text-decoration: none;
            display: inline-block;
            transition: background 0.2s;
        }
        .edit-form .btn-cancel:hover { background: #e8d5f5; }
        
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
        .btn-stats {
            background: #f8b0c0;
            color: #110d52;
        }
        
        @media (max-width: 768px) {
            .edit-form .form-row { grid-template-columns: 1fr; }
            .admin-header { flex-direction: column; gap: 0.5rem; text-align: center; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Шапка -->
        <div class="admin-header">
            <h1>🔧 Админ-панель</h1>
            <div class="user-info">👤 <?php echo htmlspecialchars($auth_login); ?></div>
        </div>

        <!-- Сообщения -->
        <?php foreach ($messages as $msg): ?>
            <?php echo $msg; ?>
        <?php endforeach; ?>

        <!-- Статистика -->
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

        <!-- Форма редактирования -->
        <?php if ($edit_id > 0 && !empty($edit_values)): ?>
        <div class="edit-form">
            <h2>✏️ Редактирование анкеты №<?php echo $edit_id; ?></h2>
            <form method="POST">
                <input type="hidden" name="edit_id" value="<?php echo $edit_id; ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>ФИО <span style="color:#c62828;">*</span></label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($edit_values['full_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Телефон <span style="color:#c62828;">*</span></label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($edit_values['phone']); ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>E-mail <span style="color:#c62828;">*</span></label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($edit_values['email']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Дата рождения <span style="color:#c62828;">*</span></label>
                        <input type="date" name="birth_date" value="<?php echo htmlspecialchars($edit_values['birth_date']); ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Пол <span style="color:#c62828;">*</span></label>
                        <select name="gender" required>
                            <option value="male" <?php echo $edit_values['gender'] === 'male' ? 'selected' : ''; ?>>♂ Мужской</option>
                            <option value="female" <?php echo $edit_values['gender'] === 'female' ? 'selected' : ''; ?>>♀ Женский</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Любимые языки</label>
                        <select name="languages[]" multiple>
                            <?php
                            $all_langs = $pdo->query("SELECT name FROM programming_languages ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
                            foreach ($all_langs as $lang): ?>
                                <option value="<?php echo htmlspecialchars($lang); ?>" 
                                    <?php echo in_array($lang, $edit_values['languages'] ?? []) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($lang); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Биография</label>
                    <textarea name="biography" rows="4"><?php echo htmlspecialchars($edit_values['biography'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group checkbox">
                    <label>
                        <input type="checkbox" name="contract_accepted" value="1" 
                            <?php echo $edit_values['contract_accepted'] ? 'checked' : ''; ?>>
                        Я ознакомлен(а) с контрактом
                    </label>
                </div>
                
                <div style="display:flex; gap:1rem; flex-wrap:wrap; margin-top:1rem;">
                    <button type="submit" class="btn-save">💾 Сохранить</button>
                    <a href="admin.php" class="btn-cancel">↩ Отмена</a>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- Таблица анкет -->
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>ФИО</th>
                        <th>Телефон</th>
                        <th>Email</th>
                        <th>Дата рожд.</th>
                        <th>Пол</th>
                        <th>Языки</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($applications)): ?>
                        <tr><td colspan="8" style="text-align:center;color:#999;padding:2rem;">Пока нет ни одной анкеты</td></tr>
                    <?php else: ?>
                        <?php foreach ($applications as $app): ?>
                        <tr>
                            <td><strong>#<?php echo $app['id']; ?></strong></td>
                            <td><?php echo htmlspecialchars($app['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($app['phone']); ?></td>
                            <td><?php echo htmlspecialchars($app['email']); ?></td>
                            <td><?php echo date('d.m.Y', strtotime($app['birth_date'])); ?></td>
                            <td class="<?php echo $app['gender'] === 'male' ? 'gender-male' : 'gender-female'; ?>">
                                <?php echo $app['gender'] === 'male' ? '♂ М' : '♀ Ж'; ?>
                            </td>
                            <td>
                                <?php 
                                $langs = explode(', ', $app['languages_list'] ?? '');
                                foreach ($langs as $lang):
                                    if (trim($lang)):
                                ?>
                                    <span class="badge"><?php echo htmlspecialchars(trim($lang)); ?></span>
                                <?php 
                                    endif;
                                endforeach;
                                if (empty($app['languages_list'])): 
                                ?>
                                    <span style="color:#999;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="actions">
                                    <a href="admin.php?edit=<?php echo $app['id']; ?>" class="btn-edit">✏️</a>
                                    <a href="admin.php?delete=<?php echo $app['id']; ?>" 
                                       class="btn-delete" 
                                       onclick="return confirm('Удалить анкету №<?php echo $app['id']; ?>?')">🗑</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Статистика по языкам -->
        <div style="background:white; border-radius:20px; padding:1.5rem 2rem; box-shadow:0 4px 12px rgba(150,98,240,0.1); margin-bottom:2rem;">
            <h2 style="color:#110d52; margin-bottom:1rem;">🌐 Популярность языков программирования</h2>
            <?php if (empty($lang_stats)): ?>
                <p style="color:#999;">Нет данных</p>
            <?php else: ?>
                <?php 
                $max_count = max(array_column($lang_stats, 'count'));
                if ($max_count == 0) $max_count = 1;
                foreach ($lang_stats as $stat): 
                ?>
                    <div style="display:flex; align-items:center; margin-bottom:0.6rem;">
                        <div style="width:100px; font-weight:600; color:#110d52; flex-shrink:0;">
                            <?php echo htmlspecialchars($stat['name']); ?>
                        </div>
                        <div style="flex:1; height:28px; background:#f0e8f5; border-radius:14px; overflow:hidden; position:relative;">
                            <div style="height:100%; width:<?php echo ($stat['count'] / $max_count) * 100; ?>%; 
                                        background:linear-gradient(90deg, #f8b0c0, #9662f0); 
                                        border-radius:14px; 
                                        display:flex; 
                                        align-items:center; 
                                        justify-content:flex-end; 
                                        padding-right:10px; 
                                        color:white; 
                                        font-weight:bold; 
                                        font-size:0.8rem;">
                                <?php echo $stat['count']; ?>
                            </div>
                        </div>
                        <div style="width:40px; text-align:right; font-weight:bold; color:#110d52; flex-shrink:0; margin-left:10px;">
                            <?php echo $stat['count']; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div style="color:#999; font-size:0.85rem; margin-top:0.5rem;">* Всего пользователей: <?php echo $total_users; ?></div>
            <?php endif; ?>
        </div>

        <!-- Кнопки внизу -->
        <div class="bottom-actions">
            <a href="index.php" class="btn-back">📝 Главная форма</a>
            <a href="admin_stats.php" class="btn-stats">📊 Подробная статистика</a>
        </div>
    </div>
</body>
</html>