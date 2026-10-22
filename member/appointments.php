<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/init.php';
require_login('MEMBER');
$m = current_member();
$rows = Database::fetchAll(
    "SELECT a.*, t.full_name trainer_name FROM appointments a JOIN trainers t ON t.trainer_id=a.trainer_id
     WHERE a.member_id=? ORDER BY a.appointment_date DESC, a.start_time",
    [$m['member_id']]
);
$pageTitle = 'Appointments';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="card">
    <h2>Your trainer appointments</h2>
    <p class="muted">Appointments are scheduled by gym staff. You can view times here and will receive SMS reminders.</p>
    <table>
        <tr><th>Date</th><th>Time</th><th>Trainer</th><th>Type</th><th>Status</th></tr>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= e($r['appointment_date']) ?></td>
                <td><?= e(substr($r['start_time'],0,5).'–'.substr($r['end_time'],0,5)) ?></td>
                <td><?= e($r['trainer_name']) ?></td>
                <td><?= e($r['appointment_type']) ?></td>
                <td><span class="badge <?= badge_class($r['status']) ?>"><?= e($r['status']) ?></span></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="5">No appointments yet.</td></tr><?php endif; ?>
    </table>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
