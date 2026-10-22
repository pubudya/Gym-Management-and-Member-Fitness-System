<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/init.php';
require_login('ADMIN');

if (is_post() && post('form') === 'create') {
    Database::insert('appointments', [
        'member_id' => (int) post('member_id'),
        'trainer_id' => (int) post('trainer_id'),
        'appointment_date' => post('appointment_date'),
        'start_time' => post('start_time'),
        'end_time' => post('end_time'),
        'appointment_type' => post('appointment_type'),
        'status' => 'BOOKED',
        'notes' => post('notes'),
    ]);
    $member = Database::fetch('SELECT * FROM members WHERE member_id=?', [(int) post('member_id')]);
    NotificationService::notifyMember($member, 'APPOINTMENT_REMINDER', 'Appointment booked on ' . post('appointment_date') . ' at ' . post('start_time') . '.');
    flash('success', 'Appointment saved.');
    redirect('admin/appointments.php');
}
if (is_post() && post('form') === 'status') {
    Database::update('appointments', ['status' => post('status')], 'appointment_id=?', [(int) post('appointment_id')]);
    flash('success', 'Appointment updated.');
    redirect('admin/appointments.php');
}

$rows = Database::fetchAll("SELECT a.*, m.full_name member_name, t.full_name trainer_name FROM appointments a JOIN members m ON m.member_id=a.member_id JOIN trainers t ON t.trainer_id=a.trainer_id ORDER BY appointment_date DESC, start_time");
$members = Database::fetchAll("SELECT member_id, full_name FROM members WHERE status='ACTIVE' ORDER BY full_name");
$trainers = Database::fetchAll("SELECT * FROM trainers WHERE status='ACTIVE'");
$pageTitle = 'Appointments';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="grid split">
    <form class="card" method="post">
        <h2>Book appointment</h2>
        <?= csrf_field() ?><input type="hidden" name="form" value="create">
        <select name="member_id"><?php foreach ($members as $m): ?><option value="<?= (int)$m['member_id'] ?>"><?= e($m['full_name']) ?></option><?php endforeach; ?></select>
        <select name="trainer_id"><?php foreach ($trainers as $t): ?><option value="<?= (int)$t['trainer_id'] ?>"><?= e($t['full_name']) ?></option><?php endforeach; ?></select>
        <input type="date" name="appointment_date" required>
        <input type="time" name="start_time" required>
        <input type="time" name="end_time" required>
        <select name="appointment_type"><option>PERSONAL_TRAINING</option><option>FITNESS_CONSULTATION</option><option>PROGRESS_REVIEW</option></select>
        <textarea name="notes"></textarea>
        <button class="btn">Save</button>
    </form>
    <div class="card">
        <h2>Diary</h2>
        <table>
            <tr><th>When</th><th>Member</th><th>Trainer</th><th>Type</th><th></th></tr>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= e($r['appointment_date'].' '.substr($r['start_time'],0,5)) ?></td>
                    <td><?= e($r['member_name']) ?></td>
                    <td><?= e($r['trainer_name']) ?></td>
                    <td><?= e($r['appointment_type']) ?><br><span class="badge <?= badge_class($r['status']) ?>"><?= e($r['status']) ?></span></td>
                    <td>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="form" value="status"><input type="hidden" name="appointment_id" value="<?= (int)$r['appointment_id'] ?>">
                            <select name="status" onchange="this.form.submit()"><?php foreach (['BOOKED','COMPLETED','CANCELLED','NO_SHOW'] as $s): ?><option <?= selected($r['status'],$s) ?>><?= $s ?></option><?php endforeach; ?></select>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
