<?php
declare(strict_types=1);

$isCoach = (current_user()['role'] ?? '') === 'COACH';
$base = $isCoach ? 'coach/workouts.php' : 'admin/workouts.php';
$members = TrainingService::membersForSelect();
$trainers = Database::fetchAll("SELECT trainer_id, full_name FROM trainers WHERE status='ACTIVE' ORDER BY full_name");

if (is_post() && post('form') === 'plan') {
    $style = strtoupper((string) post('workout_style')) ?: 'STRENGTH';
    try {
        $cover = upload_image('cover_image', 'workouts');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirect($base);
    }
    $id = Database::insert('workout_plans', [
        'trainer_id' => TrainingService::trainerIdForSave(),
        'created_by' => current_user()['user_id'],
        'plan_name' => trim((string) post('plan_name')),
        'description' => post('description'),
        'goal_type' => post('goal_type') ?: 'GENERAL_FITNESS',
        'workout_style' => $style,
        'difficulty' => post('difficulty') ?: 'BEGINNER',
        'cover_image' => $cover,
        'status' => 'ACTIVE',
    ]);
    audit('CREATE', 'workouts', (string) post('plan_name'), $id);
    flash('success', 'Workout plan created.');
    redirect($base);
}

if (is_post() && post('form') === 'exercise') {
    $type = (string) post('tracking_type') ?: 'SETS_REPS';
    $style = strtoupper((string) post('exercise_type')) ?: 'STRENGTH';
    try {
        $img = upload_image('image_path', 'workouts');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirect($base);
    }
    Database::insert('workout_exercises', [
        'workout_plan_id' => (int) post('workout_plan_id'),
        'exercise_name' => trim((string) post('exercise_name')),
        'exercise_type' => $style,
        'tracking_type' => $type,
        'workout_day' => post('workout_day'),
        'sets' => $type === 'SETS_REPS' ? ((int) post('sets') ?: null) : null,
        'repetitions' => $type === 'SETS_REPS' ? ((int) post('repetitions') ?: null) : null,
        'duration_seconds' => in_array($type, ['DURATION', 'HOLD'], true) ? ((int) post('duration_seconds') ?: (int) post('duration_seconds_hold') ?: null) : null,
        'target_weight_kg' => post('target_weight_kg') !== '' ? (float) post('target_weight_kg') : null,
        'rest_seconds' => (int) post('rest_seconds') ?: null,
        'instructions' => post('instructions'),
        'image_path' => $img,
        'exercise_order' => (int) post('exercise_order') ?: 1,
    ]);
    flash('success', 'Exercise added with prescription ' . TrainingService::prescription([
        'tracking_type' => $type,
        'sets' => post('sets'),
        'repetitions' => post('repetitions'),
        'duration_seconds' => post('duration_seconds'),
    ]) . '.');
    redirect($base);
}

if (is_post() && post('form') === 'assign') {
    $memberId = (int) post('member_id');
    TrainingService::assertMember($memberId);
    Database::insert('member_workout_plans', [
        'member_id' => $memberId,
        'workout_plan_id' => (int) post('workout_plan_id'),
        'assigned_date' => date('Y-m-d'),
        'status' => 'ACTIVE',
        'assigned_by' => current_user()['user_id'],
    ]);
    flash('success', 'Workout assigned. The member will see it on My workouts.');
    redirect($base);
}

