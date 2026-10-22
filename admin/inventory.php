<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/init.php';
require_login('ADMIN');

if (is_post() && post('form') === 'supplier') {
    Database::insert('suppliers', [
        'supplier_name' => trim((string) post('supplier_name')),
        'company_name' => post('company_name'),
        'phone' => post('phone'),
        'email' => post('email'),
        'address' => post('address'),
        'status' => 'ACTIVE',
    ]);
    flash('success', 'Supplier added.');
    redirect('admin/inventory.php');
}
if (is_post() && post('form') === 'item') {
    $qty = (int) post('quantity');
    $min = (int) post('minimum_stock');
    $status = $qty === 0 ? 'OUT_OF_STOCK' : ($qty <= $min ? 'LOW_STOCK' : 'AVAILABLE');
    Database::insert('inventory', [
        'category_id' => (int) post('category_id'),
        'supplier_id' => post('supplier_id') ?: null,
        'item_code' => trim((string) post('item_code')) ?: next_code('INV-', 'inventory', 'item_code'),
        'item_name' => trim((string) post('item_name')),
        'quantity' => $qty,
        'minimum_stock' => $min,
        'unit_price' => (float) post('unit_price'),
        'unit' => post('unit') ?: 'UNIT',
        'status' => $status,
    ]);
    flash('success', 'Item saved.');
    redirect('admin/inventory.php');
}
if (is_post() && post('form') === 'stock') {
    $item = Database::fetch('SELECT * FROM inventory WHERE inventory_id=?', [(int) post('inventory_id')]);
    $qty = (int) post('quantity');
    $type = post('transaction_type');
    $new = (int) $item['quantity'];
    if ($type === 'STOCK_IN') $new += $qty;
    elseif ($type === 'STOCK_OUT') $new = max(0, $new - $qty);
    else $new = $qty;
    $status = $new === 0 ? 'OUT_OF_STOCK' : ($new <= (int)$item['minimum_stock'] ? 'LOW_STOCK' : 'AVAILABLE');
    Database::update('inventory', ['quantity' => $new, 'status' => $status], 'inventory_id=?', [$item['inventory_id']]);
    Database::insert('stock_transactions', [
        'inventory_id' => $item['inventory_id'],
        'transaction_type' => $type,
        'quantity' => $qty,
        'reason' => post('reason'),
        'created_by' => current_user()['user_id'],
    ]);
    if ($status !== 'AVAILABLE') {
        NotificationService::notifyAdmins('LOW_STOCK', $item['item_name'] . ' is now ' . $status . ' (' . $new . ').', ['dedupe' => true]);
    }
    flash('success', 'Stock updated.');
    redirect('admin/inventory.php');
}

$cats = Database::fetchAll('SELECT * FROM inventory_categories');
$suppliers = Database::fetchAll('SELECT * FROM suppliers');
$items = Database::fetchAll("SELECT i.*, c.category_name FROM inventory i JOIN inventory_categories c ON c.category_id=i.category_id ORDER BY i.item_name");
$tx = Database::fetchAll("SELECT s.*, i.item_name FROM stock_transactions s JOIN inventory i ON i.inventory_id=s.inventory_id ORDER BY s.transaction_date DESC LIMIT 30");
$pageTitle = 'Inventory';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="grid cards-3">
    <form class="card" method="post"><h2>Supplier</h2><?= csrf_field() ?><input type="hidden" name="form" value="supplier">
        <input name="supplier_name" required placeholder="Supplier name"><input name="company_name" placeholder="Company"><input name="phone" required placeholder="Phone"><input type="email" name="email" placeholder="Email"><button class="btn">Add</button></form>
    <form class="card" method="post"><h2>Stock item</h2><?= csrf_field() ?><input type="hidden" name="form" value="item">
        <select name="category_id"><?php foreach ($cats as $c): ?><option value="<?= (int)$c['category_id'] ?>"><?= e($c['category_name']) ?></option><?php endforeach; ?></select>
        <select name="supplier_id"><option value="">No supplier</option><?php foreach ($suppliers as $s): ?><option value="<?= (int)$s['supplier_id'] ?>"><?= e($s['supplier_name']) ?></option><?php endforeach; ?></select>
        <input name="item_name" required placeholder="Item name"><input name="item_code" placeholder="Code">
        <input type="number" name="quantity" value="0"><input type="number" name="minimum_stock" value="5">
        <input type="number" step="0.01" name="unit_price" value="0"><input name="unit" value="UNIT"><button class="btn">Save item</button></form>
    <form class="card" method="post"><h2>Stock in / out</h2><?= csrf_field() ?><input type="hidden" name="form" value="stock">
        <select name="inventory_id"><?php foreach ($items as $i): ?><option value="<?= (int)$i['inventory_id'] ?>"><?= e($i['item_name'].' ('.$i['quantity'].')') ?></option><?php endforeach; ?></select>
        <select name="transaction_type"><option>STOCK_IN</option><option>STOCK_OUT</option><option>ADJUSTMENT</option></select>
        <input type="number" min="1" name="quantity" required><input name="reason" placeholder="Reason"><button class="btn">Post transaction</button></form>
</div>
<div class="card" style="margin-top:16px">
    <h2>Stock levels</h2>
    <table><tr><th>Code</th><th>Item</th><th>Qty</th><th>Min</th><th>Status</th></tr>
        <?php foreach ($items as $i): ?><tr><td><?= e($i['item_code']) ?></td><td><?= e($i['item_name']) ?></td><td><?= (int)$i['quantity'] ?></td><td><?= (int)$i['minimum_stock'] ?></td><td><span class="badge <?= badge_class($i['status']) ?>"><?= e($i['status']) ?></span></td></tr><?php endforeach; ?>
    </table>
    <h2>Recent movements</h2>
    <table><?php foreach ($tx as $t): ?><tr><td><?= e($t['transaction_date']) ?></td><td><?= e($t['item_name']) ?></td><td><?= e($t['transaction_type']) ?></td><td><?= (int)$t['quantity'] ?></td></tr><?php endforeach; ?></table>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
