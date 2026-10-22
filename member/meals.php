<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/init.php';
require_login('MEMBER');
$m = current_member();

$plans = Database::fetchAll(
    "SELECT mp.*, mmp.assigned_date FROM member_meal_plans mmp
     JOIN meal_plans mp ON mp.meal_plan_id = mmp.meal_plan_id
     WHERE mmp.member_id=? AND mmp.status='ACTIVE' ORDER BY mmp.assigned_date DESC",
    [$m['member_id']]
);
$items = Database::fetchAll(
    "SELECT i.*, mp.plan_name FROM meal_items i
     JOIN meal_plans mp ON mp.meal_plan_id = i.meal_plan_id
     JOIN member_meal_plans mmp ON mmp.meal_plan_id = mp.meal_plan_id
     WHERE mmp.member_id=? AND mmp.status='ACTIVE'
     ORDER BY FIELD(i.meal_slot,'BREAKFAST','LUNCH','DINNER','SNACK'), i.item_order",
    [$m['member_id']]
);
$grouped = [];
foreach ($items as $item) {
    $grouped[$item['meal_slot']][] = $item;
}

$pageTitle = 'My meal plan';
$pageSubtitle = $plans[0]['plan_name'] ?? 'Meals assigned by your coach or admin';
require dirname(__DIR__) . '/includes/header.php';
?>
<?php if (!$plans): ?>
    <div class="callout">No meal plan yet. Your coach or admin can assign one.</div>
<?php else: ?>
    <div class="callout"><?= e($plans[0]['plan_name']) ?><?= !empty($plans[0]['daily_calories']) ? ' · about ' . (int) $plans[0]['daily_calories'] . ' kcal / day' : '' ?></div>
<?php endif; ?>

<?php foreach ($grouped as $slot => $list): ?>
    <h2 style="margin:18px 0 10px"><?= e(ucfirst(strtolower($slot))) ?></h2>
    <div class="media-grid">
        <?php foreach ($list as $item):
            $src = TrainingService::mediaUrl($item['image_path'] ?? null, TrainingService::defaultMealImage($item['meal_slot']));
            ?>
            <article class="media-card">
                <img src="<?= e($src) ?>" alt="<?= e($item['food_name']) ?>">
                <div>
                    <h3><?= e($item['food_name']) ?></h3>
                    <p class="rx"><?= $item['calories'] ? ((int) $item['calories'] . ' kcal') : e($item['portion'] ?: '') ?></p>
                    <p class="muted"><?= e($item['portion'] ?? '') ?>
                        <?php if ($item['protein_g'] || $item['carbs_g'] || $item['fat_g']): ?>
                            · Protein <?= e((string) $item['protein_g']) ?>g · Carbs <?= e((string) $item['carbs_g']) ?>g · Fat <?= e((string) $item['fat_g']) ?>g
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($item['notes'])): ?><p><?= e($item['notes']) ?></p><?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