if ($isCoach) {
    $tid = (int) (current_coach()['trainer_id'] ?? 0);
    $plans = Database::fetchAll(
        "SELECT wp.*, t.full_name trainer_name FROM workout_plans wp
         LEFT JOIN trainers t ON t.trainer_id = wp.trainer_id
         WHERE wp.trainer_id = ? OR wp.created_by = ?
         ORDER BY wp.created_at DESC",
        [$tid, current_user()['user_id']]
    );
} else {
    $plans = Database::fetchAll(
        "SELECT wp.*, t.full_name trainer_name FROM workout_plans wp
         LEFT JOIN trainers t ON t.trainer_id = wp.trainer_id
         ORDER BY wp.created_at DESC"
    );
}
$planIds = array_map(static fn($p) => (int) $p['workout_plan_id'], $plans);
$exercises = [];
if ($planIds) {
    $in = implode(',', array_fill(0, count($planIds), '?'));
    $exercises = Database::fetchAll(
        "SELECT e.*, wp.plan_name, wp.workout_style FROM workout_exercises e
         JOIN workout_plans wp ON wp.workout_plan_id = e.workout_plan_id
         WHERE e.workout_plan_id IN ($in)
         ORDER BY e.workout_day, e.exercise_order",
        $planIds
    );
}

$pageTitle = 'Workout plans';
$pageSubtitle = $isCoach
    ? 'Create routines for your assigned members only'
    : 'Create routines for any member';
require dirname(__DIR__) . '/header.php';
?>
<div class="callout"><?= $isCoach
    ? 'You can assign workouts only to members linked to you. Admin can still create plans for everyone.'
    : 'Admin can create and assign workout plans for every member. Coaches are limited to their assigned members.' ?></div>

<div class="grid cards-3">
    <form class="card" method="post" enctype="multipart/form-data" data-validate>
        <h2>New workout plan</h2>
        <?= csrf_field() ?><input type="hidden" name="form" value="plan">
        <?php if (!$isCoach): ?>
            <label>Coach (optional)</label>
            <select name="trainer_id">
                <option value="">Gym / admin program</option>
                <?php foreach ($trainers as $t): ?><option value="<?= (int) $t['trainer_id'] ?>"><?= e($t['full_name']) ?></option><?php endforeach; ?>
            </select>
        <?php endif; ?>
        <label>Plan name</label><input name="plan_name" required placeholder="e.g. Beginner Pilates">
        <label>Style</label>
        <select name="workout_style"><?php foreach (TrainingService::styles() as $s): ?><option><?= e($s) ?></option><?php endforeach; ?></select>
        <label>Goal</label>
        <select name="goal_type"><?php foreach (TrainingService::goals() as $g): ?><option><?= e($g) ?></option><?php endforeach; ?></select>
        <label>Difficulty</label>
        <select name="difficulty"><option>BEGINNER</option><option>INTERMEDIATE</option><option>ADVANCED</option></select>
        <label>Cover photo</label><input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp">
        <label>Description</label><textarea name="description" placeholder="What this plan is for"></textarea>
        <button class="btn">Save plan</button>
    </form>

    <form class="card" method="post" enctype="multipart/form-data" data-validate>
        <h2>Add exercise</h2>
        <?= csrf_field() ?><input type="hidden" name="form" value="exercise">
        <label>Plan</label>
        <select name="workout_plan_id"><?php foreach ($plans as $p): ?><option value="<?= (int) $p['workout_plan_id'] ?>"><?= e($p['plan_name']) ?></option><?php endforeach; ?></select>
        <label>Exercise name</label><input name="exercise_name" required placeholder="e.g. Hundred, Goblet squat">
        <label>Type</label>
        <select name="exercise_type"><?php foreach (TrainingService::styles() as $s): ?><option><?= e($s) ?></option><?php endforeach; ?></select>
        <label>Day</label>
        <select name="workout_day"><?php foreach (TrainingService::days() as $d): ?><option><?= e($d) ?></option><?php endforeach; ?></select>
        <label>How to track</label>
        <select name="tracking_type" data-tracking-select>
            <option value="SETS_REPS">Sets × reps (e.g. 10 × 3)</option>
            <option value="DURATION">Time duration</option>
            <option value="HOLD">Hold / isometric</option>
        </select>
        <div data-track-panel="SETS_REPS">
            <label>Reps</label><input type="number" min="1" name="repetitions" value="10">
            <label>Sets</label><input type="number" min="1" name="sets" value="3">
            <p class="muted">Shown to members as <b>10 × 3</b></p>
        </div>
        <div data-track-panel="DURATION" hidden>
            <label>Duration (seconds)</label><input type="number" min="1" name="duration_seconds" value="45">
        </div>
        <div data-track-panel="HOLD" hidden>
            <label>Hold time (seconds)</label><input type="number" min="1" name="duration_seconds_hold" value="30">
        </div>
        <label>Rest (seconds)</label><input type="number" name="rest_seconds" value="45">
        <label>Target weight (kg, optional)</label><input type="number" step="0.1" name="target_weight_kg">
        <label>Exercise photo</label><input type="file" name="image_path" accept="image/jpeg,image/png,image/webp">
        <label>Order</label><input type="number" name="exercise_order" value="1">
        <label>Cues</label><textarea name="instructions" placeholder="Form tips for the member"></textarea>
        <button class="btn">Add exercise</button>
    </form>

    <form class="card" method="post">
        <h2>Assign to a member</h2>
        <?= csrf_field() ?><input type="hidden" name="form" value="assign">
        <?php if (!$members): ?>
            <p class="muted"><?= $isCoach ? 'No members are assigned to you yet. Ask admin to assign members.' : 'No active members.' ?></p>
        <?php else: ?>
            <label>Member</label>
            <select name="member_id"><?php foreach ($members as $m): ?><option value="<?= (int) $m['member_id'] ?>"><?= e($m['full_name'] . ' · ' . $m['member_code']) ?></option><?php endforeach; ?></select>
            <label>Plan</label>
            <select name="workout_plan_id"><?php foreach ($plans as $p): ?><option value="<?= (int) $p['workout_plan_id'] ?>"><?= e($p['plan_name']) ?></option><?php endforeach; ?></select>
            <button class="btn">Assign workout</button>
        <?php endif; ?>
    </form>
