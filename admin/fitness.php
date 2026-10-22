<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/init.php';
require_login('ADMIN');

if (is_post() && post('form') === 'goal') {
    Database::insert('fitness_goals', [
        'member_id' => (int) post('member_id'),
        'goal_type' => post('goal_type'),
        'description' => post('description'),
        'target_value' => post('target_value') !== '' ? (float) post('target_value') : null,
        'target_unit' => post('target_unit'),
        'start_date' => post('start_date') ?: date('Y-m-d'),
        'target_date' => post('target_date') ?: null,
        'status' => 'ACTIVE',
    ]);
    flash('success', 'Goal saved.');
    redirect('admin/fitness.php');
}
if (is_post() && post('form') === 'measure') {
    Database::insert('fitness_measurements', [
        'member_id' => (int) post('member_id'),
        'weight_kg' => post('weight_kg') !== '' ? (float) post('weight_kg') : null,
        'height_cm' => post('height_cm') !== '' ? (float) post('height_cm') : null,
        'chest_cm' => post('chest_cm') !== '' ? (float) post('chest_cm') : null,
        'waist_cm' => post('waist_cm') !== '' ? (float) post('waist_cm') : null,
        'hip_cm' => post('hip_cm') !== '' ? (float) post('hip_cm') : null,
        'arm_cm' => post('arm_cm') !== '' ? (float) post('arm_cm') : null,
        'leg_cm' => post('leg_cm') !== '' ? (float) post('leg_cm') : null,
        'body_fat_percentage' => post('body_fat_percentage') !== '' ? (float) post('body_fat_percentage') : null,
        'measurement_date' => post('measurement_date') ?: date('Y-m-d'),
        'notes' => post('notes'),
    ]);
    flash('success', 'Measurement saved.');
    redirect('admin/fitness.php');
}

$memberId = (int) query_param('member_id');
$members = Database::fetchAll('SELECT member_id, full_name FROM members ORDER BY full_name');
$goals = Database::fetchAll("SELECT g.*, m.full_name FROM fitness_goals g JOIN members m ON m.member_id=g.member_id ORDER BY g.created_at DESC LIMIT 40");
$measures = Database::fetchAll("SELECT x.*, m.full_name FROM fitness_measurements x JOIN members m ON m.member_id=x.member_id " . ($memberId ? 'WHERE x.member_id=?' : '') . " ORDER BY measurement_date", $memberId ? [$memberId] : []);
$chartLabels = array_column($measures, 'measurement_date');
$chartData = array_map(fn($r) => (float) $r['weight_kg'], $measures);
$pageTitle = 'Progress & goals';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="grid split">
    <form class="card" method="post">
        <h2>New goal</h2>
        <?= csrf_field() ?><input type="hidden" name="form" value="goal">
        <select name="member_id"><?php foreach ($members as $m): ?><option value="<?= (int)$m['member_id'] ?>"><?= e($m['full_name']) ?></option><?php endforeach; ?></select>
        <select name="goal_type"><?php foreach (['WEIGHT_LOSS','MUSCLE_GAIN','STRENGTH','ENDURANCE','FLEXIBILITY','GENERAL_FITNESS'] as $g): ?><option><?= $g ?></option><?php endforeach; ?></select>
        <input name="target_value" placeholder="Target value"><input name="target_unit" placeholder="kg / % / reps">
        <input type="date" name="start_date" value="<?= e(date('Y-m-d')) ?>">
        <input type="date" name="target_date">
        <textarea name="description" placeholder="Notes"></textarea>
        <button class="btn">Save goal</button>
    </form>
    <form class="card" method="post">
        <h2>Log measurements</h2>
        <?= csrf_field() ?><input type="hidden" name="form" value="measure">
        <select name="member_id"><?php foreach ($members as $m): ?><option value="<?= (int)$m['member_id'] ?>" <?= selected($memberId,$m['member_id']) ?>><?= e($m['full_name']) ?></option><?php endforeach; ?></select>
        <div class="grid form-grid">
            <input name="weight_kg" placeholder="Weight kg">
            <input name="height_cm" placeholder="Height cm">
            <input name="chest_cm" placeholder="Chest">
            <input name="waist_cm" placeholder="Waist">
            <input name="hip_cm" placeholder="Hip">
            <input name="arm_cm" placeholder="Arm">
            <input name="leg_cm" placeholder="Leg">
            <input name="body_fat_percentage" placeholder="Body fat %">
        </div>
        <input type="date" name="measurement_date" value="<?= e(date('Y-m-d')) ?>">
        <button class="btn">Save measurements</button>
    </form>
</div>
<div class="card" style="margin-top:16px">
    <div class="card-h">
        <h2>Weight trend</h2>
        <form method="get"><select name="member_id" onchange="this.form.submit()"><option value="">All members</option><?php foreach ($members as $m): ?><option value="<?= (int)$m['member_id'] ?>" <?= selected($memberId,$m['member_id']) ?>><?= e($m['full_name']) ?></option><?php endforeach; ?></select></form>
    </div>
    <canvas id="weightChart" height="110"></canvas>
</div>
<div class="card" style="margin-top:16px">
    <h2>Goals</h2>
    <table><tr><th>Member</th><th>Type</th><th>Target</th><th>Status</th></tr>
        <?php foreach ($goals as $g): ?><tr><td><?= e($g['full_name']) ?></td><td><?= e($g['goal_type']) ?></td><td><?= e($g['target_value'].' '.$g['target_unit']) ?></td><td><?= e($g['status']) ?></td></tr><?php endforeach; ?>
    </table>
</div>
<script>document.addEventListener('DOMContentLoaded',()=>voltCharts('weightChart','line',<?= json_encode($chartLabels) ?>,<?= json_encode($chartData) ?>,{label:'kg'}));</script>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
