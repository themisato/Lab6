<?php
// admin_view.php - Просмотр анкеты
require_once 'config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    $sql = "SELECT a.*, 
            GROUP_CONCAT(pl.name ORDER BY pl.name SEPARATOR ', ') as languages
            FROM applications a
            LEFT JOIN application_languages al ON a.id = al.application_id
            LEFT JOIN programming_languages pl ON al.language_id = pl.id
            WHERE a.id = :id
            GROUP BY a.id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $app = $stmt->fetch();
    
    if ($app):
?>
        <h2>👤 Анкета #<?php echo $app['id']; ?></h2>
        <div class="modal-field"><strong>ФИО:</strong> <?php echo htmlspecialchars($app['full_name']); ?></div>
        <div class="modal-field"><strong>Телефон:</strong> <?php echo htmlspecialchars($app['phone']); ?></div>
        <div class="modal-field"><strong>Email:</strong> <?php echo htmlspecialchars($app['email']); ?></div>
        <div class="modal-field"><strong>Дата рождения:</strong> <?php echo date('d.m.Y', strtotime($app['birth_date'])); ?></div>
        <div class="modal-field"><strong>Пол:</strong> <?php echo $app['gender'] == 'male' ? 'Мужской' : 'Женский'; ?></div>
        <div class="modal-field"><strong>Языки:</strong> <?php echo htmlspecialchars($app['languages'] ?? 'Не выбраны'); ?></div>
        <div class="modal-field"><strong>Биография:</strong><br><?php echo nl2br(htmlspecialchars($app['biography'] ?? 'Не указана')); ?></div>
        <div class="modal-field"><strong>Дата создания:</strong> <?php echo date('d.m.Y H:i:s', strtotime($app['created_at'])); ?></div>
        <br>
        <a href="admin.php" class="action-btn" style="display:inline-block;padding:0.5rem 1rem;background:#9662f0;color:white;text-decoration:none;border-radius:20px;">← Назад</a>
<?php 
    else:
        echo '<p>Анкета не найдена.</p>';
    endif;
} else {
    echo '<p>Неверный ID.</p>';
}
?>