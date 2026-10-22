<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/init.php';
require_login('MEMBER');
$m = current_member();

if (is_post()) {
    Database::update('notifications', ['delivery_status' => 'READ'], 'notification_id=? AND member_id=?', [(int) post('notification_id'), $m['member_id']]);
    redirect('member/notifications.php');
}

$rows = Database::fetchAll('SELECT * FROM notifications WHERE member_id=? ORDER BY created_at DESC LIMIT 80', [$m['member_id']]);
$pageTitle = 'Notifications';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="card">
    <h2>SMS & system messages</h2>
    <table>
        <tr><th>When</th><th>Type</th><th>Message</th><th>Delivery</th><th></th></tr>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= e($r['created_at']) ?></td>
                <td><?= e($r['notification_type']) ?></td>
                <td><?= e($r['message']) ?></td>
                <td><span class="badge <?= badge_class($r['delivery_status']) ?>"><?= e($r['delivery_status']) ?></span></td>
                <td>
                    <?php if ($r['delivery_status'] !== 'READ'): ?>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="notification_id" value="<?= (int)$r['notification_id'] ?>"><button class="btn small ghost">Mark read</button></form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="5">No notifications yet.</td></tr><?php endif; ?>
    </table>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
