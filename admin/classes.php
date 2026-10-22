<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/init.php';
require_login('ADMIN');

if (is_post() && post('form') === 'class') {
    Database::insert('gym_classes', [
        'class_name' => trim((string) post('class_name')),
        'description' => post('description'),
        'capacity' => max(1, (int) post('capacity')),
        'status' => 'ACTIVE',
    ]);
    flash('success', 'Class created.');
    redirect('admin/classes.php');
}
if (is_post() && post('form') === 'schedule') {
    Database::insert('class_schedules', [
        'class_id' => (int) post('class_id'),
        'trainer_id' => (int) post('trainer_id'),
        'class_date' => post('class_date'),
        'start_time' => post('start_time'),
        'end_time' => post('end_time'),
        'room' => post('room'),
        'status' => 'SCHEDULED',
    ]);
    flash('success', 'Schedule saved.');
    redirect('admin/classes.php');
}
if (is_post() && post('form') === 'booking') {
    $schedule = Database::fetch(
        "SELECT cs.*, gc.capacity, gc.class_name,
                (SELECT COUNT(*) FROM class_bookings b WHERE b.schedule_id=cs.schedule_id AND b.status IN ('BOOKED','ATTENDED','WAITING')) booked
         FROM class_schedules cs JOIN gym_classes gc ON gc.class_id=cs.class_id WHERE cs.schedule_id=?",
        [(int) post('schedule_id')]
    );
    $status = ((int)$schedule['booked'] >= (int)$schedule['capacity']) ? 'WAITING' : 'BOOKED';
    try {
        Database::insert('class_bookings', [
            'schedule_id' => (int) post('schedule_id'),
            'member_id' => (int) post('member_id'),
            'status' => $status,
        ]);
        flash('success', $status === 'WAITING' ? 'Class full — member added to waiting list.' : 'Booking saved.');
    } catch (Throwable $e) {
        flash('error', 'That member is already booked on this class.');
    }
    redirect('admin/classes.php');
}

$classes = Database::fetchAll('SELECT * FROM gym_classes ORDER BY class_name');
$trainers = Database::fetchAll("SELECT * FROM trainers WHERE status='ACTIVE'");
$members = Database::fetchAll("SELECT member_id, full_name FROM members WHERE status='ACTIVE' ORDER BY full_name");
$schedules = Database::fetchAll(
    "SELECT cs.*, gc.class_name, gc.capacity, t.full_name trainer_name,
            (SELECT COUNT(*) FROM class_bookings b WHERE b.schedule_id=cs.schedule_id AND b.status IN ('BOOKED','ATTENDED')) booked
     FROM class_schedules cs JOIN gym_classes gc ON gc.class_id=cs.class_id JOIN trainers t ON t.trainer_id=cs.trainer_id
     ORDER BY cs.class_date DESC, cs.start_time"
);
$pageTitle = 'Classes';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="grid cards-3">
    <form class="card" method="post"><h2>New class type</h2><?= csrf_field() ?><input type="hidden" name="form" value="class">
        <input name="class_name" required placeholder="Class name"><input type="number" name="capacity" value="12" min="1"><textarea name="description"></textarea><button class="btn">Save</button></form>
    <form class="card" method="post"><h2>Schedule</h2><?= csrf_field() ?><input type="hidden" name="form" value="schedule">
        <select name="class_id"><?php foreach ($classes as $c): ?><option value="<?= (int)$c['class_id'] ?>"><?= e($c['class_name']) ?></option><?php endforeach; ?></select>
        <select name="trainer_id"><?php foreach ($trainers as $t): ?><option value="<?= (int)$t['trainer_id'] ?>"><?= e($t['full_name']) ?></option><?php endforeach; ?></select>
        <input type="date" name="class_date" required><input type="time" name="start_time" required><input type="time" name="end_time" required>
        <input name="room" placeholder="Room"><button class="btn">Schedule</button></form>
    <form class="card" method="post"><h2>Book member</h2><?= csrf_field() ?><input type="hidden" name="form" value="booking">
        <select name="schedule_id"><?php foreach ($schedules as $s): ?><option value="<?= (int)$s['schedule_id'] ?>"><?= e($s['class_name'].' '.$s['class_date'].' '.$s['start_time']) ?></option><?php endforeach; ?></select>
        <select name="member_id"><?php foreach ($members as $m): ?><option value="<?= (int)$m['member_id'] ?>"><?= e($m['full_name']) ?></option><?php endforeach; ?></select>
        <button class="btn">Book</button></form>
</div>
<div class="card" style="margin-top:16px">
    <h2>Schedules & capacity</h2>
    <table><tr><th>Class</th><th>When</th><th>Trainer</th><th>Fill</th><th>Status</th></tr>
        <?php foreach ($schedules as $s): ?>
            <tr>
                <td><?= e($s['class_name']) ?></td>
                <td><?= e($s['class_date'].' '.substr($s['start_time'],0,5)) ?></td>
                <td><?= e($s['trainer_name']) ?></td>
                <td><?= (int)$s['booked'] ?>/<?= (int)$s['capacity'] ?></td>
                <td><span class="badge <?= badge_class($s['status']) ?>"><?= e($s['status']) ?></span></td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
