<?php
// admin.php - Админ-панель с HTTP-авторизацией
header('Content-Type: text/html; charset=UTF-8');
require_once 'config.php';

// ========== HTTP-АВТОРИЗАЦИЯ ==========
if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
    header('WWW-Authenticate: Basic realm="Admin Panel"');
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
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    header('HTTP/1.0 401 Unauthorized');
    echo '<h1 style="text-align:center;color:#110d52;margin-top:100px;">❌ Неверный логин или пароль!</h1>';
    exit;
}

// ========== ОСТАЛЬНОЙ КОД ==========
$messages = [];

// Удаление
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM applications WHERE id = ?")->execute([$id]);
    $messages[] = '<div class="message success">✅ Анкета №' . $id . ' удалена</div>';
}

// Редактирование
$edit_id = 0;
$edit_values = [];
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM applications WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_values = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($edit_values) {
        $lang_stmt = $pdo->prepare("SELECT pl.name FROM application_languages al JOIN programming_languages pl ON al.language_id = pl.id WHERE al.application_id = ?");
        $lang_stmt->execute([$edit_id]);
        $edit_values['languages'] = $lang_stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

// Сохранение
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
            $stmt = $pdo->prepare("UPDATE applications SET full_name=?, phone=?, email=?, birth_date=?, gender=?, biography=?, contract_accepted=? WHERE id=?");
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
            $messages[] = '<div class="message success">✅ Анкета №' . $id . ' обновлена</div>';
            $edit_id = 0;
        } catch (Exception $e) {
            $pdo->rollBack();
            $messages[] = '<div class="message error">❌ Ошибка: ' . $e->getMessage() . '</div>';
        }
    }
}

// Загрузка данных
$applications = [];
$stmt = $pdo->query("SELECT a.*, GROUP_CONCAT(pl.name SEPARATOR ', ') AS languages_list FROM applications a LEFT JOIN application_languages al ON a.id = al.application_id LEFT JOIN programming_languages pl ON al.language_id = pl.id GROUP BY a.id ORDER BY a.id DESC");
$applications = $stmt->fetchAll();

