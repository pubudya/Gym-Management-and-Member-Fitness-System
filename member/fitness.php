<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/init.php';
require_login('MEMBER');
$m = current_member();

if (is_post() && post('form') === 'progress') {
    $ex = Database::fetch(
        "SELECT we.* FROM workout_exercises we
         JOIN member_workout_plans mwp ON mwp.workout_plan_id=we.workout_plan_id
         WHERE we.exercise_id=? AND mwp.member_id=? AND mwp.status='ACTIVE'",
        [(int) post('exercise_id'), $m['member_id']]
    );
    if (!$ex) {
        flash('error', 'That exercise is not on your plan.');
    } else {
        $sets = (int) post('completed_sets');
        $reps = (int) post('completed_repetitions');
        $pct = 0;
        if (($ex['tracking_type'] ?? 'SETS_REPS') === 'SETS_REPS' && $ex['sets']) {
            $pct = min(100, round(($sets / max(1, (int) $ex['sets'])) * 100));
        } elseif (!empty($ex['duration_seconds']) && post('duration_minutes') !== '') {
            $done = (int) post('duration_minutes') * 60;
            $pct = min(100, round(($done / max(1, (int) $ex['duration_seconds'])) * 100));
        } else {
            $pct = 100;
        }
        Database::insert('workout_progress', [
            'member_id' => $m['member_id'],
            'exercise_id' => $ex['exercise_id'],
            'progress_date' => date('Y-m-d'),
            'completed_sets' => $sets,
            'completed_repetitions' => $reps,
            'weight_used_kg' => post('weight_used_kg') !== '' ? (float) post('weight_used_kg') : null,
            'duration_minutes' => (int) post('duration_minutes') ?: null,
            'completion_percentage' => $pct,
            'notes' => post('notes'),
        ]);
        flash('success', 'Session saved.');
    }
    redirect('member/fitness.php');
}

$trainer = Database::fetch("SELECT t.* FROM trainer_members tm JOIN trainers t ON t.trainer_id=tm.trainer_id WHERE tm.member_id=? AND tm.status='ACTIVE' ORDER BY tm.assigned_date DESC LIMIT 1", [$m['member_id']]);
$plans = Database::fetchAll("SELECT wp.*, mwp.assigned_date FROM member_workout_plans mwp JOIN workout_plans wp ON wp.workout_plan_id=mwp.workout_plan_id WHERE mwp.member_id=? AND mwp.status='ACTIVE' ORDER BY mwp.assigned_date DESC", [$m['member_id']]);
$exercises = Database::fetchAll(
    "SELECT we.*, wp.plan_name, wp.workout_style FROM workout_exercises we
     JOIN member_workout_plans mwp ON mwp.workout_plan_id=we.workout_plan_id
     JOIN workout_plans wp ON wp.workout_plan_id=we.workout_plan_id
     WHERE mwp.member_id=? AND mwp.status='ACTIVE' ORDER BY we.workout_day, we.exercise_order",
    [$m['member_id']]
);
$goals = Database::fetchAll('SELECT * FROM fitness_goals WHERE member_id=? ORDER BY created_at DESC', [$m['member_id']]);
$measures = Database::fetchAll('SELECT * FROM fitness_measurements WHERE member_id=? ORDER BY measurement_date', [$m['member_id']]);
$progress = Database::fetchAll(
    "SELECT wp.*, we.exercise_name FROM workout_progress wp JOIN workout_exercises we ON we.exercise_id=wp.exercise_id WHERE wp.member_id=? ORDER BY wp.progress_date DESC LIMIT 20",
    [$m['member_id']]
);
$grouped = [];
foreach ($exercises as $ex) {
    $grouped[$ex['workout_day']][] = $ex;
}
$pageTitle = 'My workouts';
$pageSubtitle = $trainer['full_name'] ?? 'Follow the plan your coach assigned';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="grid split">
    <div class="card">
        <h2>Your coach</h2>
        <p><b><?= e($trainer['full_name'] ?? 'Not assigned yet') ?></b></p>
        <p class="muted"><?= e($trainer['specialization'] ?? 'Admin can assign a personal coach.') ?></p>
        <h3>Active plans</h3>
        <?php foreach ($plans as $p): ?><p><?= e($p['plan_name']) ?> · <?= e($p['workout_style'] ?? $p['difficulty']) ?></p><?php endforeach; ?>
        <?php if (!$plans): ?><p class="muted">No workout assigned yet.</p><?php endif; ?>
        <h3>Goals</h3>
        <?php foreach ($goals as $g): ?><p><?= e($g['goal_type']) ?> · <?= e($g['target_value'] . ' ' . $g['target_unit']) ?></p><?php endforeach; ?>
    </div>
    <form class="card" method="post">
        <h2>Log what you did</h2>
        <?= csrf_field() ?><input type="hidden" name="form" value="progress">
        <select name="exercise_id"><?php foreach ($exercises as $ex): ?><option value="<?= (int) $ex['exercise_id'] ?>"><?= e($ex['workout_day'] . ' · ' . $ex['exercise_name'] . ' · ' . TrainingService::prescription($ex)) ?></option><?php endforeach; ?></select>
        <label>Sets done</label><input type="number" name="completed_sets" placeholder="e.g. 3">
        <label>Reps done</label><input type="number" name="completed_repetitions" placeholder="e.g. 10">
        <label>Weight used (kg)</label><input type="number" step="0.1" name="weight_used_kg">
        <label>Time (minutes)</label><input type="number" name="duration_minutes">
        <button class="btn">Save session</button>
    </form>
</div>

<?php foreach ($grouped as $day => $list): ?>
    <h2 style="margin:22px 0 10px"><?= e(ucfirst(strtolower($day))) ?></h2>
    <div class="media-grid">
        <?php foreach ($list as $ex):
            $src = TrainingService::mediaUrl($ex['image_path'] ?? null, TrainingService::defaultWorkoutImage($ex['exercise_type'] ?? $ex['workout_style'] ?? 'STRENGTH'));
            ?>
            <article class="media-card">
                <img src="<?= e($src) ?>" alt="<?= e($ex['exercise_name']) ?>">
                <div>
                    <span class="badge"><?= e($ex['exercise_type'] ?? 'STRENGTH') ?></span>
                    <h3><?= e($ex['exercise_name']) ?></h3>
                    <p class="rx"><?= e(TrainingService::prescription($ex)) ?></p>
                    <p class="muted"><?= e($ex['plan_name']) ?><?= !empty($ex['rest_seconds']) ? ' · rest ' . (int) $ex['rest_seconds'] . 's' : '' ?></p>
                    <?php if (!empty($ex['instructions'])): ?><p><?= e($ex['instructions']) ?></p><?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>

<div class="card" style="margin-top:16px">
    <h2>Weight progress</h2>
    <canvas id="w" height="110"></canvas>
    <h2>Recent logs</h2>
    <table><?php foreach ($progress as $p): ?><tr><td><?= e($p['progress_date']) ?></td><td><?= e($p['exercise_name']) ?></td><td><?= (int) $p['completed_sets'] ?> sets</td><td><?= e($p['completion_percentage']) ?>%</td></tr><?php endforeach; ?></table>
</div>
<script>document.addEventListener('DOMContentLoaded',()=>voltCharts('w','line',<?= json_encode(array_column($measures,'measurement_date')) ?>,<?= json_encode(array_map(fn($r)=>(float)$r['weight_kg'],$measures)) ?>,{label:'kg'}));</script>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
