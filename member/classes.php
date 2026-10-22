<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/init.php';
require_login('MEMBER');
$m = current_member();

if (is_post()) {
    $schedule = Database::fetch(
        "SELECT cs.*, gc.capacity, gc.class_name,
                (SELECT COUNT(*) FROM class_bookings b WHERE b.schedule_id=cs.schedule_id AND b.status IN ('BOOKED','ATTENDED')) booked
         FROM class_schedules cs JOIN gym_classes gc ON gc.class_id=cs.class_id WHERE cs.schedule_id=? AND cs.status='SCHEDULED'",
        [(int) post('schedule_id')]
    );
    if (!$schedule) {
        flash('error', 'Class not available.');
    } else {
        $status = ((int)$schedule['booked'] >= (int)$schedule['capacity']) ? 'WAITING' : 'BOOKED';
        try {
            Database::insert('class_bookings', [
                'schedule_id' => $schedule['schedule_id'],
                'member_id' => $m['member_id'],
                'status' => $status,
            ]);
            NotificationService::notifyMember($m, 'CLASS_REMINDER', 'Booked: ' . $schedule['class_name'] . ' on ' . $schedule['class_date'] . ' at ' . $schedule['start_time'] . '.');
            flash('success', $status === 'WAITING' ? 'Class is full. You are on the waiting list.' : 'Class booked.');
        } catch (Throwable $e) {
            flash('error', 'You already booked this class.');
        }
    }
    redirect('member/classes.php');
}

$schedules = Database::fetchAll(
    "SELECT cs.*, gc.class_name, gc.capacity, t.full_name trainer_name,
            (SELECT COUNT(*) FROM class_bookings b WHERE b.schedule_id=cs.schedule_id AND b.status IN ('BOOKED','ATTENDED')) booked
     FROM class_schedules cs JOIN gym_classes gc ON gc.class_id=cs.class_id JOIN trainers t ON t.trainer_id=cs.trainer_id
     WHERE cs.class_date >= CURDATE() AND cs.status='SCHEDULED' ORDER BY cs.class_date, cs.start_time"
);
$mine = Database::fetchAll(
    "SELECT b.*, gc.class_name, cs.class_date, cs.start_time FROM class_bookings b
     JOIN class_schedules cs ON cs.schedule_id=b.schedule_id JOIN gym_classes gc ON gc.class_id=cs.class_id
     WHERE b.member_id=? ORDER BY cs.class_date DESC",
    [$m['member_id']]
);
$pageTitle = 'Classes';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="card">
    <h2>Book a class</h2>
    <table>
        <tr><th>Class</th><th>When</th><th>Trainer</th><th>Spots</th><th></th></tr>
        <?php foreach ($schedules as $s): ?>
            <tr>
                <td><?= e($s['class_name']) ?></td>
                <td><?= e($s['class_date'].' '.substr($s['start_time'],0,5)) ?></td>
                <td><?= e($s['trainer_name']) ?></td>
                <td><?= (int)$s['booked'] ?>/<?= (int)$s['capacity'] ?></td>
                <td>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="schedule_id" value="<?= (int)$s['schedule_id'] ?>"><button class="btn small">Book</button></form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
    <h2>My bookings</h2>
    <table><?php foreach ($mine as $b): ?><tr><td><?= e($b['class_name']) ?></td><td><?= e($b['class_date']) ?></td><td><span class="badge <?= badge_class($b['status']) ?>"><?= e($b['status']) ?></span></td></tr><?php endforeach; ?></table>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
