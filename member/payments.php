<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/init.php';
require_login('MEMBER');
$m = current_member();

if (query_param('receipt')) {
    $p = Database::fetch('SELECT * FROM payments WHERE payment_id=? AND member_id=?', [(int) query_param('receipt'), $m['member_id']]);
    $pageTitle = 'Receipt';
    require dirname(__DIR__) . '/includes/header.php';
    if (!$p) { echo '<div class="alert bad">Not found.</div>'; require dirname(__DIR__) . '/includes/footer.php'; exit; }
    echo '<div class="card"><h2>Receipt '.e(PaymentService::receiptNumber($p)).'</h2><p>'.e(money($p['amount'])).' · '.$p['payment_method'].' · '.$p['payment_status'].'</p><button class="btn" onclick="voltPrint()">Print</button></div>';
    require dirname(__DIR__) . '/includes/footer.php'; exit;
}

if (is_post() && post('form') === 'online') {
    if ((string) setting('online_payments_enabled', '1') !== '1') {
        flash('error', 'Online payments are disabled.');
        redirect('member/payments.php');
    }
    $amount = (float) post('amount');
    $msId = post('membership_id') ?: null;
    if ($amount <= 0) {
        flash('error', 'Enter an amount.');
        redirect('member/payments.php');
    }
    $pid = PaymentService::record([
        'member_id' => $m['member_id'],
        'membership_id' => $msId,
        'amount' => $amount,
        'payment_method' => 'ONLINE',
        'payment_status' => 'PENDING',
        'paid_by' => 'MEMBER',
        'notes' => 'GymPay demo checkout',
    ]);
    Database::insert('payment_transactions', [
        'payment_id' => $pid,
        'gateway_name' => 'GymPay Demo',
        'amount' => $amount,
        'status' => 'PROCESSING',
    ]);
    $_SESSION['pending_payment'] = $pid;
    redirect('member/payments.php?checkout=1');
}

if (is_post() && post('form') === 'gateway') {
    $pid = (int) ($_SESSION['pending_payment'] ?? 0);
    PaymentService::completeOnline($pid, post('result') === 'SUCCESS');
    unset($_SESSION['pending_payment']);
    flash('success', 'Payment gateway response saved.');
    redirect('member/payments.php');
}

$memberships = Database::fetchAll('SELECT * FROM memberships WHERE member_id=? ORDER BY created_at DESC', [$m['member_id']]);
$pays = Database::fetchAll('SELECT * FROM payments WHERE member_id=? ORDER BY created_at DESC', [$m['member_id']]);
$outstanding = Database::fetch("SELECT COALESCE(SUM(balance),0) s FROM memberships WHERE member_id=? AND status IN ('ACTIVE','PENDING')", [$m['member_id']])['s'] ?? 0;
$pageTitle = 'Payments';
require dirname(__DIR__) . '/includes/header.php';

if (query_param('checkout') && !empty($_SESSION['pending_payment'])):
    $p = Database::fetch('SELECT * FROM payments WHERE payment_id=?', [$_SESSION['pending_payment']]);
?>
<div class="card" style="max-width:480px;margin:auto">
    <h2>GymPay checkout</h2>
    <p>Amount due: <b><?= e(money($p['amount'])) ?></b></p>
    <p class="muted">Demo gateway — choose a result. Wire a real provider later in PaymentService.</p>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="form" value="gateway"><input type="hidden" name="result" value="SUCCESS"><button class="btn full">Simulate successful pay</button></form>
    <form method="post" style="margin-top:8px"><?= csrf_field() ?><input type="hidden" name="form" value="gateway"><input type="hidden" name="result" value="FAILED"><button class="btn ghost full">Simulate failed pay</button></form>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; exit; endif; ?>

<div class="grid split">
    <form class="card" method="post">
        <h2>Pay online</h2>
        <p>Outstanding: <b><?= e(money($outstanding)) ?></b></p>
        <?= csrf_field() ?><input type="hidden" name="form" value="online">
        <select name="membership_id"><option value="">No membership link</option><?php foreach ($memberships as $ms): ?><option value="<?= (int)$ms['membership_id'] ?>" <?= selected(query_param('pay'), $ms['membership_id']) ?>>#<?= (int)$ms['membership_id'] ?> balance <?= e(money($ms['balance'])) ?></option><?php endforeach; ?></select>
        <input type="number" min="1" step="0.01" name="amount" required value="<?= e((string)($outstanding ?: '')) ?>">
        <button class="btn">Continue to GymPay</button>
    </form>
    <div class="card">
        <h2>History</h2>
        <table>
            <?php foreach ($pays as $p): ?>
                <tr>
                    <td><?= e($p['payment_date'] ?: $p['created_at']) ?></td>
                    <td><?= e(money($p['amount'])) ?></td>
                    <td><?= e($p['payment_method']) ?></td>
                    <td><span class="badge <?= badge_class($p['payment_status']) ?>"><?= e($p['payment_status']) ?></span></td>
                    <td><a class="btn small ghost" href="?receipt=<?= (int)$p['payment_id'] ?>">Receipt</a></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
