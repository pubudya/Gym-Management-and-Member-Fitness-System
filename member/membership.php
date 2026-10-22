<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/init.php';
require_login('MEMBER');
$m = current_member();
$ms = active_membership((int) $m['member_id']);
$history = Database::fetchAll(
    'SELECT * FROM memberships WHERE member_id=? ORDER BY created_at DESC',
    [$m['member_id']]
);
$fee = monthly_fee();

if (is_post()) {
    if ($fee <= 0) {
        flash('error', 'The gym has not set a monthly fee yet. Please contact the gym owner.');
        redirect('member/membership.php');
    }
    $start = date('Y-m-d');
    $end = date('Y-m-d', strtotime('+1 month'));
    $id = Database::insert('memberships', [
        'member_id' => $m['member_id'],
        'start_date' => $start,
        'end_date' => $end,
        'amount' => $fee,
        'discount' => 0,
        'final_amount' => $fee,
        'paid_amount' => 0,
        'balance' => $fee,
        'status' => 'PENDING',
        'created_by' => current_user()['user_id'],
    ]);
    flash('success', 'Monthly renewal created. Pay online or at the desk to activate.');
    redirect('member/payments.php?pay=' . $id);
}

$pageTitle = 'Membership';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="grid split">
    <div class="card">
        <h2>Current membership</h2>
        <?php if ($ms): ?>
            <p><b>Monthly gym fee</b> · <?= e($ms['status']) ?></p>
            <p><?= e($ms['start_date']) ?> → <?= e($ms['end_date']) ?></p>
            <p>Paid <?= e(money($ms['paid_amount'])) ?> · Balance <?= e(money($ms['balance'])) ?></p>
        <?php else: ?><p>No current membership.</p><?php endif; ?>
        <p class="muted">The monthly fee is set by the gym owner. Members cannot change it.</p>
    </div>
    <form class="card" method="post">
        <h2>Pay next month</h2>
        <?= csrf_field() ?>
        <p>Amount due: <b><?= e(money($fee)) ?></b> for 1 month</p>
        <button class="btn">Create monthly renewal</button>
    </form>
</div>
<div class="card" style="margin-top:16px">
    <h2>History</h2>
    <table>
        <?php foreach ($history as $h): ?>
            <tr>
                <td>Monthly fee</td>
                <td><?= e($h['start_date'] . ' → ' . $h['end_date']) ?></td>
                <td><?= e(money($h['final_amount'])) ?></td>
                <td><?= e(money($h['balance'])) ?></td>
                <td><span class="badge <?= badge_class($h['status']) ?>"><?= e($h['status']) ?></span></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$history): ?><tr><td>No membership history yet.</td></tr><?php endif; ?>
    </table>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
