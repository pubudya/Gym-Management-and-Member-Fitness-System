<?php
declare(strict_types=1);

$isCoach = (current_user()['role'] ?? '') === 'COACH';
$base = $isCoach ? 'coach/meals.php' : 'admin/meals.php';
$members = TrainingService::membersForSelect();
$trainers = Database::fetchAll("SELECT trainer_id, full_name FROM trainers WHERE status='ACTIVE' ORDER BY full_name");

if (is_post() && post('form') === 'plan') {
    try {
        $cover = upload_image('cover_image', 'meals');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirect($base);
    }
    $id = Database::insert('meal_plans', [
        'trainer_id' => TrainingService::trainerIdForSave(),
        'created_by' => current_user()['user_id'],
        'plan_name' => trim((string) post('plan_name')),
        'description' => post('description'),
        'goal_type' => post('goal_type') ?: 'GENERAL_FITNESS',
        'cover_image' => $cover,
        'daily_calories' => (int) post('daily_calories') ?: null,
        'status' => 'ACTIVE',
    ]);
    audit('CREATE', 'meals', (string) post('plan_name'), $id);
    flash('success', 'Meal plan created.');
    redirect($base);
}

if (is_post() && post('form') === 'item') {
    try {
        $img = upload_image('image_path', 'meals');
    } catch (Throwable $e) {
        flash('error', $e->getMessage());
        redirect($base);
    }
    Database::insert('meal_items', [
        'meal_plan_id' => (int) post('meal_plan_id'),
        'meal_slot' => post('meal_slot') ?: 'LUNCH',
        'food_name' => trim((string) post('food_name')),
        'portion' => post('portion'),
        'calories' => (int) post('calories') ?: null,
        'protein_g' => post('protein_g') !== '' ? (float) post('protein_g') : null,
        'carbs_g' => post('carbs_g') !== '' ? (float) post('carbs_g') : null,
        'fat_g' => post('fat_g') !== '' ? (float) post('fat_g') : null,
        'image_path' => $img,
        'notes' => post('notes'),
        'item_order' => (int) post('item_order') ?: 1,
    ]);
    flash('success', 'Meal added with photo.');
    redirect($base);
}

if (is_post() && post('form') === 'assign') {
    $memberId = (int) post('member_id');
    TrainingService::assertMember($memberId);
    Database::insert('member_meal_plans', [
        'member_id' => $memberId,
        'meal_plan_id' => (int) post('meal_plan_id'),
        'assigned_date' => date('Y-m-d'),
        'status' => 'ACTIVE',
        'assigned_by' => current_user()['user_id'],
    ]);
    flash('success', 'Meal plan assigned to the member.');
    redirect($base);
}

if ($isCoach) {
    $tid = (int) (current_coach()['trainer_id'] ?? 0);
    $plans = Database::fetchAll(
        "SELECT mp.*, t.full_name trainer_name FROM meal_plans mp
         LEFT JOIN trainers t ON t.trainer_id = mp.trainer_id
         WHERE mp.trainer_id = ? OR mp.created_by = ?
         ORDER BY mp.created_at DESC",
        [$tid, current_user()['user_id']]
    );
} else {
    $plans = Database::fetchAll(
        "SELECT mp.*, t.full_name trainer_name FROM meal_plans mp
         LEFT JOIN trainers t ON t.trainer_id = mp.trainer_id
         ORDER BY mp.created_at DESC"
    );
}
$planIds = array_map(static fn($p) => (int) $p['meal_plan_id'], $plans);
$items = [];
if ($planIds) {
    $in = implode(',', array_fill(0, count($planIds), '?'));
    $items = Database::fetchAll(
        "SELECT i.*, mp.plan_name FROM meal_items i JOIN meal_plans mp ON mp.meal_plan_id = i.meal_plan_id
         WHERE i.meal_plan_id IN ($in) ORDER BY FIELD(i.meal_slot,'BREAKFAST','LUNCH','DINNER','SNACK'), i.item_order",
        $planIds
    );
}

$pageTitle = 'Meal plans';
$pageSubtitle = $isCoach
    ? 'Nutrition for your assigned members only'
    : 'Nutrition plans for any member';
require dirname(__DIR__) . '/header.php';
?>
<div class="callout"><?= $isCoach
    ? 'Coaches can assign meal plans only to members assigned to them.'
    : 'Admin can create meal plans for every member. Coaches only see and assign their own members.' ?></div>

