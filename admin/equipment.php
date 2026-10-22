<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/init.php';
require_login('ADMIN');

if (is_post() && post('form') === 'equipment') {
    $id = (int) post('equipment_id');
    $payload = [
        'category_id' => (int) post('category_id'),
        'equipment_code' => trim((string) post('equipment_code')) ?: next_code('EQ-', 'equipment', 'equipment_code'),
        'equipment_name' => trim((string) post('equipment_name')),
        'brand' => post('brand'),
        'model' => post('model'),
        'serial_number' => post('serial_number') ?: null,
        'quantity' => (int) post('quantity'),
        'purchase_date' => post('purchase_date') ?: null,
        'purchase_price' => post('purchase_price') !== '' ? (float) post('purchase_price') : null,
        'warranty_expiry' => post('warranty_expiry') ?: null,
        'location' => post('location'),
        'condition_status' => post('condition_status'),
        'status' => post('status'),
        'health_score' => (float) (post('health_score') ?: 100),
    ];
    if ($id) { Database::update('equipment', $payload, 'equipment_id=?', [$id]); }
    else { $id = Database::insert('equipment', $payload); }
    audit('SAVE', 'equipment', $payload['equipment_name'], $id);
    flash('success', 'Equipment saved.');
    redirect('admin/equipment.php');
}
if (is_post() && post('form') === 'maint') {
    Database::insert('equipment_maintenance', [
        'equipment_id' => (int) post('equipment_id'),
        'maintenance_date' => post('maintenance_date'),
        'problem' => post('problem'),
        'description' => post('description'),
        'technician_name' => post('technician_name'),
        'cost' => (float) (post('cost') ?: 0),
        'next_maintenance_date' => post('next_maintenance_date') ?: null,
        'status' => post('status') ?: 'SCHEDULED',
        'created_by' => current_user()['user_id'],
    ]);
    Database::update('equipment', ['status' => 'UNDER_MAINTENANCE'], 'equipment_id=?', [(int) post('equipment_id')]);
    flash('success', 'Maintenance job created.');
    redirect('admin/equipment.php');
}

$cats = Database::fetchAll('SELECT * FROM equipment_categories');
$items = Database::fetchAll("SELECT e.*, c.category_name FROM equipment e JOIN equipment_categories c ON c.category_id=e.category_id ORDER BY e.equipment_name");
$jobs = Database::fetchAll("SELECT em.*, e.equipment_name FROM equipment_maintenance em JOIN equipment e ON e.equipment_id=em.equipment_id ORDER BY em.maintenance_date DESC");
$pageTitle = 'Equipment';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="grid split">
    <form class="card" method="post">
        <h2>Add / update equipment</h2>
        <?= csrf_field() ?><input type="hidden" name="form" value="equipment">
        <select name="category_id"><?php foreach ($cats as $c): ?><option value="<?= (int)$c['category_id'] ?>"><?= e($c['category_name']) ?></option><?php endforeach; ?></select>
        <input name="equipment_code" placeholder="Code (auto if empty)">
        <input name="equipment_name" required placeholder="Name">
        <div class="grid form-grid">
            <input name="brand" placeholder="Brand"><input name="model" placeholder="Model">
            <input name="serial_number" placeholder="Serial"><input type="number" name="quantity" value="1">
            <input type="date" name="purchase_date"><input type="number" step="0.01" name="purchase_price" placeholder="Price">
            <input type="date" name="warranty_expiry"><input name="location" placeholder="Location">
        </div>
        <select name="condition_status"><option>EXCELLENT</option><option selected>GOOD</option><option>FAIR</option><option>POOR</option><option>CRITICAL</option></select>
        <select name="status"><option>AVAILABLE</option><option>IN_USE</option><option>DAMAGED</option><option>UNDER_MAINTENANCE</option><option>RETIRED</option><option>LOST</option></select>
        <input type="number" name="health_score" min="0" max="100" value="100">
        <button class="btn">Save equipment</button>
    </form>
    <form class="card" method="post">
        <h2>Maintenance</h2>
        <?= csrf_field() ?><input type="hidden" name="form" value="maint">
        <select name="equipment_id"><?php foreach ($items as $i): ?><option value="<?= (int)$i['equipment_id'] ?>"><?= e($i['equipment_name']) ?></option><?php endforeach; ?></select>
        <input type="date" name="maintenance_date" value="<?= e(date('Y-m-d')) ?>">
        <input name="problem" placeholder="Problem">
        <textarea name="description"></textarea>
        <input name="technician_name" placeholder="Technician">
        <input type="number" step="0.01" name="cost" placeholder="Cost">
        <input type="date" name="next_maintenance_date">
        <select name="status"><option>SCHEDULED</option><option>IN_PROGRESS</option><option>COMPLETED</option><option>CANCELLED</option></select>
        <button class="btn ember">Create job</button>
    </form>
</div>
<div class="card" style="margin-top:16px">
    <h2>Inventory of machines</h2>
    <table><tr><th>Code</th><th>Name</th><th>Condition</th><th>Status</th><th>Health</th></tr>
        <?php foreach ($items as $i): ?>
            <tr>
                <td><?= e($i['equipment_code']) ?></td><td><?= e($i['equipment_name']) ?><br><small><?= e($i['category_name']) ?></small></td>
                <td><?= e($i['condition_status']) ?></td>
                <td><span class="badge <?= badge_class($i['status']) ?>"><?= e($i['status']) ?></span></td>
                <td><?= e($i['health_score']) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
    <h2>Maintenance jobs</h2>
    <table><?php foreach ($jobs as $j): ?><tr><td><?= e($j['equipment_name']) ?></td><td><?= e($j['maintenance_date']) ?></td><td><?= e($j['problem']) ?></td><td><span class="badge <?= badge_class($j['status']) ?>"><?= e($j['status']) ?></span></td></tr><?php endforeach; ?></table>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