// Статистика
$total_users = $pdo->query("SELECT COUNT(*) as total FROM applications")->fetch()['total'];
$lang_stats = $pdo->query("SELECT pl.name, COUNT(DISTINCT al.application_id) AS count FROM programming_languages pl LEFT JOIN application_languages al ON pl.id = al.language_id GROUP BY pl.id, pl.name ORDER BY count DESC")->fetchAll();
$gender_stats = $pdo->query("SELECT gender, COUNT(*) as count FROM applications GROUP BY gender")->fetchAll();
$male_count = 0; $female_count = 0;
foreach ($gender_stats as $g) {
    if ($g['gender'] == 'male') $male_count = $g['count'];
    if ($g['gender'] == 'female') $female_count = $g['count'];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Админ-панель</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { background: #f5f0ff; font-family: 'Segoe UI', sans-serif; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #9662f0, #110d52); color: white; padding: 20px 30px; border-radius: 20px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        .header h1 { margin: 0; }
        .header .user { background: rgba(255,255,255,0.2); padding: 8px 20px; border-radius: 30px; }
        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 16px; text-align: center; box-shadow: 0 2px 8px rgba(150,98,240,0.1); }
        .stat-number { font-size: 30px; font-weight: bold; background: linear-gradient(135deg, #9662f0, #110d52); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .stat-label { color: #666; }
        .message { padding: 10px 15px; border-radius: 10px; margin-bottom: 10px; }
        .message.success { background: #e8f5e9; color: #2e7d32; }
        .message.error { background: #ffebee; color: #c62828; }
        table { width: 100%; background: white; border-collapse: collapse; border-radius: 20px; overflow: hidden; box-shadow: 0 2px 8px rgba(150,98,240,0.1); }
        th { background: linear-gradient(135deg, #f8b0c0, #9662f0); color: white; padding: 12px 16px; text-align: left; }
        td { padding: 10px 16px; border-bottom: 1px solid #f0e8f5; }
        tr:hover { background: #faf5ff; }
        .badge { background: #9662f0; color: white; padding: 2px 10px; border-radius: 20px; font-size: 11px; display: inline-block; margin: 2px; }
        .actions a { padding: 5px 12px; border-radius: 20px; text-decoration: none; font-size: 13px; margin: 0 3px; }
        .btn-edit { background: #e8d5f5; color: #110d52; }
        .btn-edit:hover { background: #9662f0; color: white; }
        .btn-delete { background: #ffebee; color: #c62828; }
        .btn-delete:hover { background: #c62828; color: white; }
        .gender-male { color: #2b3cf0; font-weight: bold; }
        .gender-female { color: #d55a83; font-weight: bold; }
        .edit-form { background: white; padding: 20px; border-radius: 20px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(150,98,240,0.1); }
        .edit-form .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .edit-form .form-group { margin-bottom: 15px; }
        .edit-form .form-group label { display: block; font-weight: 600; color: #110d52; margin-bottom: 5px; }
        .edit-form .form-group input, .edit-form .form-group select, .edit-form .form-group textarea { width: 100%; padding: 8px 12px; border: 2px solid #e8d5f5; border-radius: 12px; font-size: 14px; font-family: inherit; }
        .edit-form .form-group select[multiple] { min-height: 100px; }
        .edit-form .btn-save { background: linear-gradient(135deg, #9662f0, #110d52); color: white; border: none; padding: 10px 30px; border-radius: 40px; font-size: 16px; font-weight: bold; cursor: pointer; }
        .edit-form .btn-cancel { background: #f5f0ff; color: #110d52; border: 2px solid #e8d5f5; padding: 10px 30px; border-radius: 40px; text-decoration: none; display: inline-block; }
        .bottom { margin-top: 20px; text-align: center; }
        .bottom a { padding: 10px 30px; background: linear-gradient(135deg, #9662f0, #110d52); color: white; text-decoration: none; border-radius: 40px; display: inline-block; margin: 5px; }
        .bottom a:hover { transform: translateY(-2px); }
        @media (max-width: 768px) { .edit-form .form-row { grid-template-columns: 1fr; } .stats { grid-template-columns: 1fr 1fr; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 Админ-панель</h1>
            <div class="user">👤 <?php echo htmlspecialchars($auth_login); ?></div>
        </div>

        <?php foreach ($messages as $msg): echo $msg; endforeach; ?>

        <div class="stats">
            <div class="stat-card"><div class="stat-number"><?php echo $total_users; ?></div><div class="stat-label">Всего пользователей</div></div>
            <div class="stat-card"><div class="stat-number"><?php echo $male_count; ?></div><div class="stat-label">♂ Мужчины</div></div>
            <div class="stat-card"><div class="stat-number"><?php echo $female_count; ?></div><div class="stat-label">♀ Женщины</div></div>
            <div class="stat-card"><div class="stat-number"><?php echo count($lang_stats); ?></div><div class="stat-label">🌐 Языков</div></div>
        </div>

        <?php if ($edit_id > 0 && !empty($edit_values)): ?>
        <div class="edit-form">
            <h2 style="color:#110d52;">✏️ Редактирование анкеты №<?php echo $edit_id; ?></h2>
            <form method="POST">
                <input type="hidden" name="edit_id" value="<?php echo $edit_id; ?>">
                <div class="form-row">
                    <div class="form-group"><label>ФИО *</label><input type="text" name="full_name" value="<?php echo htmlspecialchars($edit_values['full_name']); ?>" required></div>
                    <div class="form-group"><label>Телефон *</label><input type="tel" name="phone" value="<?php echo htmlspecialchars($edit_values['phone']); ?>" required></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>E-mail *</label><input type="email" name="email" value="<?php echo htmlspecialchars($edit_values['email']); ?>" required></div>
                    <div class="form-group"><label>Дата рождения *</label><input type="date" name="birth_date" value="<?php echo htmlspecialchars($edit_values['birth_date']); ?>" required></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Пол *</label><select name="gender" required><option value="male" <?php echo $edit_values['gender']==='male'?'selected':''; ?>>♂ Мужской</option><option value="female" <?php echo $edit_values['gender']==='female'?'selected':''; ?>>♀ Женский</option></select></div>
                    <div class="form-group"><label>Любимые языки</label><select name="languages[]" multiple><?php $all_langs = $pdo->query("SELECT name FROM programming_languages ORDER BY name")->fetchAll(PDO::FETCH_COLUMN); foreach ($all_langs as $lang): ?><option value="<?php echo htmlspecialchars($lang); ?>" <?php echo in_array($lang, $edit_values['languages'] ?? []) ? 'selected' : ''; ?>><?php echo htmlspecialchars($lang); ?></option><?php endforeach; ?></select></div>
                </div>
                <div class="form-group"><label>Биография</label><textarea name="biography" rows="4"><?php echo htmlspecialchars($edit_values['biography'] ?? ''); ?></textarea></div>
                <div class="form-group"><label><input type="checkbox" name="contract_accepted" value="1" <?php echo $edit_values['contract_accepted'] ? 'checked' : ''; ?>> Я ознакомлен(а) с контрактом</label></div>
                <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:15px;">
                    <button type="submit" class="btn-save">💾 Сохранить</button>
                    <a href="admin.php" class="btn-cancel">↩ Отмена</a>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <table>
            <thead><tr><th>ID</th><th>ФИО</th><th>Email</th><th>Телефон</th><th>Пол</th><th>Языки</th><th>Действия</th></tr></thead>
            <tbody>
                <?php if (empty($applications)): ?>
                    <tr><td colspan="7" style="text-align:center;color:#999;padding:40px;">😕 Нет ни одной анкеты</td></tr>
                <?php else: foreach ($applications as $app): ?>
                <tr>
                    <td><strong>#<?php echo $app['id']; ?></strong></td>
                    <td><?php echo htmlspecialchars($app['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($app['email']); ?></td>
                    <td><?php echo htmlspecialchars($app['phone']); ?></td>
                    <td class="<?php echo $app['gender']==='male'?'gender-male':'gender-female'; ?>">
                        <?php echo $app['gender']==='male'?'♂ Мужской':'♀ Женский'; ?>
                    </td>
                    <td>
                        <?php 
                        $langs = explode(', ', $app['languages_list'] ?? '');
                        foreach ($langs as $lang) {
                            if (trim($lang)) echo '<span class="badge">' . htmlspecialchars(trim($lang)) . '</span>';
                        }
                        if (empty($app['languages_list'])) echo '<span style="color:#999;">—</span>';
                        ?>
                    </td>
                    <td class="actions">
                        <a href="admin.php?edit=<?php echo $app['id']; ?>" class="btn-edit">✏️</a>
                        <a href="admin.php?delete=<?php echo $app['id']; ?>" class="btn-delete" onclick="return confirm('Удалить анкету?')">🗑</a>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>

        <div class="bottom">
            <a href="index.php">📝 Главная форма</a>
            <a href="admin_stats.php">📊 Подробная статистика</a>
        </div>
    </div>
</body>
</html>