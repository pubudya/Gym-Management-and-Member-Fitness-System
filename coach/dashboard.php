<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/init.php';
require_login('COACH');

$coach = current_coach();
if (!$coach) {
    flash('error', 'This coach login is not linked to a coach profile. Ask admin to re-save the coach.');
    redirect('index.php');
}

$ids = TrainingService::assignedMemberIds();
$members = TrainingService::membersForSelect();
$workouts = $ids ? Database::fetchAll(
    'SELECT COUNT(*) c FROM member_workout_plans WHERE member_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ") AND status='ACTIVE'",
    $ids
)[0]['c'] ?? 0 : 0;
$meals = 0;
if ($ids) {
    $meals = Database::fetch(
        'SELECT COUNT(*) c FROM member_meal_plans WHERE member_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ") AND status='ACTIVE'",
        $ids
    )['c'] ?? 0;
}
$recent = [];
if ($ids) {
    $recent = Database::fetchAll(
        'SELECT wp.*, m.full_name, we.exercise_name FROM workout_progress wp
         JOIN members m ON m.member_id = wp.member_id
         JOIN workout_exercises we ON we.exercise_id = wp.exercise_id
         WHERE wp.member_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')
         ORDER BY wp.progress_date DESC, wp.created_at DESC LIMIT 8',
        $ids
    );
}

$pageTitle = 'Coach dashboard';
$pageSubtitle = 'Only your assigned members';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="callout">Welcome, <?= e($coach['full_name']) ?>. You can track progress and build workout + meal plans for members assigned to you — not the whole gym.</div>
<div class="grid stats">
    <div class="stat volt"><span>Assigned members</span><b><?= count($members) ?></b></div>
    <div class="stat"><span>Active workouts</span><b><?= (int) $workouts ?></b></div>
    <div class="stat ice"><span>Active meal plans</span><b><?= (int) $meals ?></b></div>
    <div class="stat ember"><span>Specialization</span><b><?= e($coach['specialization'] ?: 'Coach') ?></b></div>
</div>
<div class="grid split" style="margin-top:16px">
    <div class="card">
        <div class="card-h"><h2>Your members</h2><a class="btn small" href="<?= e(url('coach/members.php')) ?>">Open</a></div>
        <?php foreach ($members as $m): ?>
            <p><b><?= e($m['full_name']) ?></b> <span class="muted"><?= e($m['member_code']) ?></span></p>
        <?php endforeach; ?>
        <?php if (!$members): ?><p class="muted">Admin has not assigned members to you yet.</p><?php endif; ?>
        <p><a class="btn" href="<?= e(url('coach/workouts.php')) ?>">Create workout</a>
           <a class="btn ghost" href="<?= e(url('coach/meals.php')) ?>">Create meal plan</a></p>
    </div>
    <div class="card">
        <h2>Latest logged sessions</h2>
        <table>
            <tr><th>Member</th><th>Exercise</th><th>When</th><th>%</th></tr>
            <?php foreach ($recent as $r): ?>
                <tr>
                    <td><?= e($r['full_name']) ?></td>
                    <td><?= e($r['exercise_name']) ?></td>
                    <td><?= e($r['progress_date']) ?></td>
                    <td><?= e((string) $r['completion_percentage']) ?>%</td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$recent): ?><tr><td colspan="4">No progress yet.</td></tr><?php endif; ?>
        </table>
    </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
