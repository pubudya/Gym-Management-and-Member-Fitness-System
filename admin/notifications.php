<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/init.php';
require_login('ADMIN');

if (is_post() && post('form') === 'announce') {
    $n = NotificationService::announce(trim((string) post('title')), trim((string) post('message')), (bool) post('sms'));
    flash('success', "Announcement stored and sent to $n members.");
    redirect('admin/notifications.php');
}
if (is_post() && post('form') === 'template') {
    Database::update('notification_templates', [
        'template_name' => post('template_name'),
        'message_template' => post('message_template'),
        'status' => post('status'),
    ], 'template_id=?', [(int) post('template_id')]);
    flash('success', 'Template updated.');
    redirect('admin/notifications.php');
}

$notes = Database::fetchAll('SELECT * FROM notifications ORDER BY created_at DESC LIMIT 80');
$templates = Database::fetchAll('SELECT * FROM notification_templates ORDER BY template_code');
$pageTitle = 'Notifications';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="grid split">
    <form class="card" method="post">
        <h2>Send announcement</h2>
        <?= csrf_field() ?><input type="hidden" name="form" value="announce">
        <input name="title" required placeholder="Title">
        <textarea name="message" required placeholder="Message to all active members"></textarea>
        <label><input type="checkbox" name="sms" value="1" checked> Also send SMS</label>
        <button class="btn">Send</button>
    </form>
    <div class="card">
        <h2>Templates</h2>
        <?php foreach ($templates as $t): ?>
            <form method="post" style="margin-bottom:12px">
                <?= csrf_field() ?><input type="hidden" name="form" value="template"><input type="hidden" name="template_id" value="<?= (int)$t['template_id'] ?>">
                <small><?= e($t['template_code']) ?> · <?= e($t['recipient_type']) ?></small>
                <input name="template_name" value="<?= e($t['template_name']) ?>">
                <textarea name="message_template"><?= e($t['message_template']) ?></textarea>
                <select name="status"><option <?= selected($t['status'],'ACTIVE') ?>>ACTIVE</option><option <?= selected($t['status'],'INACTIVE') ?>>INACTIVE</option></select>
                <button class="btn small">Save</button>
            </form>
        <?php endforeach; ?>
    </div>
</div>
<div class="card" style="margin-top:16px">
    <h2>Notification log</h2>
    <table>
        <tr><th>When</th><th>To</th><th>Type</th><th>Phone</th><th>Status</th></tr>
        <?php foreach ($notes as $n): ?>
            <tr>
                <td><?= e($n['created_at']) ?></td>
                <td><?= e($n['recipient_type'].' · '.$n['recipient_name']) ?></td>
                <td><?= e($n['notification_type']) ?></td>
                <td><?= e($n['phone_number']) ?></td>
                <td><span class="badge <?= badge_class($n['delivery_status']) ?>"><?= e($n['delivery_status']) ?></span></td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