<div class="grid cards-3">
    <form class="card" method="post" enctype="multipart/form-data" data-validate>
        <h2>New meal plan</h2>
        <?= csrf_field() ?><input type="hidden" name="form" value="plan">
        <?php if (!$isCoach): ?>
            <label>Coach (optional)</label>
            <select name="trainer_id">
                <option value="">Gym / admin program</option>
                <?php foreach ($trainers as $t): ?><option value="<?= (int) $t['trainer_id'] ?>"><?= e($t['full_name']) ?></option><?php endforeach; ?>
            </select>
        <?php endif; ?>
        <label>Plan name</label><input name="plan_name" required placeholder="e.g. Lean muscle plate">
        <label>Goal</label>
        <select name="goal_type"><?php foreach (TrainingService::goals() as $g): ?><option><?= e($g) ?></option><?php endforeach; ?></select>
        <label>Daily calories (optional)</label><input type="number" name="daily_calories" placeholder="2200">
        <label>Cover photo</label><input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp">
        <label>Notes</label><textarea name="description"></textarea>
        <button class="btn">Save meal plan</button>
    </form>

    <form class="card" method="post" enctype="multipart/form-data" data-validate>
        <h2>Add a meal / food</h2>
        <?= csrf_field() ?><input type="hidden" name="form" value="item">
        <label>Plan</label>
        <select name="meal_plan_id"><?php foreach ($plans as $p): ?><option value="<?= (int) $p['meal_plan_id'] ?>"><?= e($p['plan_name']) ?></option><?php endforeach; ?></select>
        <label>When</label>
        <select name="meal_slot"><?php foreach (TrainingService::slots() as $s): ?><option><?= e($s) ?></option><?php endforeach; ?></select>
        <label>Food name</label><input name="food_name" required placeholder="e.g. Grilled chicken bowl">
        <label>Portion</label><input name="portion" placeholder="1 bowl / 200g">
        <div class="grid form-grid">
            <div><label>Calories</label><input type="number" name="calories"></div>
            <div><label>Protein (g)</label><input type="number" step="0.1" name="protein_g"></div>
            <div><label>Carbs (g)</label><input type="number" step="0.1" name="carbs_g"></div>
            <div><label>Fat (g)</label><input type="number" step="0.1" name="fat_g"></div>
        </div>
        <label>Photo</label><input type="file" name="image_path" accept="image/jpeg,image/png,image/webp">
        <label>Order</label><input type="number" name="item_order" value="1">
        <label>Notes</label><textarea name="notes" placeholder="Prep tips"></textarea>
        <button class="btn">Add meal</button>
    </form>

    <form class="card" method="post">
        <h2>Assign to a member</h2>
        <?= csrf_field() ?><input type="hidden" name="form" value="assign">
        <?php if (!$members): ?>
            <p class="muted"><?= $isCoach ? 'No members assigned to you yet.' : 'No active members.' ?></p>
        <?php else: ?>
            <label>Member</label>
            <select name="member_id"><?php foreach ($members as $m): ?><option value="<?= (int) $m['member_id'] ?>"><?= e($m['full_name']) ?></option><?php endforeach; ?></select>
            <label>Meal plan</label>
            <select name="meal_plan_id"><?php foreach ($plans as $p): ?><option value="<?= (int) $p['meal_plan_id'] ?>"><?= e($p['plan_name']) ?></option><?php endforeach; ?></select>
            <button class="btn">Assign meals</button>
        <?php endif; ?>
    </form>
</div>

<div class="media-grid" style="margin-top:18px">
    <?php foreach ($items as $item):
        $src = TrainingService::mediaUrl($item['image_path'] ?? null, TrainingService::defaultMealImage($item['meal_slot']));
        ?>
        <article class="media-card">
            <img src="<?= e($src) ?>" alt="">
            <div>
                <span class="badge"><?= e($item['meal_slot']) ?></span>
                <h3><?= e($item['food_name']) ?></h3>
                <p class="rx"><?= $item['calories'] ? ((int) $item['calories'] . ' kcal') : 'Portion' ?> <?= e($item['portion'] ?? '') ?></p>
                <p class="muted"><?= e($item['plan_name']) ?>
                    <?php if ($item['protein_g'] || $item['carbs_g'] || $item['fat_g']): ?>
                        · P<?= e((string) $item['protein_g']) ?> C<?= e((string) $item['carbs_g']) ?> F<?= e((string) $item['fat_g']) ?>
                    <?php endif; ?>
                </p>
            </div>
        </article>
    <?php endforeach; ?>
    <?php if (!$items): ?><p class="muted">Add meals with photos so members can follow breakfast, lunch, dinner and snacks clearly.</p><?php endif; ?>
</div>
<?php require dirname(__DIR__) . '/footer.php'; ?>
