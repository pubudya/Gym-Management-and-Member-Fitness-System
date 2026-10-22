<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/init.php';
require_login('ADMIN');

if (is_post()) {
    Database::insert('expenses', [
        'category_id' => (int) post('category_id'),
        'amount' => (float) post('amount'),
        'description' => post('description'),
        'expense_date' => post('expense_date') ?: date('Y-m-d'),
        'payment_method' => post('payment_method'),
        'reference_number' => post('reference_number'),
        'created_by' => current_user()['user_id'],
    ]);
    audit('CREATE', 'expenses', post('description'));
    flash('success', 'Expense recorded.');
    redirect('admin/expenses.php');
}

$cats = Database::fetchAll("SELECT * FROM expense_categories WHERE status='ACTIVE'");
$rows = Database::fetchAll("SELECT e.*, c.category_name FROM expenses e JOIN expense_categories c ON c.category_id=e.category_id ORDER BY expense_date DESC");
$total = array_sum(array_map(fn($r) => (float)$r['amount'], $rows));
$pageTitle = 'Expenses';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="grid split">
    <form class="card" method="post" data-validate>
        <h2>Add expense</h2>
        <?= csrf_field() ?>
        <select name="category_id"><?php foreach ($cats as $c): ?><option value="<?= (int)$c['category_id'] ?>"><?= e($c['category_name']) ?></option><?php endforeach; ?></select>
        <input type="number" min="1" step="0.01" name="amount" required placeholder="Amount">
        <input type="date" name="expense_date" value="<?= e(date('Y-m-d')) ?>">
        <select name="payment_method"><option>CASH</option><option>CARD</option><option>BANK_TRANSFER</option><option>ONLINE</option></select>
        <input name="reference_number" placeholder="Reference">
        <textarea name="description" placeholder="Description"></textarea>
        <button class="btn">Save</button>
    </form>
    <div class="card">
        <div class="card-h"><h2>Ledger</h2><b><?= e(money($total)) ?></b></div>
        <table>
            <?php foreach ($rows as $r): ?>
                <tr><td><?= e($r['expense_date']) ?></td><td><?= e($r['category_name']) ?></td><td><?= e($r['description']) ?></td><td><?= e(money($r['amount'])) ?></td></tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
