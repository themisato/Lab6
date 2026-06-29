<?php
// admin.php - Панель администратора (сессионная авторизация)
session_start();
require_once 'config.php';

// ========== ПРОВЕРКА АВТОРИЗАЦИИ ==========
$error = '';

// Выход
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Обработка входа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login']) && isset($_POST['password'])) {
    $login = trim($_POST['login']);
    $password = trim($_POST['password']);
    
    $stmt = $pdo->prepare("SELECT password_hash FROM admin WHERE login = ?");
    $stmt->execute([$login]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin && password_verify($password, $admin['password_hash'])) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_login'] = $login;
        header('Location: admin.php');
        exit;
    } else {
        $error = '❌ Неверный логин или пароль!';
    }
}

// Если не авторизован - показываем форму входа
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Вход в админ-панель</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { background: #f5f0ff; font-family: 'Segoe UI', sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; }
            .login-box { background: white; padding: 40px; border-radius: 24px; box-shadow: 0 8px 30px rgba(150,98,240,0.2); width: 360px; }
            .login-box h1 { color: #110d52; text-align: center; margin-bottom: 10px; font-size: 28px; }
            .login-box .subtitle { text-align: center; color: #9662f0; margin-bottom: 25px; font-size: 14px; }
            .login-box input { width: 100%; padding: 12px 16px; border: 2px solid #e8d5f5; border-radius: 12px; font-size: 16px; margin-bottom: 15px; transition: border-color 0.3s; }
            .login-box input:focus { outline: none; border-color: #9662f0; }
            .login-box button { width: 100%; padding: 14px; background: linear-gradient(135deg, #9662f0, #110d52); color: white; border: none; border-radius: 40px; font-size: 18px; font-weight: bold; cursor: pointer; transition: transform 0.2s; }
            .login-box button:hover { transform: scale(1.02); }
            .error { color: #c62828; background: #ffebee; padding: 12px; border-radius: 10px; margin-bottom: 15px; text-align: center; }
            .hint { text-align: center; color: #999; margin-top: 15px; font-size: 13px; }
            .hint strong { color: #110d52; }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h1>🔐 Админ-панель</h1>
            <p class="subtitle">Введите логин и пароль для входа</p>
            <?php if ($error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="text" name="login" placeholder="Логин" value="admin" required>
                <input type="password" name="password" placeholder="Пароль" value="admin123" required>
                <button type="submit">🚪 Войти</button>
            </form>
            <div class="hint">Логин: <strong>admin</strong> | Пароль: <strong>admin123</strong></div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ========== АДМИН-ПАНЕЛЬ (только для авторизованных) ==========
$messages = [];

// Удаление
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM applications WHERE id = ?")->execute([$id]);
        $messages[] = '<div style="background:#e8f5e9;padding:12px 16px;border-radius:10px;color:#2e7d32;margin-bottom:15px;border-left:4px solid #4caf50;">✅ Анкета №' . $id . ' удалена</div>';
    } catch (Exception $e) {
        $messages[] = '<div style="background:#ffebee;padding:12px 16px;border-radius:10px;color:#c62828;margin-bottom:15px;border-left:4px solid #f44336;">❌ Ошибка удаления</div>';
    }
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
        $messages[] = '<div style="background:#ffebee;padding:12px 16px;border-radius:10px;color:#c62828;margin-bottom:15px;border-left:4px solid #f44336;">⚠️ Заполните все поля</div>';
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
            $messages[] = '<div style="background:#e8f5e9;padding:12px 16px;border-radius:10px;color:#2e7d32;margin-bottom:15px;border-left:4px solid #4caf50;">✅ Анкета №' . $id . ' обновлена</div>';
            $edit_id = 0;
        } catch (Exception $e) {
            $pdo->rollBack();
            $messages[] = '<div style="background:#ffebee;padding:12px 16px;border-radius:10px;color:#c62828;margin-bottom:15px;border-left:4px solid #f44336;">❌ Ошибка: ' . $e->getMessage() . '</div>';
        }
    }
}

// Загрузка данных
$applications = $pdo->query("
    SELECT a.*, GROUP_CONCAT(pl.name SEPARATOR ', ') AS languages_list
    FROM applications a
    LEFT JOIN application_languages al ON a.id = al.application_id
    LEFT JOIN programming_languages pl ON al.language_id = pl.id
    GROUP BY a.id
    ORDER BY a.id DESC
")->fetchAll();

// Статистика
$total_users = $pdo->query("SELECT COUNT(*) as total FROM applications")->fetch()['total'];
$lang_stats = $pdo->query("SELECT pl.name, COUNT(DISTINCT al.application_id) AS count FROM programming_languages pl LEFT JOIN application_languages al ON pl.id = al.language_id GROUP BY pl.id, pl.name ORDER BY count DESC")->fetchAll();

$gender_data = $pdo->query("SELECT gender, COUNT(*) as count FROM applications GROUP BY gender")->fetchAll();
$male_count = $female_count = 0;
foreach ($gender_data as $g) {
    if ($g['gender'] == 'male') $male_count = $g['count'];
    if ($g['gender'] == 'female') $female_count = $g['count'];
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель</title>
    <link rel="stylesheet" href="style.css">
    <style>
        * { box-sizing: border-box; }
        body { background: #f5f0ff; font-family: 'Segoe UI', sans-serif; }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .admin-header {
            background: linear-gradient(135deg, #9662f0, #110d52);
            color: white;
            padding: 20px 30px;
            border-radius: 24px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .admin-header h1 { margin: 0; font-size: 24px; }
        .admin-header .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .admin-header .user-info .badge {
            background: rgba(255,255,255,0.2);
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 14px;
        }
        .admin-header .user-info .btn-logout {
            background: rgba(255,255,255,0.25);
            color: white;
            padding: 8px 20px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.2s;
        }
        .admin-header .user-info .btn-logout:hover { background: rgba(255,255,255,0.4); }
        .admin-header .nav-links a {
            color: white;
            text-decoration: none;
            padding: 6px 16px;
            border-radius: 30px;
            background: rgba(255,255,255,0.15);
            transition: background 0.2s;
            font-size: 14px;
        }
        .admin-header .nav-links a:hover { background: rgba(255,255,255,0.3); }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white;
            padding: 18px;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(150,98,240,0.1);
        }
        .stat-card .number {
            font-size: 28px;
            font-weight: bold;
            background: linear-gradient(135deg, #9662f0, #110d52);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .stat-card .label { color: #666; font-size: 14px; margin-top: 4px; }
        .table-wrapper {
            overflow-x: auto;
            background: white;
            border-radius: 20px;
            padding: 0 0 2px 0;
            box-shadow: 0 4px 16px rgba(150,98,240,0.1);
        }
        table { width: 100%; border-collapse: collapse; min-width: 1000px; font-size: 14px; }
        th { background: linear-gradient(135deg, #f8b0c0, #9662f0); color: white; padding: 12px 16px; text-align: left; font-weight: 600; }
        td { padding: 10px 16px; border-bottom: 1px solid #f0e8f5; vertical-align: middle; }
        tr:hover { background: #faf5ff; }
        .badge-lang {
            display: inline-block;
            background: #9662f0;
            color: white;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 11px;
            margin: 2px;
        }
        .gender-male { color: #2b3cf0; font-weight: 600; }
        .gender-female { color: #d55a83; font-weight: 600; }
        .actions a {
            padding: 4px 12px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 13px;
            margin: 0 2px;
            display: inline-block;
        }
        .btn-edit { background: #e8d5f5; color: #110d52; }
        .btn-edit:hover { background: #9662f0; color: white; }
        .btn-delete { background: #ffebee; color: #c62828; }
        .btn-delete:hover { background: #c62828; color: white; }
        .edit-form {
            background: white;
            padding: 25px;
            border-radius: 20px;
            margin-bottom: 25px;
            box-shadow: 0 4px 16px rgba(150,98,240,0.1);
        }
        .edit-form h2 { color: #110d52; margin-bottom: 20px; }
        .edit-form .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .edit-form .form-group { margin-bottom: 15px; }
        .edit-form .form-group label { display: block; font-weight: 600; color: #110d52; margin-bottom: 5px; font-size: 14px; }
        .edit-form .form-group input,
        .edit-form .form-group select,
        .edit-form .form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 2px solid #e8d5f5;
            border-radius: 12px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.3s;
        }
        .edit-form .form-group input:focus,
        .edit-form .form-group select:focus,
        .edit-form .form-group textarea:focus {
            outline: none;
            border-color: #9662f0;
        }
        .edit-form .form-group select[multiple] { min-height: 120px; }
        .edit-form .form-group.checkbox label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: normal;
        }
        .edit-form .form-group.checkbox input { width: auto; }
        .edit-form .btn-save {
            background: linear-gradient(135deg, #9662f0, #110d52);
            color: white;
            border: none;
            padding: 10px 30px;
            border-radius: 40px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .edit-form .btn-save:hover { transform: translateY(-2px); }
        .edit-form .btn-cancel {
            background: #f5f0ff;
            color: #110d52;
            border: 2px solid #e8d5f5;
            padding: 10px 30px;
            border-radius: 40px;
            text-decoration: none;
            display: inline-block;
            font-size: 16px;
        }
        .edit-form .btn-cancel:hover { background: #e8d5f5; }
        .bottom-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 25px;
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
        .btn-stats { background: #f8b0c0; color: #110d52; }
        @media (max-width: 768px) {
            .edit-form .form-row { grid-template-columns: 1fr; }
            .admin-header { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="admin-header">
            <h1>🔧 Админ-панель</h1>
            <div class="user-info">
                <span class="badge">👤 <?php echo htmlspecialchars($_SESSION['admin_login']); ?></span>
                <a href="admin.php?logout=1" class="btn-logout">🚪 Выйти</a>
                <div class="nav-links">
                    <a href="index.php">📝 Форма</a>
                    <a href="list.php">📋 Анкеты</a>
                </div>
            </div>
        </div>

        <?php foreach ($messages as $msg): echo $msg; endforeach; ?>

        <div class="stats-grid">
            <div class="stat-card"><div class="number"><?php echo $total_users; ?></div><div class="label">👤 Всего пользователей</div></div>
            <div class="stat-card"><div class="number"><?php echo $male_count; ?></div><div class="label">♂ Мужчины</div></div>
            <div class="stat-card"><div class="number"><?php echo $female_count; ?></div><div class="label">♀ Женщины</div></div>
            <div class="stat-card"><div class="number"><?php echo count($lang_stats); ?></div><div class="label">🌐 Языков</div></div>
        </div>

        <?php if ($edit_id > 0 && !empty($edit_values)): ?>
        <div class="edit-form">
            <h2>✏️ Редактирование анкеты №<?php echo $edit_id; ?></h2>
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
                    <div class="form-group"><label>Пол *</label><select name="gender" required><option value="male" <?php echo $edit_values['gender'] === 'male' ? 'selected' : ''; ?>>♂ Мужской</option><option value="female" <?php echo $edit_values['gender'] === 'female' ? 'selected' : ''; ?>>♀ Женский</option></select></div>
                    <div class="form-group"><label>Любимые языки</label><select name="languages[]" multiple><?php $all_langs = $pdo->query("SELECT name FROM programming_languages ORDER BY name")->fetchAll(PDO::FETCH_COLUMN); foreach ($all_langs as $lang): ?><option value="<?php echo htmlspecialchars($lang); ?>" <?php echo in_array($lang, $edit_values['languages'] ?? []) ? 'selected' : ''; ?>><?php echo htmlspecialchars($lang); ?></option><?php endforeach; ?></select></div>
                </div>
                <div class="form-group"><label>Биография</label><textarea name="biography" rows="4"><?php echo htmlspecialchars($edit_values['biography'] ?? ''); ?></textarea></div>
                <div class="form-group checkbox"><label><input type="checkbox" name="contract_accepted" value="1" <?php echo $edit_values['contract_accepted'] ? 'checked' : ''; ?>> Я ознакомлен(а) с контрактом</label></div>
                <div style="display:flex; gap:15px; flex-wrap:wrap; margin-top:15px;">
                    <button type="submit" class="btn-save">💾 Сохранить</button>
                    <a href="admin.php" class="btn-cancel">↩ Отмена</a>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <div class="table-wrapper">
            <table>
                <thead><tr><th>ID</th><th>ФИО</th><th>Телефон</th><th>Email</th><th>Дата рожд.</th><th>Пол</th><th>Языки</th><th>Действия</th></tr></thead>
                <tbody>
                    <?php if (empty($applications)): ?>
                        <tr><td colspan="8" style="text-align:center;color:#999;padding:30px;">😕 Нет ни одной анкеты</td></tr>
                    <?php else: foreach ($applications as $app): ?>
                        <tr>
                            <td><strong>#<?php echo $app['id']; ?></strong></td>
                            <td><?php echo htmlspecialchars($app['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($app['phone']); ?></td>
                            <td><?php echo htmlspecialchars($app['email']); ?></td>
                            <td><?php echo date('d.m.Y', strtotime($app['birth_date'])); ?></td>
                            <td class="<?php echo $app['gender'] === 'male' ? 'gender-male' : 'gender-female'; ?>"><?php echo $app['gender'] === 'male' ? '♂ Мужской' : '♀ Женский'; ?></td>
                            <td><?php $langs = explode(', ', $app['languages_list'] ?? ''); foreach ($langs as $lang): if (trim($lang)): ?><span class="badge-lang"><?php echo htmlspecialchars(trim($lang)); ?></span><?php endif; endforeach; ?></td>
                            <td class="actions">
                                <a href="admin.php?edit=<?php echo $app['id']; ?>" class="btn-edit">✏️</a>
                                <a href="admin.php?delete=<?php echo $app['id']; ?>" class="btn-delete" onclick="return confirm('Удалить анкету №<?php echo $app['id']; ?>?')">🗑</a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <div class="bottom-actions">
            <a href="admin_stats.php" class="btn-stats">📊 Подробная статистика</a>
            <a href="index.php" class="btn-back">📝 Главная форма</a>
        </div>
    </div>
</body>
</html>