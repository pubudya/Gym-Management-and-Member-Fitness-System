<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/init.php';
require_login('MEMBER');

$m = current_member();
$ms = active_membership((int) $m['member_id']);
$open = AttendanceService::openVisit((int) $m['member_id']);
$trainer = Database::fetch(
    "SELECT t.* FROM trainer_members tm JOIN trainers t ON t.trainer_id=tm.trainer_id
     WHERE tm.member_id=? AND tm.status='ACTIVE' ORDER BY tm.assigned_date DESC LIMIT 1",
    [$m['member_id']]
);
$plan = Database::fetch(
    "SELECT wp.* FROM member_workout_plans mwp JOIN workout_plans wp ON wp.workout_plan_id=mwp.workout_plan_id
     WHERE mwp.member_id=? AND mwp.status='ACTIVE' ORDER BY mwp.assigned_date DESC LIMIT 1",
    [$m['member_id']]
);
$measures = Database::fetchAll('SELECT * FROM fitness_measurements WHERE member_id=? ORDER BY measurement_date', [$m['member_id']]);
$classes = Database::fetchAll(
    "SELECT cs.*, gc.class_name FROM class_bookings b JOIN class_schedules cs ON cs.schedule_id=b.schedule_id
     JOIN gym_classes gc ON gc.class_id=cs.class_id WHERE b.member_id=? AND cs.class_date>=CURDATE() AND b.status IN ('BOOKED','WAITING')",
    [$m['member_id']]
);
$score = engagement_score((int) $m['member_id']);
$payload = member_qr_payload($m);
$pageTitle = 'My dashboard';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="grid stats">
    <div class="stat volt"><span>Membership</span><b><?= e($ms['status'] ?? 'None') ?></b></div>
    <div class="stat"><span>Expires</span><b><?= e($ms['end_date'] ?? '—') ?></b></div>
    <div class="stat ember"><span>Balance</span><b><?= e(money($ms['balance'] ?? 0)) ?></b></div>
    <div class="stat ice"><span>Engagement</span><b><?= $score ?></b></div>
</div>
<div class="grid cards-2" style="margin-top:16px">
    <div class="card">
        <h2><?= $open ? 'You are checked in' : 'Gym check-in' ?></h2>
        <form method="post" action="<?= e(url('member/attendance.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="form" value="toggle">
            <button class="btn <?= $open ? 'ember' : '' ?>"><?= $open ? 'Check out now' : 'Check in now' ?></button>
        </form>
        <p class="muted">Or show this QR at the desk. Code: <b><?= e($m['member_code']) ?></b></p>
        <div class="qr-box" data-qr="<?= e($payload) ?>"></div>
    </div>
    <div class="card">
        <h2>Assigned trainer & plan</h2>
        <p>Coach: <b><?= e($trainer['full_name'] ?? 'Not assigned') ?></b></p>
        <p>Workout: <b><?= e($plan['plan_name'] ?? 'Not assigned') ?></b></p>
        <p><a class="btn small" href="<?= e(url('member/fitness.php')) ?>">Open workouts</a>
           <a class="btn small ghost" href="<?= e(url('member/meals.php')) ?>">Open meal plan</a></p>
        <p>Live occupancy: <?= occupancy_count() ?> / <?= e(setting('gym_capacity','80')) ?></p>
        <h3>Upcoming classes</h3>
        <?php foreach ($classes as $c): ?><p><?= e($c['class_name'].' · '.$c['class_date'].' '.substr($c['start_time'],0,5)) ?></p><?php endforeach; ?>
        <?php if (!$classes): ?><p class="muted">No bookings yet.</p><?php endif; ?>
    </div>
</div>
<div class="card" style="margin-top:16px">
    <h2>Weight progress</h2>
    <canvas id="meWeight" height="110"></canvas>
</div>
<script>document.addEventListener('DOMContentLoaded',()=>voltCharts('meWeight','line',<?= json_encode(array_column($measures,'measurement_date')) ?>,<?= json_encode(array_map(fn($r)=>(float)$r['weight_kg'],$measures)) ?>,{label:'kg'}));</script>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
