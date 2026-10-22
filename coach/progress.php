<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/init.php';
require_login('COACH');

$memberId = (int) query_param('member_id');
$members = TrainingService::membersForSelect();
if ($memberId) {
    TrainingService::assertMember($memberId);
}
if (!$memberId && $members) {
    $memberId = (int) $members[0]['member_id'];
}

$member = $memberId ? Database::fetch('SELECT * FROM members WHERE member_id=?', [$memberId]) : null;
$measures = $memberId ? Database::fetchAll('SELECT * FROM fitness_measurements WHERE member_id=? ORDER BY measurement_date', [$memberId]) : [];
$progress = $memberId ? Database::fetchAll(
    "SELECT wp.*, we.exercise_name, we.tracking_type, we.sets, we.repetitions FROM workout_progress wp
     JOIN workout_exercises we ON we.exercise_id=wp.exercise_id
     WHERE wp.member_id=? ORDER BY wp.progress_date DESC LIMIT 30",
    [$memberId]
) : [];
$goals = $memberId ? Database::fetchAll('SELECT * FROM fitness_goals WHERE member_id=? ORDER BY created_at DESC', [$memberId]) : [];
$workouts = $memberId ? Database::fetchAll(
    "SELECT wp.plan_name, mwp.assigned_date, mwp.status FROM member_workout_plans mwp
     JOIN workout_plans wp ON wp.workout_plan_id=mwp.workout_plan_id WHERE mwp.member_id=? ORDER BY mwp.assigned_date DESC",
    [$memberId]
) : [];
$meals = $memberId ? Database::fetchAll(
    "SELECT mp.plan_name, mmp.assigned_date, mmp.status FROM member_meal_plans mmp
     JOIN meal_plans mp ON mp.meal_plan_id=mmp.meal_plan_id WHERE mmp.member_id=? ORDER BY mmp.assigned_date DESC",
    [$memberId]
) : [];

$gain = null;
if (count($measures) >= 2) {
    $gain = (float) $measures[count($measures) - 1]['weight_kg'] - (float) $measures[0]['weight_kg'];
}

$pageTitle = 'Member progress';
$pageSubtitle = $member['full_name'] ?? 'Pick an assigned member';
require dirname(__DIR__) . '/includes/header.php';
?>
<form class="card" method="get" style="margin-bottom:16px">
    <label>Assigned member</label>
    <select name="member_id" onchange="this.form.submit()">
        <?php foreach ($members as $m): ?>
            <option value="<?= (int) $m['member_id'] ?>" <?= selected($memberId, $m['member_id']) ?>><?= e($m['full_name']) ?></option>
        <?php endforeach; ?>
    </select>
</form>
<?php if (!$member): ?>
    <div class="alert">No assigned members to review.</div>
<?php else: ?>
<div class="grid stats">
    <div class="stat"><span>Latest weight</span><b><?= e((string) (($measures ? $measures[count($measures)-1]['weight_kg'] : '—'))) ?></b></div>
    <div class="stat volt"><span>Change</span><b><?= $gain === null ? '—' : (($gain > 0 ? '+' : '') . number_format($gain, 1) . ' kg') ?></b></div>
    <div class="stat ice"><span>Sessions logged</span><b><?= count($progress) ?></b></div>
    <div class="stat"><span>Engagement</span><b><?= engagement_score($memberId) ?></b></div>
</div>
<div class="card" style="margin-top:16px">
    <h2>Body measurements</h2>
    <canvas id="w" height="110"></canvas>
</div>
<div class="grid split" style="margin-top:16px">
    <div class="card">
        <h2>Assigned workouts</h2>
        <?php foreach ($workouts as $w): ?><p><?= e($w['plan_name']) ?> · <?= e($w['status']) ?></p><?php endforeach; ?>
        <?php if (!$workouts): ?><p class="muted">None yet. <a href="<?= e(url('coach/workouts.php')) ?>">Create one</a></p><?php endif; ?>
        <h2>Assigned meals</h2>
        <?php foreach ($meals as $w): ?><p><?= e($w['plan_name']) ?> · <?= e($w['status']) ?></p><?php endforeach; ?>
        <?php if (!$meals): ?><p class="muted">None yet. <a href="<?= e(url('coach/meals.php')) ?>">Create one</a></p><?php endif; ?>
        <h2>Goals</h2>
        <?php foreach ($goals as $g): ?><p><?= e($g['goal_type']) ?> · <?= e($g['target_value'] . ' ' . $g['target_unit']) ?></p><?php endforeach; ?>
    </div>
    <div class="card">
        <h2>Workout log</h2>
        <table>
            <?php foreach ($progress as $p): ?>
                <tr>
                    <td><?= e($p['progress_date']) ?></td>
                    <td><?= e($p['exercise_name']) ?></td>
                    <td><?= (int) $p['completed_sets'] ?> × <?= (int) $p['completed_repetitions'] ?></td>
                    <td><?= e((string) $p['completion_percentage']) ?>%</td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
<script>document.addEventListener('DOMContentLoaded',()=>voltCharts('w','line',<?= json_encode(array_column($measures,'measurement_date')) ?>,<?= json_encode(array_map(fn($r)=>(float)$r['weight_kg'],$measures)) ?>,{label:'kg'}));</script>
<?php endif; ?>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
