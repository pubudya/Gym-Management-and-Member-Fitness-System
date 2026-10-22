<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/init.php';
require_login('ADMIN');

if (query_param('receipt')) {
    $p = Database::fetch(
        "SELECT p.*, m.full_name, m.member_code, m.phone FROM payments p JOIN members m ON m.member_id=p.member_id WHERE p.payment_id=?",
        [(int) query_param('receipt')]
    );
    $pageTitle = 'Receipt ' . PaymentService::receiptNumber($p ?? ['payment_id' => 0]);
    require dirname(__DIR__) . '/includes/header.php';
    if (!$p) { echo '<div class="alert bad">Receipt not found.</div>'; require dirname(__DIR__) . '/includes/footer.php'; exit; }
    ?>
    <div class="card" style="max-width:640px;margin:auto">
        <h2><?= e(setting('gym_name', APP_NAME)) ?> receipt</h2>
        <p>Receipt: <b><?= e(PaymentService::receiptNumber($p)) ?></b></p>
        <p>Member: <?= e($p['full_name']) ?> (<?= e($p['member_code']) ?>)</p>
        <p>Amount: <b><?= e(money($p['amount'])) ?></b></p>
        <p>Method: <?= e($p['payment_method']) ?> · Status: <?= e($p['payment_status']) ?></p>
        <p>Date: <?= e($p['payment_date']) ?></p>
        <button class="btn" type="button" onclick="voltPrint()">Print</button>
    </div>
    <?php require dirname(__DIR__) . '/includes/footer.php'; exit;
}

if (is_post() && post('form') === 'pay') {
    $id = PaymentService::record([
        'member_id' => (int) post('member_id'),
        'membership_id' => post('membership_id') ?: null,
        'amount' => (float) post('amount'),
        'payment_method' => post('payment_method'),
        'payment_status' => 'SUCCESS',
        'paid_by' => 'ADMIN',
        'notes' => post('notes'),
    ]);
    $member = Database::fetch('SELECT * FROM members WHERE member_id=?', [(int) post('member_id')]);
    NotificationService::notifyMember($member, 'PAYMENT_SUCCESS', 'Payment of ' . money(post('amount')) . ' received. Thank you.');
    NotificationService::notifyAdmins('ADMIN_PAYMENT_SUCCESS', 'Desk payment received from ' . $member['full_name'] . '.');
    audit('CREATE', 'payments', 'Offline payment', $id);
    flash('success', 'Payment recorded.');
    redirect('admin/payments.php');
}

if (is_post() && post('form') === 'verify') {
    $ok = post('decision') === 'SUCCESS';
    PaymentService::completeOnline((int) post('payment_id'), $ok, 'ADMIN-VERIFY', (string) post('notes'));
    flash('success', 'Online transaction updated.');
    redirect('admin/payments.php');
}

$pending = Database::fetchAll(
    "SELECT p.*, m.full_name FROM payments p JOIN members m ON m.member_id=p.member_id
     WHERE p.payment_method='ONLINE' AND p.payment_status='PENDING' ORDER BY p.created_at DESC"
);
$rows = Database::fetchAll(
    "SELECT p.*, m.full_name, m.member_code FROM payments p JOIN members m ON m.member_id=p.member_id ORDER BY p.created_at DESC LIMIT 80"
);
$members = Database::fetchAll('SELECT member_id, full_name FROM members ORDER BY full_name');
$memberships = Database::fetchAll("SELECT membership_id, member_id, status, balance FROM memberships ORDER BY created_at DESC");
$pageTitle = 'Payments';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="grid split">
    <form class="card" method="post" data-validate>
        <h2>Record offline payment</h2>
        <?= csrf_field() ?><input type="hidden" name="form" value="pay">
        <label>Member</label>
        <select name="member_id" required><?php foreach ($members as $m): ?><option value="<?= (int)$m['member_id'] ?>"><?= e($m['full_name']) ?></option><?php endforeach; ?></select>
        <label>Membership (optional)</label>
        <select name="membership_id"><option value="">None</option><?php foreach ($memberships as $ms): ?><option value="<?= (int)$ms['membership_id'] ?>">#<?= (int)$ms['membership_id'] ?> balance <?= e(money($ms['balance'])) ?></option><?php endforeach; ?></select>
        <label>Amount</label><input type="number" min="1" step="0.01" name="amount" required>
        <label>Method</label><select name="payment_method"><option>CASH</option><option>CARD</option><option>BANK_TRANSFER</option></select>
        <label>Notes</label><textarea name="notes"></textarea>
        <button class="btn" type="submit">Save payment</button>
    </form>
    <div class="card">
        <h2>Verify online transactions</h2>
        <?php foreach ($pending as $p): ?>
            <form method="post" class="card" style="box-shadow:none;margin-bottom:10px">
                <?= csrf_field() ?><input type="hidden" name="form" value="verify"><input type="hidden" name="payment_id" value="<?= (int)$p['payment_id'] ?>">
                <b><?= e($p['full_name']) ?></b> · <?= e(money($p['amount'])) ?><br>
                <small><?= e($p['transaction_reference']) ?></small>
                <select name="decision"><option>SUCCESS</option><option>FAILED</option></select>
                <button class="btn small" type="submit">Verify</button>
            </form>
        <?php endforeach; ?>
        <?php if (!$pending): ?><p class="muted">No pending online payments.</p><?php endif; ?>
    </div>
</div>
<div class="card" style="margin-top:16px">
    <h2>Payment history</h2>
    <table>
        <tr><th>Ref</th><th>Member</th><th>Amount</th><th>Method</th><th>Status</th><th></th></tr>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= e($r['transaction_reference']) ?></td>
                <td><?= e($r['full_name']) ?></td>
                <td><?= e(money($r['amount'])) ?></td>
                <td><?= e($r['payment_method']) ?></td>
                <td><span class="badge <?= badge_class($r['payment_status']) ?>"><?= e($r['payment_status']) ?></span></td>
                <td><a class="btn small ghost" href="?receipt=<?= (int)$r['payment_id'] ?>">Receipt</a></td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