</div>

<div class="media-grid" style="margin-top:18px">
    <?php foreach ($exercises as $ex):
        $src = TrainingService::mediaUrl($ex['image_path'] ?? null, TrainingService::defaultWorkoutImage($ex['exercise_type'] ?? $ex['workout_style'] ?? 'STRENGTH'));
        ?>
        <article class="media-card">
            <img src="<?= e($src) ?>" alt="">
            <div>
                <span class="badge"><?= e($ex['exercise_type'] ?? 'STRENGTH') ?></span>
                <h3><?= e($ex['exercise_name']) ?></h3>
                <p class="rx"><?= e(TrainingService::prescription($ex)) ?></p>
                <p class="muted"><?= e($ex['plan_name']) ?> · <?= e(ucfirst(strtolower($ex['workout_day']))) ?>
                    <?= !empty($ex['rest_seconds']) ? ' · rest ' . (int) $ex['rest_seconds'] . 's' : '' ?></p>
                <?php if (!empty($ex['instructions'])): ?><p><?= e($ex['instructions']) ?></p><?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
    <?php if (!$exercises): ?><p class="muted">Add a plan, then add exercises with photos and 10 × 3 or timed prescriptions.</p><?php endif; ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const sel = document.querySelector('[data-tracking-select]');
  if (!sel) return;
  const sync = () => {
    document.querySelectorAll('[data-track-panel]').forEach((el) => {
      el.hidden = el.getAttribute('data-track-panel') !== sel.value;
    });
    const hold = document.querySelector('[name=duration_seconds_hold]');
    const dur = document.querySelector('[name=duration_seconds]');
    if (sel.value === 'HOLD' && hold && dur) dur.value = hold.value;
  };
  sel.addEventListener('change', sync);
  const hold = document.querySelector('[name=duration_seconds_hold]');
  if (hold) hold.addEventListener('input', () => {
    const dur = document.querySelector('[name=duration_seconds]');
    if (dur) dur.value = hold.value;
  });
  sync();
});
</script>
<?php require dirname(__DIR__) . '/footer.php'; ?>
